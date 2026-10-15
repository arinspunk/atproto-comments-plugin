# Spec Delta

## Purpose

Define cómo una entrada de WordPress queda vinculada a su post ancla de AT Protocol: dónde se guarda el vínculo, quién lo edita, qué formatos se aceptan y cómo se normalizan.

## ADDED Requirements

### Requirement: Almacenamiento del vínculo
El vínculo SHALL guardarse en el meta de entrada `_atproto_thread_uri`, siempre en forma canónica `at://<did>/app.bsky.feed.post/<rkey>`. Un valor vacío significa que la entrada no está vinculada.

#### Scenario: Guardar un vínculo
- **WHEN** el editor guarda `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u`
- **THEN** el meta contiene exactamente ese valor

#### Scenario: Vaciar el campo
- **WHEN** el editor borra el contenido del campo y guarda
- **THEN** la entrada deja de estar vinculada y su vista individual no muestra el componente

### Requirement: Formatos aceptados
El campo SHALL aceptar un URI `at://` con DID `plc` o `web` y colección `app.bsky.feed.post`, y una URL `https` de cualquier app con la forma `/profile/<did>/post/<rkey>`, que se convierte a `at://`. Se ignoran los espacios al principio y al final.

#### Scenario: URL de app con DID
- **WHEN** el editor pega `https://bsky.app/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u`
- **THEN** se guarda `at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u`

#### Scenario: Otra app compatible
- **WHEN** el editor pega `https://mu.social/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u`
- **THEN** se guarda el mismo `at://` que con bsky.app

### Requirement: Rechazo de valores no válidos
Un valor que no encaje con los formatos aceptados SHALL rechazarse sin modificar el vínculo guardado, con un mensaje que explique el motivo. Un handle en lugar de un DID SHALL tener un mensaje propio que indique usar el DID.

#### Scenario: URL con handle
- **WHEN** el editor pega `https://bsky.app/profile/usoritual.com/post/3mwso7oovnp2u`
- **THEN** el vínculo no cambia y se muestra un mensaje indicando que se necesita la URL o el `at://` con el DID (`did:…`)

#### Scenario: URI at:// con handle
- **WHEN** el editor pega `at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u`
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
