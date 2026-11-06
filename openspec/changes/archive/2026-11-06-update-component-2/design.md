# Design

## Context

- `scripts/vendor.mjs` copia `dist/index.js` y `dist/atproto-comments.css` de `node_modules/atproto-comments` a `assets/vendor/atproto-comments/` y escribe la versión en `version.php`. Se ejecuta dentro de `npm run build`. `assets/vendor/` no está en git: se genera en cada build y entra en el zip.
- `Display::enqueue_assets()` encola `index.js` con `wp_enqueue_script_module()` y el CSS con `wp_enqueue_style()`. Ambos llevan `?ver=` con la versión del componente.
- Desde la 1.3.0, `dist/index.js` es un módulo de pocas líneas que importa la clase de `./element.js` y la vuelve a exportar, en dos sentencias con el mismo especificador:
  ```js
  import { AtprotoComments } from "./element.js";
  export * from "./element.js";
  ```
  `element.js` es un archivo nuevo para el plugin y la 1.1.0 no lo tenía.
- `UI_Strings` tiene dos listas que hay que mantener iguales: `SOURCE`, con los textos en inglés idénticos al diccionario `EN` del componente, y `all()`, con las llamadas `__()`/`_x()`. `translated()` envía solo las claves cuya traducción difiere de `SOURCE`.
- Diccionario `EN` de la 2.0.0 (`dist/element.js`), claves nuevas desde la 1.1.0:

  | Clave | Texto | Desde |
  |---|---|---|
  | `go` | `Open` | 1.4.0 |
  | `unknownAuthor` | `Unknown` | 1.4.0 |
  | `commentIn` | `Comment in…` | 2.0.0 |
  | `replyIn` | `Reply to {name} in…` | 2.0.0 |
  | `close` | `Close` | 2.0.0 |
  | `lastUsed` | `Last used` | 2.0.0 |
  | `pickerNote` | (vacío) | 2.0.0 |

  `viewMore` pasó a `View more replies` en la 1.4.0. El `…` es U+2026.

## Goals / Non-Goals

**Goals:**
- Servir la 2.0.0 completa (`index.js` y `element.js`) desde el plugin, sin que una actualización futura pueda mezclar módulos de dos versiones.
- Mantener `SOURCE` idéntico al diccionario `EN`, para que `translated()` siga enviando solo lo traducido.

**Non-Goals:**
- `pickerNote` (una nota propia de cada sitio; si alguien la pide, sería un ajuste o un filtro, no una cadena traducible).
- CSS propio para el panel nuevo: se usa el del componente.
- Cambiar el enlace del editor que abre el post vinculado (filtro `atproto_comments_post_url`).
- Volver a hacer las capturas de `.wordpress-org/`.

## Decisions

### 1. Copiar `element.js` y versionar su importación en `vendor.mjs`

`vendor.mjs` copia también `dist/element.js`. Al copiar `index.js`, sustituye cada `"./element.js"` por `"./element.js?ver=<versión>"`. Si no encuentra ninguno, o si quedan especificadores relativos distintos de ese, falla con un mensaje claro. Así una versión futura que reorganice los archivos no se publica rota sin que nadie se entere. Las dos sentencias deben acabar con el mismo especificador: si fuesen distintos, el navegador cargaría `element.js` dos veces como dos módulos diferentes.

Sin esto, `index.js` lleva `?ver=` pero `element.js` siempre se pide con la misma URL. Si el servidor no manda cabeceras de caché, el navegador puede reutilizar el `element.js` de una versión anterior junto al `index.js` nuevo.

Alternativas descartadas:
- **Copiar `element.js` sin tocar nada.** Funciona en esta subida, porque `element.js` no existía antes, pero deja el problema para la siguiente versión del componente.
- **Reescribir `"./element.js"` como especificador *bare* (`"atproto-comments/element"`), registrarlo con `wp_register_script_module()` y declararlo como dependencia de `atproto-comments`.** WordPress pondría el `?ver=` y añadiría un `<link rel="modulepreload">` para `element.js`, lo que evita la ida y vuelta extra. Se descarta porque los especificadores *bare* necesitan import maps (Safari 16.4) y el componente promete Safari 15.4; WordPress no incluye polyfill. Con el especificador relativo no hace falta import map. Se puede revisar si el componente sube su requisito mínimo.
- **Registrar `element.js` en el import map usando su URL absoluta como ID.** Tiene el mismo requisito de import maps y además depende de que la URL coincida exactamente, así que se rompe con plugins de CDN o con cambios de dominio.
- **Usar `dist/index.umd.js` con `wp_enqueue_script()`.** Cambia el tipo de carga, que hoy es un módulo, y obliga a revisar la estrategia de carga y las dependencias.
- **Empaquetar `index.js` y `element.js` en un solo archivo con esbuild o con wp-scripts.** Añade un paso de build para seis líneas.

### 2. Comprobar `element.js` en `dist.mjs` y en la prueba de humo

`REQUIRED` de `scripts/dist.mjs` añade `assets/vendor/atproto-comments/element.js`, para que el zip no se genere sin él. `scripts/smoke.sh` no puede encontrar `element.js` en el HTML, porque solo aparece dentro de `index.js`. Por eso descarga el `index.js` encolado, extrae el especificador de `element.js` y comprueba que esa URL responde 200.

Alternativa descartada: comprobar solo que el archivo existe en el zip. No detecta un especificador mal reescrito.

### 3. Claves nuevas en `UI_Strings` con comentarios para traductores

