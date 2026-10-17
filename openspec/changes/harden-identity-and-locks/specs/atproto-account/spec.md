# Spec Delta

## ADDED Requirements

### Requirement: Identidad fijada de la cuenta
El sitio SHALL guardar el DID de su cuenta de AT Protocol como identidad fijada. Si el identificador es un DID, la identidad fijada SHALL ser ese DID desde que se guarda. Si es un handle, SHALL fijarse con la primera resolución correcta. Cambiar el identificador en los ajustes SHALL borrar la identidad fijada.

#### Scenario: Identificador con DID
- **WHEN** el administrador guarda la cuenta con el identificador `did:plc:abc234567abcdefghijklmno`
- **THEN** la identidad fijada es ese DID, sin ninguna petición de red

#### Scenario: Primera resolución de un handle
- **WHEN** la cuenta tiene un handle, aún no tiene identidad fijada y el administrador prueba la conexión
- **THEN** la identidad fijada pasa a ser el DID al que resuelve el handle

#### Scenario: Cambio de identificador
- **WHEN** el administrador cambia el identificador de la cuenta y guarda
- **THEN** la identidad fijada se borra y se fija de nuevo en la siguiente resolución

### Requirement: Contraseña solo para la identidad fijada
Si la cuenta tiene identidad fijada y su identificador resuelve a otro DID, el plugin no SHALL descifrar la app password ni enviarla a ningún servidor en esa petición.

#### Scenario: Resolución falseada
- **WHEN** la cuenta tiene fijado `did:plc:abc234567abcdefghijklmno` y su handle resuelve a otro DID cuyo documento declara ese handle
- **THEN** no se abre sesión en ningún servidor y la petición termina con un error que indica que la identidad de la cuenta ha cambiado

### Requirement: Confirmación de un cambio de identidad
Si la prueba de conexión resuelve un DID distinto del fijado, los ajustes SHALL mostrar el DID fijado, el DID nuevo, su handle y su servidor, y SHALL ofrecer usar la nueva identidad. Solo esa confirmación explícita del administrador SHALL cambiar la identidad fijada, y solo si el DID confirmado coincide con el que se resuelve al confirmar.

#### Scenario: Aviso en la prueba de conexión
- **WHEN** el administrador prueba la conexión y la cuenta resuelve a un DID distinto del fijado
- **THEN** ve el DID anterior, el nuevo, el handle y el servidor, con una explicación y un botón para usar la nueva identidad, y no se ha enviado la contraseña

#### Scenario: Confirmar la nueva identidad
- **WHEN** el administrador pulsa el botón para usar la nueva identidad y la cuenta sigue resolviendo a ese DID
- **THEN** la identidad fijada pasa a ser el DID nuevo y la prueba de conexión continúa con normalidad

#### Scenario: La resolución cambia antes de confirmar
- **WHEN** el administrador confirma un DID, pero al confirmar la cuenta resuelve a otro distinto
- **THEN** la identidad fijada no cambia, no se envía la contraseña y se muestra de nuevo el aviso con los datos actuales

### Requirement: Recomendación de cuenta
La sección de cuenta de los ajustes SHALL recomendar usar el DID de la cuenta como identificador.

#### Scenario: Abrir la sección de cuenta
- **WHEN** el administrador abre los ajustes
- **THEN** junto al identificador ve la recomendación de usar el DID (`did:plc:…`)
