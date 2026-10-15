# Tasks

## 1. Andamiaje y herramientas

- [x] 1.1 Crear `atproto-comments.php` con cabeceras WP.org (Plugin Name, Version 0.1.0, Requires at least 6.5, Requires PHP 7.4, License GPLv2 or later, Text Domain atproto-comments), constantes `ATPROTO_COMMENTS_*`, autoload simple de `includes/` y `Plugin::init()` vacío, y verificar que el plugin se activa sin avisos en wp-env (`wp plugin activate atproto-comments`)
- [x] 1.2 Crear `package.json` con `atproto-comments` (versión exacta, comprobada en npm), `@wordpress/scripts` y `@wordpress/env`; script `vendor` que copia `dist/index.js` y `dist/atproto-comments.css` a `assets/vendor/atproto-comments/` y escribe `version.php`; `build` = `vendor` + `wp-scripts build`; y verificar que `npm ci && npm run build` genera `assets/vendor/atproto-comments/` y `build/`
- [x] 1.3 Crear `.wp-env.json` con la combinación mínima (WP 6.5, PHP 7.4, temas Twenty Twenty-Five y Twenty Twenty-One, plugins `plugin-check` y `classic-editor` inactivo) y `.gitignore` (`node_modules/`, `vendor/`, `build/`, `assets/vendor/`, `.wp-env.override.json`); documentar en el README del repo cómo levantar la combinación actual (última versión de WP con `.wp-env.override.json`, PHP 8.3 con `WP_ENV_PHP_VERSION=8.3`); y verificar que `npx wp-env start` levanta el sitio con el plugin activo en las dos combinaciones
- [x] 1.4 Crear `composer.json` con PHPCS, WPCS, PHPCompatibilityWP y PHPUnit polyfills; `phpcs.xml.dist` (WordPress-Extra, testVersion 7.4-, prefijos `atproto_comments`/`AtprotoComments`, text domain); y `phpunit.xml.dist` para la suite de WP dentro de wp-env, y verificar que `composer phpcs` pasa y que `npm run test:php` ejecuta una suite vacía sin errores

## 2. Normalización de vínculos

- [x] 2.1 Crear `tests/fixtures/thread-uris.json` con casos de entrada y salida: `at://` válido con `did:plc` y `did:web`, URL de bsky.app y mu.social con DID (con y sin barra final, query y fragmento), espacios alrededor, cadena vacía, URL con handle y `at://` con handle (`handle`), colección distinta, DID mal formado, rkey `.`/`..` y esquema `http` (`format`). Verificar que el JSON es válido y cubre todos los escenarios de `specs/thread-linking`
- [x] 2.2 Implementar `includes/class-thread-uri.php` (`Thread_Uri::normalize()` pura, sin funciones de WP) y su test PHPUnit que recorre el fixture, y verificar que `npm run test:php` pasa
- [x] 2.3 Implementar `src/editor/thread-uri.js` con la misma API y su test en `tests/js/` que recorre el mismo fixture, y verificar que `npm run test:js` pasa

## 3. Ajustes y desinstalación

- [x] 3.1 Implementar `includes/class-settings.php`: `defaults()`, `get()` con `wp_parse_args`, `register_setting` con `sanitize_callback` por campo (AppView https sin barra final, profundidad 1–20, posición `replace`|`after_content`, tipos públicos existentes sin `attachment`; `post_types` ausente = `[]`) y `add_settings_error` conservando el valor anterior del campo no válido, sin duplicar avisos cuando el callback se ejecuta dos veces en el primer guardado; verificar con tests PHPUnit cada regla de validación, los valores por defecto de `specs/plugin-settings`, la lista vacía y un único aviso en el primer guardado
- [x] 3.2 Crear la página Ajustes → AT Protocol Comments (`manage_options`) con los cuatro campos y textos traducibles, y verificar en wp-env que un administrador la ve y guarda, que un Editor recibe acceso denegado y que los errores aparecen como avisos
- [x] 3.3 Crear `uninstall.php` (comprueba `WP_UNINSTALL_PLUGIN`, borra la opción y, en multisite, la borra en cada sitio con `get_sites()` y `switch_to_blog()`, sin tocar metas), y verificar en wp-env que tras desinstalar no existe `atproto_comments_settings` y que el meta de una entrada vinculada sigue presente; verificar el caso multisite con un test PHPUnit en modo multisite (`WP_TESTS_MULTISITE=1`) con dos sitios

## 4. Meta del vínculo y editor clásico

- [x] 4.1 Implementar `includes/class-post-meta.php` con `register_post_meta` para cada tipo activo (`single`, `string`, `show_in_rest` con `pattern` de forma canónica o vacío, `auth_callback` `edit_post`, `sanitize_callback` que devuelve la forma canónica si el valor es válido y, si no, el valor recortado sin vaciarlo) y `add_post_type_support( $type, 'custom-fields' )` para los tipos activos que no lo tengan; verificar con tests PHPUnit REST que un usuario con permiso guarda un `at://`, que un valor no canónico devuelve 400 y no cambia el meta, que un usuario sin `edit_post` es rechazado, y que en un tipo personalizado registrado sin `custom-fields` el meta aparece y se guarda por REST
- [x] 4.2 Añadir el metabox del editor clásico (solo tipos activos, `__back_compat_meta_box`, nonce, guardado en `save_post_{type}` que sale sin hacer nada si falta el nonce, en autosaves, en revisiones o sin `edit_post`; aviso por transient de usuario mostrado en `admin_notices`); verificar con tests PHPUnit que un guardado sin nonce y un autosave no tocan el meta ni crean aviso; y verificar en wp-env con el plugin Classic Editor que pegar una URL de bsky.app con DID guarda el `at://`, que un handle muestra el aviso específico y conserva el valor anterior, y que vaciar el campo desvincula; y sin Classic Editor, que el metabox no aparece en el editor de bloques

