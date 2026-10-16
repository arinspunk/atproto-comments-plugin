# Design

## Context

`plugin-scaffold-display` está implementado y archivado. Lo que condiciona este diseño:

- **El servidor no hace ninguna llamada a la red.** El `readme.txt` lo declara así ("the WordPress server itself makes no requests"). Este cambio introduce las primeras llamadas y tiene que reescribir "External services".
- **Normalización pura y compartida.** `Thread_Uri::normalize()` (PHP) y `src/editor/thread-uri.js` son funciones puras que se prueban con `tests/fixtures/thread-uris.json`. Hoy un handle devuelve `error: 'handle'` y se rechaza.
- **El meta solo admite la forma canónica por REST.** El `pattern` del schema de `_atproto_thread_uri` rechaza con 400 todo lo que no sea `at://<did>/…`. El panel normaliza en el cliente y envía la forma canónica.
- **Ajustes en una sola opción.** `atproto_comments_settings` se guarda desde `options.php` con el grupo `atproto_comments`, con autoload, y su `sanitize_callback` conserva por campo el valor anterior.
- **Panel del editor en un único componente** (`ThreadPanel` en `src/editor/index.js`), con JSX clásico porque WP 6.5 no registra `react-jsx-runtime`.
- **Funciones disponibles en WP 6.5 + PHP 7.4:** `sodium_crypto_secretbox` siempre está disponible, porque WordPress incluye `sodium_compat` desde 5.2 aunque falte `ext-sodium`. `ext-intl` no está garantizada.

La motivación está en `proposal.md`; los requisitos, en `specs/`.

## Goals / Non-Goals

**Goals:**
- Que la app password no salga nunca del servidor ni quede en claro en la base de datos, en los logs o en las respuestas.
- Que las llamadas a hosts que decide el usuario (dominio del handle, `did:web`, PDS) no permitan atacar la red interna (SSRF).
- Una sola implementación de la resolución de identidad, que usan la cuenta, la publicación y el campo del vínculo.
- Que una publicación nunca deje un post huérfano por un doble clic.

**Non-Goals:**
- Facets (enlaces, menciones, hashtags), `langs` y respuestas o citas: el post es texto plano con tarjeta.
- Cachear la sesión o la identidad entre publicaciones (ver decisión 5).
- Publicar desde el editor clásico. El metabox solo gana la resolución de handles.
- Borrar o editar el post en AT Protocol desde WordPress.
- OAuth de AT Protocol: la v1 usa app passwords.

## Decisions

### 1. Nuevas piezas

```
includes/
  class-crypto.php        cifrado y descifrado de la app password
  class-identity.php      handle → DID → documento DID → PDS
  class-xrpc.php          cliente XRPC mínimo (POST JSON, POST binario, mapa de errores)
  class-account.php       opción de cuenta, sección de ajustes, ruta REST de prueba
  class-anchor-post.php   ruta REST de publicación, lock y construcción del registro
  class-thread-link.php   resolución de handles en el vínculo (envuelve Thread_Uri)
src/editor/
  index.js                ThreadPanel + PublishForm
  publish-form.js         formulario de publicación
  graphemes.js            contador de grafemas
src/settings/index.js     botón de prueba de conexión (nueva entrada de wp-scripts)
```

`Thread_Uri` sigue siendo pura y sin red. Todo lo que hace peticiones queda en `Identity` y `Xrpc`, y cada una pasa por un único método de transporte para poder sustituirlo en los tests (`pre_http_request` para HTTP y un filtro interno para DNS).

Alternativa descartada: una sola clase `Client` con identidad, sesión y publicación. Mezcla responsabilidades y complica probar la resolución sin XRPC.

### 2. Cifrado con `sodium_crypto_secretbox`

- Clave: `hash_hmac( 'sha256', 'atproto-comments-app-password-v1', wp_salt( 'auth' ), true )`, 32 bytes. `wp_salt( 'auth' )` concatena `AUTH_KEY` y `AUTH_SALT`, y si las constantes no están definidas (o tienen el valor por defecto) usa sales generadas en la base de datos.
- Formato guardado: `v1:` + base64( nonce de 24 bytes ‖ texto cifrado ). El prefijo permite cambiar de algoritmo más adelante.
- Si el descifrado devuelve `false`, el formato no se reconoce o falta el valor, `Crypto::decrypt()` devuelve `null` y la cuenta se trata como no configurada. Los ajustes muestran un aviso que pide volver a escribir la contraseña.
- La contraseña en claro solo vive en una variable local durante `createSession`. No se guarda en propiedades estáticas ni aparece en mensajes de error, y se llama a `sodium_memzero()` al terminar.

