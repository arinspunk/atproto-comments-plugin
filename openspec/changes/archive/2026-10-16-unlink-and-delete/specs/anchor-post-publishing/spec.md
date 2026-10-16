# Spec Delta

## ADDED Requirements

### Requirement: Acción de borrar el post ancla
Con la entrada vinculada, el panel del editor de bloques SHALL ofrecer "Borrar de Bluesky" solo si la cuenta del sitio está configurada, el DID del vínculo coincide con el de esa cuenta y el usuario tiene el permiso para publicar y además puede borrar la entrada (`delete_post`). Si no se cumple, el panel SHALL ofrecer solo desvincular, salvo en el caso de la cuenta aún sin resolver.

#### Scenario: Post de la cuenta del sitio
- **WHEN** un Editor abre una entrada vinculada a un post cuyo DID es el de la cuenta configurada
- **THEN** el panel ofrece "Desvincular" y "Borrar de Bluesky"

#### Scenario: Post de otra cuenta
- **WHEN** la entrada está vinculada a un post cuyo DID no es el de la cuenta configurada
- **THEN** el panel ofrece solo "Desvincular"

#### Scenario: Cuenta sin configurar
- **WHEN** la entrada está vinculada y el sitio no tiene cuenta de AT Protocol configurada
- **THEN** el panel ofrece solo "Desvincular"

#### Scenario: Autor en su propia entrada
- **WHEN** un usuario con rol Autor abre una entrada suya vinculada a un post de la cuenta del sitio, sin que el filtro haya cambiado la capacidad de publicación
- **THEN** el panel ofrece solo "Desvincular"

#### Scenario: Usuario sin permiso para borrar
- **WHEN** un usuario con el permiso para publicar pero sin `delete_post` sobre la entrada abre una entrada vinculada a un post de la cuenta del sitio
- **THEN** el panel ofrece solo "Desvincular"

### Requirement: Cuenta aún sin resolver
Si el usuario tiene el permiso para borrar y la cuenta está configurada, pero el sitio aún no conoce el DID de la cuenta, el panel SHALL mostrar "Borrar de Bluesky" deshabilitado, con una indicación para probar la conexión en los ajustes.

#### Scenario: DID de la cuenta aún desconocido
- **WHEN** un Editor abre una entrada vinculada y la cuenta del sitio, configurada con un handle, no se ha resuelto todavía (ni prueba de conexión ni publicación desde que se guardó)
- **THEN** el panel muestra "Borrar de Bluesky" deshabilitado con una indicación para probar la conexión en los ajustes, y "Desvincular" disponible

### Requirement: Confirmación del borrado
Antes de borrar, el panel SHALL pedir confirmación en un diálogo que avisa de que el borrado no se puede deshacer, indica cuántas respuestas tiene el post y aclara que esas respuestas dejarán de verse en el hilo pero seguirán en los perfiles de quienes las escribieron. Si no se puede obtener el número, el diálogo SHALL decirlo y permitir confirmar igualmente. Cancelar no SHALL hacer ninguna llamada de borrado.

#### Scenario: Post con respuestas
- **WHEN** el editor pulsa "Borrar de Bluesky" en un post con 12 respuestas
- **THEN** el diálogo indica que unas 12 respuestas dejarán de verse en el hilo, que seguirán en los perfiles de sus autores, y pide confirmar

#### Scenario: Número de respuestas desconocido
- **WHEN** el editor pulsa "Borrar de Bluesky" y no se puede consultar el número de respuestas
- **THEN** el diálogo indica que no se ha podido saber cuántas respuestas tiene y permite confirmar o cancelar

#### Scenario: Cancelar
- **WHEN** el editor cancela el diálogo
- **THEN** no se borra nada y la entrada sigue vinculada

### Requirement: Endpoint de borrado
El borrado SHALL hacerse por un endpoint REST del plugin que recibe solo el ID de la entrada. El repo y el rkey del post SHALL obtenerse del vínculo guardado en el servidor, nunca de la petición. El endpoint SHALL exigir el permiso para publicar, `delete_post` sobre la entrada y un nonce REST válido.

#### Scenario: Usuario sin permiso
- **WHEN** un usuario sin `delete_post` sobre la entrada llama al endpoint de borrado
- **THEN** recibe un error 403, no se contacta con el PDS y el vínculo no cambia

#### Scenario: Autor sin la capacidad de publicación
- **WHEN** un Autor llama al endpoint de borrado para su propia entrada sin que el filtro haya cambiado la capacidad
- **THEN** recibe un error 403 y no se contacta con el PDS

#### Scenario: Petición sin nonce
- **WHEN** se llama al endpoint con cookie de sesión pero sin nonce REST
- **THEN** la petición se rechaza y no se borra nada

