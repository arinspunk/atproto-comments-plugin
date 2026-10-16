# thread-linking Specification

## Purpose

Define cómo una entrada de WordPress queda vinculada a su post ancla de AT Protocol: dónde se guarda el vínculo, quién lo edita, qué formatos se aceptan y cómo se normalizan.

## Requirements

### Requirement: Almacenamiento del vínculo
El vínculo SHALL guardarse en el meta de entrada `_atproto_thread_uri`, siempre en forma canónica `at://<did>/app.bsky.feed.post/<rkey>`. Un valor vacío significa que la entrada no está vinculada.

#### Scenario: Guardar un vínculo
- **WHEN** el editor guarda `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u`
- **THEN** el meta contiene exactamente ese valor

#### Scenario: Vaciar el campo
- **WHEN** el editor borra el contenido del campo y guarda
- **THEN** la entrada deja de estar vinculada y su vista individual no muestra el componente

### Requirement: Formatos aceptados
El campo SHALL aceptar un URI `at://` con DID `plc` o `web` o con handle, y colección `app.bsky.feed.post`, y una URL `https` de cualquier app con la forma `/profile/<did o handle>/post/<rkey>`, que se convierte a `at://`. Los handles SHALL resolverse a DID en el servidor antes de guardar. Se ignoran los espacios al principio y al final.

#### Scenario: URL de app con DID
- **WHEN** el editor pega `https://bsky.app/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u`
- **THEN** se guarda `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u`

#### Scenario: Otra app compatible
- **WHEN** el editor pega `https://mu.social/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u`
- **THEN** se guarda el mismo `at://` que con bsky.app

#### Scenario: URL de app con handle
- **WHEN** el editor pega `https://bsky.app/profile/usoritual.com/post/3mwso7oovnp2u` y `usoritual.com` resuelve a `did:plc:abc234567abcdefghijklmno`
- **THEN** se guarda `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u`

#### Scenario: URI at:// con handle resoluble
- **WHEN** el editor pega `at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u` y el handle resuelve a un DID
- **THEN** se guarda el `at://` con el DID, igual que con la URL con handle

### Requirement: Rechazo de valores no válidos
Un valor que no encaje con los formatos aceptados SHALL rechazarse sin modificar el vínculo guardado, con un mensaje que explique el motivo. Un handle que no pueda resolverse a DID SHALL tener un mensaje propio que lo indique y sugiera usar la URL o el `at://` con el DID.

#### Scenario: URL con handle
- **WHEN** el editor pega `https://bsky.app/profile/no-existe.example/post/3mwso7oovnp2u` y el handle no se puede resolver
- **THEN** el vínculo no cambia y se muestra un mensaje indicando que el handle no se pudo resolver y que puede usarse la URL o el `at://` con el DID (`did:…`)

#### Scenario: URI at:// con handle
- **WHEN** el editor pega `at://no-existe.example/app.bsky.feed.post/3mwso7oovnp2u` y el handle no se puede resolver
- **THEN** el vínculo no cambia y se muestra el mismo mensaje que con una URL con handle

#### Scenario: Colección distinta
- **WHEN** el editor pega `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.like/3mwso7oovnp2u`
- **THEN** el vínculo no cambia y se muestra un error de formato

### Requirement: Edición en el editor de bloques
En los tipos de contenido activos, el editor de bloques SHALL mostrar un panel lateral con un campo para el vínculo, que valida el formato antes de guardar y muestra el error en el propio panel.

#### Scenario: Error visible en el panel
- **WHEN** el editor escribe un valor no válido en el panel
- **THEN** el panel muestra el error y el valor no se envía al guardar la entrada

### Requirement: Edición en el editor clásico
En los tipos de contenido activos, el editor clásico SHALL mostrar un metabox con el mismo campo, protegido con nonce. Un valor no válido SHALL mostrar un aviso de administración tras guardar. El metabox no SHALL mostrarse en el editor de bloques, y los guardados sin nonce (REST), los autoguardados y las revisiones no SHALL modificar el vínculo a través de él.

#### Scenario: Valor no válido en el editor clásico
- **WHEN** el editor guarda la entrada con un valor no válido en el metabox
- **THEN** la entrada se guarda, el vínculo anterior se mantiene y aparece un aviso de error

#### Scenario: Editor de bloques
- **WHEN** el editor abre una entrada de un tipo activo en el editor de bloques
- **THEN** ve el panel lateral del vínculo y no ve el metabox del editor clásico

#### Scenario: Autoguardado
- **WHEN** WordPress autoguarda una entrada vinculada
- **THEN** el vínculo no cambia y no aparece ningún aviso

