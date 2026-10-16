# Design

## Context

`publish-from-editor` está implementado y archivado. Lo que condiciona este diseño:

- **Ya existe la ruta de publicación** (`POST /atproto-comments/v1/posts/<id>/anchor-post` en `Anchor_Post`), con `user_can_publish()`, el plazo global de 25 s (`Deadline`), el lock por entrada (`INSERT IGNORE` en `wp_options`) y el mapa de errores de `Xrpc`. El argumento `text` de la ruta es obligatorio y su 409 de lock dice que el post "ya se está publicando".
- **La cuenta guarda solo `identifier` y `password`.** El identificador puede ser un handle, así que el servidor no conoce el DID de la cuenta sin resolverlo, y al guardar los ajustes no se hace ninguna petición a la red.
- **`register_setting` engancha el `sanitize_callback` a cualquier `update_option()`** de la opción, no solo al formulario de ajustes. `Account::sanitize()` devuelve hoy solo `identifier` y `password`: cualquier otra clave que se guarde por `update_option()` se pierde.
- **El panel (`ThreadPanel`) muestra siempre el campo del vínculo** y debajo `PublishForm`. Tras publicar, `PublishForm` marca el vínculo como guardado con `editPost` + `receiveEntityRecords(…, persistedEdits)`, comprobado en WP 6.5 y en la última versión. Ese `editPost` entra en el historial de deshacer del editor.
- **El editor recibe `window.atprotoCommentsEditor`** (`accountReady`, `canPublish`, `settingsUrl`) desde `Post_Meta::editor_data()`, sin el identificador ni la contraseña.
- **El servidor no habla nunca con el AppView**; solo lo hace el navegador del visitante, y así lo declara el `readme.txt`.
- **En la ruta REST de WordPress, un meta `null` borra la fila** (`WP_REST_Meta_Fields::update_value`), mientras que `''` guarda una fila con la cadena vacía.

La motivación está en `proposal.md`; los requisitos, en `specs/`.

## Goals / Non-Goals

**Goals:**
- Que el borrado remoto solo pueda afectar al post del vínculo guardado y solo si es de la cuenta del sitio, aunque el navegador mande otra cosa.
- Que el mismo grupo de usuarios que puede publicar en la cuenta del sitio sea el único que puede borrar de ella.
- Que publicar y borrar la misma entrada a la vez no deje el vínculo incoherente.
- Que una entrada solo se desvincule tras un borrado si el post ya no existe en el PDS.
- Que nada en el editor (deshacer incluido) pueda volver a vincular un post desvinculado o borrado sin una acción explícita.
- Que abrir el editor no haga ninguna petición de red desde el servidor.

**Non-Goals:**
- Borrar automáticamente el post al borrar o enviar a la papelera la entrada (ver spec).
- Editar el post remoto (no existe en el lexicon; habría que borrar y volver a crear).
- Borrar el post desde el editor clásico o desde un listado de entradas.
- Borrar las respuestas: son registros de otros repos y no se pueden tocar.

## Decisions

### 1. Panel con dos estados

`ThreadPanel` decide según el valor **guardado** del meta (`getCurrentPostAttribute( 'meta' )`), no el editado:

- **Sin vínculo:** como hoy, el campo para pegar y `PublishForm`.
- **Vinculado:** nuevo componente `LinkedState` (`src/editor/linked-state.js`) con el `at://`, un `ExternalLink` al post, el botón "Desvincular" y, según el caso, "Borrar de Bluesky" (decisión 3).

Así un valor escrito y sin guardar no cambia el estado.

El enlace es `https://bsky.app/profile/<did>/post/<rkey>`, la misma app que ya aparece en los textos de ayuda, con el filtro `atproto_comments_post_url` (PHP) para cambiarlo; el resultado se pasa en `atprotoCommentsEditor.postUrlTemplate` con `{did}` y `{rkey}`. Alternativa descartada: no mostrar enlace. El editor necesita ver el post para decidir si lo borra.

Alternativa descartada: mantener el campo editable también en el estado vinculado. Con dos acciones explícitas ya no hace falta, y evita que se cambie el post por error.

### 2. Desvincular se guarda al momento

"Desvincular" llama a la ruta core `POST /wp/v2/<rest_base>/<id>` solo con `{ meta: { _atproto_thread_uri: null } }`, y después marca el meta como guardado con `persist-link.js` (decisión 2b).