## 5. Panel del editor de bloques

- [x] 5.1 Implementar `src/editor/index.js` con `PluginDocumentSettingPanel` (de `@wordpress/editor` y, si no existe, de `@wordpress/edit-post`, según el design §7), un `TextControl` que normaliza al perder el foco o pulsar Enter, `editPost` solo con valores válidos y el error visible en el panel; encolarlo en `enqueue_block_editor_assets` solo para tipos activos usando `build/editor.asset.php`; verificar en wp-env (WP 6.5 y última) que pegar una URL con DID muestra el `at://` y se guarda con la entrada, que un valor no válido muestra el error y no marca cambios, que el panel no aparece en tipos inactivos, que en un tipo personalizado activo sin `custom-fields` también guarda y que en la última versión no aparece en la consola el aviso de obsolescencia de `wp.editPost.PluginDocumentSettingPanel`

## 6. Visualización en el frontend

- [x] 6.1 Implementar `includes/class-ui-strings.php` con `all()` (las 13 claves traducibles y comentarios `translators:`: marcador `{name}`, abreviaturas de tiempo) y `translated()` (solo las claves cuya traducción difiere del texto fuente), y verificar con tests PHPUnit que `all()` devuelve exactamente las claves del componente, que `replyTo` contiene `{name}`, que sin traducción `translated()` devuelve `[]` y que, con un filtro `gettext` que traduce una cadena, devuelve solo esa clave
- [x] 6.2 Implementar `Display::should_render()` y `Display::render()` (atributos `thread-uri`, `appview`, `max-depth`, `lang` desde `determine_locale()` con `_`→`-`, `strings` con `esc_attr( wp_json_encode( UI_Strings::translated() ) )` y omitido si está vacío, flag por petición), y verificar con tests PHPUnit las condiciones de `specs/comments-display` (vista individual, tipo activo, vínculo válido; nada en archivos ni feeds; nada con un meta no canónico), `es_ES`→`es-ES`, `de_DE_formal`→`de-DE-formal`, la ausencia de `strings` sin traducciones y que una segunda llamada devuelve vacío
- [x] 6.3 Encolar el módulo y el CSS en `wp_enqueue_scripts` solo cuando `should_render()` es cierto para el objeto consultado, con la versión de `version.php`, y verificar en wp-env que una entrada vinculada carga `assets/vendor/atproto-comments/index.js` como `type="module"` y el CSS, y que la portada y una entrada sin vínculo no los cargan
- [x] 6.4 Implementar el modo "después del contenido" (filtro `the_content` con las comprobaciones de bucle principal y `! doing_filter( 'get_the_excerpt' )`) y el modo "sustituir" (filtro `comments_template` con `templates/comments.php`; `render_block_core/comments`, `render_block_core/post-comments-form` y `render_block_core/post-comments`; respaldo tras el contenido con comentarios cerrados), y verificar con tests PHPUnit que cada filtro devuelve el componente o vacío según el modo, el estado de los comentarios y el flag, y que generar el extracto de la entrada consultada no consume el flag
- [x] 6.5 Verificar en wp-env con un hilo real (p. ej. `at://did:plc:t3q3ylsnsser3o74xz42sdqk/app.bsky.feed.post/3mwso7oovnp2u`), en Twenty Twenty-Five y en Twenty Twenty-One, los dos modos con comentarios abiertos y cerrados: un solo componente, sin lista ni formulario nativos en "sustituir", ambos sistemas en "después del contenido", textos y fechas en español con el sitio en `es_ES` y textos en portugués del componente con el sitio en `pt_BR`

## 7. readme e internacionalización

- [x] 7.1 Escribir `readme.txt` (formato WP.org: descripción, instalación, FAQ sobre cómo obtener el `at://` o la URL con DID, qué hacer si el tema no muestra los comentarios y cómo cerrar los comentarios nativos en modo "sustituir"; "External services" con el AppView, qué se envía, cuándo y los enlaces a términos y privacidad; nota de `localStorage`; enlace al repositorio y al paquete npm del componente como fuente del código incluido; changelog 0.1.0), y verificar que pasa el validador de readmes de WordPress.org sin errores
- [x] 7.2 Generar `languages/atproto-comments.pot` con `wp i18n make-pot` y registrar las traducciones del JS con `wp_set_script_translations` (sin `load_plugin_textdomain`, innecesario desde WP 4.6 para plugins de WordPress.org y señalado por Plugin Check), y verificar que el `.pot` incluye las 13 cadenas del componente y las de ajustes, metabox y panel

## 8. Verificación de integración

- [x] 8.1 Ejecutar `wp plugin check atproto-comments` en wp-env y verificar que no hay errores (los avisos se revisan uno a uno y se corrigen o justifican en el PR)
- [x] 8.2 Ejecutar `composer phpcs`, `npm run test:php`, `npm run test:js` y `npm run build` desde limpio (`git clean -fdx` en una copia) y verificar que todo pasa con WP 6.5 + PHP 7.4 y con la última versión de WP + PHP 8.3

## Workflow follow-up

- Archivar el cambio cuando esté revisado y pasar `plugin-settings`, `thread-linking` y `comments-display` a `openspec/specs/`.
- Continuar con `publish-from-editor`.
