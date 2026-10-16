# Spec Delta

## Purpose

Permite publicar desde el editor de WordPress, cuando el editor lo decida, el post ancla de AT Protocol de una entrada, con un texto propio y una tarjeta que enlaza a la entrada, y vincularlo automáticamente.

## ADDED Requirements

### Requirement: Formulario de publicación en el editor de bloques
En los tipos de contenido activos, el panel lateral del editor de bloques SHALL incluir un campo de texto para el post y un botón de publicación. El campo SHALL mostrar un contador de grafemas con el límite de 300.

#### Scenario: Contador de grafemas
- **WHEN** el editor escribe un texto con emojis compuestos
- **THEN** el contador cuenta cada emoji como un grafema

#### Scenario: Texto demasiado largo
- **WHEN** el texto supera los 300 grafemas
- **THEN** el contador lo indica y el botón queda deshabilitado

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
La publicación SHALL hacerse por un endpoint REST del plugin que recibe solo el ID de la entrada y el texto. Todo lo demás (URL, título, extracto, imagen, cuenta) SHALL obtenerlo el servidor. El endpoint SHALL exigir `edit_post` sobre la entrada y un nonce REST válido.

#### Scenario: Usuario sin permiso
- **WHEN** un usuario sin `edit_post` sobre la entrada llama al endpoint
- **THEN** recibe un error 403 y no se crea ningún post

#### Scenario: Petición sin nonce
- **WHEN** se llama al endpoint con cookie de sesión pero sin nonce REST
- **THEN** la petición se rechaza y no se crea ningún post

### Requirement: Validación en el servidor
El endpoint SHALL repetir las precondiciones: la entrada existe, es de un tipo activo, está publicada, no tiene vínculo, la cuenta está configurada y el texto tiene entre 1 y 300 grafemas. Si alguna falla SHALL responder con un error y un mensaje traducido sin llamar al PDS.

#### Scenario: Entrada no publicada
- **WHEN** se llama al endpoint con una entrada en borrador
- **THEN** responde con un error 400 y no contacta con el PDS

#### Scenario: Texto de 301 grafemas
- **WHEN** se llama al endpoint con un texto de 301 grafemas
- **THEN** responde con un error 400 y no contacta con el PDS

### Requirement: Protección contra doble envío
Una entrada SHALL tener como máximo un post ancla creado por el plugin. Si la entrada ya está vinculada, el endpoint SHALL responder con un error 409. Dos peticiones simultáneas para la misma entrada SHALL crear como máximo un post.

#### Scenario: Entrada ya vinculada
- **WHEN** se llama al endpoint para una entrada con `_atproto_thread_uri`
- **THEN** responde con un error 409 y no se crea ningún post

#### Scenario: Doble clic
- **WHEN** llegan dos peticiones para la misma entrada casi a la vez
- **THEN** solo una crea el post y la otra recibe un error

### Requirement: Contenido del post
El post SHALL ser un `app.bsky.feed.post` con el texto del editor tal cual, como texto plano, y la fecha de creación actual. Los enlaces, menciones y hashtags del texto no SHALL convertirse en facets en esta versión.

#### Scenario: Texto con hashtag
- **WHEN** el editor publica el texto `Nueva entrada #wordpress`
- **THEN** el post contiene ese texto sin facets y el hashtag no es un enlace

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
