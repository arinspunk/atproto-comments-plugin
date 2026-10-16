# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Origen del vínculo
El vínculo SHALL poder rellenarse a mano o publicando el post ancla desde el editor. En ambos casos se guarda igual y el editor puede cambiarlo o vaciarlo después. Vaciar o cambiar el vínculo no SHALL borrar ni modificar ningún post en AT Protocol.

#### Scenario: Vínculo creado al publicar
- **WHEN** el editor publica el post ancla desde el panel
- **THEN** el campo del vínculo muestra el `at://` del post creado

#### Scenario: Vaciar un vínculo publicado
- **WHEN** el editor vacía el vínculo de una entrada cuyo post ancla publicó el plugin
- **THEN** la entrada queda sin vincular y el post sigue existiendo en AT Protocol
