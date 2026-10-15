# Design

## Context

El repo está vacío; solo tiene OpenSpec inicializado. El plugin envuelve el web component `atproto-comments@^1.1.0` (npm, MIT). Su API está documentada en `node_modules/atproto-comments/README.md`. Lo que condiciona este diseño:

- **Lectura única de atributos.** El componente lee `thread-uri`, `appview`, `lang`, `max-depth` y `strings` una sola vez, al conectarse. El HTML tiene que salir completo desde PHP.
- **Sin vínculo válido, error visible.** Si falta `thread-uri` o no es un `at://` válido, el componente muestra un error. Por eso PHP no imprime el elemento si la entrada no tiene un vínculo válido.
- **Light DOM.** Renderiza en el light DOM y se tematiza con variables `--atproto-*`. Su CSS (`dist/atproto-comments.css`) va aparte del JS (`dist/index.js`, un ESM que se auto-registra).
- **Idioma.** Divide `lang` por `-` y pasa el valor completo a `Intl`. Con `es_ES` (guion bajo), el texto cae al inglés y las fechas también. Sin atributo `lang`, usa el `lang` de `<html>`.
- **Cadenas propias del componente.** Incluye diccionarios en inglés y portugués. Las claves de `strings` sustituyen a las suyas una a una; las que no se pasan salen de su diccionario para `lang` o, si no lo tiene, del inglés.
- **Llamadas y almacenamiento en el navegador.** El fetch al AppView lo hace el navegador del visitante, y la app preferida se guarda en su `localStorage` (`atproto-preferred-app`).
- **Hilos en temas de bloques.** En temas de bloques, el bloque `core/comments` con bloques internos (el caso por defecto en Twenty Twenty-Five) **no** usa `comments_template()`, así que el filtro `comments_template` no basta.

Los motivos y el alcance están en `proposal.md`; los requisitos, en `specs/`.

## Goals / Non-Goals

**Goals:**
- **Una sola normalización de vínculos, igual en PHP y en JS.** La validación del panel y la del servidor no pueden discrepar.
- **Inserción robusta en temas clásicos y de bloques**, sin duplicados.
- **Una base de código que pase Plugin Check y PHPCS (WPCS) desde el primer commit.**

**Non-Goals:**
- Llamadas del servidor a la red (resolver handles, publicar). Llegan con `publish-from-editor`.
- Tematizar desde el plugin (mapear los colores del tema a `--atproto-*`). El componente ya hereda tipografía y color.
- Cachear o renderizar los comentarios en el servidor (SEO). Queda como idea para después de la v1.

## Decisions

### 1. Estructura y espacio de nombres

```
atproto-comments.php          cabeceras WP.org, constantes, bootstrap
includes/
  class-plugin.php            registro de hooks
  class-settings.php          Settings API, valores por defecto, getters
  class-thread-uri.php        normalización/validación (pura, sin WP)
  class-post-meta.php         register_post_meta, metabox clásico, avisos
  class-display.php           decisión de render, inserción, assets
  class-ui-strings.php        cadenas __() para el atributo strings
templates/comments.php        plantilla que sustituye a comments_template
src/editor/                   panel del editor de bloques (@wordpress/scripts)
  index.js, thread-uri.js
build/                        salida de wp-scripts (no versionada)
assets/vendor/atproto-comments/   dist/ del componente (no versionado)
tests/php/, tests/js/, tests/fixtures/thread-uris.json
readme.txt, uninstall.php, package.json, composer.json,
phpcs.xml.dist, phpunit.xml.dist, .wp-env.json
```

- **Código PHP.** Va bajo el namespace `AtprotoComments` (válido en PHP 7.4). Nada de sintaxis de PHP 8 (`match`, tipos unión, propiedades promovidas). Las constantes llevan el prefijo `ATPROTO_COMMENTS_`, la opción se llama `atproto_comments_settings`, los hooks propios `atproto_comments_*` y el text domain es `atproto-comments`.
- **`build/` y `assets/vendor/` no se versionan.** Se generan con `npm run build`. El zip de WP.org se monta en `wporg-release`. Alternativa descartada: versionar los artefactos, que mete ruido en los diffs y desincroniza versiones.

