# Tasks

## 1. DID fijado de la cuenta

- [ ] 1.1 Sustituir `Account::remember_did()` por `Account::pinned_did()` y `Account::pin_did( $did )` (design §1), y añadir una función común (p. ej. `Account::check_identity( $resolved_did )`) que aplica la tabla: sin DID fijado, fija y sigue; igual, sigue; distinto, devuelve `atproto_comments_identity_changed` (409) con `pinned_did`, `resolved_did`, `resolved_handle` y `resolved_pds` en los datos. Verificar con tests PHPUnit cada fila de la tabla, que `pin_did()` conserva el identificador y la contraseña cifrada, y que la opción sigue sin autoload.
- [ ] 1.2 Usar la comprobación en `Anchor_Post::publish()` y `Anchor_Post::delete()` justo después de `resolve_account()` y antes de `Crypto::decrypt()`. Verificar con tests PHPUnit (`pre_http_request` y DNS simulado) que, con un DID fijado distinto del resuelto, ni la publicación ni el borrado descifran la contraseña ni hacen ninguna petición al PDS (ningún `createSession`, `createRecord`, `uploadBlob` ni `deleteRecord`), que el vínculo no cambia, y que sin DID fijado la primera publicación lo fija.
- [ ] 1.3 Añadir `accept_did` (opcional, string) a `POST /atproto-comments/v1/account/test` según el design §2. Verificar con tests PHPUnit: DID distinto sin `accept_did` → 409 con los cuatro datos y sin `createSession`; `accept_did` igual al resuelto → DID fijado actualizado y respuesta normal; `accept_did` distinto del resuelto → 409, sin cambios y sin `createSession`; sin DID fijado → se fija y sigue.
- [ ] 1.4 En `src/settings/index.js`, mostrar el 409 `atproto_comments_identity_changed` en la región `aria-live` con el DID anterior, el nuevo, el handle y el servidor, la explicación de que no hay que confirmarlo si no se esperaba, y el botón "Use the new identity" que repite la prueba con `accept_did`; añadir bajo el identificador la recomendación de usar el DID. En el panel del editor, mostrar el error de identidad cambiada con el enlace a los ajustes si hay `settingsUrl`. Verificar en wp-env con WP 6.5 y la última versión, forzando con un mu-plugin de pruebas (filtro de DNS o `pre_http_request`) que el handle resuelva a otro DID: el aviso aparece con los datos, confirmar fija el DID nuevo y la prueba termina en verde, y publicar desde el editor antes de confirmar muestra el error con el enlace.

## 2. Resolución de handles del vínculo

- [ ] 2.1 Añadir a `Identity::resolve_handle()` el parámetro `$dns_min` (por defecto `DNS_MIN_TIME`), y hacer que `Thread_Link::resolve_handle()` use un `Deadline` de 15 s con `$dns_min` = 8 y la caché de fallos de 60 s (`atproto_comments_handle_miss_` + `md5( $handle )`), según el design §3. Ampliar `uninstall.php` para borrar también esos transients. Verificar con tests PHPUnit que el HTTPS va primero, que el DNS se intenta solo si quedan al menos 8 s (reloj simulado), que un fallo crea el transient y la segunda llamada no hace ninguna petición ni consulta DNS, que un acierto se sigue cacheando 10 minutos, que un handle que solo publica DNS se resuelve, y que la desinstalación borra los transients de fallos.

## 3. Cerrojo con dueño

- [ ] 3.1 Cambiar `Anchor_Post::acquire_lock()` y `release_lock()` según el design §4 (valor `"<marca>|<token>"`, recuperación con `UPDATE` condicional, liberación con `DELETE` condicional) y actualizar sus dos llamadas para pasar el valor al `finally`. Verificar con tests PHPUnit: dos llamadas a `acquire_lock()` sobre una fila caducada, intercalando el `SELECT` de ambas antes de sus `UPDATE` (con un filtro `query` de pruebas), y solo una obtiene el cerrojo; `release_lock()` con otro valor no borra la fila; una fila con el formato antiguo (solo marca) caducada se recupera y una sin caducar bloquea; y los tests existentes del cerrojo siguen pasando.

## 4. Tope del `did:web`

- [ ] 4.1 Cambiar la expresión del `did:web` a `did:web:[a-zA-Z0-9.-]{1,253}(?:%3A[0-9]{1,5})?` en `Thread_Uri::DID`, `Thread_Uri::PATTERN` y `src/editor/thread-uri.js`, y añadir a `tests/fixtures/thread-uris.json` un `did:web` de 253 caracteres (válido) y otro de 254 (`format`). Verificar con `npm run test:php` y `npm run test:js`, y con un test PHPUnit REST que el de 254 caracteres devuelve 400.

## 5. Acciones de actividad

- [ ] 5.1 Disparar `atproto_comments_anchor_published` tras guardar el vínculo de una publicación correcta y `atproto_comments_anchor_deleted` tras borrar el meta en un borrado correcto, con `( $post_id, $uri, get_current_user_id() )`, documentadas con un docblock de hook. Verificar con tests PHPUnit (`did_action` y un callback que guarda los argumentos) los argumentos en los dos casos, también en el borrado de un post que ya no existía, y que no se disparan con credenciales rechazadas, identidad cambiada, 429 ni resultado incierto.

## 6. readme e internacionalización

- [ ] 6.1 Añadir a la FAQ de `readme.txt` "Which account should I use?" (cuenta dedicada y DID como identificador) y "It says the account's identity has changed", y las dos acciones en "Who can publish?"; añadir las entradas al changelog. Verificar que pasa el validador de readmes de WordPress.org.
- [ ] 6.2 Regenerar `languages/atproto-comments.pot` con `npm run make-pot` y verificar que incluye los mensajes de identidad cambiada, el botón de confirmación y la recomendación del DID.

## 7. Verificación de integración

- [ ] 7.1 En wp-env, con una cuenta real de prueba: configurarla con su handle, probar la conexión (queda fijado el DID), publicar y borrar un post ancla con normalidad; después cambiar el identificador a su DID y repetir la publicación.
- [ ] 7.2 Ejecutar `composer phpcs`, `npm run lint:js`, `npm run test:php`, `npm run test:php:multisite`, `npm run test:js`, `npm run build` y `npm run plugin-check` con WP 6.5 + PHP 7.4 y con la última versión de WP + PHP 8.3, y verificar que todo pasa.

## Workflow follow-up

- Archivar el cambio cuando esté revisado y pasar `atproto-account`, `anchor-post-publishing` y `thread-linking` a `openspec/specs/`.
- Abrir la PR siguiendo la skill `flujo-pr`. Si `wporg-release` aún no ha publicado la v1, incluir este cambio antes del primer tag para que la revisión de WordPress.org vea el plugin endurecido.