### Requirement: Permisos de edición
Solo los usuarios que pueden editar la entrada (`edit_post`) SHALL poder modificar el vínculo desde el editor y desde la API REST. El vínculo no es un dato privado: puede aparecer en las respuestas REST públicas de la entrada, igual que en su HTML.

#### Scenario: Petición REST sin permiso
- **WHEN** un usuario sin `edit_post` sobre la entrada envía `meta._atproto_thread_uri` por la API REST
- **THEN** la petición se rechaza y el vínculo no cambia

#### Scenario: Valor no válido por REST
- **WHEN** un usuario con permiso envía un valor no válido por la API REST
- **THEN** la API responde con un error 400 y el vínculo no cambia

### Requirement: Origen del vínculo
El vínculo SHALL poder rellenarse a mano o publicando el post ancla desde el editor. En ambos casos se guarda igual y el editor puede cambiarlo o vaciarlo después. Vaciar, cambiar o desvincular el vínculo no SHALL borrar ni modificar ningún post en AT Protocol; solo la acción explícita de borrar el post ancla lo hace.

#### Scenario: Vínculo creado al publicar
- **WHEN** el editor publica el post ancla desde el panel
- **THEN** el panel pasa al estado vinculado con el `at://` del post creado

#### Scenario: Vaciar un vínculo publicado
- **WHEN** el editor vacía el vínculo de una entrada cuyo post ancla publicó el plugin
- **THEN** la entrada queda sin vincular y el post sigue existiendo en AT Protocol

#### Scenario: Desvincular un post publicado
- **WHEN** el editor pulsa "Desvincular" en una entrada cuyo post ancla publicó el plugin
- **THEN** la entrada queda sin vincular y el post y sus respuestas siguen existiendo en AT Protocol

### Requirement: Estado vinculado en el editor de bloques
Cuando la entrada tiene un vínculo guardado, el panel lateral del editor de bloques SHALL mostrar el estado "vinculado" en lugar del formulario para pegar o publicar: el `at://` guardado, un enlace para abrir el post y las acciones disponibles. El estado SHALL reflejar el vínculo guardado, no un valor escrito y aún sin guardar.

#### Scenario: Abrir una entrada vinculada
- **WHEN** el editor abre en el editor de bloques una entrada con `_atproto_thread_uri`
- **THEN** el panel muestra el vínculo, un enlace al post y la acción de desvincular, y no muestra el campo para pegar ni el formulario de publicación

#### Scenario: Abrir una entrada sin vínculo
- **WHEN** el editor abre una entrada sin `_atproto_thread_uri`
- **THEN** el panel muestra el campo para pegar el vínculo y, si procede, el formulario de publicación

### Requirement: Desvincular desde el editor de bloques
El panel SHALL ofrecer una acción "Desvincular" que borra `_atproto_thread_uri` de la entrada y vuelve a mostrar el campo para pegar y el formulario de publicación, sin recargar la página. Desvincular no SHALL contactar con ningún PDS ni modificar ningún post en AT Protocol.

#### Scenario: Desvincular un post pegado a mano
- **WHEN** el editor pulsa "Desvincular" en una entrada vinculada a un post de otra cuenta
- **THEN** la entrada queda sin vínculo, el panel vuelve a mostrar el campo para pegar y no se hace ninguna llamada a AT Protocol

#### Scenario: Vista de la entrada tras desvincular
- **WHEN** el editor desvincula una entrada publicada
- **THEN** la vista individual de la entrada deja de mostrar el componente de comentarios

#### Scenario: Volver a vincular
- **WHEN** el editor desvincula una entrada y después pega otro enlace válido
- **THEN** la entrada queda vinculada al nuevo post

#### Scenario: Deshacer tras desvincular
- **WHEN** el editor desvincula una entrada, pulsa Ctrl+Z y después "Actualizar"
- **THEN** la entrada sigue sin vínculo; para volver a vincularla hay que pegar el enlace o publicar

#### Scenario: Otros cambios sin guardar
- **WHEN** el editor tiene cambios sin guardar en el título y desvincula la entrada
- **THEN** la entrada queda sin vínculo y el cambio del título sigue pendiente de guardar

### Requirement: Permiso para desvincular
Solo los usuarios que pueden editar la entrada (`edit_post`) SHALL poder desvincularla, desde el panel y desde la API REST. No SHALL hacer falta la capacidad de publicación ni la cuenta del sitio configurada.

#### Scenario: Autor en su propia entrada
- **WHEN** un usuario con rol Autor abre una entrada suya vinculada
- **THEN** puede desvincularla aunque no tenga la capacidad de publicación

#### Scenario: Sin cuenta configurada
- **WHEN** el sitio no tiene cuenta de AT Protocol configurada y el editor desvincula una entrada
- **THEN** la entrada queda sin vínculo