Alternativas descartadas:
- `openssl_encrypt` (AES-256-GCM): `ext-openssl` no está garantizada y hay que gestionar a mano el IV y la etiqueta. `sodium_compat` garantiza secretbox en cualquier sitio con WP 6.5.
- Guardar la contraseña en `wp-config.php` (constante): es más seguro, pero rompe el flujo de ajustes que pide la propuesta. Queda como posible mejora: si se define `ATPROTO_COMMENTS_APP_PASSWORD`, tendría prioridad.

### 3. Opción de cuenta separada

Nueva opción `atproto_comments_account` (sin autoload), con `identifier` (handle en minúsculas sin `@`, o DID) y `password` (cifrada). Se registra con `register_setting` en el mismo grupo `atproto_comments`, así que la guarda el mismo formulario de `options.php`, pero tiene su propio `sanitize_callback`:

- Identificador vacío o no válido (no encaja con `Thread_Uri::HANDLE` ni con `Thread_Uri::DID`) → error y valor anterior.
- Campo de contraseña vacío → se conserva la cifrada. Si trae valor, se recorta y se cifra.
- Botón "Olvidar cuenta" (`name="atproto_comments_account[forget]"`, un submit del mismo formulario) → devuelve la opción vacía.
- El campo se pinta siempre vacío, con `autocomplete="new-password"` y la indicación "Hay una contraseña guardada".

Al guardar no se hace ninguna petición a la red: la comprobación es el botón de prueba.

Alternativa descartada: añadir claves a `atproto_comments_settings`. Esa opción se carga en cada petición (autoload), y su `sanitize_callback` mezclaría los errores de visualización con los de la cuenta. Con dos opciones, los errores son independientes sin más lógica.

### 4. Resolución de identidad

`Identity::resolve_handle( $handle )`:
1. DNS: `dns_get_record( '_atproto.' . $handle, DNS_TXT )`, si la función existe. Se usa el único registro `did=…` válido; si hay varios, se descarta.
2. HTTPS: `wp_safe_remote_get( "https://{$handle}/.well-known/atproto-did" )`. La primera línea del cuerpo tiene que ser un DID válido.

`Identity::resolve_did( $did )`:
- `did:plc` → `https://plc.directory/<did>`; `did:web` → `https://<dominio con %3A decodificado>/.well-known/did.json`.
- Se comprueba que `id` coincide con el DID. El PDS es el `serviceEndpoint` del servicio con `id` `#atproto_pds` (o `<did>#atproto_pds`) y tipo `AtprotoPersonalDataServer`, y tiene que ser una URL `https`.
- Devuelve también `alsoKnownAs` para la verificación bidireccional.

`Identity::resolve_account( $identifier )` encadena las dos funciones y, si el identificador era un handle, exige `at://<handle>` en `alsoKnownAs`. Con un DID, el handle que se muestra es el primero de `alsoKnownAs`.

En el campo del vínculo solo se usa `resolve_handle()` (sin documento DID ni verificación bidireccional): basta con el DID para formar el `at://`, y se ahorra una petición. El resultado se guarda en un transient de 10 minutos por handle, para no repetir la consulta en un guardado inmediato.

Todas las peticiones:
- usan `wp_safe_remote_*`, que rechaza IP privadas y de loopback y valida las redirecciones (protección contra SSRF);
- tienen un timeout de 5 s, `redirection` de 2 como máximo y `limit_response_size` de 64 KB;
- devuelven `WP_Error` con códigos propios (`atproto_comments_handle_unresolved`, `…_did_unresolved`, `…_no_pds`, `…_handle_mismatch`, `…_network`) y mensajes traducidos.

Alternativas descartadas:
- `com.atproto.identity.resolveHandle` en un AppView o PDS: pone un servicio de terceros en el camino y contradice el requisito de no depender de un AppView fijo. Además, el AppView configurado hoy solo lo usa el navegador del visitante.
- DNS over HTTPS (Cloudflare, Google): añade otro servicio externo que declarar. Si `dns_get_record` no está disponible o falla, el método HTTPS cubre los handles alojados en un PDS (p. ej. `*.bsky.social`).

### 5. Sin caché de sesión ni de identidad

Cada publicación y cada prueba de conexión resuelven la identidad y llaman a `createSession` de nuevo. Los `accessJwt` y `refreshJwt` no se guardan nunca.

