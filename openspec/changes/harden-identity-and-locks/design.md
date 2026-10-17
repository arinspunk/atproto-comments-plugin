# Design

## Context

`plugin-scaffold-display`, `publish-from-editor` y `unlink-and-delete` están archivados. Lo que condiciona este diseño, comprobado en el código de `main`:

- **La cuenta se resuelve en cada operación.** `Anchor_Post::publish()`, `Anchor_Post::delete()` y `Account::rest_test()` llaman a `Identity::resolve_account()` y, si va bien, a `Account::remember_did()`, que guarda el DID resuelto **sea cual sea**. Después se descifra la app password y se llama a `createSession` contra el PDS del documento DID.
- **La verificación bidireccional no protege de una resolución falseada.** `resolve_account()` exige que el documento DID declare el handle en `alsoKnownAs`, pero si el handle se resuelve a un DID del atacante, el documento también es suyo y declara lo que quiera.
- **La especificación de handles de AT Protocol** (`atproto.com/specs/handle`) permite usar el primer resultado de DNS o HTTPS y, ante un conflicto, preferir el DNS o marcarlo como ambiguo. No exige comparar. El código actual es conforme; con plazo prueba HTTPS primero porque `dns_get_record()` no tiene timeout.
- **La cuenta ya guarda `did`** (desde `unlink-and-delete`): `Account::get()['did']`, que `sanitize()` conserva mientras no cambie el identificador y vacía si cambia. Si el identificador es un DID, `did` es ese DID.
- **`Thread_Link::resolve_handle()`** (metabox y `GET /resolve-handle`, permiso `edit_posts`) llama a `Identity::resolve_handle()` sin `Deadline`: DNS primero, sin timeout propio. Cachea solo los aciertos (10 min).
- **El cerrojo** (`Anchor_Post::acquire_lock()`): `INSERT IGNORE` atómico con la marca de tiempo; si la fila existe y tiene más de 120 s, `release_lock()` (un `DELETE` por nombre) y otro `INSERT IGNORE`. `release_lock()` en el `finally` también borra por nombre.
- **`Thread_Uri::DID`** acepta `did:web:[a-zA-Z0-9.-]+` sin tope, y la misma expresión está repetida en `Thread_Uri::PATTERN` y en `src/editor/thread-uri.js`.

La motivación está en `proposal.md`; los requisitos, en `specs/`.

## Goals / Non-Goals

**Goals:**
- Que la app password nunca se envíe a un PDS de un DID distinto del que el administrador ha validado, aunque la resolución del handle esté falseada.
- Que un cambio legítimo de identidad (el handle pasa a otra cuenta) se pueda aceptar con una acción explícita del administrador, viendo qué cambia.
- Que resolver un handle desde el editor tenga un tiempo máximo y no se pueda repetir en bucle a coste completo.
- Que, con un cerrojo caducado, como mucho una petición publique o borre, y que ninguna petición libere el cerrojo de otra.

**Non-Goals:**
- Comparar DNS y HTTPS y fallar si discrepan: la especificación no lo pide, no protege frente a quien controla la red y no aplica a los handles que solo publican por HTTPS.
- Resolver con DNS sobre HTTPS o DNSSEC: añadiría un servicio externo que declarar.
- Guardar en la base de datos un historial de quién publicó o borró. El cambio expone acciones para que un plugin de auditoría lo haga.
- Limitar `GET /resolve-handle` a una capacidad más estrecha que `edit_posts`: los colaboradores también vinculan entradas.

## Decisions

### 1. DID fijado de la cuenta

El campo `did` de `atproto_comments_account` pasa a ser el **DID fijado**: la identidad que el sitio acepta para su cuenta. `Account::remember_did()` se sustituye por dos funciones:

- `Account::pinned_did()`: el `did` guardado, o `''`.
- `Account::pin_did( $did )`: lo guarda (por `update_option`, pasando por `sanitize()`, como hoy).

Publicar y borrar, después de `resolve_account()` y **antes** de descifrar la contraseña:

| `pinned_did()` | DID resuelto | Acción |
|---|---|---|
| `''` | cualquiera | `pin_did()` con el resuelto y se sigue (primer uso). |
| igual al resuelto | — | Se sigue. |
| distinto del resuelto | — | Error `atproto_comments_identity_changed` (409), sin descifrar la contraseña ni llamar a `createSession`. El mensaje pide revisar la cuenta en los ajustes. |

La prueba de conexión aplica la misma tabla, con una diferencia: con un DID distinto, la respuesta 409 incluye `{ pinned_did, resolved_did, resolved_handle, resolved_pds }` para que el administrador vea qué ha cambiado (decisión 2).

