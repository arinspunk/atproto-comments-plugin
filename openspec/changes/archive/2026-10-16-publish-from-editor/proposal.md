# Proposal

## Why

Después de `plugin-scaffold-display`, el editor todavía tiene que crear el post en Bluesky o en otra app y copiar su URI a mano. Este cambio permite publicar el post ancla desde el propio editor de WordPress, cuando el editor decida y no al publicar la entrada, con un texto propio y una tarjeta que enlaza a la entrada.

Depende de `plugin-scaffold-display`, que tiene que estar implementado y archivado antes.

## What Changes

- Ajustes de cuenta de AT Protocol para todo el sitio: handle y app password, con la contraseña cifrada en la base de datos con una clave derivada de `AUTH_KEY`. Botón para comprobar la conexión.
- Resolución de identidad desde el servidor: handle → DID → documento DID → endpoint del PDS (`#atproto_pds`). No se fija `bsky.social` en el código; tiene que funcionar con PDS como Eurosky.
- Panel del editor de bloques: campo de texto con contador de 300 grafemas y botón "Publish to Bluesky" (traducible), habilitado solo si la entrada está publicada y no tiene vínculo.
- Solo Editores y Administradores (capacidad `edit_others_posts`, además de `edit_post` sobre la entrada) pueden publicar en la cuenta del sitio. El filtro `atproto_comments_publish_capability` permite cambiar la capacidad.
- Endpoint REST propio (recibe solo `post_id` y el texto; permiso de publicación y nonce) que:
  - abre sesión (`com.atproto.server.createSession`);
  - sube la imagen destacada si existe (`uploadBlob`, tamaño `medium_large`, límite de unos 1 MB);
  - crea el post (`createRecord` de `app.bsky.feed.post`) con `embed.external` (URL, título y extracto de la entrada) y el idioma del sitio en `langs`;
  - guarda el `at://` resultante en `_atproto_thread_uri`.
- Protección contra doble envío: si la entrada ya tiene vínculo, el endpoint lo rechaza, y un lock evita que dos peticiones simultáneas creen dos posts.
- Todo el proceso termina dentro de un plazo de 25 s, para no chocar con el límite de ejecución de PHP. Si no se sabe si el post llegó a crearse, el panel pide comprobar el perfil antes de reintentar.
- Al pegar el vínculo a mano se aceptan también URLs con handle, que se resuelven a DID con la infraestructura de identidad de este cambio.
- "External services" del `readme.txt` ampliado: el PDS de la cuenta, qué se envía (texto, URL, título, extracto, imagen, idioma) y cuándo (solo al pulsar el botón).

## Capabilities

### New Capabilities
- `atproto-account`: configuración de la cuenta del sitio, almacenamiento seguro de credenciales, prueba de conexión y resolución handle → DID → PDS.
- `anchor-post-publishing`: publicación manual del post ancla desde el editor (permiso, precondiciones, contenido del post, tarjeta enlazada, imagen, errores y protección contra doble envío).

### Modified Capabilities
- `thread-linking`: el vínculo puede rellenarse publicando, y al pegarlo se aceptan URLs con handle, resueltas a DID.
- `plugin-settings`: nueva sección de cuenta en la página de ajustes.

## Impact

- Primeras llamadas del servidor a la red: resolución de identidad (DNS `_atproto.<handle>` o `https://<handle>/.well-known/atproto-did`, y `plc.directory` o `/.well-known/did.json`) y XRPC al PDS. Se hacen con `wp_safe_remote_*` (protección contra SSRF), con timeouts dentro de un plazo global y errores traducidos.
- Datos sensibles: app password cifrada con `sodium_crypto_secretbox` (disponible siempre gracias a `sodium_compat`), en una opción sin autoload, y nunca enviada al navegador.
- PHP: clases de cifrado, plazo, identidad, cliente XRPC, cuenta, publicación y resolución de handles en el vínculo. JS: el panel de `src/editor/` se amplía con el formulario de publicación, y una entrada nueva para la prueba de conexión en los ajustes.
- Decidido en el design: sin caché de sesión entre publicaciones, botón "Publish to Bluesky" y sin detección de hashtags (los facets quedan fuera de la v1).