Motivo: publicar es una acción manual y poco frecuente (una vez por entrada). Guardar los tokens añadiría un secreto más que cifrar, renovar y revocar. Resolver siempre de nuevo también sigue la migración de una cuenta a otro PDS sin pasos extra. El límite de `createSession` de Bluesky (30 cada 5 minutos, 300 al día por cuenta) queda muy por encima de este uso.

Alternativa descartada: guardar la sesión en un transient cifrado y renovarla con `refreshSession`. Ahorra unos 300 ms por publicación a cambio de mucho más estado y más casos de error.

### 6. Cliente XRPC

`Xrpc::procedure( $pds, $nsid, $body, $token = null )` (JSON) y `Xrpc::upload_blob( $pds, $bytes, $mime, $token )`, sobre `wp_safe_remote_post` sin redirecciones. Timeouts: 10 s, y 20 s para `uploadBlob`.

Mapa de errores a `WP_Error`, cada uno con su estado HTTP para la respuesta REST:

| PDS | Código | Estado REST |
|---|---|---|
| error de transporte o timeout | `atproto_comments_network` | 502 |
| 401, `AuthenticationRequired`, `InvalidPassword` | `atproto_comments_auth` | 502 |
| `AuthFactorTokenRequired` | `atproto_comments_auth_factor` ("usa una app password") | 502 |
| `AccountTakedown`, `AccountDeactivated` | `atproto_comments_account_unavailable` | 502 |
| 429 | `atproto_comments_rate_limited` | 429 |
| cualquier otro error o JSON no válido | `atproto_comments_server` | 502 |

Un `error`/`message` del PDS nunca se muestra tal cual; como mucho se añade el código `error` entre paréntesis para dar soporte. Tras `createSession` se comprueba que el `did` de la respuesta coincide con el resuelto.

### 7. Ruta de publicación

`POST /atproto-comments/v1/posts/(?P<id>\d+)/anchor-post` con `{ text }`. El `permission_callback` exige `edit_post`. La protección CSRF es la de la API REST: con autenticación por cookie, sin `X-WP-Nonce` el usuario se trata como anónimo y falla el permiso. `apiFetch` envía el nonce.

Orden:
1. Validar: la entrada existe, el tipo está activo, `post_status === 'publish'`, el vínculo está vacío, la cuenta está configurada y se puede descifrar, y el texto tiene entre 1 y 300 grafemas y como mucho 3000 bytes UTF-8 (el otro límite del lexicon). Fallo → 400, o 409 si ya hay vínculo.
2. Tomar el lock (decisión 8) y volver a comprobar el vínculo dentro del lock.
3. `resolve_account` → `createSession` → imagen (decisión 10) → `createRecord`.
4. Validar con `Thread_Uri::is_canonical()` la `uri` devuelta y guardarla con `update_post_meta`.
5. Liberar el lock en un `finally`, y responder `{ uri }` con 201.

**Conteo de grafemas en PHP:** `preg_match_all( '/\X/u', … )`. `\X` (clústeres de grafemas extendidos de PCRE2) está disponible en PHP 7.3+ sin `ext-intl`. En JS se usa `Intl.Segmenter` y, si no existe, `Array.from( text ).length`, que cuenta de más y nunca deja pasar un texto que el servidor rechace.

**Respuesta ambigua:** si `createRecord` agota el tiempo o falla el transporte después de enviar la petición, el post puede haberse creado. En ese caso, el error (`atproto_comments_uncertain`) dice que se compruebe el perfil antes de reintentar y que, si el post existe, se pegue su enlace.

Alternativa descartada: fijar el `rkey` (TID generado en el servidor) para que un reintento sea idempotente. La forma en que cada PDS responde a un `rkey` repetido no está bien documentada, y el caso es raro.

### 8. Lock contra doble envío

`INSERT IGNORE` en `wp_options` de `atproto_comments_lock_<post_id>`, con la marca de tiempo y `autoload = 'no'`, usando `$wpdb->query()`. Si afecta a una fila, el lock es nuestro. Si no, y la marca tiene más de 120 s, se borra y se reintenta una vez. Si no, 409.

Se usa una consulta directa (anotada para PHPCS) porque:
- `add_option()` hace `INSERT … ON DUPLICATE KEY UPDATE` y no falla si la fila ya existe;
- los transients no son atómicos sin una caché de objetos persistente;
- `add_post_meta( …, true )` hace un `SELECT` y luego un `INSERT`, así que tiene una condición de carrera.

