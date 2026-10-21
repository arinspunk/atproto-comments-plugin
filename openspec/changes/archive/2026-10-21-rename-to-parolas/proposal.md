# Proposal

## Why

La revisión previa de WordPress.org (20 de octubre de 2026) dejó en pausa el envío de la v0.1.0 por la guía 17. Tanto el nombre «Comments from atproto» como el slug `atproto-comments` usan la marca «atproto» de Bluesky Social PBC de forma que puede sugerir afiliación, y el slug empieza por esa marca. WordPress.org no admite un slug que empiece por una marca ajena, aunque haya permiso, y pide un término propio delante y la marca al final, tras «for» o «with». Hay que renombrar el plugin antes de la aprobación: el slug no se puede cambiar después.

## What Changes

- **Nombre visible:** «Parolas – Comments with atproto», en la cabecera del plugin, en `readme.txt` y en la página de ajustes. El menú de Ajustes muestra «Parolas».
- **Slug, text domain y carpeta del plugin:** `parolas`. El text domain cambia en todas las cadenas traducibles (PHP y JS) y en la carga de traducciones de los scripts. El zip pasa a ser `parolas-X.Y.Z.zip`, con la carpeta `parolas/`.
- **Fichero principal:** `atproto-comments.php` pasa a ser `parolas.php`. Hoy no hay instalaciones, así que cambiar el nombre que identifica al plugin instalado (`parolas/parolas.php`) no desactiva el plugin en ningún sitio.
- **Herramientas:** `.wp-env.json`, los scripts npm (`test:php`, `make-pot`, `plugin-check`), `scripts/dist.mjs`, `scripts/smoke.sh`, `scripts/check-version.mjs`, el bootstrap de PHPUnit, PHPCS (text domain) y ESLint (comprobación del text domain en JS). El `.pot` pasa a ser `languages/parolas.pot`.
- **Workflows de GitHub** (los cambia el usuario, porque la política de la organización no deja a Claude leer `.github/workflows/`): `SLUG` de `release.yml` y `wporg-assets.yml`, y la ruta del plugin en wp-env de `ci.yml`.
- **Documentación:** `readme.txt` (nombre, aviso de independencia, rutas de Ajustes y entrada de changelog), `README.md`, la skill `wp-env` y el `context` de `openspec/config.yaml`.
- **Versión 0.1.1**, publicada con el workflow Release a partir del tag `v0.1.1`. El zip se sube al envío en pausa y se responde al hilo de revisión pidiendo reservar el slug `parolas`.
- **Gráficos de WordPress.org:** el banner y el icono se rehacen sin «at://», con la marca Parolas (los hace el usuario). La captura de ajustes se repite, porque muestra el nombre antiguo en el menú.

No cambia:
- **Los prefijos internos:** namespace `AtprotoComments`, constantes `ATPROTO_COMMENTS_`, opciones y hooks `atproto_comments_*`, metas `_atproto_*`, el slug de la página de ajustes y los handles de scripts.
- **El web component:** paquete npm `atproto-comments`, etiqueta `<atproto-comments>` y `assets/vendor/atproto-comments/`.
- **El repositorio:** `atproto-comments-plugin`.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

Ninguna. Ninguna spec menciona el nombre del plugin, el slug, el text domain ni el fichero principal, y los identificadores que sí aparecen en las specs (metas, opciones, filtros y la etiqueta del componente) no cambian. El cambio declara `skip_specs: true`.

## Impact

- **Código:** las cadenas traducibles de `includes/`, `src/` y `templates/` (unas 127, solo el argumento del text domain), la cabecera del plugin y `Settings::add_page()`.
- **Instalaciones de desarrollo:** el plugin pasa a montarse en `wp-content/plugins/parolas`. Hay que reiniciar wp-env y el plugin se activa con el nuevo nombre.
- **WordPress.org:** el envío en pausa se actualiza con el zip de la v0.1.1 y se pide el slug `parolas` en el hilo de revisión.
- **GitHub:** se publica la Release `v0.1.1`. La Release `v0.1.0` se queda con el nombre antiguo.