Se envía `null` y no `''`: la ruta REST de WordPress borra la fila del meta, igual que `delete_post_meta()` en la ruta de borrado, y no quedan filas vacías. El valor que se marca como guardado en el editor es `''` (el `default` del meta registrado), que es lo que devuelve la API para una entrada sin la fila.

Motivos:
- El estado del panel se basa en el valor guardado (decisión 1); si desvincular fuera solo una edición, el panel no cambiaría hasta "Actualizar".
- El permiso y la validación son los del meta registrado (`edit_post`, `auth_callback`), sin código nuevo en el servidor.
- Enviar solo `meta` no toca los demás cambios sin guardar de la entrada.

El `rest_base` se toma de `getPostType( type ).rest_base` en `core`.

Alternativas descartadas:
- `editPost( { meta: '' } )` y esperar a "Actualizar": el editor ve el formulario, pero la entrada sigue vinculada hasta guardar; es confuso y contradice la spec.
- Un endpoint propio `DELETE …/thread-link`: duplica el permiso y la validación que ya da el meta registrado.
- Enviar `''`: funciona igual en el frontend, pero deja una fila vacía por cada entrada desvinculada.

### 2b. Marcar el vínculo como guardado sin entrar en el historial

El mecanismo de `PublishForm` se extrae a `src/editor/persist-link.js` (`persistLink( registry, postType, postId, value )`) y lo usan publicar, desvincular y borrar. Cambio respecto a hoy: el `editPost` se llama con `{ undoIgnore: true }` (segundo argumento, que `editPost` pasa a `editEntityRecord`).

Motivo: sin eso, tras desvincular o borrar, un Ctrl+Z deja el `at://` antiguo como cambio pendiente, y el siguiente "Actualizar" vuelve a vincular la entrada; en el caso del borrado, a un post que ya no existe, y el frontend muestra el error del componente. Lo mismo al revés tras publicar: deshacer quitaría el vínculo recién creado sin que el editor lo pidiera.

Se comprueba en la tarea 0.1 que `undoIgnore` existe y funciona en WP 6.5 y en la última versión. Si en alguna no funciona, la alternativa es, tras `persistLink`, limpiar la edición del meta con `editPost` del valor guardado y aceptar el deshacer documentado en Riesgos.

Alternativa descartada: dejar que el deshacer funcione y "respetar" la edición pendiente. Es una forma de volver a vincular sin querer que el editor no ve venir.

### 3. DID de la cuenta en el editor sin red

La opción `atproto_comments_account` gana un campo `did`:
- se rellena cada vez que `Identity::resolve_account()` va bien (prueba de conexión, publicación y borrado), con `Account::remember_did( $did )`;
- si el identificador es un DID, es ese mismo valor;
- se vacía cuando cambia el identificador, y "Olvidar cuenta" lo borra.

**Cómo se guarda sin perderse en el `sanitize_callback`.** Como `register_setting` aplica `Account::sanitize()` a cualquier `update_option()` (ver Context), `sanitize()` aprende a tratar el `did` con la misma regla que ya usa para la contraseña cifrada:
- si la entrada trae `did` con forma de DID válida (`Thread_Uri::DID`) y el identificador no cambia respecto al guardado, se conserva el de la entrada;
- si la entrada no trae `did` (el formulario de ajustes no lo envía) y el identificador no cambia, se conserva el guardado;
- si el identificador cambia, `did` es el identificador cuando este es un DID, y `''` si es un handle;
- `forget` devuelve `defaults()`, que incluye `did => ''`.

`remember_did( $did )` hace `update_option( OPTION, array_merge( Account::get(), [ 'did' => $did ] ) )` solo si el valor cambia. Pasa por `sanitize()` como cualquier guardado, y la contraseña cifrada se conserva por la rama que ya existe (`password` con el prefijo `v1:`). No se quita ni se salta ningún filtro.

Alternativas descartadas:
- `remove_filter( 'sanitize_option_atproto_comments_account', … )` alrededor del `update_option()`: depende de cómo registra WordPress el callback y se rompe si otro plugin filtra la opción.
- Una opción aparte `atproto_comments_account_did`: otra opción que borrar al olvidar la cuenta y al desinstalar, y que se puede desincronizar del identificador.

