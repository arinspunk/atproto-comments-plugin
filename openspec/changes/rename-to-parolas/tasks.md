# Tasks

## 1. Text domain `parolas`

- [x] 1.1 Cambiar la propiedad `text_domain` de `WordPress.WP.I18n` en `phpcs.xml.dist` a `parolas`, y la descripción del ruleset al nuevo nombre. Cambiar el text domain de las cadenas traducibles de `includes/` y `templates/` y de las dos llamadas a `wp_set_script_translations()`, sin tocar `Settings::PAGE`, los handles, el espacio REST, los IDs HTML ni la clave de cifrado (decisión 1 y 3). Actualizar los filtros `gettext` de `tests/php/ui-strings-test.php` y `tests/php/display-test.php` que comparan el dominio. Se verifica con `composer phpcs` en wp-env sin errores de `WordPress.WP.I18n` y con `npm run test:php` en verde.
- [x] 1.2 Activar en `eslint.config.cjs` la regla `@wordpress/i18n-text-domain` con `allowedTextDomain: 'parolas'` y cambiar el text domain de las llamadas de `@wordpress/i18n` en `src/`, sin tocar el nombre de `registerPlugin()`. Se verifica con `npm run lint:js` sin errores (y comprobando antes del cambio de `src/` que la regla sí marca el dominio antiguo), `npm run test:js` y `npm run build`.
- [x] 1.3 Comprobar que en `includes/`, `src/` y `templates/` `'atproto-comments'` solo queda en los identificadores de la decisión 3. Se verifica con `grep -rn "'atproto-comments'" includes src templates` y revisando cada resultado.

## 2. Fichero principal, cabecera y página de ajustes

- [x] 2.1 `git mv atproto-comments.php parolas.php`. En la cabecera: `Plugin Name: Parolas – Comments with atproto` y `Text Domain: parolas`. Actualizar las referencias: `scripts/check-version.mjs` (lectura y nombres de las fuentes) y `tests/js/check-version.test.js`, `tests/js/dist.test.js`, `tests/php/bootstrap.php` y `WP_UNINSTALL_PLUGIN` en `tests/php/uninstall-test.php` (`parolas/parolas.php`). Se verifica con `npm run check-version`, `npm run test:js` y `npm run test:php`.
- [x] 2.2 Título y entrada de menú de la página de ajustes: «Parolas» (decisión 4). Se verifica cargando la página de ajustes en wp-env y con el título en la prueba de humo (tarea 3.2).

## 3. Entorno, empaquetado y prueba de humo

- [x] 3.1 Montar el plugin en `wp-content/plugins/parolas`: clave de `mappings` y activación de `afterStart` en `.wp-env.json`, `--env-cwd` de `test:php` y `test:php:multisite`, y `make-pot` (`languages/parolas.pot`, `--slug=parolas --domain=parolas`) y `plugin-check` (`wp plugin check parolas`) en `package.json`. Regenerar el `.pot` con `npm run make-pot` y borrar `languages/atproto-comments.pot`. Se verifica con `npx wp-env start --update` (el plugin `parolas` queda activo), `npm run test:php`, `npm run test:php:multisite` y `npm run plugin-check` en la última versión sin filas `ERROR` y sin `textdomain_mismatch`.
- [x] 3.2 `scripts/dist.mjs`: `SLUG = 'parolas'`. `scripts/smoke.sh`: comprobación de `dist/parolas/parolas.php`, la clave de `mappings` `wp-content/plugins/parolas` hacia `./dist/parolas`, `wp plugin activate parolas`, `wp plugin check parolas` y el título «Parolas» de la página de ajustes. Se verifica con `npm run dist && unzip -l dist/parolas-*.zip` (carpeta `parolas/` con `parolas.php`, sin `atproto-comments.php`) y con `scripts/smoke.sh 6.5` y `scripts/smoke.sh latest` en verde.

## 4. Workflows de GitHub (los aplica el usuario)

> La política de Claude Code de la organización no deja a Claude leer `.github/workflows/`. Claude deja los cambios redactados en el scratchpad; el usuario los aplica, ejecuta `actionlint` y marca la tarea.

- [ ] 4.1 `release.yml` y `wporg-assets.yml`: `SLUG: parolas`. `ci.yml`: `--env-cwd=wp-content/plugins/parolas` en el paso de `composer install`. Se verifica con `actionlint` y con la CI de la PR en verde (los cuatro checks de siempre).

## 5. Documentación, versión y configuración de OpenSpec

- [x] 5.1 `readme.txt`: título «Parolas – Comments with atproto», la primera frase de la descripción, el aviso de independencia («Parolas is an independent project…») y las rutas «Settings → Parolas». `README.md`: título, aviso, la fila del fichero principal, las rutas `wp-content/plugins/parolas`, el nombre del zip en «Releasing» y la sección de envío (slug `parolas`). Skill `.claude/skills/wp-env/SKILL.md`: las rutas. `openspec/config.yaml`: text domain `parolas` en `context`. Se verifica con `grep -rn "Comments from atproto\|plugins/atproto-comments\|atproto-comments\.php" --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=openspec .` sin resultados fuera de los cambios archivados, y siguiendo en local los comandos del README que cambian.
- [x] 5.2 Versión 0.1.1 (`npm version 0.1.1 --no-git-tag-version`, cabecera y constante de `parolas.php`, `Stable tag`) y entrada `= 0.1.1 =` en el changelog de `readme.txt` (decisión 6). Se verifica con `npm run check-version -- v0.1.1`.

## 6. Integración

- [ ] 6.1 Ejecutar en local la cadena completa sin publicar sobre el mismo zip: `npm run check-version -- v0.1.1`, `npm run dist`, `scripts/smoke.sh 6.5` y `scripts/smoke.sh latest`. Comprobar a mano en wp-env que el panel «AT Protocol comments» del editor aparece y que la página de ajustes se llama «Parolas». Se verifica con los cuatro comandos en verde y las dos comprobaciones manuales.

## Workflow follow-up

- El usuario rehace el banner y el icono sin «at://» y repite `screenshot-4.png` con el menú «Parolas» (decisión 7). No bloquea el envío; conviene tenerlo antes de activar `WPORG_DEPLOY`.
- Abrir la PR, archivar el cambio en la rama y fusionar con la CI en verde.
- Empujar el tag `v0.1.1` en `main` y comprobar que la release genera la GitHub Release con `parolas-0.1.1.zip`.
- En la página de envío de WordPress.org, subir `parolas-0.1.1.zip` con «Upload updated "…" plugin for review» y responder **en el hilo de revisión** algo breve: el renombrado a «Parolas – Comments with atproto» y la petición explícita de reservar el slug `parolas`.
- Actualizar la memoria del proyecto con el resultado de la revisión.
