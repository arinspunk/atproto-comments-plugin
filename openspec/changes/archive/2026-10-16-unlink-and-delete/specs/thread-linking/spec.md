# Spec Delta

## ADDED Requirements

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

## MODIFIED Requirements

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