`Post_Meta::editor_data()` añade `accountDid` (el DID es público, no un secreto) y `canDelete` (decisión 4). En el panel, con la entrada vinculada:
- si `canDelete`, `accountReady` y el DID del vínculo es `accountDid`: "Borrar de Bluesky" activo;
- si `canDelete` y `accountReady` pero `accountDid` está vacío (cuenta con handle aún no resuelta, por ejemplo en un sitio que publicó con la versión anterior): "Borrar de Bluesky" deshabilitado, con la pista "Prueba la conexión en los ajustes para poder borrar desde aquí" (y el enlace a ajustes si hay `settingsUrl`);
- en cualquier otro caso: solo "Desvincular".

La comprobación que cuenta es la del servidor (decisión 5), que resuelve la cuenta de nuevo; el panel solo decide qué mostrar.

Alternativas descartadas:
- Resolver el handle al cargar el editor: sería una petición de red en cada apertura del editor de una entrada vinculada.
- Una ruta `GET` de estado que resuelva la cuenta al abrir el panel: misma petición, solo que más tarde, y otra ruta que mantener.
- Resolver al guardar los ajustes: el diseño anterior decidió que guardar no hace peticiones de red.
- Ocultar el botón con `accountDid` vacío: tras actualizar el plugin, el editor no sabría por qué no puede borrar.

### 4. Permiso para borrar

`Anchor_Post::user_can_delete( $post_id )` = `user_can_publish( $post_id )` (que ya incluye `edit_post` y el filtro `atproto_comments_publish_capability`) y además `current_user_can( 'delete_post', $post_id )`. La usan la ruta y `editor_data()`, como en la publicación.

Así un Autor puede desvincular sus entradas, pero no borrar posts de la cuenta del sitio, aunque WordPress le deje borrar sus entradas publicadas. Alternativa descartada: solo `edit_post` y `delete_post` (la idea inicial de la propuesta), que deja a los Autores borrar de la cuenta oficial, al revés que la publicación.

### 5. Ruta de borrado

`DELETE /atproto-comments/v1/posts/(?P<id>\d+)/anchor-post`: la misma ruta, registrada con **dos endpoints** en el mismo `register_rest_route` (un array por método), para que el argumento obligatorio `text` siga siendo solo del `POST`. El `DELETE` no declara argumentos y solo lee `id`; el resto de parámetros se ignora. `apiFetch` envía el nonce (y, con su middleware por defecto, convierte el `DELETE` en `POST` con `X-HTTP-Method-Override`, así que funciona en hosts que bloquean `DELETE`).

El `permission_callback` del `DELETE` reutiliza la lógica de `rest_permission()` (404 si la entrada no existe y el usuario puede editar entradas) con `user_can_delete()` en lugar de `user_can_publish()`.

Orden:
1. Validar sin red: la entrada existe, el tipo está activo, el meta tiene un vínculo canónico, la cuenta está configurada y se puede descifrar. Fallo → 400 (o 404 si no existe la entrada, como en la publicación).
2. Tomar el **mismo lock** que la publicación y volver a leer el vínculo dentro del lock.
3. `resolve_account` y comparar su DID con el del vínculo. Se guarda el DID resuelto en la cuenta (decisión 3). Si no coincide → 403 `atproto_comments_not_owner`, sin sesión.
4. `createSession` → `com.atproto.repo.deleteRecord` con `repo` = DID del vínculo, `collection` = `app.bsky.feed.post` y `rkey` del vínculo, todo con el plazo global de 25 s.
5. Según la respuesta (decisión 6), `delete_post_meta( $id, '_atproto_thread_uri' )` y 200 `{ uri: '' }`, o error con el vínculo intacto.
6. Liberar el lock en un `finally`.

La validación del DID se hace antes de `createSession`, así un vínculo ajeno no gasta una sesión ni manda la app password.

Compartir el lock evita que un borrado y una publicación de la misma entrada se crucen: la publicación comprueba "sin vínculo" dentro del lock y el borrado "con vínculo". El mensaje del 409 de lock pasa a ser común a las dos operaciones: "Ya hay una operación en curso con el post de esta entrada. Espera un momento y recarga el editor."

Alternativa descartada: recibir `uri` o `rkey` desde el panel. Un usuario con permiso podría borrar cualquier post de la cuenta usando una entrada cualquiera.

### 6. Post inexistente, confirmación e idempotencia

Respuestas de `deleteRecord` y qué hace la ruta:

