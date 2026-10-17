# Proposal

## Why

Un análisis de arquitectura y seguridad (2026-10-17) señaló tres puntos en el código, que se han comprobado en `main`:

- La cuenta del sitio se resuelve de nuevo en cada publicación, borrado y prueba de conexión, y el DID que sale se acepta y se guarda sin compararlo con el anterior (`Account::remember_did()` justo después de `Identity::resolve_account()`). Si la resolución del handle se falsea (por ejemplo, envenenando el DNS que usa el servidor), el atacante controla el documento DID, así que la verificación bidireccional por `alsoKnownAs` no protege, y `createSession` le envía la app password.
- `GET /resolve-handle` y el guardado del metabox resuelven handles sin plazo y con el DNS primero; `dns_get_record()` no tiene timeout propio, y un handle que no resuelve se puede repetir sin límite.
- La recuperación de un cerrojo caducado (`SELECT` → `DELETE` → `INSERT`) no tiene dueño: dos peticiones pueden quitarse el cerrojo y crear dos posts. `release_lock()` también borra sin comprobar de quién es.

El análisis proponía comparar las dos vías de resolución (DNS y HTTPS) y fallar si discrepan, citando la especificación de AT Protocol. La especificación dice lo contrario: se puede usar el primer resultado, y ante un conflicto se prefiere el DNS. Además, comparar no protege frente a quien controla la red, y los handles `*.bsky.social` solo publican por HTTPS. Este cambio ataca el riesgo de fondo fijando el DID de la cuenta.

## What Changes

- **DID fijado de la cuenta.** Publicar y borrar solo abren sesión si la cuenta resuelve al DID guardado. Si resuelve a otro, se para antes de enviar la contraseña y se pide revisar la cuenta en los ajustes. Solo la prueba de conexión, con una confirmación explícita del administrador, puede cambiar el DID guardado. Si aún no hay DID guardado, la primera resolución correcta lo fija.
- **Resolución de handles del vínculo con plazo.** `GET /resolve-handle` y el metabox usan un plazo de 15 s, HTTPS primero y DNS después solo si queda tiempo; los fallos se recuerdan 60 s para no repetir la misma consulta.
- **Cerrojo con dueño.** Cada petición guarda un token propio en la fila del cerrojo; recuperar uno caducado es un `UPDATE` condicional que solo gana una petición, y liberar solo borra la fila si el token es el suyo.
- **Tope de longitud en `did:web`** (253 caracteres en el dominio), en PHP, JS, el `pattern` REST y los fixtures.
- **Acciones para registro de actividad:** `atproto_comments_anchor_published` y `atproto_comments_anchor_deleted`, con la entrada, el `at://` y el usuario de WordPress, para que un plugin de auditoría pueda anotar quién publicó o borró.
- **FAQ:** usar una cuenta de Bluesky dedicada al sitio, configurarla con su DID (`did:plc:…`) en lugar del handle, y qué significa el aviso de identidad cambiada.

## Capabilities

### New Capabilities

### Modified Capabilities
- `atproto-account`: DID fijado de la cuenta y confirmación de un cambio de identidad en la prueba de conexión.
- `anchor-post-publishing`: comprobación del DID fijado antes de publicar o borrar, cerrojo con dueño y acciones de actividad.
- `thread-linking`: resolución de handles con tiempo limitado y tope de longitud del `did:web`.

## Impact

- PHP: `Account` (`pinned_did()`, `pin_did()`, prueba de conexión con `accept_did`), `Anchor_Post` (comprobación del DID, cerrojo con token, acciones), `Thread_Link` (plazo y caché de fallos), `Identity` (umbral de DNS configurable por llamada), `Thread_Uri` (regex).
- JS: `src/settings/index.js` (aviso de identidad cambiada y botón de confirmación), `src/editor/thread-uri.js` (regex), mensajes del panel para el error nuevo.
- `tests/fixtures/thread-uris.json`, `readme.txt` (FAQ y changelog).
- Sin migración de datos: los sitios que ya tienen `did` guardado quedan fijados a él; los que no, lo fijan en la siguiente resolución.
