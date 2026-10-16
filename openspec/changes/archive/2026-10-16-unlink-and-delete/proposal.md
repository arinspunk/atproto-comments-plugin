# Proposal

## Why

Una vez vinculada o publicada una entrada, no hay forma de corregir un error: un texto mal escrito, el post equivocado o un vínculo pegado por error. Este cambio cierra el ciclo de vida del vínculo: desvincular sin tocar nada fuera de WordPress, y borrar el post ancla de AT Protocol cuando pertenece a la cuenta del sitio.

Depende de `publish-from-editor`, porque necesita la cuenta configurada y el cliente XRPC.

## What Changes

- En el panel del editor de bloques, con la entrada vinculada: estado "vinculado" con el `at://`, un enlace al post y dos acciones.
  - **Desvincular:** borra `_atproto_thread_uri` al momento y vuelve a mostrar el formulario (pegar o publicar). El post remoto y sus respuestas siguen existiendo.
  - **Borrar de Bluesky:** borra el post con `com.atproto.repo.deleteRecord` y, si sale bien, desvincula.
- El borrado se confirma con un diálogo en el panel que indica cuántas respuestas dejarán de verse en el hilo (`replyCount`, consultado al AppView desde el navegador del editor). Las respuestas no se borran: siguen en los perfiles de quienes las escribieron.
- Salvaguardas del borrado:
  - el endpoint REST recibe solo `post_id` y obtiene el repo y el rkey del meta guardado, no del navegador;
  - solo se ofrece y se permite si el DID del vínculo coincide con el de la cuenta configurada (si no, solo se puede desvincular);
  - un `RecordNotFound` del PDS cuenta como éxito y desvincula; un 404 genérico o un tiempo agotado se confirman consultando el registro (`com.atproto.repo.getRecord`) antes de desvincular;
  - con cualquier otro error, el vínculo se mantiene y se muestra el aviso;
  - ni desvincular ni borrar entran en el historial de deshacer del editor, para que un Ctrl+Z seguido de "Actualizar" no vuelva a poner el vínculo antiguo.
- Permisos: `edit_post` para desvincular; para borrar en remoto, el mismo permiso que para publicar (`edit_others_posts` por defecto, filtro `atproto_comments_publish_capability`) y además `delete_post`.
- La cuenta guarda su DID tras cada resolución correcta, para que el editor sepa sin peticiones de red si el post es de la cuenta. Mientras no se conozca, el botón de borrar aparece deshabilitado con una pista para probar la conexión.
- Borrar o enviar a la papelera la entrada de WordPress **no** toca el post remoto.
- En el editor clásico se puede desvincular vaciando el campo, como ya ocurre. El borrado remoto es exclusivo del editor de bloques en la v1.

## Capabilities

### New Capabilities

### Modified Capabilities
- `thread-linking`: estado vinculado en el panel, acción explícita de desvincular y su efecto.
- `anchor-post-publishing`: borrado del post ancla (permiso, confirmación, comprobación del DID, tratamiento de errores e independencia respecto del borrado de la entrada de WordPress).

## Impact

- Ruta REST de borrado (`DELETE` en la misma ruta que la publicación) y ampliación del panel del editor (estado vinculado, acciones, diálogo de confirmación con `__experimentalConfirmDialog` o un `Modal`).
- Campo `did` en la opción `atproto_comments_account`.
- El navegador del editor consulta `replyCount` al AppView configurado al abrir el diálogo de borrado; el servidor sigue sin hablar con el AppView.
- `readme.txt`: "External services" incluye la llamada de borrado, la consulta de confirmación al PDS y la consulta al AppView desde el editor.
