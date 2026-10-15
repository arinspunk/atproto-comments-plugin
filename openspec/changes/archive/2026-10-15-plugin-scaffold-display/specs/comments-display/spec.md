# Spec Delta

## Purpose

Define cuándo y dónde se muestra el componente `<atproto-comments>` en el frontend, con qué atributos (hilo, AppView, profundidad, idioma, cadenas) y cómo se cargan sus assets.

## ADDED Requirements

### Requirement: Condiciones para mostrar los comentarios
El componente SHALL mostrarse solo en la vista individual de una entrada de un tipo activo con un vínculo válido, y solo para el contenido principal de la página. No SHALL mostrarse en archivos, portada de entradas, feeds, extractos ni consultas secundarias.

#### Scenario: Entrada vinculada
- **WHEN** un visitante abre una entrada `post` con `_atproto_thread_uri` válido
- **THEN** la página contiene un `<atproto-comments>` con ese `thread-uri`

#### Scenario: Entrada sin vínculo
- **WHEN** un visitante abre una entrada sin `_atproto_thread_uri`
- **THEN** la página no contiene `<atproto-comments>` y los comentarios nativos se muestran como siempre

#### Scenario: Listado y feed
- **WHEN** se muestra un archivo de categoría o el feed RSS que incluye entradas vinculadas
- **THEN** no aparece ningún `<atproto-comments>`

### Requirement: Un único componente por página
En una misma página SHALL imprimirse como máximo un `<atproto-comments>` por entrada vinculada, aunque se ejecuten varios de los puntos de inserción.

#### Scenario: Tema que dispara varios puntos de inserción
- **WHEN** el modo es "sustituir" y el tema aplica el filtro de contenido y además carga la plantilla de comentarios
- **THEN** la página contiene un solo `<atproto-comments>`

### Requirement: Modo sustituir en temas clásicos
En modo "sustituir", en una entrada vinculada de un tema clásico, el componente SHALL ocupar el lugar de la plantilla de comentarios del tema, y la lista y el formulario nativos no SHALL mostrarse.

#### Scenario: Tema clásico
- **WHEN** un tema clásico carga la plantilla de comentarios de una entrada vinculada
- **THEN** en su lugar aparece `<atproto-comments>` y no aparecen ni la lista ni el formulario de comentarios nativos

### Requirement: Modo sustituir en temas de bloques
En modo "sustituir", en una entrada vinculada de un tema de bloques, la salida del bloque de comentarios SHALL reemplazarse por el componente.

#### Scenario: Tema de bloques
- **WHEN** un tema de bloques (p. ej. Twenty Twenty-Five) muestra una entrada vinculada
- **THEN** en el lugar del bloque de comentarios aparece `<atproto-comments>` y no aparecen la lista ni el formulario nativos

### Requirement: Modo sustituir con comentarios cerrados
En modo "sustituir", el componente SHALL mostrarse aunque los comentarios nativos de la entrada estén cerrados. Si el tema no muestra ninguna zona de comentarios, el componente SHALL aparecer tras el contenido.

#### Scenario: Comentarios cerrados en un tema clásico
- **WHEN** una entrada vinculada tiene los comentarios cerrados y ninguno aprobado, y el tema no carga la plantilla de comentarios
- **THEN** `<atproto-comments>` aparece tras el contenido de la entrada

### Requirement: Modo después del contenido
En modo "después del contenido", el componente SHALL añadirse al final del contenido de la entrada, y los comentarios nativos SHALL seguir funcionando sin cambios.

#### Scenario: Ambos sistemas a la vez
- **WHEN** el modo es "después del contenido" y una entrada vinculada tiene comentarios nativos abiertos
- **THEN** la página muestra el contenido, después `<atproto-comments>`, y después los comentarios nativos del tema

### Requirement: Atributos del componente
El componente SHALL recibir `thread-uri` del vínculo, `appview` y `max-depth` de los ajustes, y `lang` con el locale del sitio en formato BCP 47 (`es_ES` pasa a `es-ES`). Todos los valores SHALL escaparse como atributos HTML.

#### Scenario: Locale con guion bajo
- **WHEN** el idioma del sitio es `es_ES`
- **THEN** el componente tiene `lang="es-ES"`

#### Scenario: Locale con variante
- **WHEN** el idioma del sitio es `de_DE_formal`
- **THEN** el componente tiene `lang="de-DE-formal"`

### Requirement: Cadenas traducibles
Todas las claves de interfaz del componente (`reply`, `replyTo`, `comment`, `otherApp`, `inputPlaceholder`, `invalidDomain`, `error`, `viewMore`, `now`, `minutes`, `hours`, `days`, `months`) SHALL ser traducibles en el dominio `atproto-comments`. El componente SHALL recibir en `strings`, como JSON, solo las claves cuya traducción difiere del texto fuente; las demás SHALL quedar a cargo del diccionario propio del componente para `lang`. El marcador `{name}` de `replyTo` SHALL conservarse.

#### Scenario: Sitio con traducción del plugin
- **WHEN** el sitio está en español y existe la traducción del plugin al español
- **THEN** el botón principal muestra el texto traducido de "Comment"

#### Scenario: Sitio sin traducción del plugin y con diccionario en el componente
- **WHEN** el sitio está en `pt_BR` y no existe traducción del plugin al portugués
- **THEN** el componente no recibe el atributo `strings` y muestra sus textos en portugués

#### Scenario: Sitio sin traducción en ninguno de los dos
- **WHEN** el sitio está en un idioma sin traducción del plugin ni diccionario en el componente
- **THEN** el componente muestra los textos en inglés

### Requirement: Carga de assets
El JS del componente (como módulo ES) y su CSS SHALL cargarse solo en las páginas que van a mostrar el componente, desde los archivos incluidos en el plugin y nunca desde un CDN.

#### Scenario: Página sin comentarios de AT Protocol
- **WHEN** un visitante abre una entrada sin vínculo o la portada
- **THEN** la página no carga el JS ni el CSS del componente

#### Scenario: Página con comentarios de AT Protocol
- **WHEN** un visitante abre una entrada vinculada
- **THEN** la página carga el módulo y la hoja de estilos desde la carpeta del plugin
