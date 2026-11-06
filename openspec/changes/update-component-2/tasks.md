# Tasks

## 1. Dependencia y copia del componente

- [ ] 1.1 Subir `atproto-comments` a `2.0.0` en `package.json` (versión exacta, como ahora) con `npm install atproto-comments@2.0.0 --save-exact`. Se verifica con `npm ls atproto-comments` → `2.0.0` y con el `package-lock.json` actualizado.
- [ ] 1.2 `scripts/vendor.mjs`: separar en una función exportada y pura la reescritura de `index.js`. La función sustituye cada `"./element.js"` por `"./element.js?ver=<versión>"` y lanza un error si no hay ninguno o si queda algún otro especificador relativo (decisión 1). El script copia además `dist/element.js` y solo ejecuta la copia cuando se lanza directamente, no al importarlo. Escribir `tests/js/vendor.test.js` con estos casos:
  - el `index.js` de la 2.0.0, copiado como fixture en `tests/fixtures/atproto-comments-2.0.0-index.js`, queda con las dos sentencias apuntando a la misma URL versionada;
  - un `index.js` sin `./element.js` lanza error;
  - un import relativo a otro archivo lanza error.
  Se verifica con `npm run test:js`, `npm run lint:js` y `npm run vendor`, que deja en `assets/vendor/atproto-comments/` `index.js` (con `?ver=2.0.0`), `element.js`, el CSS y `version.php` con `"2.0.0"`.
- [ ] 1.3 `scripts/dist.mjs`: añadir `assets/vendor/atproto-comments/element.js` a `REQUIRED` y su caso en `tests/js/dist.test.js` si los tests comprueban la lista (decisión 2). Se verifica con `npm run test:js` y con `npm run dist && unzip -l dist/parolas-*.zip | grep element.js`.
- [ ] 1.4 `scripts/smoke.sh`: después de `check_asset` de `index.js`, descargar ese `index.js`, extraer la URL de `element.js?ver=…`, resolverla contra la de `index.js` y comprobar que responde 200 (decisión 2). Se verifica con `scripts/smoke.sh latest` en verde. También se comprueba que falla si en `dist/parolas/` se borra `element.js` antes de lanzarlo, y luego se vuelve a generar el zip.

## 2. Cadenas traducibles

- [ ] 2.1 `includes/class-ui-strings.php`: añadir `commentIn`, `replyIn`, `close`, `lastUsed`, `go` y `unknownAuthor` a `SOURCE` y a `all()`, con el texto exacto del diccionario `EN` de la 2.0.0 (`…` como U+2026), comentarios `translators:` y `_x()` con contexto en `close`, `go` y `unknownAuthor`, y cada clave en la misma posición en `SOURCE` y en `all()`. Cambiar `viewMore` a «View more replies» en los dos sitios (decisión 3). En el mismo paso, `tests/php/ui-strings-test.php`:
  - `COMPONENT_KEYS` pasa a las 19 claves de la 2.0.0 sin `pickerNote`, con el comentario apuntando a la 2.0.0, y el recuento a 19;
  - test de que `replyIn` conserva `{name}`;
  - test de que `translated()` incluye `close` y `unknownAuthor` cuando se traducen con `gettext_with_context` (decisión 4).
  Se verifica con `npm run test:php` y `composer phpcs` en wp-env sin errores.
- [ ] 2.2 Regenerar `languages/parolas.pot` con `npm run make-pot`. Se verifica con que el `.pot` contiene las seis cadenas nuevas con sus comentarios y contextos, y «View more replies» en lugar de «View more on Bluesky» (`grep -c` de cada una).

## 3. Versión y documentación

- [ ] 3.1 Versión 0.2.0: `npm version 0.2.0 --no-git-tag-version`, la cabecera `Version` y la constante `ATPROTO_COMMENTS_VERSION` de `parolas.php` y el `Stable tag` de `readme.txt` (decisión 5). Se verifica con `npm run check-version -- v0.2.0`.
- [ ] 3.2 `readme.txt`:
  - entrada `= 0.2.0 =` en el changelog con:
    - el panel nuevo y sus apps, la última app usada primero, los enlaces del autor y de la fecha, las respuestas ocultas y «View more replies»;
    - las correcciones de las 1.1.x a 1.4: sin **Reply** en autores sin DID, funciona con `localStorage` bloqueado y en Firefox anterior a la 125, y nombre accesible en «Other app»;
    - el aviso para CSS y JS de temas: `.atproto-app-popover*` → `.atproto-app-sheet*`, `.atproto-reply__byline` como enlace, la fecha dentro de `a.atproto-reply__permalink` (`.atproto-reply__time::before` → `.atproto-reply__permalink::before`) y sin `aria-expanded`/`popovertarget` en los botones;
  - en la pregunta «Can visitors reply from my site?», que **Comment** y **Reply** abren un panel para elegir la app (Bluesky u otra).
  Revisar que la sección «Privacy» sigue siendo correcta: misma clave de `localStorage`. Se verifica pasando `readme.txt` por el validador de https://wordpress.org/plugins/developers/readme-validator/ sin errores.
- [ ] 3.3 `README.md`: cambiar `dist/atproto-comments-*.zip` y `dist/atproto-comments/` por `dist/parolas-*.zip` y `dist/parolas/`, y añadir a la descripción de `smoke.sh` la comprobación de `element.js`. Se verifica con `grep -n "dist/atproto-comments" README.md` sin resultados.

## 4. Integración

- [ ] 4.1 Ejecutar en local la cadena completa sobre el mismo zip: `npm run check-version -- v0.2.0`, `npm run build`, `npm run dist`, `scripts/smoke.sh 6.5`, `scripts/smoke.sh latest` y `npm run plugin-check` en la última versión, sin filas `ERROR`. Se verifica con todos los comandos en verde.
- [ ] 4.2 Prueba manual en wp-env (última versión) con una entrada vinculada a un hilo con respuestas anidadas. Se verifica comprobando en el navegador:
  - **Comment** y **Reply** abren el panel anclado abajo, con título, cerrar y las ocho apps;
  - Escape, el botón de cerrar y un clic fuera cierran el panel;
  - tras elegir una app, al reabrir sale primera con «Last used»;
  - el autor y la fecha de una respuesta abren la app elegida;
  - en la pestaña Red, `element.js` se pide con `?ver=2.0.0` y una sola vez;
  - con el sitio en `pt_BR`, los textos del panel salen en portugués;
  - con un filtro `gettext` temporal que traduce «Last used», esa clave aparece en el atributo `strings` y en el panel.

## Workflow follow-up

- Trabajar en una rama (`flujo-pr`): abrir la PR, archivar el cambio en la rama y fusionar con la CI en verde.
- Publicar antes la v0.1.1 en WordPress.org (sección 1 de `.ariadne/next-steps.md`): es la versión revisada y la primera del SVN. La 0.2.0 es su primera actualización y no se etiqueta hasta que la v0.1.1 esté en el SVN y la página pública funcione.
- Empujar el tag `v0.2.0` en `main` y comprobar la GitHub Release y la subida al SVN (`tags/0.2.0`, `Stable tag` 0.2.0).
- Valorar si se rehace `screenshot-1.png`: el autor y la fecha ahora son enlaces, y el selector es un panel.