**Primer uso.** Fijar el DID en la primera resolución (también si es una publicación) deja una ventana: si la primera resolución de todas ya está falseada, se fija el DID del atacante. Se acepta porque:
- la sección de ajustes invita a probar la conexión justo después de guardar la cuenta, y la prueba muestra el handle, el DID y el PDS resueltos;
- con un identificador `did:…` no hay ventana: `sanitize()` fija `did` igual al identificador al guardar, sin red;
- la alternativa (no publicar hasta probar la conexión) rompe a los sitios que ya publican con la versión anterior y no tienen `did` guardado.

**Qué no cambia:** la cuenta se sigue resolviendo en cada operación para obtener el PDS actual (una cuenta puede migrar de PDS sin cambiar de DID), y `resolve_account()` sigue exigiendo `alsoKnownAs`.

Alternativas descartadas:
- Comparar DNS y HTTPS (la recomendación del análisis): ver Non-Goals.
- Guardar también el PDS y no volver a resolver: rompe la migración de PDS y la resolución sigue haciendo falta para detectarla.
- Actualizar el DID fijado en silencio y avisar después: la contraseña ya se habría enviado.

### 2. Confirmar un cambio de identidad

`POST /atproto-comments/v1/account/test` acepta un parámetro opcional `accept_did`. Con un DID distinto del fijado:

- sin `accept_did`, o con uno que no coincide con el resuelto ahora: 409 `atproto_comments_identity_changed` con los datos de la decisión 1, sin sesión;
- con `accept_did` igual al resuelto en **esta** petición: `pin_did( $resolved )`, `createSession` y respuesta normal `{ handle, did, pds }`.

`src/settings/index.js` muestra el 409 en la región `aria-live` con el DID anterior, el nuevo, el handle y el PDS, y un botón "Usar la nueva identidad" que repite la prueba con `accept_did`. El aviso explica que, si no se esperaba el cambio, no hay que confirmarlo.

Cambiar el identificador en los ajustes ya vacía `did` (comportamiento actual de `sanitize()`), así que cambiar de cuenta no pide confirmación: la siguiente resolución fija la nueva.

Alternativa descartada: confirmar desde el panel del editor. El permiso para publicar no implica poder decidir cuál es la cuenta del sitio; eso es `manage_options`.

### 3. Resolución de handles del vínculo con plazo

`Identity::resolve_handle( $handle, $deadline = null, $dns_min = self::DNS_MIN_TIME )` gana el tercer parámetro: el tiempo mínimo que tiene que quedar para intentar el DNS después del HTTPS. La cuenta sigue usando 20 s.

`Thread_Link::resolve_handle()`:
- crea un `Deadline` de 15 s (`Thread_Link::DEADLINE`) y llama con `$dns_min` = 8 s, así que el orden es HTTPS (timeout 5 s) y, si falla y quedan al menos 8 s, DNS;
- guarda los fallos en un transient de 60 s (`atproto_comments_handle_miss_` + `md5( $handle )`) y, mientras existe, responde el error sin red;
- los aciertos se siguen cacheando 10 minutos.

`dns_get_record()` sigue sin timeout propio: el tiempo que puede tardar lo acota el resolver del sistema (habitualmente unos 5 s por intento). Con el umbral de 8 s, un DNS lento puede alargar la petición más allá de los 15 s, pero solo una vez por handle cada 60 s gracias a la caché de fallos.

Un handle recién configurado tarda como mucho 60 s en poder resolverse tras un fallo. El mensaje de error del panel ya sugiere pegar la URL con el DID.

Alternativas descartadas:
- No usar DNS en el vínculo: los handles con dominio propio que solo publican el registro TXT no se podrían pegar.
- Un límite de ritmo por usuario: más estado para el mismo efecto que la caché de fallos, que ya evita repetir la consulta cara.

### 4. Cerrojo con dueño

La fila del cerrojo guarda `"<marca de tiempo>|<token>"`, con `<token>` = `wp_generate_password( 20, false )`.

- `acquire_lock( $post_id )` devuelve el valor guardado (`string`) o `false`:
  1. `INSERT IGNORE` con el valor nuevo; si afecta a una fila, devuelve ese valor.
  2. Si no, `SELECT` del valor actual. Si no ha caducado (120 s), `false`.
  3. Si ha caducado: `UPDATE … SET option_value = <nuevo> WHERE option_name = <nombre> AND option_value = <valor leído>`. Si afecta a una fila, es nuestro; si no, otra petición ganó: `false`.
  4. Si la fila desapareció entre el `SELECT` y el `UPDATE`, un único reintento del `INSERT IGNORE`.