### 2. Componente incluido desde npm

`atproto-comments` es una dependencia de npm con versión exacta en `package-lock.json`. Un script `npm run vendor` (Node, sin dependencias) copia `node_modules/atproto-comments/dist/{index.js,atproto-comments.css}` a `assets/vendor/atproto-comments/`, y `npm run build` lo ejecuta antes de `wp-scripts build`. La versión de los assets que se encolan es la `version` del `package.json` del componente, leída al copiar y escrita en `assets/vendor/atproto-comments/version.php`, para invalidar la caché del navegador cuando se actualice.

Alternativas descartadas:
- `index.umd.js`: con WP 6.5+ podemos usar el ESM.
- Importar el componente desde el bundle del editor: no hace falta en el editor.

### 3. Carga como Script Module, decidida en `wp_enqueue_scripts`

`wp_enqueue_script_module( 'atproto-comments', …/index.js, [], $ver )` y `wp_enqueue_style( 'atproto-comments', …/atproto-comments.css, [], $ver )`.

La decisión de cargar se toma en `wp_enqueue_scripts`, a partir del objeto consultado (`is_singular()` + tipo activo + vínculo válido). Coincide con las condiciones de render, así que nunca se carga en balde. Además, el CSS va en el `<head>` y no hay salto visual.

### 4. Un único decisor de render y un flag por petición

`Display::should_render( $post )` concentra las condiciones: vista individual, entrada consultada, tipo activo y vínculo válido. `Display::render( $post )` devuelve el HTML del componente y marca la entrada como ya mostrada en un array estático. Los puntos de inserción llaman a `render()` y, si la entrada ya está mostrada, devuelven vacío.

**Modo "después del contenido":** filtro `the_content` (prioridad 20). Solo actúa si `in_the_loop() && is_main_query() && get_the_ID() === get_queried_object_id()`, el contexto no es `is_feed()` y no se está generando un extracto (`! doing_filter( 'get_the_excerpt' )`). Así quedan fuera los extractos y los bloques "Query Loop" de otras entradas. La última comprobación evita además que un extracto automático, cuya salida se descarta, consuma el flag por petición.

**Modo "sustituir":**
- **Temas clásicos:** filtro `comments_template` que devuelve `templates/comments.php`, que hace `echo Display::render()`. Así se reemplazan a la vez la lista y el formulario del tema.
- **Temas de bloques:** filtros `render_block_core/comments`, `render_block_core/post-comments` (el bloque heredado) y `render_block_core/post-comments-form`. Los dos primeros devuelven el componente; el de formulario devuelve `''` en entradas vinculadas, para que no aparezca un formulario nativo suelto. En modo heredado (`core/comments` con `legacy` y `core/post-comments`) el bloque pasa por `comments_template()`, así que su salida ya trae el componente y el flag ya está consumido: si la salida contiene `<atproto-comments`, se devuelve tal cual (si se sustituyera por `render()`, que devuelve `''`, el componente desaparecería).
- **Comentarios cerrados:** muchos temas clásicos ni siquiera llaman a `comments_template()` cuando `! comments_open() && ! get_comments_number()`. En ese caso, el filtro `the_content` también actúa en modo "sustituir" e inserta el componente tras el contenido. Si el tema llama después a la plantilla, el flag evita el duplicado.

Alternativa descartada: usar siempre `the_content` y ocultar los comentarios nativos con `comments_open`/`pings_open` en `false`. Así se pierde la posición de la zona de comentarios del tema y aparece el texto "Comments are closed".

### 5. Normalización de vínculos duplicada en PHP y JS, con fixtures compartidos

`Thread_Uri::normalize( string ): array{ ok: bool, uri?: string, error?: 'empty'|'handle'|'format' }` y su equivalente en JS (`src/editor/thread-uri.js`). Reglas:
- Se recortan los espacios. Una cadena vacía se considera un vínculo vacío válido (desvincula).
- `at://<did>/app.bsky.feed.post/<rkey>` se acepta tal cual.
- `https://<host>/profile/<did>/post/<rkey>[/][?…][#…]` se convierte a `at://`.
- `<did>` es `did:plc:[a-z2-7]{24}` o `did:web:[a-zA-Z0-9.-]+(%3A[0-9]+)?`, y `<rkey>` es `[A-Za-z0-9._~:-]{1,512}`, excepto `.` y `..`.
- Si en lugar de un DID hay un handle (no empieza por `did:` y parece un dominio), el error es `handle`, tanto en la URL de app como en un `at://<handle>/…`.

