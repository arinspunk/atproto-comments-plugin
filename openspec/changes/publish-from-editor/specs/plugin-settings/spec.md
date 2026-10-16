# Spec Delta

## MODIFIED Requirements

### Requirement: Desinstalación
Al desinstalar el plugin desde WordPress SHALL borrarse la opción de ajustes y las credenciales de la cuenta de AT Protocol, en multisite en cada sitio de la red. Los metas `_atproto_thread_uri` de las entradas SHALL conservarse.

#### Scenario: Desinstalar en multisite
- **WHEN** el plugin tiene ajustes guardados en dos sitios de una red y se desinstala desde la administración de la red
- **THEN** ninguno de los dos sitios conserva `atproto_comments_settings`

#### Scenario: Desinstalar y reinstalar
- **WHEN** se desinstala el plugin y después se vuelve a instalar
- **THEN** los ajustes vuelven a sus valores por defecto y las entradas vinculadas siguen mostrando sus comentarios

#### Scenario: Credenciales tras desinstalar
- **WHEN** el sitio tiene una cuenta de AT Protocol configurada y se desinstala el plugin
- **THEN** la base de datos no conserva ni el identificador ni la app password cifrada

## ADDED Requirements

### Requirement: Sección de cuenta
La página de ajustes SHALL incluir una sección "Cuenta de AT Protocol" con el identificador, la app password, la prueba de conexión y la opción de olvidar la cuenta. La sección SHALL explicar que se debe usar una app password y no la contraseña principal.

#### Scenario: Abrir los ajustes
- **WHEN** el administrador abre la página de ajustes
- **THEN** ve la sección de cuenta junto a los ajustes de visualización, con un enlace o texto que explica qué es una app password

#### Scenario: Errores independientes
- **WHEN** el administrador guarda una profundidad no válida y una cuenta válida
- **THEN** se muestra el error de profundidad y la cuenta se guarda
