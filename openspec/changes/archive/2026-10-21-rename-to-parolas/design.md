# Design

## Context

Motivación en `proposal.md`. El cambio no altera el comportamiento del plugin (`skip_specs: true`): cambia cómo se llama, cómo se identifica ante WordPress y cómo se empaqueta.

Estado actual que condiciona el diseño:

- La cadena `'atproto-comments'` aparece unas 127 veces en `includes/`, `src/` y `templates/`. Casi siempre es el text domain, pero no siempre:
  - `Settings::PAGE` (slug de la página de ajustes);
  - el ID del módulo y el handle del estilo del componente en `Display::enqueue_assets()`;
  - el nombre de `registerPlugin()` del panel del editor.

  Además hay handles `atproto-comments-*`, el espacio REST `atproto-comments/v1`, IDs de elementos HTML y la clave de cifrado `atproto-comments-app-password-v1`.
- PHPCS ya comprueba el text domain (`WordPress.WP.I18n`, propiedad `text_domain` en `phpcs.xml.dist`). En JS no hay ninguna comprobación equivalente configurada.
- `.wp-env.json` monta el repositorio en `wp-content/plugins/atproto-comments`. Esa ruta se repite en los scripts npm (`test:php`, `make-pot`, `plugin-check`), en `scripts/smoke.sh`, en `ci.yml` y en la skill `wp-env`.
- Los workflows de `.github/workflows/` no los puede leer ni editar Claude (política de la organización). Los cambia el usuario a partir de instrucciones.
- La Release `v0.1.0` de GitHub y su tag ya existen, con el nombre antiguo. El zip enviado a WordPress.org es el suyo.

## Goals / Non-Goals

**Goals:**

- Que nada de lo que el directorio revisa use la marca como identificador. Eso incluye el nombre visible, el slug, el text domain, la carpeta, el fichero principal, el zip y los gráficos.
- Que el renombrado del text domain sea completo y no toque los identificadores que no son text domain, comprobado con herramientas y no a ojo.
- Que el nuevo zip pase por la misma cadena que la v0.1.0: CI, prueba de humo en WP 6.5 y en la última versión, y Plugin Check.

**Non-Goals:**

- Renombrar los prefijos internos, el slug de la página de ajustes, los handles, el espacio REST o la clave de cifrado (decisión 3).
- Cambiar el web component, su paquete npm o su etiqueta.
- Renombrar el repositorio de GitHub.
- Migrar datos: no hay instalaciones.

## Decisions

### 1. Text domain `parolas`, cambiado solo donde es text domain, y comprobado con PHPCS y ESLint

El text domain se cambia en:
- los argumentos de las funciones de traducción, en PHP (`__()`, `esc_html__()`, `_n()`…) y en JS (`@wordpress/i18n`);
- las dos llamadas a `wp_set_script_translations()`;
- la cabecera `Text Domain:`.

No se toca ninguna otra aparición de `'atproto-comments'`.

La comprobación la hacen las herramientas, no una búsqueda:
- **PHP:** la propiedad `text_domain` de PHPCS pasa a `parolas`. Cualquier cadena con el dominio antiguo da error.
- **JS:** se activa la regla `@wordpress/i18n-text-domain` con `allowedTextDomain: 'parolas'` en `eslint.config.cjs`. Se aplica a `src/` mediante `npm run lint:js`.
- **Después de los cambios:** una búsqueda de `'atproto-comments'` en `includes/`, `src/` y `templates/` solo debe devolver los identificadores de la decisión 3.

- Descartado: sustituir `'atproto-comments'` por `'parolas'` en todo el código. Cambiaría el slug de la página de ajustes, el ID del módulo del componente y el nombre del plugin del editor.
- Descartado: mantener el text domain `atproto-comments`. WordPress.org exige que coincida con el slug; el escáner automático del envío ya lo marcó como error con el slug `comments-from-atproto`.

### 2. Fichero principal `parolas.php`

`atproto-comments.php` se renombra a `parolas.php` con `git mv`. El código del fichero no cambia: usa `__FILE__`, `plugin_dir_path()` y `plugin_dir_url()`. Se actualizan las referencias:
- `scripts/check-version.mjs` y sus tests;
- `scripts/smoke.sh`;
- `tests/js/dist.test.js`;
- el bootstrap de PHPUnit y la constante `WP_UNINSTALL_PLUGIN` de su test (`parolas/parolas.php`);
- `README.md`.

- Descartado: mantener `atproto-comments.php`. WordPress.org no exige que coincida con el slug, pero el zip `parolas/` mostraría la marca en el fichero que el revisor abre primero. Cambiarlo después de publicar desactivaría el plugin en todos los sitios al actualizar; hoy no cuesta nada.

### 3. Los identificadores internos se quedan

No cambian el namespace, las constantes, las opciones, los hooks y filtros, las metas, el slug de la página de ajustes, los handles de scripts y estilos, el espacio REST, los IDs HTML ni la clave de cifrado.

- **No los revisa el directorio.** La guía 17 trata de nombres, slugs, URLs y gráficos. La política de marca de atproto permite la marca en identificadores de código (secciones 3.7 y 3.8).
- **Menos riesgo en una ronda de revisión que conviene ganar a la primera.** El renombrado se queda en unos pocos ficheros más el text domain, sin deltas de specs.
- **Coste aceptado:** los filtros públicos documentados (`atproto_comments_publish_capability`, `atproto_comments_post_url`) mantienen el prefijo, y no coinciden con el nombre del plugin.

