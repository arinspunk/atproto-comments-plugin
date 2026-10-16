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
El vínculo SHALL poder rellenarse a mano o publicando el post ancla desde el editor. En ambos casos se guarda igual y el editor puede cambiarlo o vaciarlo después. Vaciar o cambiar el vínculo no SHALL borrar ni modificar ningún post en AT Protocol.

#### Scenario: Vínculo creado al publicar
- **WHEN** el editor publica el post ancla desde el panel
- **THEN** el campo del vínculo muestra el `at://` del post creado

#### Scenario: Vaciar un vínculo publicado
- **WHEN** el editor vacía el vínculo de una entrada cuyo post ancla publicó el plugin
- **THEN** la entrada queda sin vincular y el post sigue existiendo en AT Protocol