| Respuesta del PDS | Acción |
|---|---|
| 200 | Desvincula. |
| Error JSON con `error` = `RecordNotFound` (400 o 404) | Desvincula: el post no existe. |
| 404 sin JSON o con otro `error` (p. ej. `MethodNotImplemented`, página HTML de un proxy) | Confirma con `getRecord`. |
| Tiempo agotado o fallo de transporte | Confirma con `getRecord`. |
| Credenciales, 429, cuenta no disponible u otro error JSON | Error con su mensaje; el vínculo no cambia. |

**Confirmación con `getRecord`.** `GET <pds>/xrpc/com.atproto.repo.getRecord?repo=<did>&collection=app.bsky.feed.post&rkey=<rkey>` (pública, sin token), dentro del plazo global y solo si quedan al menos 3 s:
- error JSON `RecordNotFound` → el post no existe: desvincula;
- 200 con el registro → el post sigue ahí: error `atproto_comments_delete_failed` (502) con el vínculo intacto;
- cualquier otra cosa o sin tiempo → `atproto_comments_delete_unconfirmed` (502): "No se ha podido confirmar el borrado. Vuelve a intentarlo."

`Xrpc` gana `atproto_comments_record_not_found` solo para el error JSON `RecordNotFound`, y un método `query()` para peticiones `GET` sin token. Un 404 genérico **no** cuenta como "no encontrado": podría venir de una URL de PDS mal formada o de un proxy, y desvincular entonces dejaría el post vivo sin que el editor lo sepa.

Gracias a eso reintentar es seguro: si un intento anterior borró el post y agotó el tiempo, el reintento recibe 200 o `RecordNotFound` (o lo confirma con `getRecord`) y desvincula. Por eso, a diferencia de la publicación, el borrado no necesita el mensaje de "resultado incierto". El panel usa el mensaje de `atproto_comments_delete_unconfirmed` si su llamada falla sin respuesta JSON del plugin.

Se confirma contra un PDS real (Bluesky y otro, p. ej. Eurosky) en la verificación de integración qué devuelve cada uno para `deleteRecord` y `getRecord` de un registro inexistente.

Comprobado el 2026-10-16:

| PDS | `deleteRecord` de un registro inexistente | `getRecord` de un registro inexistente |
|---|---|---|
| Bluesky (`*.host.bsky.network`) | 200 `{}` → desvincula | 400 `{"error":"RecordNotFound"}` → desvincula |
| Eurosky (`eurosky.social`) | 200 `{}` → desvincula | 400 `{"error":"RecordNotFound"}` → desvincula |

Alternativa descartada: tratar cualquier 404 como éxito. Es más simple, pero puede desvincular sin borrar.

### 7. Número de respuestas desde el navegador

Al abrir el diálogo, el panel pide `GET <appview>/xrpc/app.bsky.feed.getPosts?uris=<at-uri>` con `fetch` y un `AbortController` de 5 s, y lee `posts[0].replyCount`. El AppView es el de los ajustes, que se pasa en `atprotoCommentsEditor.appview`. Mientras carga, el diálogo muestra un spinner y el botón de confirmar ya está activo; si falla, se dice que no se ha podido saber el número.

`replyCount` cuenta las respuestas directas e indirectas que el AppView ha indexado; puede quedarse corto si alguna no se ha indexado aún, y el texto dice "unas N respuestas" cuando N > 0.

**Qué pasa con las respuestas.** Borrar el post no borra las respuestas: son registros de otros repos y siguen en los perfiles de sus autores. Lo que ocurre es que dejan de verse en el hilo del post y en la entrada. El diálogo lo dice así ("unas N respuestas dejarán de verse en el hilo; seguirán en los perfiles de quienes las escribieron"), no "se perderán".

Alternativas descartadas:
- Pedirlo desde el servidor: sería la primera llamada del servidor al AppView, otro servicio externo en el lado del servidor y más tiempo dentro del plazo de la ruta.
- Reutilizar el componente de comentarios para contarlas: carga todo el hilo para obtener un número.

El diálogo es un `Modal` de `@wordpress/components` con dos botones ("Cancelar" y uno `isDestructive` para borrar). `__experimentalConfirmDialog` existe en WP 6.5 y en la última versión (tarea 0.2), pero no permite un botón de confirmar destructivo; `Modal` es lo que usa por dentro y da el mismo foco y la misma salida con Escape, comprobados en las dos versiones.

### 8. Borrar la entrada de WordPress