- Descartado: renombrarlo todo a `parolas`. Supone cambiar casi todo el código, las specs `thread-linking`, `plugin-settings`, `comments-display` y `anchor-post-publishing`, y probarlo entero, solo por coherencia interna.

### 4. Nombre visible y textos de la interfaz

- **Cabecera `Plugin Name:` y título de `readme.txt`:** «Parolas – Comments with atproto». Sigue el patrón que pide WordPress.org: término propio delante y la marca al final, tras «with». «for» se descartó porque se lee como «comentarios para atproto», y «from», porque admite la lectura «hecho por atproto».
- **Menú y título de la página de ajustes:** «Parolas». Es corto para el menú de Ajustes, y el nombre completo ya está en la cabecera.
- **`readme.txt`:** las rutas «Settings → Comments from atproto» pasan a «Settings → Parolas». El aviso de independencia pasa a «Parolas is an independent project…».
- **Lo que no cambia:** el panel «AT Protocol comments» del editor y el resto de textos descriptivos (secciones 3.1 y 3.2 de la política de atproto).

- Descartado: «Parolas – Comments with atproto» también en el menú. Es demasiado largo para la columna de Ajustes.

### 5. `Plugin URI` sigue apuntando al repositorio

`https://github.com/arinspunk/atproto-comments-plugin` contiene «atproto», y el correo de revisión dice que también miran las URLs. Se mantiene: es el nombre de un repositorio, permitido por la sección 3.7 de la política de atproto, y renombrarlo rompe enlaces del componente, de los badges y de los clones.

- Descartado: renombrar el repositorio ahora. Si el revisor lo pide, se renombra entonces; GitHub redirige el nombre antiguo.
- Descartado: quitar `Plugin URI`. Es opcional, pero es la vía para que los usuarios encuentren el código fuente.

### 6. Versión 0.1.1 con el workflow Release

El cambio se publica como 0.1.1 en los seis sitios que comprueba `check-version`. `readme.txt` añade una entrada `= 0.1.1 =` («Renamed to Parolas – Comments with atproto; text domain `parolas`.») y conserva la de 0.1.0. El tag `v0.1.1` en `main` lanza la release: CI, zip `parolas-0.1.1.zip`, prueba de humo y GitHub Release.

- Descartado: borrar y rehacer el tag y la Release `v0.1.0`. Reescribe algo publicado, y el zip enviado a WordPress.org dejaría de corresponder a una release.
- Descartado: construir el zip en local y subirlo sin tag. Saltaría la prueba en CI y el zip no quedaría ligado a un commit.

### 7. Gráficos

El banner y el icono se rehacen sin «at://» (los hace el usuario):
- el banner lleva «Parolas» en lugar de «at://»;
- el icono, un motivo propio, por ejemplo el bocadillo con «//» de los bocetos.

Se mantienen los nombres de fichero y los tamaños de `.wordpress-org/`. `screenshot-4.png` se repite porque muestra «Comments from atproto» en el menú de Ajustes. Las demás capturas no muestran el nombre del plugin.

### 8. Verificación: WP 6.5 y la última versión

| Qué | WP 6.5 (PHP 7.4) | Última WP (PHP 8.3) |
|---|---|---|
| PHPUnit y multisite (CI y local) | ✓ | ✓ |
| PHPCS con text domain `parolas` | ✓ (PHP 7.4) | |
| ESLint con `i18n-text-domain` y tests JS | (Node) | (Node) |
| `smoke.sh` sobre el zip `parolas/` | ✓ | ✓ |
| Plugin Check sobre el zip, incluido que el text domain coincida con la carpeta | | ✓ |
| Página de ajustes con el título «Parolas» | ✓ (smoke) | ✓ (smoke) |

## Risks / Trade-offs

- [El revisor también objeta la URL del repositorio, los prefijos internos o la descripción] → Se corrige en la siguiente ronda. El repositorio se puede renombrar con redirección automática, y los prefijos se cambiarían en otro cambio con sus deltas de specs.
- [Queda alguna cadena con el dominio antiguo] → PHPCS y ESLint fallan en la CI. La búsqueda final deja solo los identificadores de la decisión 3.
- [El renombrado rompe algo en ejecución, como el plugin del editor o la carga de traducciones de JS] → Las pruebas de PHPUnit y de humo en las dos combinaciones, y una comprobación manual del panel del editor en wp-env.
- [Las capturas o los gráficos no llegan a tiempo] → No bloquean el envío: los assets van al SVN tras la aprobación. Solo `screenshot-4.png` conviene rehacerla antes de activar `WPORG_DEPLOY`.
- [El entorno wp-env de desarrollo queda con el plugin antiguo activo y no encontrado] → `wp-env start --update` vuelve a ejecutar `afterStart`, que activa `parolas`. Si hiciera falta, se ejecuta `wp-env reset all`.

## Migration Plan

1. Rama `rename-to-parolas`. Implementar y verificar en local, incluidas las pruebas de humo.
2. El usuario aplica en `.github/workflows/` los cambios de `SLUG` y de la ruta de wp-env.
3. PR con la CI en verde, archivar el cambio y fusionar.
4. Tag `v0.1.1` en `main`: la release genera `parolas-0.1.1.zip`.
5. Subir el zip con «Upload updated…» en la página del envío y responder al hilo de revisión pidiendo el slug `parolas`.

Marcha atrás: hasta el paso 5 nada sale del repositorio; basta con no fusionar. Después, el slug se podría volver a pedir por el hilo, mientras no haya aprobación.