#### Scenario: Parámetros extra
- **WHEN** la petición incluye, además del ID de la entrada, un URI o un rkey de otro post
- **THEN** se ignoran y solo se considera el post del vínculo guardado

### Requirement: Validación del borrado en el servidor
El endpoint SHALL comprobar, sin llamar al PDS si alguna falla, que la entrada existe, es de un tipo activo, tiene vínculo, la cuenta está configurada y el DID del vínculo coincide con el de la cuenta. Si falla SHALL responder con un error y un mensaje traducido, y el vínculo no SHALL cambiar.

#### Scenario: Entrada sin vínculo
- **WHEN** se llama al endpoint para una entrada sin `_atproto_thread_uri`
- **THEN** responde con un error y no contacta con el PDS

#### Scenario: DID distinto
- **WHEN** se llama al endpoint para una entrada vinculada a un post de otra cuenta
- **THEN** responde con un error 403, no contacta con el PDS para borrar y el vínculo no cambia

#### Scenario: Operación en curso
- **WHEN** se llama al endpoint de borrado mientras se está publicando o borrando el post de la misma entrada
- **THEN** responde con un error 409, con un mensaje que indica que ya hay una operación en curso, y no contacta con el PDS

### Requirement: Resultado del borrado
Si el PDS borra el post, o responde explícitamente que el post no existe (`RecordNotFound`), el endpoint SHALL borrar `_atproto_thread_uri` y responder con éxito. Si la respuesta no es concluyente (un 404 sin ese error, un tiempo agotado o un fallo de red), el endpoint SHALL consultar el registro en el PDS y desvincular solo si el PDS confirma que no existe. El panel SHALL volver a mostrar el campo para pegar y el formulario de publicación sin recargar la página.

#### Scenario: Borrado correcto
- **WHEN** el editor confirma el borrado y el PDS borra el post
- **THEN** la entrada queda sin vínculo, el panel muestra el formulario y la vista de la entrada deja de mostrar el componente

#### Scenario: Post ya borrado
- **WHEN** el editor confirma el borrado de un post que ya se había borrado en Bluesky
- **THEN** la entrada queda sin vínculo y el panel no muestra ningún error

#### Scenario: 404 genérico con el post todavía presente
- **WHEN** la petición de borrado recibe un 404 sin el error `RecordNotFound` (por ejemplo, una página de error de un proxy) y el post sigue existiendo en el PDS
- **THEN** la entrada sigue vinculada y el panel muestra un error que indica que el post no se ha borrado

#### Scenario: Deshacer tras borrar
- **WHEN** el editor borra el post desde el panel, pulsa Ctrl+Z y después "Actualizar"
- **THEN** la entrada sigue sin vínculo

### Requirement: Errores del borrado
Con cualquier otro error (credenciales, sesión, límite de peticiones, tiempo agotado o fallo de red), el vínculo no SHALL cambiar y el panel SHALL mostrar un mensaje traducido que explique el motivo, sin datos de la app password. Reintentar el borrado SHALL ser seguro aunque el intento anterior llegara a borrar el post.

#### Scenario: Credenciales rechazadas
- **WHEN** el PDS rechaza la app password al borrar
- **THEN** la entrada sigue vinculada y el panel muestra un error que indica revisar la cuenta en los ajustes

#### Scenario: Tiempo agotado
- **WHEN** la petición de borrado al PDS agota el tiempo de espera
- **THEN** la entrada sigue vinculada, el panel indica que no se ha podido confirmar el borrado y permite reintentar

#### Scenario: Reintento tras un borrado no confirmado
- **WHEN** el editor reintenta el borrado y el post ya no existe porque el intento anterior sí lo borró
- **THEN** la entrada queda sin vínculo y no se muestra ningún error

### Requirement: Independencia de la entrada de WordPress
Enviar a la papelera, restaurar o borrar definitivamente la entrada de WordPress no SHALL borrar ni modificar el post ancla en AT Protocol.

#### Scenario: Borrar la entrada definitivamente
- **WHEN** el editor borra definitivamente una entrada cuyo post ancla publicó el plugin
- **THEN** no se hace ninguna llamada al PDS y el post sigue existiendo en AT Protocol

#### Scenario: Papelera
- **WHEN** el editor envía a la papelera una entrada vinculada
- **THEN** el post ancla no cambia

### Requirement: Borrado solo en el editor de bloques
En esta versión, el borrado del post ancla SHALL ofrecerse solo en el editor de bloques. El metabox del editor clásico no SHALL ofrecer borrar el post; allí se desvincula vaciando el campo.

#### Scenario: Editor clásico
- **WHEN** el editor abre una entrada vinculada a un post de la cuenta del sitio en el editor clásico
- **THEN** el metabox muestra el campo del vínculo y no ofrece borrar el post