Alternativa descartada: `GET_LOCK()` de MySQL. Depende de la conexión y no lo soportan bien algunos proxies o SQLite (WordPress Playground).

### 9. Contenido del registro

```json
{
  "$type": "app.bsky.feed.post",
  "text": "<texto tal cual>",
  "createdAt": "<gmdate Y-m-d\\TH:i:s.v\\Z>",
  "embed": {
    "$type": "app.bsky.embed.external",
    "external": { "uri": "<get_permalink>", "title": "…", "description": "…", "thumb": { blob } }
  }
}
```

- `title`: `post_title` en crudo (no `get_the_title()`, que añade "Protegido:" y aplica filtros de HTML), sin etiquetas y con las entidades decodificadas.
- `description`: si la entrada tiene contraseña, `''`. Si no, `get_the_excerpt( $post )` sin etiquetas, con las entidades decodificadas, los espacios colapsados y recortado a 300 grafemas. El filtro `the_content` del plugin ya se salta los extractos (`doing_filter( 'get_the_excerpt' )`).
- `thumb`: solo si la subida va bien (decisión 10).

### 10. Imagen de la tarjeta

Se toma el `medium_large` de la imagen destacada con `image_get_intermediate_size()`. Si no hay intermedio (imagen original de 768 px de ancho o menos), se usa el archivo original. El archivo se lee del disco (`get_attached_file()` y la carpeta del tamaño) y se exige que:
- exista;
- tenga uno de los tipos `image/jpeg`, `image/png`, `image/webp` o `image/gif`;
- ocupe como mucho 1 000 000 bytes.

Si falla cualquier comprobación, o falla `uploadBlob`, se publica sin `thumb`.

Alternativa descartada: descargar la imagen por su URL. Funcionaría con plugins que llevan los medios a S3, pero supone otra petición saliente y una superficie de SSRF más. Con medios externos, la tarjeta sale sin imagen y se documenta en la FAQ.

### 11. Panel del editor

`ThreadPanel` añade un `PublishForm` debajo del campo del vínculo. El PHP pasa con `wp_add_inline_script` un objeto `atprotoCommentsEditor = { accountReady: bool, settingsUrl: string|null }`. `settingsUrl` solo se envía si el usuario tiene `manage_options`. Nunca se envía el identificador ni la contraseña.

El estado del botón sale de `core/editor`:
- `getCurrentPostAttribute( 'status' ) === 'publish'`;
- `! isEditedPostDirty()`;
- vínculo vacío tanto en el valor guardado como en el editado;
- `accountReady`, y texto válido.

Cada motivo de bloqueo tiene su texto. El texto del post es estado local de React, no meta: no se guarda con la entrada.

Tras un 201, el panel pone el vínculo en el registro guardado sin marcar la entrada como modificada: `receiveEntityRecords( 'postType', type, [ registro con el meta nuevo ] )` en `core`, y después `editPost` con el mismo valor. Si en alguna versión eso marcara la entrada como sucia, la alternativa aceptable es dejar solo `editPost`: guardar después es inocuo, porque escribe el mismo valor.

**Etiqueta del botón:** "Publish to Bluesky", con una ayuda que aclara que el post se ve en cualquier app de AT Protocol. Bluesky es el nombre que reconocen los editores, y el registro es un `app.bsky.feed.post`. Alternativa descartada: "Publish to AT Protocol", que es más exacta pero no la entiende la mayoría.

### 12. Handles en el campo del vínculo

`Thread_Uri::normalize()` sigue devolviendo `error: 'handle'`, pero ahora añade `handle` y `rkey`. Los fixtures se actualizan para cubrir esos campos, en PHP y en JS a la vez. La resolución queda fuera de la función pura:

- **Editor de bloques:** si `normalize` devuelve `handle`, el panel llama a `GET /atproto-comments/v1/resolve-handle?handle=…` (permiso `edit_posts`). Con el DID forma el `at://` canónico y hace `editPost`. Mientras resuelve, muestra un spinner y no toca el meta. Si falla, muestra el mensaje de "handle no resuelto". El `pattern` del meta no cambia: por REST solo entra la forma canónica.
- **Editor clásico:** `Thread_Link::resolve( $raw )` normaliza, resuelve el handle si hace falta y devuelve el `at://` o el error `handle_unresolved`, que se muestra como aviso con el mecanismo de transient que ya existe.

Alternativa descartada: aceptar handles en el meta y resolverlos en el `sanitize_callback`. Haría peticiones de red en cada guardado por REST, y un fallo no se puede comunicar desde ese callback.