Se añaden `commentIn`, `replyIn`, `close`, `lastUsed`, `go` y `unknownAuthor` a `SOURCE` y a `all()`, con `__()` o `_x()` y el dominio `parolas`, en la misma posición en las dos listas: `test_source_matches_untranslated_strings` compara con `assertSame`, que también comprueba el orden. Llevan comentario `translators:`:
- `replyIn`: «{name} is the author's name; keep it as is».
- `go`: es el nombre accesible del botón `→` que abre la app escrita en «Other app».
- `commentIn`, `replyIn`: el título del panel termina en «…».

`close`, `go` y `unknownAuthor` usan `_x()` con contexto («close the app picker», «open the app typed in Other app», «name shown for a reply author with no name or handle»), porque «Close», «Open» y «Unknown» son cadenas muy genéricas que otras partes de WordPress traducen de otra forma. En `unknownAuthor` el contexto además indica que se refiere a una persona, para la concordancia de género en idiomas que la tienen.

`viewMore` cambia su texto fuente en `SOURCE` y en `all()` a «View more replies». La v0.1.1 se publica antes en el SVN, así que puede haber traducciones de «View more on Bluesky» en translate.wordpress.org cuando llegue la 0.2.0. Esas traducciones pasan a obsoletas y la cadena nueva queda pendiente; mientras tanto, si el idioma tiene diccionario en el componente (portugués), se usa ese, y si no, sale en inglés. Es una sola cadena y se acepta.

Alternativas descartadas:
- **Dejar `viewMore` como estaba para no perder sus traducciones.** Diría «Bluesky» aunque el enlace abra Deer o Blacksky, y además siempre se enviaría en `strings`, porque no coincidiría con el texto por defecto del componente. Eso taparía el diccionario portugués del componente.
- **Ofrecer `pickerNote` con un texto vacío.** `__( '' )` no es una cadena válida para traducir.

### 4. Los tests leen las claves del componente como lista fija

`COMPONENT_KEYS` de `tests/php/ui-strings-test.php` pasa a ser la lista de la 2.0.0 sin `pickerNote` (19 claves), con la referencia a `src/i18n.ts` de la 2.0.0. Se mantiene fija en el test, como hasta ahora, en lugar de leerla de `node_modules`. Los tests de PHP corren dentro de wp-env, donde `node_modules` no siempre está montado.

Se añaden casos para el marcador `{name}` de `replyIn` y para que `translated()` incluya las cadenas con contexto nuevas (`close`, `unknownAuthor`).

El test de `vendor.mjs` (JS) usa como fixture una copia del `index.js` de la 2.0.0 en `tests/fixtures/`, no el de `node_modules`, para que los casos no cambien al instalar otra versión. La comprobación sobre el archivo real la hace `npm run vendor`, que falla si no puede reescribirlo.

Alternativa descartada: generar la lista leyendo `dist/types/i18n.d.ts`. Acopla el test de PHP a un archivo del build de JS.

### 5. Versión 0.2.0

La subida trae novedades visibles para el visitante (el panel, las apps nuevas y los enlaces en las respuestas), no solo correcciones. La versión se cambia con `npm version 0.2.0 --no-git-tag-version`, más la cabecera y la constante de `parolas.php` y el `Stable tag`, y se valida con `check-version`.

Alternativa descartada: 0.1.2. Ocultaría que cambia la interfaz del visitante.

### Qué se verifica en cada versión de WordPress

- **WP 6.5 y la última versión**:
  - prueba de humo (`scripts/smoke.sh 6.5` y `latest`), con la carga de `index.js`, `element.js` y el CSS;
  - `npm run test:php` (la CI lo ejecuta en las dos).
- **Solo la última versión**:
  - Plugin Check;
  - la prueba manual en el navegador: panel, apps, «Last used», enlaces del autor y de la fecha, y textos en portugués con `pt_BR`.
  - El componente no depende de la versión de WordPress, solo del navegador. Lo único de WordPress que interviene es `wp_enqueue_script_module()`, que ya cubre la prueba de humo en 6.5.

## Risks / Trade-offs

- **[Riesgo] Una versión futura del componente cambia los archivos que importa `index.js`.** → `vendor.mjs` falla si encuentra especificadores relativos inesperados, así que el build se detiene en vez de publicar un zip roto.
- **[Riesgo] Temas con CSS propio sobre `.atproto-app-popover*`, `span.atproto-reply__byline` o `.atproto-reply__time::before`, o con JS que lea `aria-expanded`/`popovertarget`.** → El plugin no los usa. Se avisa en el changelog de `readme.txt` con los nombres nuevos.
- **[Trade-off] `element.js` se descubre al ejecutar `index.js`, no al leer el HTML.** → Una ida y vuelta más en la primera visita. El `modulepreload` lo evitaría, pero exige import maps (decisión 1). El componente se pinta tras la carga de la página, así que no bloquea nada.
- **[Riesgo] El navegador del visitante no soporta `<dialog>`.** → El componente ya exigía Safari 15.4 o posterior. `<dialog>` está en todos los navegadores desde 2022.
- **[Trade-off] Modificar un archivo de terceros (`index.js`).** → El cambio son dos especificadores, lo hace un script y se puede repetir en cada build. La licencia MIT del componente lo permite y la licencia se mantiene.

## Migration Plan

No hay migración de datos. La preferencia de app del visitante (`localStorage`, `atproto-preferred-app`) tiene el mismo formato en la 2.0.

Publicación: primero la v0.1.1, la versión revisada por WordPress.org, en el SVN (`trunk` y `tags/0.1.1`). La 0.2.0 es su primera actualización: el tag `v0.2.0` en `main`, solo cuando la v0.1.1 ya está en el SVN. Con `WPORG_DEPLOY` activo, el workflow de release lo sube al SVN. Para volver atrás, se publica una 0.2.1 con la dependencia en 1.1.0. WordPress.org no permite retirar una versión, pero se puede volver a poner el `Stable tag` en 0.1.1.
