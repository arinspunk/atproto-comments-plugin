# Spec Delta

## MODIFIED Requirements

### Requirement: Cadenas traducibles
Todas las claves de interfaz del componente (`reply`, `replyTo`, `comment`, `commentIn`, `replyIn`, `close`, `lastUsed`, `otherApp`, `inputPlaceholder`, `go`, `invalidDomain`, `error`, `viewMore`, `unknownAuthor`, `now`, `minutes`, `hours`, `days`, `months`) SHALL ser traducibles en el dominio `parolas`. El componente SHALL recibir en `strings`, como JSON, solo las claves cuya traducción difiere del texto fuente; las demás SHALL quedar a cargo del diccionario propio del componente para `lang`. El marcador `{name}` de `replyTo` y de `replyIn` SHALL conservarse. El texto fuente de `viewMore` SHALL ser «View more replies», sin nombrar ninguna app. La nota opcional del selector (`pickerNote`) no SHALL ofrecerse para traducir.

#### Scenario: Sitio con traducción del plugin
- **WHEN** el sitio está en español y existe la traducción del plugin al español
- **THEN** el botón principal muestra el texto traducido de "Comment"

#### Scenario: Panel del selector traducido
- **WHEN** el sitio está en español, existe la traducción del plugin al español, el visitante ya eligió antes una app y pulsa **Reply** en una respuesta de Ana
- **THEN** el título del panel, su botón de cerrar y la marca de la última app usada salen traducidos, y el título incluye el nombre «Ana»

#### Scenario: Sitio sin traducción del plugin y con diccionario en el componente
- **WHEN** el sitio está en `pt_BR` y no existe traducción del plugin al portugués
- **THEN** el componente no recibe el atributo `strings` y muestra sus textos en portugués

#### Scenario: Sitio sin traducción en ninguno de los dos
- **WHEN** el sitio está en un idioma sin traducción del plugin ni diccionario en el componente
- **THEN** el componente muestra los textos en inglés, y el enlace a más respuestas dice «View more replies»

### Requirement: Carga de assets
El JS del componente (como módulo ES, junto con los módulos que importa) y su CSS SHALL cargarse solo en las páginas que van a mostrar el componente, desde los archivos incluidos en el plugin y nunca desde un CDN. La URL de cada módulo del componente, incluidos los que importa, SHALL incluir la versión del componente, de modo que una actualización no reutilice módulos de la versión anterior guardados en la caché.

#### Scenario: Página sin comentarios de AT Protocol
- **WHEN** un visitante abre una entrada sin vínculo o la portada
- **THEN** la página no carga el JS ni el CSS del componente

#### Scenario: Página con comentarios de AT Protocol
- **WHEN** un visitante abre una entrada vinculada
- **THEN** la página carga desde la carpeta del plugin el módulo, cada módulo que importa y la hoja de estilos, y todos responden correctamente

#### Scenario: Actualización del componente
- **WHEN** el plugin se actualiza a una versión con otra versión del componente y un visitante vuelve a una entrada vinculada
- **THEN** cada URL de módulo del componente que pide el navegador es distinta de la de la versión anterior