`tests/fixtures/thread-uris.json` reúne los casos de entrada y salida esperada, y lo leen tanto PHPUnit como `wp-scripts test-unit-js`. Así las dos implementaciones no pueden divergir sin que falle un test.

Alternativa descartada: validar solo en el servidor. El panel no podría mostrar el error antes de guardar, y el guardado REST fallaría con un mensaje genérico.

### 6. Registro del meta y validación por canal

`register_post_meta( $type, '_atproto_thread_uri', … )` para cada tipo activo, con:
- `single => true`, `type => string`;
- `show_in_rest => [ 'schema' => [ 'pattern' => <solo forma canónica o vacío> ] ]`;
- `auth_callback`: `current_user_can( 'edit_post', $post_id )`;
- `sanitize_callback`: si `Thread_Uri::normalize` acepta el valor, devuelve la forma canónica; si no, devuelve el valor recortado sin más.

**El rechazo no se hace en el `sanitize_callback`.** Ese callback no recibe el ID de la entrada, así que no puede recuperar el valor anterior, y devolver `''` desvincularía la entrada en silencio. Cada canal valida antes de escribir, y `Display::should_render()` vuelve a validar al leer, así que un valor no canónico escrito por código ajeno (`update_post_meta`) nunca llega al frontend.

Cómo se valida en cada canal:
- **REST (editor de bloques):** el panel normaliza en el cliente y envía siempre la forma canónica. Si alguien envía otra cosa por REST, el `pattern` del schema devuelve 400 (`WP_REST_Meta_Fields` valida contra el schema antes de llamar a `update_metadata`). El meta lleva `_`, así que es protegido; por eso el `auth_callback` es obligatorio para que el editor pueda escribirlo.
- **Editor clásico:** el metabox con nonce guarda en `save_post_{type}`. Si el valor no es válido, no se toca el meta y se guarda un aviso en un transient por usuario (`atproto_comments_notice_{user_id}`), que `admin_notices` muestra y borra.

**Lectura del vínculo.** `show_in_rest` expone el meta en las respuestas REST de las entradas, también a visitantes anónimos en las publicadas. Se acepta: es el mismo `at://` que se imprime en el frontend. El `auth_callback` controla solo la escritura.

**Tipos de contenido sin `custom-fields`.** El meta solo aparece en REST si el tipo declara soporte para `custom-fields`. Para cada tipo activo que no lo tenga, el plugin llama a `add_post_type_support( $type, 'custom-fields' )` en `init` (prioridad alta, después del registro del tipo). Efecto secundario aceptado: el editor puede mostrar el panel "Campos personalizados" si el usuario lo activa en sus preferencias.

**Metabox del editor clásico:**
- Se registra con `'__back_compat_meta_box' => true`, para que no aparezca también en el editor de bloques junto al panel.
- El guardado sale sin hacer nada (ni aviso) si falta el nonce, que es lo normal en los guardados por REST, si es un autosave (`DOING_AUTOSAVE`), si es una revisión (`wp_is_post_revision`) o si el usuario no tiene `edit_post`.

### 7. Panel del editor de bloques

`PluginDocumentSettingPanel`, importado de `@wordpress/editor`. Se carga solo en los tipos activos (`enqueue_block_editor_assets` comprobando el `post_type` de la pantalla). El campo es un `TextControl` local:
- al perder el foco o pulsar Enter, normaliza;
- si el valor es válido, hace `editPost( { meta: { _atproto_thread_uri: canonical } } )` y muestra la forma canónica;
- si no, muestra `Notice`/`help` con el error y no toca el meta.

La edición queda marcada como cambio pendiente y se guarda con la entrada.

