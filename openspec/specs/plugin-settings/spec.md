# plugin-settings Specification

## Purpose

Define los ajustes globales del plugin (AppView, profundidad, posición y tipos de contenido), sus valores por defecto, quién puede cambiarlos y qué se borra al desinstalar.

## Requirements

### Requirement: Valores por defecto
Sin ajustes guardados, el plugin SHALL comportarse con: AppView `https://public.api.bsky.app`, profundidad máxima `6`, posición "sustituir comentarios nativos" y tipos de contenido activos `post`.

#### Scenario: Instalación nueva
- **WHEN** se activa el plugin por primera vez y una entrada tiene un vínculo válido
- **THEN** los comentarios se muestran en lugar de los comentarios nativos, consultando `https://public.api.bsky.app` con profundidad 6

### Requirement: Acceso a los ajustes
La página de ajustes SHALL ser accesible solo para usuarios con la capacidad `manage_options`.

#### Scenario: Editor sin permisos
- **WHEN** un usuario con rol Editor intenta abrir la página de ajustes
- **THEN** WordPress le deniega el acceso

### Requirement: Validación del AppView
El AppView SHALL guardarse solo si es una URL `https` válida; se le quitan las barras finales. Un valor no válido SHALL rechazarse con un aviso y conservar el valor anterior.

#### Scenario: URL http
- **WHEN** el administrador guarda `http://appview.example`
- **THEN** se muestra un error y el AppView sigue siendo el anterior

#### Scenario: Barra final
- **WHEN** el administrador guarda `https://appview.example/`
- **THEN** se guarda `https://appview.example`

### Requirement: Validación de la profundidad
La profundidad máxima SHALL ser un entero entre 1 y 20. Un valor fuera de rango o no numérico SHALL rechazarse con un aviso y conservar el valor anterior.

#### Scenario: Valor fuera de rango
- **WHEN** el administrador guarda `0` o `50`
- **THEN** se muestra un error y la profundidad sigue siendo la anterior

#### Scenario: Primer guardado con un valor no válido
- **WHEN** el administrador guarda por primera vez los ajustes con la profundidad `50`
- **THEN** se muestra un único aviso de error y se guardan los demás valores

### Requirement: Posición de los comentarios
El ajuste de posición SHALL ofrecer dos valores: "sustituir comentarios nativos" y "después del contenido". Cualquier otro valor SHALL ignorarse.

#### Scenario: Cambio de posición
- **WHEN** el administrador elige "después del contenido" y guarda
- **THEN** las entradas vinculadas muestran los comentarios de AT Protocol tras el contenido y conservan los comentarios nativos

### Requirement: Tipos de contenido activos
Los ajustes SHALL listar los tipos de contenido públicos (con `public => true`, excepto `attachment`) para que el administrador elija en cuáles se activa el plugin. Solo se guardan tipos existentes. Se SHALL poder desmarcar todos. El campo de vínculo SHALL funcionar en cualquier tipo activo, aunque el tipo no declare soporte para campos personalizados.

#### Scenario: Activar en páginas
- **WHEN** el administrador marca "Páginas" y guarda
- **THEN** el editor de páginas muestra el campo de vínculo y las páginas vinculadas muestran comentarios

#### Scenario: Tipo personalizado sin campos personalizados
- **WHEN** el administrador activa un tipo público registrado sin soporte para `custom-fields`
- **THEN** el panel del editor de bloques de ese tipo guarda el vínculo igual que en las entradas

#### Scenario: Ningún tipo activo
- **WHEN** el administrador desmarca todos los tipos y guarda
- **THEN** se guarda la lista vacía y ninguna entrada muestra el campo ni el componente

#### Scenario: Tipo de contenido desactivado
- **WHEN** una entrada de un tipo no activo tiene `_atproto_thread_uri` guardado
- **THEN** en el frontend no se muestra el componente

### Requirement: Desinstalación
Al desinstalar el plugin desde WordPress SHALL borrarse la opción de ajustes, en multisite en cada sitio de la red. Los metas `_atproto_thread_uri` de las entradas SHALL conservarse.

#### Scenario: Desinstalar en multisite
- **WHEN** el plugin tiene ajustes guardados en dos sitios de una red y se desinstala desde la administración de la red
- **THEN** ninguno de los dos sitios conserva `atproto_comments_settings`

#### Scenario: Desinstalar y reinstalar
- **WHEN** se desinstala el plugin y después se vuelve a instalar
- **THEN** los ajustes vuelven a sus valores por defecto y las entradas vinculadas siguen mostrando sus comentarios
