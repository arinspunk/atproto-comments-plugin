# anchor-post-publishing Specification

## Purpose

Permite publicar desde el editor de WordPress, cuando el editor lo decida, el post ancla de AT Protocol de una entrada, con un texto propio y una tarjeta que enlaza a la entrada, y vincularlo automáticamente.

## Requirements

### Requirement: Formulario de publicación en el editor de bloques
En los tipos de contenido activos, el panel lateral del editor de bloques SHALL incluir un campo de texto para el post y un botón de publicación. El campo SHALL mostrar un contador de grafemas con el límite de 300.

#### Scenario: Contador de grafemas
- **WHEN** el editor escribe un texto con emojis compuestos
- **THEN** el contador cuenta cada emoji como un grafema

#### Scenario: Texto demasiado largo
- **WHEN** el texto supera los 300 grafemas
- **THEN** el contador lo indica y el botón queda deshabilitado

### Requirement: Permiso para publicar
Solo los usuarios que pueden editar la entrada (`edit_post`) y además tienen la capacidad de publicación SHALL poder publicar el post ancla. La capacidad de publicación SHALL ser por defecto `edit_others_posts` (Editores y Administradores) y SHALL poder cambiarse con el filtro `atproto_comments_publish_capability`. El editor de bloques y el endpoint SHALL aplicar la misma comprobación.

#### Scenario: Autor en su propia entrada
- **WHEN** un usuario con rol Autor abre una entrada suya publicada y sin vínculo
- **THEN** el panel no muestra el formulario de publicación, indica que solo los editores pueden publicar en la cuenta del sitio, y el campo del vínculo sigue disponible

#### Scenario: Editor
- **WHEN** un usuario con rol Editor abre una entrada publicada de otro autor y sin vínculo
- **THEN** el panel muestra el formulario de publicación

#### Scenario: Filtro que abre el permiso
- **WHEN** el sitio devuelve `publish_posts` en `atproto_comments_publish_capability` y un Autor publica desde su propia entrada
- **THEN** el post se crea y la entrada queda vinculada

### Requirement: Precondiciones del botón
El botón SHALL estar habilitado solo si la entrada está publicada (estado `publish`) y guardada, no tiene vínculo, la cuenta del sitio está configurada y el texto tiene entre 1 y 300 grafemas. Si no lo está, el panel SHALL indicar el motivo.

#### Scenario: Entrada en borrador
- **WHEN** el editor abre el panel de una entrada en borrador
- **THEN** el botón está deshabilitado y el panel indica que antes hay que publicar la entrada

#### Scenario: Entrada ya vinculada
- **WHEN** la entrada ya tiene un `_atproto_thread_uri`
- **THEN** el formulario de publicación no se puede usar y el panel muestra el vínculo existente

#### Scenario: Cuenta sin configurar
- **WHEN** el sitio no tiene cuenta de AT Protocol configurada
- **THEN** el botón está deshabilitado y el panel indica que falta configurar la cuenta, con un enlace a los ajustes para quien tenga `manage_options`

#### Scenario: Cambios sin guardar
- **WHEN** la entrada publicada tiene cambios sin guardar en el editor
- **THEN** el botón está deshabilitado y el panel indica que hay que actualizar la entrada primero

### Requirement: Publicación solo bajo demanda
El post ancla SHALL crearse solo cuando el editor pulsa el botón. Publicar o actualizar la entrada no SHALL crear ningún post en AT Protocol.

#### Scenario: Publicar la entrada
- **WHEN** el editor publica una entrada nueva sin pulsar el botón de publicación del post
- **THEN** no se hace ninguna llamada al PDS y la entrada sigue sin vínculo

### Requirement: Endpoint de publicación
La publicación SHALL hacerse por un endpoint REST del plugin que recibe solo el ID de la entrada y el texto. Todo lo demás (URL, título, extracto, imagen, idioma, cuenta) SHALL obtenerlo el servidor. El endpoint SHALL exigir el permiso para publicar y un nonce REST válido.

#### Scenario: Usuario sin permiso
- **WHEN** un usuario sin `edit_post` sobre la entrada llama al endpoint
- **THEN** recibe un error 403 y no se crea ningún post

#### Scenario: Autor sin la capacidad de publicación
- **WHEN** un Autor llama al endpoint para su propia entrada sin que el filtro haya cambiado la capacidad
- **THEN** recibe un error 403 y no se contacta con el PDS

#### Scenario: Petición sin nonce
- **WHEN** se llama al endpoint con cookie de sesión pero sin nonce REST
- **THEN** la petición se rechaza y no se crea ningún post

### Requirement: Validación en el servidor
El endpoint SHALL repetir las precondiciones: la entrada existe, es de un tipo activo, está publicada, no tiene vínculo, la cuenta está configurada y el texto tiene entre 1 y 300 grafemas y como mucho 3000 bytes en UTF-8. Si alguna falla SHALL responder con un error y un mensaje traducido sin llamar al PDS.

#### Scenario: Entrada no publicada
- **WHEN** se llama al endpoint con una entrada en borrador
- **THEN** responde con un error 400 y no contacta con el PDS

#### Scenario: Texto de 301 grafemas
- **WHEN** se llama al endpoint con un texto de 301 grafemas
- **THEN** responde con un error 400 y no contacta con el PDS