`@wordpress/scripts` genera `build/editor.js` y `editor.asset.php` con las dependencias. Alternativa descartada: JS sin build. Hace falta JSX y el manifiesto de dependencias.

**Origen de `PluginDocumentSettingPanel` según la versión (comprobado en los paquetes de npm de cada versión):**
- WP 6.5 (`@wordpress/editor` 13.28, `@wordpress/edit-post` 7.28): solo existe en `@wordpress/edit-post`.
- WP 6.6 en adelante (`@wordpress/editor` 14.x): se mueve a `@wordpress/editor`. `@wordpress/edit-post` mantiene un alias que, al renderizarse, avisa en consola (`wp.editPost.PluginDocumentSettingPanel` obsoleto desde 6.6) y devuelve `null` en el editor del sitio. El alias sigue en la última versión publicada (`@wordpress/edit-post` 8.57).

Se importan los dos y se usa el primero que exista:

```js
import { PluginDocumentSettingPanel as EditorPanel } from '@wordpress/editor';
import { PluginDocumentSettingPanel as LegacyPanel } from '@wordpress/edit-post';

const PluginDocumentSettingPanel = EditorPanel || LegacyPanel;
```

`@wordpress/scripts` convierte estos imports en accesos a `wp.editor` y `wp.editPost`, así que en WP 6.5 `EditorPanel` es `undefined` sin error. El aviso de obsolescencia solo sale al renderizar el alias, que en WP 6.6+ nunca se usa. `wp-edit-post` entra en las dependencias de `editor.asset.php`; no importa, porque el panel solo se encola en el editor de entradas.

Alternativa descartada: subir el mínimo a WP 6.6 e importar solo de `@wordpress/editor`. Es más limpio y se puede hacer más adelante, pero no compensa cambiar el requisito mínimo por tres líneas.

### 8. Atributos e idioma

- `lang`: `str_replace( '_', '-', determine_locale() )`. Es el mismo valor que WordPress pone en `<html lang>` (vía `get_bloginfo( 'language' )`), así que el componente obtendría lo mismo sin el atributo. Se pasa de forma explícita para no depender de que el tema imprima `language_attributes()`.
- `strings`: `UI_Strings::all()` devuelve las 13 claves con `__( '…', 'atproto-comments' )` y comentarios `/* translators: */`, por ejemplo:
  - `{name}` es el nombre del autor y no se traduce;
  - `minutes` es la abreviatura que aparece como "5 m".

  `UI_Strings::translated()` filtra ese array y deja solo las claves cuya traducción es distinta del texto fuente en inglés. Solo esas se pasan al componente, para que las demás salgan de su propio diccionario (p. ej. el portugués incluido) en lugar de forzar el inglés. Si no queda ninguna clave, no se imprime el atributo `strings`.

  Se imprime con `esc_attr( wp_json_encode( … ) )`.

  Alternativa descartada: pasar siempre las 13 claves. Un sitio en `pt_PT` o `pt_BR` sin traducción del plugin vería inglés aunque el componente trae portugués.
- `thread-uri`, `appview` y `max-depth` se escapan con `esc_attr`.

### 9. Ajustes

- Una única opción, `atproto_comments_settings`, de tipo array con claves `appview`, `max_depth`, `position` (`replace`|`after_content`) y `post_types` (array). Se registra con `register_setting` y un `sanitize_callback`.
- Si algún valor no es válido: `add_settings_error` y se conserva el valor anterior **de ese campo**; los demás campos válidos se guardan.
- `post_types` ausente en el envío (todas las casillas desmarcadas) se guarda como `[]`, no como el valor anterior: es una elección válida que desactiva el plugin en todos los tipos.
- Cuando la opción aún no existe, WordPress ejecuta el `sanitize_callback` dos veces en el primer guardado (`update_option` → `add_option`). Para no duplicar avisos, el callback devuelve la entrada tal cual si ya tiene la forma saneada (los cuatro campos con tipos correctos), o se comprueba con `get_settings_errors()` antes de añadir un error con el mismo código.
- Valores por defecto centralizados en `Settings::defaults()`, combinados con `wp_parse_args` al leer.
- Página en Ajustes → AT Protocol Comments (`options-general.php`), con capacidad `manage_options`.

