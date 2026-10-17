# Spec Delta

## ADDED Requirements

### Requirement: Identidad de la cuenta al publicar y borrar
Antes de abrir sesión para publicar o borrar, el endpoint SHALL comprobar que la cuenta resuelve a su identidad fijada. Si resuelve a otro DID, SHALL responder con un error sin descifrar la app password ni contactar con ningún servidor de la cuenta, y el vínculo no SHALL cambiar. Si la cuenta aún no tiene identidad fijada, la primera resolución correcta SHALL fijarla.

#### Scenario: Identidad cambiada al publicar
- **WHEN** un Editor publica el post ancla y la cuenta resuelve a un DID distinto del fijado
- **THEN** no se crea ningún post, no se envía la contraseña, la entrada sigue sin vínculo y el panel indica que la identidad de la cuenta ha cambiado y que hay que revisarla en los ajustes

#### Scenario: Identidad cambiada al borrar
- **WHEN** un Editor borra el post ancla y la cuenta resuelve a un DID distinto del fijado
- **THEN** no se borra nada, no se envía la contraseña y la entrada sigue vinculada

#### Scenario: Primera publicación sin identidad fijada
- **WHEN** la cuenta tiene un handle sin identidad fijada y un Editor publica el post ancla
- **THEN** el DID resuelto queda fijado y la publicación sigue con normalidad

### Requirement: Cerrojo con dueño
Si una operación anterior de publicar o borrar dejó el cerrojo de la entrada sin liberar y ha caducado, como mucho una de varias peticiones simultáneas SHALL tomarlo. Una petición no SHALL liberar el cerrojo que tiene otra.

#### Scenario: Dos peticiones con un cerrojo caducado
- **WHEN** el cerrojo de una entrada tiene más de 120 segundos y llegan dos peticiones de publicación casi a la vez
- **THEN** solo una crea el post y la otra recibe un error 409

#### Scenario: Liberar un cerrojo ajeno
- **WHEN** una petición termina y el cerrojo de la entrada lo tiene otra petición
- **THEN** el cerrojo de la otra petición sigue en su sitio

### Requirement: Acciones de actividad
Tras publicar o borrar correctamente el post ancla, el plugin SHALL disparar una acción de WordPress con el ID de la entrada, el URI `at://` del post y el ID del usuario que hizo la operación, para que otros plugins puedan registrarlo. No SHALL dispararla si la operación falla.

#### Scenario: Publicación registrada
- **WHEN** un Editor publica el post ancla y el PDS lo crea
- **THEN** se dispara `atproto_comments_anchor_published` con la entrada, el `at://` y el ID del Editor

#### Scenario: Borrado registrado
- **WHEN** un Editor borra el post ancla, o el post ya no existía
- **THEN** se dispara `atproto_comments_anchor_deleted` con la entrada, el `at://` borrado y el ID del Editor

#### Scenario: Operación fallida
- **WHEN** la publicación falla por credenciales rechazadas
- **THEN** no se dispara ninguna de las dos acciones