- `release_lock( $post_id, $value )` hace `DELETE … WHERE option_name = <nombre> AND option_value = <valor>`: solo borra su propio cerrojo.

Las dos operaciones que lo usan (`rest_publish` y la ruta de borrado) guardan el valor y lo pasan al `finally`. Las filas con el formato antiguo (solo marca de tiempo) se leen igual: `(int)` del valor antes del `|`.

Alternativa descartada: `GET_LOCK()` de MySQL (ya descartado en `publish-from-editor`).

### 5. Tope del `did:web`

`did:web:[a-zA-Z0-9.-]{1,253}(?:%3A[0-9]{1,5})?`, el mismo tope de 253 caracteres que un nombre de dominio, en `Thread_Uri::DID`, `Thread_Uri::PATTERN` y `src/editor/thread-uri.js`. Los fixtures compartidos ganan un caso con 254 caracteres (error de formato) y otro con 253 (válido).

Un vínculo guardado antes con un `did:web` más largo deja de considerarse canónico y el frontend no lo muestra, igual que cualquier otro valor no canónico. No es un caso realista: ningún dominio pasa de 253 caracteres.

### 6. Acciones de actividad

Tras guardar el vínculo de una publicación correcta:

```php
do_action( 'atproto_comments_anchor_published', $post_id, $uri, get_current_user_id() );
```

Tras borrar el meta en un borrado correcto (también cuando el post ya no existía):

```php
do_action( 'atproto_comments_anchor_deleted', $post_id, $uri, get_current_user_id() );
```

Se documentan en la FAQ. No se disparan en errores ni al desvincular (desvincular es una edición del meta que ya registran los hooks de WordPress).

Alternativa descartada: un meta `_atproto_published_by`. Se pierde al borrar el post ancla y no cubre el borrado; un plugin de auditoría guarda mejor el historial.

### 7. FAQ y ajustes

- Nueva entrada de FAQ "Which account should I use?": una cuenta dedicada al sitio, no la personal de nadie, porque la app password permite publicar y borrar en su nombre; y configurarla con su DID (`did:plc:…`, se ve en la prueba de conexión) en lugar del handle, porque así el plugin no depende de resolver el handle.
- Nueva entrada "It says the account's identity has changed": qué significa, cuándo es esperado (el handle pasó a otra cuenta) y que, si no lo es, no hay que confirmarlo.
- Las acciones de la decisión 6, en la entrada de quién puede publicar.
- La sección de cuenta de los ajustes añade una línea bajo el identificador: "Recommended: use the account's DID (did:plc:…)".

### 8. Verificación por versión

| Qué | WP 6.5 + PHP 7.4 | Última WP + PHP 8.3 |
|---|---|---|
| PHPUnit: tabla de la decisión 1 en publicar, borrar y prueba (sin `createSession` ni descifrado con DID distinto) | sí | sí |
| PHPUnit: `accept_did` que coincide, que no coincide y ausente | sí | sí |
| PHPUnit: plazo y orden HTTPS → DNS en el vínculo, caché de fallos | sí | sí |
| PHPUnit: cerrojo (dos recuperaciones de un cerrojo caducado, solo una gana; liberar con otro token no borra; fila con formato antiguo) | sí | sí |
| PHPUnit + tests JS: fixtures del `did:web` de 253 y 254 caracteres | sí | sí |
| PHPUnit: acciones con los argumentos correctos y sin disparar en errores | sí | sí |
| Aviso de identidad cambiada y botón de confirmación en los ajustes | manual | manual |

## Risks / Trade-offs

- [Primer uso con una resolución ya falseada] → Ventana única, cerrada en la práctica por la prueba de conexión tras guardar y nula con un identificador `did:…`, que la FAQ y los ajustes recomiendan.
- [Un cambio legítimo de identidad bloquea publicar y borrar hasta que el administrador lo confirma] → Es el comportamiento buscado; el error del panel lo explica y enlaza a los ajustes si el usuario tiene `manage_options`.
- [Un handle recién configurado tarda hasta 60 s en resolverse tras un fallo] → Aceptable; el mensaje sugiere la URL con el DID.
- [DNS del sistema lento en el vínculo] → Acotado por el resolver del sistema y por la caché de fallos.
- [Filas de cerrojo con formato antiguo durante la actualización] → Se leen igual; como mucho viven 120 s.

## Migration Plan

Sin migración de datos. Tras actualizar, los sitios con `did` guardado quedan fijados a él; los que no, lo fijan en la siguiente resolución. Volver atrás es instalar la versión anterior: el campo `did` se sigue usando como hoy.