No se engancha nada a `wp_trash_post`, `before_delete_post` ni `delete_post`. El meta se borra con la entrada como cualquier otro meta, y el post remoto sigue ahí. La spec lo fija con tests que comprueban que no hay ninguna petición HTTP al borrar una entrada vinculada.

### 9. Readme

- "External services" → PDS: añade "to delete, the post's repository and record key, and to confirm the deletion, a public read of that record", y en **When** "or clicks 'Delete from Bluesky'".
- AppView: nueva línea; el navegador del editor pide el número de respuestas del post vinculado al abrir el diálogo de borrado.
- Identity resolution → **When**: añade el borrado.
- FAQ: qué hace "Desvincular" frente a "Borrar de Bluesky", qué pasa con las respuestas al borrar, que borrar la entrada no borra el post, que el borrado usa el mismo permiso que la publicación, y por qué el botón puede aparecer deshabilitado (cuenta con handle aún no probada).

### 10. Verificación por versión

| Qué | WP 6.5 + PHP 7.4 | Última versión de WP + PHP 8.3 |
|---|---|---|
| PHPUnit de la ruta de borrado: permisos (Autor, Editor, filtro, sin `delete_post`), `text` no exigido en `DELETE`, parámetros extra ignorados, DID distinto sin sesión, éxito, `RecordNotFound`, 404 genérico y timeout confirmados con `getRecord` (existe / no existe / sin respuesta), errores, lock compartido con la publicación y su mensaje común | sí | sí |
| PHPUnit: ninguna petición HTTP al enviar a la papelera o borrar una entrada vinculada | sí | sí |
| PHPUnit: `did` de la cuenta se rellena al resolver, se conserva al guardar el formulario sin cambiar el identificador, se vacía al cambiar el identificador u olvidar la cuenta | sí | sí |
| Desvincular por `POST /wp/v2/…` con meta `null`: la fila se borra, la entrada no queda modificada en el editor y un "Actualizar" posterior no vuelve a poner el vínculo | manual | manual |
| `editPost` con `undoIgnore`: tras publicar, desvincular o borrar, Ctrl+Z no cambia el meta | manual | manual |
| Diálogo de confirmación (`Modal`) accesible: foco dentro, Escape cancela y devuelve el foco | manual | manual |
| Estado vinculado y no vinculado del panel, enlace al post, botones según `canDelete` y `accountDid` (activo, deshabilitado con pista, ausente) | manual | manual |
| Tests JS (plantilla del enlace, estado del botón de borrar) | `wp-scripts test-unit-js` | — |
| Borrado real contra un PDS (Bluesky y otro), incluido el borrado de un post ya borrado | manual, una vez | manual, una vez |
| Plugin Check y PHPCS (`PHPCompatibilityWP` 7.4-) | sí | sí |

## Risks / Trade-offs

- [Borrado irreversible por error] → Diálogo con el número de respuestas, botón destructivo y solo en el editor de bloques; quien no puede publicar no ve el botón.
- [`accountDid` vacío con una cuenta con handle aún no resuelta, también tras actualizar desde la versión anterior] → El botón aparece deshabilitado con una pista para probar la conexión. Se documenta en la FAQ.
- [`accountDid` desactualizado si el handle de la cuenta pasa a otro DID] → El servidor resuelve de nuevo y responde 403 con un mensaje claro; el `did` guardado se actualiza en esa misma petición.
- [PDS que responde a `deleteRecord` y a `getRecord` con errores no reconocidos] → `delete_unconfirmed` y el vínculo se mantiene; el editor puede reintentar o desvincular a mano. Se comprueba con dos PDS reales.
- [`undoIgnore` no disponible o con otro comportamiento en alguna versión] → Se comprueba en la tarea 0.1; la alternativa está en la decisión 2b.
- [`replyCount` del AppView desactualizado o AppView caído] → El número es orientativo y el diálogo funciona sin él.
- [Desvincular desde la ruta core actualiza `post_modified` y dispara los hooks de guardado] → Es lo mismo que guardar el meta desde el editor; no crea revisión porque no cambia el contenido.

## Migration Plan

No hay migración: el campo `did` de la cuenta se rellena solo en la siguiente resolución (hasta entonces el botón de borrar aparece deshabilitado con una pista), y los vínculos existentes no cambian. Los vínculos vaciados antes con `''` siguen funcionando igual que una fila borrada. Para volver atrás basta con la versión anterior del plugin: su `sanitize()` descarta el campo `did` en el siguiente guardado, sin efectos.