#### Scenario: Emoji compuesto
- **WHEN** se llama al endpoint con un texto de 300 grafemas en el que uno es el emoji `👩‍💻` (varios puntos de código unidos con ZWJ)
- **THEN** el servidor lo cuenta como 300 grafemas y acepta el texto

### Requirement: Protección contra doble envío
Una entrada SHALL tener como máximo un post ancla creado por el plugin. Si la entrada ya está vinculada, el endpoint SHALL responder con un error 409. Dos peticiones simultáneas para la misma entrada SHALL crear como máximo un post.

#### Scenario: Entrada ya vinculada
- **WHEN** se llama al endpoint para una entrada con `_atproto_thread_uri`
- **THEN** responde con un error 409 y no se crea ningún post

#### Scenario: Doble clic
- **WHEN** llegan dos peticiones para la misma entrada casi a la vez
- **THEN** solo una crea el post y la otra recibe un error

### Requirement: Contenido del post
El post SHALL ser un `app.bsky.feed.post` con el texto del editor tal cual, como texto plano, la fecha de creación actual y el idioma principal del sitio en `langs`. Los enlaces, menciones y hashtags del texto no SHALL convertirse en facets en esta versión.

#### Scenario: Texto con hashtag
- **WHEN** el editor publica el texto `Nueva entrada #wordpress`
- **THEN** el post contiene ese texto sin facets y el hashtag no es un enlace

#### Scenario: Idioma del post
- **WHEN** el idioma del sitio es `gl_ES` y el editor publica el post
- **THEN** el post tiene `langs` igual a `["gl"]`

### Requirement: Tarjeta enlazada
El post SHALL incluir una tarjeta externa con la URL permanente de la entrada, su título y su extracto, como texto plano sin HTML. En una entrada protegida con contraseña, la tarjeta no SHALL incluir el extracto.

#### Scenario: Tarjeta de una entrada
- **WHEN** se publica el post de una entrada titulada `Café &amp; tostadas`
- **THEN** la tarjeta enlaza a la URL permanente de la entrada y su título es `Café & tostadas`

#### Scenario: Entrada protegida con contraseña
- **WHEN** se publica el post de una entrada protegida con contraseña
- **THEN** la tarjeta tiene URL y título pero no descripción

### Requirement: Imagen de la tarjeta
Si la entrada tiene imagen destacada, la tarjeta SHALL incluirla en tamaño `medium_large` si el archivo no supera 1 000 000 bytes. Si no hay imagen, supera el límite o falla su subida, el post SHALL publicarse sin imagen.

#### Scenario: Imagen destacada pequeña
- **WHEN** se publica el post de una entrada con una imagen destacada de 300 KB en `medium_large`
- **THEN** la tarjeta del post muestra esa imagen

#### Scenario: Imagen demasiado grande
- **WHEN** la imagen destacada en `medium_large` ocupa 2 MB
- **THEN** el post se publica con la tarjeta sin imagen

### Requirement: Vínculo tras publicar
Cuando el post se crea, su URI `at://` SHALL guardarse en `_atproto_thread_uri` en forma canónica y devolverse en la respuesta. El panel SHALL mostrar el vínculo sin recargar la página.

#### Scenario: Publicación correcta
- **WHEN** el editor pulsa el botón y el PDS crea el post
- **THEN** el panel muestra el `at://` del post, el formulario de publicación deja de estar disponible y la vista de la entrada muestra el componente de comentarios

### Requirement: Errores de publicación
Si falla la resolución de la cuenta, la sesión o la creación del post, el endpoint SHALL responder con un mensaje traducido que explique el motivo, sin datos de la app password. El vínculo no SHALL cambiar y el texto del panel SHALL conservarse para reintentar.

#### Scenario: Credenciales rechazadas
- **WHEN** el PDS rechaza la app password al publicar
- **THEN** el panel muestra un error que indica revisar la cuenta en los ajustes y conserva el texto

#### Scenario: Límite de peticiones
- **WHEN** el PDS responde con un error 429
- **THEN** el panel muestra un mensaje que indica que se vuelva a intentar más tarde

### Requirement: Resultado incierto
Si no se puede saber si el post llegó a crearse, el panel SHALL decir que se compruebe el perfil antes de reintentar y que, si el post existe, se pegue su enlace en el campo del vínculo. Esto SHALL ocurrir cuando la creación del post agota el tiempo o pierde la conexión después de enviarse, y también cuando la llamada al endpoint falla sin una respuesta del plugin (error 500, 502 o 504, o fallo de red).

#### Scenario: Tiempo agotado al crear el post
- **WHEN** la petición de creación del post al PDS agota el tiempo de espera
- **THEN** el vínculo no cambia y el panel muestra el mensaje de resultado incierto, conservando el texto

#### Scenario: Corte del servidor
- **WHEN** la llamada al endpoint termina con un error 504 de un proxy, sin respuesta JSON del plugin
- **THEN** el panel muestra el mensaje de resultado incierto, no un error genérico

### Requirement: Tiempo máximo de la publicación
El endpoint SHALL terminar en como mucho 25 segundos, sumando todas sus llamadas de red. Si no queda tiempo para subir la imagen, el post SHALL publicarse sin imagen. Si no queda tiempo para crear el post, SHALL responder con un error traducido sin crearlo.

#### Scenario: Resolución de identidad lenta
- **WHEN** la resolución de la cuenta y la sesión tardan 15 segundos en total
- **THEN** el post se publica sin imagen y la respuesta llega antes de 25 segundos

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
