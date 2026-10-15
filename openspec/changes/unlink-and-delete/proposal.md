# Proposal

## Why

Una vez vinculada o publicada una entrada, no hay forma de corregir un error: un texto mal escrito, el post equivocado o un vínculo pegado por error. Este cambio cierra el ciclo de vida del vínculo: desvincular sin tocar nada fuera de WordPress, y borrar el post ancla de AT Protocol cuando pertenece a la cuenta del sitio.

Depende de `publish-from-editor`, porque necesita la cuenta configurada y el cliente XRPC.

## What Changes

- En el panel del editor de bloques, con la entrada vinculada: estado "Publicado / vinculado" con enlace al post y dos acciones.
  - **Desvincular:** borra `_atproto_thread_uri` y vuelve a mostrar el formulario (pegar o publicar). El post remoto y sus respuestas siguen existiendo.
  - **Borrar de Bluesky:** borra el post con `com.atproto.repo.deleteRecord` y, si sale bien, desvincula.
- El borrado se confirma con un `ConfirmDialog` en el panel que indica cuántas respuestas se perderán (`replyCount`).
- Salvaguardas del borrado:
  - el endpoint REST recibe solo `post_id` y obtiene el repo y el rkey del meta guardado, no del navegador;
  - solo se ofrece y se permite si el DID del vínculo coincide con el de la cuenta configurada (si no, solo se puede desvincular);
  - un 404 o un "record not found" cuenta como éxito y desvincula;
  - con cualquier otro error, el vínculo se mantiene y se muestra el aviso.
- Permisos: `edit_post` para desvincular y `delete_post` para borrar en remoto.
- Borrar o enviar a la papelera la entrada de WordPress **no** toca el post remoto.
- En el editor clásico se puede desvincular vaciando el campo, como ya ocurre. El borrado remoto es exclusivo del editor de bloques en la v1.

## Capabilities

### New Capabilities

### Modified Capabilities
- `thread-linking`: acción explícita de desvincular y su efecto.
- `anchor-post-publishing`: borrado del post ancla (precondiciones, confirmación, comprobación del DID, tratamiento de errores e independencia respecto del borrado de la entrada de WordPress).

## Impact

- Nuevo endpoint REST de borrado y ampliación del panel del editor (estado vinculado, acciones, `ConfirmDialog`).
- Lectura de `replyCount` del AppView desde el servidor o el navegador (por decidir en el design).
- `readme.txt`: "External services" incluye la llamada de borrado.
