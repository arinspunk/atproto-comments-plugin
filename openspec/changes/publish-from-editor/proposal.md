# Proposal

## Why

Después de `plugin-scaffold-display`, el editor todavía tiene que crear el post en Bluesky o en otra app y copiar su URI a mano. Este cambio permite publicar el post ancla desde el propio editor de WordPress, cuando el editor decida y no al publicar la entrada, con un texto propio y una tarjeta que enlaza a la entrada.

Depende de `plugin-scaffold-display`, que tiene que estar implementado y archivado antes.

## What Changes

- Ajustes de cuenta de AT Protocol para todo el sitio: handle y app password, con la contraseña cifrada en la base de datos con una clave derivada de `AUTH_KEY`. Botón para comprobar la conexión.
- Resolución de identidad desde el servidor: handle → DID → documento DID → endpoint del PDS (`#atproto_pds`). No se fija `bsky.social` en el código; tiene que funcionar con PDS como Eurosky.
- Panel del editor de bloques: campo de texto con contador de 300 grafemas y botón "Publicar en Bluesky", habilitado solo si la entrada está publicada y no tiene vínculo.
- Endpoint REST propio (recibe solo `post_id` y el texto; `edit_post` y nonce) que:
  - abre sesión (`com.atproto.server.createSession`);
  - sube la imagen destacada si existe (`uploadBlob`, tamaño `medium_large`, límite de unos 1 MB);
  - crea el post (`createRecord` de `app.bsky.feed.post`) con `embed.external` (URL, título y extracto de la entrada);
  - guarda el `at://` resultante en `_atproto_thread_uri`.
- Protección contra doble envío: si la entrada ya tiene vínculo, el endpoint lo rechaza.
- Al pegar el vínculo a mano se aceptan también URLs con handle, que se resuelven a DID con la infraestructura de identidad de este cambio.
- "External services" del `readme.txt` ampliado: el PDS de la cuenta, qué se envía (texto, URL, título, extracto, imagen) y cuándo (solo al pulsar el botón).

## Capabilities

### New Capabilities
- `atproto-account`: configuración de la cuenta del sitio, almacenamiento seguro de credenciales, prueba de conexión y resolución handle → DID → PDS.
- `anchor-post-publishing`: publicación manual del post ancla desde el editor (precondiciones, contenido del post, tarjeta enlazada, imagen, errores y protección contra doble envío).

### Modified Capabilities
- `thread-linking`: el vínculo puede rellenarse publicando, y al pegarlo se aceptan URLs con handle, resueltas a DID.
- `plugin-settings`: nueva sección de cuenta en la página de ajustes.

## Impact

- Primeras llamadas del servidor a la red: resolución de identidad (`com.atproto.identity.resolveHandle`, `plc.directory` o `/.well-known/did.json`) y XRPC al PDS. Se hacen con `wp_remote_*`, con timeouts y errores traducidos.
- Datos sensibles: app password cifrada (`sodium_crypto_secretbox` o `openssl`, por decidir en el design) y nunca enviada al navegador.
- PHP: clases de cliente XRPC, identidad y cifrado. JS: el panel de `src/editor/` se amplía con el formulario de publicación.
- Por decidir en el design: cómo se cachea la sesión (o no) entre publicaciones, la etiqueta del botón (Bluesky o AT Protocol) y si se detectan hashtags (los facets quedan fuera de la v1).