### 13. Prueba de conexión

`POST /atproto-comments/v1/account/test` (permiso `manage_options`) prueba la cuenta **guardada**: `resolve_account` + `createSession`. Devuelve `{ handle, did, pds }` o el error traducido. `src/settings/index.js` (entrada nueva de `wp-scripts`, encolada solo en la página de ajustes) pinta el resultado en una región `aria-live`. Si el formulario tiene cambios sin guardar en la cuenta, el botón avisa de que se prueba lo guardado.

### 14. Desinstalación y readme

- `uninstall.php` también borra `atproto_comments_account`, los locks (`atproto_comments_lock_%`) y los transients de handles, en cada sitio en multisite.
- "External services" del `readme.txt` se reescribe con:
  - el PDS de la cuenta: sesión, imagen y post, solo al pulsar el botón o al probar la conexión;
  - `plc.directory` o el dominio de `did:web`: el DID;
  - el dominio del handle: DNS y `/.well-known/atproto-did`;
  - qué datos se envían en cada caso;
  - los términos de Bluesky para cuentas en sus PDS.
- En "Privacy" se explica que la app password se guarda cifrada.

### 15. Verificación por versión

| Qué | WP 6.5 + PHP 7.4 | Última versión de WP + PHP 8.3 |
|---|---|---|
| PHPUnit (cifrado, identidad con `pre_http_request` y DNS simulado, ruta de publicación, lock, ajustes, desinstalación) | sí (`.wp-env.json`) | sí (`.wp-env.override.json`) |
| `sodium_compat` sin `ext-sodium` (test que fuerza el polyfill) | sí | no hace falta |
| `INSERT IGNORE` y `autoload = 'no'` (6.6 introduce `on`/`off`, pero sigue aceptando `no`) | sí | sí |
| Panel: selectores de `core/editor`, `receiveEntityRecords` sin marcar la entrada como modificada, estado del botón | manual | manual |
| Tests JS (grafemas, fixtures del vínculo) | `wp-scripts test-unit-js`, sin depender de la versión | — |
| Publicación real contra un PDS (Bluesky y otro PDS, p. ej. Eurosky) | manual, una vez | manual, una vez |
| Plugin Check y PHPCS (`PHPCompatibilityWP` 7.4-) | sí | sí |

## Risks / Trade-offs

- [`AUTH_KEY` o `AUTH_SALT` rotadas (algunos hosts lo hacen tras un incidente)] → La cuenta pasa a no configurada, con un aviso claro en los ajustes. No hay pérdida de datos más allá de volver a escribir la contraseña.
- [Quien lea la base de datos y `wp-config.php` puede descifrar la contraseña] → Es inevitable si el servidor tiene que usarla. Se mitiga recomendando una app password (revocable sin cambiar la contraseña principal) y lo dice la sección de ajustes.
- [`dns_get_record` desactivada o lenta en algunos hosts, sin timeout configurable] → El método HTTPS sirve de alternativa. Un handle con dominio propio que solo publique DNS fallará en ese host, con un mensaje que sugiere usar el DID.
- [Respuesta ambigua de `createRecord`: post creado pero no vinculado] → Mensaje específico que pide revisar el perfil y pegar el enlace. Un reintento ciego crearía un duplicado; el lock solo evita los envíos simultáneos.
- [El vínculo se guarda con `update_post_meta` fuera del guardado de la entrada; si el editor guarda después con un estado antiguo del meta, podría borrarlo] → El panel actualiza el registro en el store tras el 201, así que el siguiente guardado envía el valor nuevo. Se comprueba a mano en las dos versiones.
- [`wp_safe_remote_*` rechaza también un PDS autoalojado en una red privada (p. ej. pruebas locales)] → Se acepta en la v1. Si hace falta, el filtro estándar `http_request_host_is_external` de WordPress permite autorizar un host.
- [Medios externos (offload a S3)] → La tarjeta sale sin imagen; se documenta en la FAQ.
- [Cambios del lexicon de `app.bsky.feed.post` o `embed.external`] → Los campos que se usan son estables desde 2023. Los límites (300 grafemas, 3000 bytes, 1 MB) están en constantes de una sola clase.

## Migration Plan

No hay migración de datos: las opciones nuevas se crean al guardar la cuenta y los vínculos existentes no cambian. Para volver atrás basta con desactivar el plugin o volver a la versión anterior. La opción de cuenta queda huérfana pero cifrada, y se borra al desinstalar.
