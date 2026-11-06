# Proposal

## Why

El plugin incluye el web component `atproto-comments` 1.1.0. El 2026-11-05 salió la 2.0.0, con un selector de app nuevo (un panel `<dialog>` con más apps y la última usada primero) y con el autor y la fecha de cada respuesta enlazados en la app elegida. Ahora que WordPress.org ha aprobado el plugin, esta es su primera actualización: la versión 0.2.0.

Al subir de versión, el componente trae seis claves de interfaz nuevas que el plugin no traduce todavía. Sin ellas, el panel sale en inglés en las webs en otros idiomas aunque exista la traducción del plugin.

## What Changes

- Dependencia `atproto-comments` de `1.1.0` a `2.0.0`, copiada en `assets/vendor/atproto-comments/`. Desde la 1.3.0, `index.js` importa `element.js`, así que el plugin pasa a incluir y servir los dos archivos. Sin `element.js` el componente no carga.
- Para el visitante (lo hace el componente, el plugin no añade código):
  - **Comment** y **Reply** abren un panel anclado abajo con título, botón de cerrar, la lista de apps (Bluesky, Anisota, Blacksky, Deer, Impro, Mu, Northsky, Red Dwarf) y el campo «Other app».
  - La app usada la última vez aparece primero. Se guarda con la misma clave de `localStorage`, así que la elección hecha con la 1.x se conserva.
  - El autor y la fecha de cada respuesta enlazan al perfil y a la respuesta en la app elegida.
  - Las respuestas de cuentas con etiquetas que las ocultan (`!hide`, `porn`…) y las que el autor del hilo ocultó dejan de mostrarse.
  - Las fechas de más de un año salen en el formato del idioma de la página.
  - Correcciones que llegan con las versiones 1.1.x a 1.4: las respuestas de autores sin DID ya no tienen **Reply** ni «View more replies» (abrían `/profile//post/…`), el componente funciona con `localStorage` bloqueado y en Firefox anterior a la 125 (sin `Intl.Segmenter`), y el campo «Other app» tiene nombre accesible.
- Seis claves traducibles nuevas, en el dominio `parolas`: `commentIn`, `replyIn` (con el marcador `{name}`), `close`, `lastUsed`, `go` y `unknownAuthor` (`close`, `go` y `unknownAuthor` con contexto). `pickerNote` (una nota opcional, vacía por defecto) no se ofrece.
- El texto fuente de `viewMore` pasa de «View more on Bluesky» a «View more replies», porque el enlace abre la app que eligió el visitante, que puede no ser Bluesky. Coincide con el texto por defecto del componente.
- **BREAKING (solo para quien personalizó el CSS del tema)**:
  - Desaparecen las clases `.atproto-app-popover*`; las sustituyen `.atproto-app-sheet*`.
  - `.atproto-reply__byline` pasa a ser un enlace (`<a>` en lugar de `<span>`) cuando el autor tiene DID.
  - La fecha va dentro de un enlace nuevo, `a.atproto-reply__permalink`, y el `· ` previo pasa de `.atproto-reply__time::before` a `.atproto-reply__permalink::before`.
  - Los botones que abren el selector ya no tienen `aria-expanded` ni `popovertarget`; el estado abierto es el atributo `open` de `dialog.atproto-app-sheet`.
  - El plugin no tiene CSS ni JS propio sobre ninguno de ellos, así que el aviso va en el changelog.
- Versión del plugin 0.2.0, con entrada en el changelog de `readme.txt`.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `comments-display`:
  - «Cadenas traducibles» pasa a cubrir las claves del componente 2.0 y el nuevo texto de `viewMore`, y corrige el dominio a `parolas`.
  - «Carga de assets» exige que se sirvan desde el plugin todos los módulos que importa el componente, y que una actualización no deje en la caché del navegador ninguno de la versión anterior.

## Impact

- `package.json` y `package-lock.json`: dependencia `atproto-comments` 2.0.0 y versión 0.2.0.
- `scripts/vendor.mjs` (copia `element.js` y lo versiona), `scripts/dist.mjs` (archivos obligatorios del zip) y `scripts/smoke.sh` (comprobación de `element.js`).
- `includes/class-ui-strings.php` y `tests/php/ui-strings-test.php`.
- `languages/parolas.pot`, regenerado.
- `parolas.php` (cabecera y constante de versión) y `readme.txt` (`Stable tag`, changelog y la pregunta sobre cómo responden los visitantes).
- `README.md`: restos del nombre anterior en la sección del zip y de la prueba de humo (`dist/atproto-comments*` → `dist/parolas*`), y la comprobación nueva de `element.js`.
- Publicación: la v0.1.1, la versión revisada por WordPress.org, se sube primero al SVN; la 0.2.0 llega después como su primera actualización.
- Sin cambios en el servidor, en los ajustes ni en los datos guardados. No hay servicios externos nuevos: las apps nuevas son enlaces que abre el visitante, el componente no les hace ninguna petición.