### 10. Desinstalación, readme y código de terceros

- `uninstall.php` comprueba `WP_UNINSTALL_PLUGIN` y hace `delete_option( 'atproto_comments_settings' )`. En multisite la opción es de cada sitio, así que recorre `get_sites( [ 'fields' => 'ids', 'number' => 0 ] )` con `switch_to_blog()`/`restore_current_blog()` y la borra en cada uno (`delete_site_option` no serviría: borra opciones de red). No toca los metas.
- `readme.txt` en formato WP.org, con:
  - descripción;
  - "External services": qué se envía al AppView (el `at://` del post) y cuándo (en cada visita), con enlaces a los términos y la política de privacidad de Bluesky como operador del AppView por defecto;
  - privacidad: la clave en `localStorage`;
  - FAQ: cómo obtener el `at://` o la URL con DID, qué hacer si el tema no muestra los comentarios y cómo cerrar los comentarios nativos;
  - enlace al repositorio y al paquete npm del componente, porque WordPress.org exige que el código incluido minificado tenga su fuente legible accesible.

### 11. Calidad y entorno

- **Entorno local:** `@wordpress/env` con los temas Twenty Twenty-Five (bloques) y Twenty Twenty-One (clásico). wp-env admite una sola versión de WordPress y de PHP a la vez, así que:
  - `.wp-env.json` fija la combinación mínima: WP 6.5 y PHP 7.4;
  - la combinación actual (última versión de WP, PHP 8.3) se levanta con un `.wp-env.override.json` (no versionado) o con la variable `WP_ENV_PHP_VERSION`; el README del repo documenta los dos comandos;
  - la CI de `wporg-release` cubre la matriz completa.
- **PHPCS:** con `WordPress-Extra` + `PHPCompatibilityWP` (`testVersion 7.4-`), vía Composer.
- **PHPUnit:** con la suite de WordPress dentro de wp-env (`wp-env run tests-cli`).
- **Tests JS:** `wp-scripts test-unit-js`.
- **Plugin Check:** el plugin `plugin-check` instalado en wp-env, con `wp plugin check atproto-comments`.

## Risks / Trade-offs

- [Temas de bloques con plantillas propias que no usan `core/comments`] → La inserción tras el contenido actúa como respaldo cuando los comentarios están cerrados. Para otros casos, el administrador puede pasar al modo "después del contenido". Lo documentamos en la FAQ.
- [Filtro `the_content` disparado por constructores de páginas o SEO fuera del bucle] → Las comprobaciones `in_the_loop`/`is_main_query`/ID consultado y el flag por petición lo evitan.
- [El alias de `PluginDocumentSettingPanel` en `@wordpress/edit-post` podría eliminarse en una versión futura] → El código usa primero `@wordpress/editor`, así que solo afectaría a WP 6.5, y para entonces lo razonable será subir el mínimo.
- [El CSS del tema afecta al componente (light DOM)] → Se acepta: el componente usa clases `.atproto-*` con poca especificidad. Si aparece algún caso concreto, se corrige en el componente.
- [En modo "sustituir", los comentarios nativos siguen abiertos aunque estén ocultos: `wp-comments-post.php` y la API REST de comentarios aceptan envíos automáticos (spam)] → En la v1 no se cierran, para no cambiar datos de la entrada ni el texto "Comments are closed" en otras vistas. Se documenta en la FAQ que, si no se quieren comentarios nativos, se cierren en la entrada o en Ajustes → Comentarios. Cerrarlos automáticamente queda como idea posterior.
- [Un extracto automático o un plugin que aplique `the_content` dentro del bucle y descarte la salida podría consumir el flag] → Se excluye `get_the_excerpt`; otros casos se cubren cambiando de modo.
- [Regex de DID/rkey demasiado estricta para DIDs futuros] → Se limita a `plc` y `web`, los únicos que admite AT Protocol hoy. Ampliarla es un cambio local con fixtures.

## Migration Plan

No hay migración: es la primera versión. El despliegue en WordPress.org llega con `wporg-release`. Hasta entonces, el plugin se instala desde un zip o un clon con `npm ci && npm run build`.
