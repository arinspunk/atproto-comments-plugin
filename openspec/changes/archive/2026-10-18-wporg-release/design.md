# Design

## Context

Motivación y alcance en `proposal.md`. Este cambio no tiene specs (`skip_specs: true`): no cambia el comportamiento del plugin, solo cómo se empaqueta y se publica.

Estado actual que condiciona el diseño:

- La versión vive hoy en **cuatro** sitios, no tres: la cabecera `Version:` y la constante `ATPROTO_COMMENTS_VERSION` de `atproto-comments.php`, `Stable tag` de `readme.txt` y `version` de `package.json` (más su eco en `package-lock.json`, que la repite dos veces: en la raíz y en `packages[""]`). Todos están en `0.1.0`.
- `ci.yml` tiene `concurrency: group: ci-${{ github.ref }}` con `cancel-in-progress`, y sus jobs hacen `actions/checkout` sin `ref`. Un workflow reutilizable hereda el contexto de quien lo llama: con `workflow_dispatch` desde `main`, `github.ref` es `refs/heads/main` y el checkout sacaría `main`, no el tag.
- `10up/action-wordpress-plugin-deploy` calcula la versión con `${GITHUB_REF#refs/tags/}` y le quita la `v` (`deploy.sh`) si no recibe `VERSION`. Si `tags/<VERSION>` ya existe en el SVN, termina sin hacer nada. Instala Subversion si el runner no lo tiene. Lee `ASSETS_DIR` desde `$GITHUB_WORKSPACE`.
- `10up/action-wordpress-plugin-asset-update`, sin `IGNORE_OTHER_FILES: true`, copia todo el repositorio a `trunk` (con `.distignore`) y aborta si cambia algo más que el readme. Como el repositorio no tiene `build/` ni `assets/vendor/`, siempre vería cambios. Con `IGNORE_OTHER_FILES: true` copia solo el readme a `trunk` y a `tags/<Stable tag>` (si existe) y la carpeta de assets.
- `openspec validate` exige `skip_specs: true` en `.openspec.yaml` para aceptar un cambio sin deltas.
- `build/` (wp-scripts) y `assets/vendor/` (web component copiado por `scripts/vendor.mjs`) están en `.gitignore`: solo existen después de `npm ci && npm run build`.
- `composer.json` no tiene dependencias de ejecución (solo `php >=7.4`), y el plugin carga sus clases con su propio `spl_autoload_register`. `vendor/` solo contiene herramientas de desarrollo.
- El repositorio se llama `atproto-comments-plugin`, pero el slug de WordPress.org, la carpeta del plugin y el text domain son `atproto-comments`.
- El nombre visible es **«Comments from atproto»** y el readme incluye un aviso de independencia (rama `rename-and-changelog`). WordPress.org genera el slug a partir del `Plugin Name`, así que con ese nombre propondría `comments-from-atproto`, no `atproto-comments` (decisión 9).
- La CI de pull requests (`ci-checks`) ya pasa PHPCS, PHPUnit, tests JS, build y Plugin Check, y `main` está protegida.
- El SVN de WordPress.org no existe hasta que se aprueba el slug. El primer zip se sube a mano por el formulario.

## Goals / Non-Goals

**Goals:**

- Que el zip de la GitHub Release y lo que llega al SVN sean **exactamente el mismo contenido**, construido una sola vez.
- Que el workflow de release sirva ya para el primer envío manual, antes de que exista el SVN, sin cambiarlo después.
- Que no se pueda publicar una versión con números de versión incoherentes, ni desde un commit que no esté en `main` o no haya pasado la CI.
- Que el zip se pruebe instalado de verdad, no solo el árbol del repositorio.

**Non-Goals:**

- Automatizar el cambio de versión (bump) o el changelog. Se edita a mano en una PR; el workflow solo comprueba la coherencia.
- Que el cambio dependa de los assets de WordPress.org. El banner y las capturas ya están en la rama; el icono, no. Son diseño gráfico, no tooling, y no bloquean nada de este cambio (decisión 7). Lo que falte queda en el follow-up, antes de activar `WPORG_DEPLOY`.
- Gestionar traducciones: las hace translate.wordpress.org con los paquetes de idioma una vez publicado el plugin.
- Publicar versiones beta o RC en WordPress.org.

## Decisions

### 1. Una comprobación de versión en Node, compartida por la CI y la release

Un script `scripts/check-version.mjs` lee los cuatro sitios (cabecera, constante, `Stable tag`, `package.json` y las dos apariciones de `package-lock.json`) y falla si no coinciden. Con un argumento (`v1.2.3`), además exige que coincidan con el tag. En la CI de PR se ejecuta sin argumento, para que una PR de bump a medias falle antes de fusionarse y no al empujar el tag.

- Descartado: comprobarlo con `grep` en el YAML del workflow. Es frágil y no se puede ejecutar en local.
- Descartado: un script de bump que reescriba los cuatro sitios. Es más código que mantener para algo que pasa pocas veces. Se puede añadir más adelante sin tocar este diseño.
- Descartado: comprobar solo los tres sitios que nombra la propuesta. La constante es la que usa el plugin para versionar los assets; si se desincroniza, la caché de los navegadores sirve JS viejo.

### 2. El workflow de release se dispara con tags `v*.*.*` y reutiliza la CI

`release.yml` arranca con `push: tags: ['v*.*.*']` y también con `workflow_dispatch` y un input `tag` (para volver a desplegar un tag ya publicado, por ejemplo tras la aprobación). Los jobs van en cadena:

1. **verify**: resuelve el tag (el del `push`, o el input `tag` con `workflow_dispatch`), hace checkout de ese tag con historial completo, comprueba que su commit es ancestro de `origin/main` (`git merge-base --is-ancestor`) y ejecuta `check-version.mjs` con el tag. Expone tres outputs: `tag` (`v0.1.0`), `version` (`0.1.0`) y `sha` (el commit del tag).
2. **ci**: llama a `ci.yml` como workflow reutilizable (`uses: ./.github/workflows/ci.yml`) con `with: ref: ${{ needs.verify.outputs.sha }}`. Para eso `ci.yml` añade `on: workflow_call` con un input `ref` (opcional), y cada `actions/checkout` usa `ref: ${{ inputs.ref || github.sha }}`. Su `concurrency` pasa a `ci-${{ github.workflow }}-${{ inputs.ref || github.ref }}`: en una llamada reutilizable, `github.workflow` es el del llamador (`Release`), así que no comparte grupo con la CI de `main` y no se cancelan entre sí.
3. **package**: construye el zip (decisión 3).
4. **smoke**: instala el zip en WordPress limpio (decisión 5).
5. **github-release**: crea la GitHub Release del tag y adjunta el zip.
6. **deploy**: sube al SVN (decisión 4).

**Todos los jobs trabajan sobre el mismo commit.** `package`, `smoke` (si necesita el repositorio), `github-release` y `deploy` hacen checkout de `needs.verify.outputs.sha` y usan `needs.verify.outputs.tag`/`version`; ninguno vuelve a leer `github.ref`. Sin esto, un `workflow_dispatch` lanzado desde `main` construiría, publicaría o desplegaría `main` en lugar del tag.

- Descartado: fiarse de que `main` está protegida y no repetir la CI. Un tag puede apuntar a cualquier commit, incluido uno de una rama sin revisar. La comprobación de ancestro más la CI repetida cuestan unos minutos y cierran ese hueco.
- Descartado: publicar la release al fusionar en `main` con detección automática de versión. Mezcla «fusionar» y «publicar», que deben ser decisiones separadas.

### 3. El zip se construye una vez, con `.distignore` y la carpeta `atproto-comments/`

El job **package** ejecuta `npm ci` y `npm run dist`. Ese script hace `npm run build` y después `node scripts/dist.mjs`, que copia el árbol a `dist/atproto-comments/` filtrando con `.distignore` (una copia recursiva propia: `fs.cpSync` no admite copiar dentro del propio origen, y `dist/` está dentro del repositorio) y lo comprime con `zip` como `dist/atproto-comments-X.Y.Z.zip`, con `atproto-comments/` como carpeta raíz. El zip se sube como artefacto del workflow; los jobs siguientes lo descargan y no vuelven a construir.

Sintaxis de `.distignore`, interpretada por `dist.mjs`: una entrada por línea; `#` para comentarios; `*` como comodín dentro de un nombre; una `/` final indica directorio; una `/` inicial ancla la entrada a la raíz del repositorio, y sin ella la entrada se compara con el nombre del fichero o directorio a cualquier profundidad. **Los directorios se anclan siempre** (`/vendor/`, no `vendor/`): sin ancla, `vendor/` excluiría también `assets/vendor/`, que el zip necesita.

`.distignore` excluye: `/dist/`, `/src/`, `/tests/`, `/scripts/`, `/openspec/`, `/node_modules/`, `/vendor/`, `/.github/`, `/.claude/`, `/.ariadne/`, `/.wordpress-org/`, `/.git*`, `/.wp-env*.json`, `/.nvmrc`, `/.distignore`, `/composer.*`, `/package*.json`, `/phpcs.xml.dist`, `/phpunit.xml.dist`, `/eslint.config.cjs`, `/README.md`, y en cualquier profundidad `.DS_Store` y `.phpunit.result.cache`.

- Descartado: `rsync --exclude-from=.distignore`. La política de Claude Code de la organización deniega `rsync` en local, así que el empaquetado solo se podría probar en el runner. Además, su sintaxis sin ancla excluye `assets/vendor/` con el patrón `vendor/`.
- Descartado: `tar --exclude-from`/`-X`. `bsdtar` (macOS) y GNU tar (runner) interpretan los patrones de forma distinta, y el zip local y el de CI podrían no coincidir.

**No se ejecuta `composer install --no-dev` ni se incluye `vendor/`** (esto se aparta de la propuesta). Sin dependencias de ejecución solo generaría un autoloader que nadie carga. Si algún día hay una dependencia de ejecución, se añade el paso y se saca `vendor/` de `.distignore`.

- Descartado: `git archive` con `export-ignore` en `.gitattributes`. Solo incluye ficheros versionados, y `build/` y `assets/vendor/` no lo están.
- Descartado: dejar que `10up/action-wordpress-plugin-deploy` genere el zip (`generate-zip: true`). Ese zip solo existe si hay SVN, así que no sirve para el primer envío. Además, construir por un lado y desplegar por otro abre la puerta a que difieran.
- Descartado: `wp dist-archive`. Necesita WP-CLI en el runner solo para hacer un `rsync` y un `zip`.

### 4. El despliegue al SVN se activa con una variable del repo y usa el zip ya probado

El job **deploy** solo se ejecuta si la variable del repositorio `WPORG_DEPLOY` vale `true`. Mientras no se apruebe el slug, la release termina en la GitHub Release y ese zip es el que se sube a mano por el formulario. Tras la aprobación se activa la variable y se relanza el tag con `workflow_dispatch`.

El job hace checkout de `needs.verify.outputs.sha` (la acción lee `ASSETS_DIR` del workspace), descomprime el artefacto y llama a `10up/action-wordpress-plugin-deploy` con:
- `BUILD_DIR` apuntando a la carpeta descomprimida;
- `SLUG: atproto-comments`;
- `ASSETS_DIR: .wordpress-org`;
- **`VERSION: ${{ needs.verify.outputs.version }}`**, siempre explícito.

Así el SVN recibe el contenido del zip y no un `rsync` nuevo del workspace. `VERSION` es obligatorio en este diseño: sin él, la acción lo calcula a partir de `GITHUB_REF`, y en un `workflow_dispatch` desde `main` (el relanzamiento tras la aprobación) crearía en el SVN el tag `refs/heads/main`. Si `tags/<VERSION>` ya existe, la acción termina sin cambios, así que relanzar un tag ya desplegado es inocuo.

No hace falta un paso para instalar Subversion: la acción lo instala si falta.

Los secrets `SVN_USERNAME`/`SVN_PASSWORD` viven en un *environment* de GitHub `wordpress-org`, que solo usa este job. Las acciones de terceros que reciben secrets se fijan por SHA de commit, no por tag.

- Descartado: dejar el slug por defecto. La acción usa el nombre del repo (`atproto-comments-plugin`), que no es el slug.
- Descartado: secrets a nivel de repositorio. Cualquier workflow los podría leer; el environment los limita al job de deploy y permite exigir aprobación manual si se quiere.
- Descartado: detectar si el SVN existe y saltar el deploy en silencio. Si una release real no llega a WordPress.org por un fallo de red, el workflow saldría en verde igualmente.

### 5. Prueba de humo del zip en WP 6.5 y en la última versión

El job **smoke** es una matriz `core: [6.5, latest]`. En cada caso arranca wp-env con un `.wp-env.override.json` generado que:
- reutiliza la misma clave de `mappings` que `.wp-env.json` (`wp-content/plugins/atproto-comments`) apuntando a `./dist/atproto-comments`, para que se monte el zip y no el repositorio (si se usara otra clave, se montarían los dos);
- fija `core` (`WordPress/WordPress#6.5` o `null`) y `phpVersion` (`7.4` o `8.3`);
- activa `WP_DEBUG_LOG` en `config`.

Después:
1. activa el plugin con WP-CLI;
2. crea con WP-CLI una entrada publicada con `_atproto_thread_uri` canónico y carga su URL con `curl`: la respuesta tiene que incluir `<atproto-comments` y las URLs de `assets/vendor/atproto-comments/index.js` y del CSS, y esos dos ficheros tienen que responder 200;
3. carga la página de ajustes del plugin como administrador (`wp eval` con `do_action( 'admin_menu' )` o una petición con cookie de sesión);
4. falla si `wp-content/debug.log` existe y no está vacío.

Así se ejecuta el código que imprime el componente y carga los assets, que es lo que falla si al zip le falta `assets/vendor/` o `build/`, y los avisos PHP no dependen de que se impriman en la página.

Contra **latest** ejecuta además Plugin Check sobre el plugin instalado desde el zip, ya sin la lista de exclusiones de `npm run plugin-check`. `wp plugin check` sale con código 0 aunque encuentre errores, así que el script lee su salida CSV y falla si hay alguna fila `ERROR`.

Tres detalles para que la prueba sea repetible en local, donde wp-env no empieza de cero como en CI:
- hace `wp-env stop` antes de arrancar, para recrear los contenedores: `npm run dist` sustituye `dist/atproto-comments/`, y unos contenedores en marcha conservarían el montaje de la carpeta borrada;
- vacía las bases de datos con `wp-env reset all` tras arrancar, para no arrastrar datos de otra versión de WordPress;
- guarda el `.wp-env.override.json` del desarrollador y lo restaura al terminar (`trap`), también si falla.

Reparto de verificaciones:

| Qué | WP 6.5 (PHP 7.4) | Última WP |
|---|---|---|
| El zip se instala y se activa sin avisos | ✓ | ✓ |
| Una entrada vinculada imprime el componente y sus assets responden 200, sin entradas en `debug.log` | ✓ | ✓ |
| Plugin Check sobre el contenido del zip | | ✓ |
| Tests PHP/JS (vía `ci.yml` reutilizado) | según la matriz de `ci-checks` | según la matriz de `ci-checks` |

- Descartado: confiar en el Plugin Check de la CI. Se ejecuta sobre el repositorio con exclusiones a mano. Lo que importa es lo que contiene el zip: que no falte `build/` y que no se cuele `src/`.
- Descartado: Plugin Check también en 6.5. El plugin Plugin Check exige versiones recientes de WordPress, y sus reglas siguen al directorio actual, no a la versión mínima.

### 6. Workflow aparte para `readme.txt` y assets, con guarda sobre `Stable tag`

`wporg-assets.yml` se ejecuta en `push` a `main` cuando cambian `readme.txt` o `.wordpress-org/**`, y también con `workflow_dispatch`. Usa `10up/action-wordpress-plugin-asset-update` con `SLUG: atproto-comments`, `ASSETS_DIR: .wordpress-org` e **`IGNORE_OTHER_FILES: true`**, y comparte el environment `wordpress-org` y la misma variable `WPORG_DEPLOY`.

`IGNORE_OTHER_FILES: true` es imprescindible: sin él, la acción copia el repositorio entero a `trunk` y aborta si cambia algo más que el readme, y el repositorio no tiene `build/` ni `assets/vendor/` (ver Context). Con él copia solo `readme.txt` a `trunk` y a `tags/<Stable tag>`, y `.wordpress-org/` a `assets/`.

Antes de subir comprueba que el tag de git `v<Stable tag>` existe. Si no existe, es una PR de bump fusionada pero aún sin publicar: se salta con un aviso, para no enseñar en WordPress.org un readme de una versión que todavía no está en el SVN. La acción por sí sola no lo evita: si no encuentra el tag en el SVN, actualiza igualmente el readme de `trunk`.

- Descartado: actualizar readme y assets solo dentro del workflow de release. Arreglar una errata o subir una captura obligaría a sacar una versión.
- Descartado: dispararlo en cada push a `main`. Sin el filtro por rutas haría commits vacíos al SVN.

### 7. Assets de WordPress.org: el formato fijado, el contenido cuando esté

Este cambio fija dónde y cómo van los assets, y no depende de que estén todos:

- **Nada depende de ellos.** El formulario del primer envío solo recibe el zip, y los assets no van dentro: van a `assets/` del SVN, que existe solo tras la aprobación. La acción de deploy sincroniza `ASSETS_DIR` solo si la carpeta existe (`deploy.sh`), y Plugin Check no los exige.
- **Se suben sin sacar versión.** Lo que falte se añade en una PR a `.wordpress-org/`, que dispara el workflow de assets (decisión 6).
- **La sección `== Screenshots ==` de `readme.txt` va siempre con las capturas.** Sin las imágenes, la ficha mostraría leyendas sueltas.

Estado actual: el banner, las cuatro capturas y la sección `== Screenshots ==` ya están en la rama. Falta el icono.

Formato que espera WordPress.org, documentado en la guía (decisión 8):
- `icon-128x128.png` e `icon-256x256.png` (opcionalmente `icon.svg`, siempre con los PNG de reserva). **Pendiente.**
- `banner-772x250.png` y `banner-1544x500.png`.
- `screenshot-1.png` a `screenshot-4.png`, numeradas en el mismo orden que las leyendas de `== Screenshots ==`: comentarios en el frontend, publicar el post ancla desde el editor, entrada vinculada (abrir, desvincular, borrar) y ajustes. Todas miden 1470 × 922, tomadas con la última versión de WordPress.

Todos en `.wordpress-org/`, que `.distignore` excluye del zip.

- Descartado: esperar a tener todos los assets para cerrar el cambio. Bloquearía el archivado esperando un trabajo de diseño que no afecta a ningún workflow.
- Descartado: icono SVG sin PNG. WordPress.org lo admite, pero exige igualmente los PNG de reserva.

### 8. Guía de release en `README.md`

Una sección «Publicar una versión» en el `README.md` del repo (que no entra en el zip) con:

- los pasos del bump: PR que cambia los cuatro sitios y el changelog de `readme.txt`, fusión y `git tag vX.Y.Z && git push --tags`;
- el primer envío: tag con `WPORG_DEPLOY` sin activar, descargar el zip de la GitHub Release, subirlo en https://wordpress.org/plugins/developers/add/ **pidiendo el slug `atproto-comments`** (y adjuntando el permiso de Bluesky si ya ha llegado, decisión 9), esperar la aprobación, crear el environment y los secrets, activar la variable y relanzar el tag;
- cómo actualizar solo el readme o los assets;
- los nombres y tamaños de los assets de la decisión 7, y que la sección `== Screenshots ==` se añade junto con las capturas.

### 9. Slug `atproto-comments` solicitado en el envío, con plan B «Parolas»

La política de marca de atproto (https://atproto.com/about/trademarks/atproto-trademark-policy) y las normas de WordPress.org tratan distinto el nombre y el slug:

- **Nombre visible:** usar la marca como identificador principal del producto exige permiso (sección 4.4: «Atproto Cloud» lo necesita, «Cloud services for atproto» no). «Comments from atproto» la usa de forma descriptiva. El readme aclara que el plugin es independiente y no está afiliado a AT Protocol ni a Bluesky (sección 6), y los assets no usan logos de AT Protocol ni de Bluesky (secciones 4.6 y 5.6).
- **Slug:** la sección 3.7 permite la marca en nombres de paquete o repositorio que describen compatibilidad sin parecer oficiales. Es el mismo caso que los paquetes npm `atproto-comments` y JSR `@arinspunk/atproto-comments` del web component. Pero la **guía 17 de WordPress.org** no admite un slug que empiece por la marca de otro («we will not permit a slug to begin with another product's term») salvo prueba de titularidad o representación.

Por eso:

1. En el envío se pide expresamente el slug `atproto-comments`, porque WordPress.org propondría `comments-from-atproto` a partir del nombre.
2. El 17 de octubre de 2026 se pidió permiso por escrito a trademarks@atproto.com para usar ese slug. Si la respuesta llega antes del envío, se adjunta como prueba para el revisor. No es un requisito para enviar.
3. Si el revisor rechaza el slug, el plugin pasa a llamarse **Parolas**, con slug y text domain `parolas`, **antes** de la aprobación, cuando aún no hay instalaciones. Ese renombrado va en un cambio propio: afecta al text domain y, si se decide, a los prefijos internos.

El riesgo que queda tras la publicación es bajo: si Bluesky objetara el nombre visible, este se cambia en una versión normal, y el slug está amparado por la sección 3.7.

- Descartado: «AT Protocol Comments» y «ATproto Comments». En los dos la marca es el nombre (sección 4.4), y «ATproto» no es una grafía oficial (AT Protocol, atproto, atprotocol).
- Descartado: renombrar ya a Parolas. Rompe el vínculo con el web component, y el riesgo de esperar es solo una ronda más de revisión.
- Descartado: el slug `parolas-atproto-comments`. Es válido, pero alarga el text domain y no coincide con ningún nombre visible.

## Risks / Trade-offs

- [El revisor de WordPress.org rechaza el slug `atproto-comments` por la guía 17] → Plan B de la decisión 9: renombrar a Parolas (`parolas`) antes de la aprobación, en un cambio propio. La variable `SLUG` de los dos workflows y la carpeta del zip pasan a `parolas`. El coste es una ronda más de revisión, sin usuarios afectados.
- [El slug que asigna WordPress.org no es `atproto-comments` por otro motivo (por ejemplo, porque ya está cogido)] → El slug sale de una sola variable de entorno compartida por los dos workflows, y la carpeta del zip se deriva de ella. Habría que cambiar también el text domain, y eso sí tocaría código: se decidiría en otro cambio si llega a pasar.
- [La revisión de WordPress.org pide cambios en el código] → Se corrigen en PRs normales y se envía un zip nuevo con un tag de parche. El SVN no se toca hasta la aprobación.
- [Un tag empujado por error lanza una release] → La comprobación de ancestro y de versión lo frenan casi siempre. Si llega al SVN, WordPress.org no permite borrar tags: se publica una versión de parche que corrija y se sube `Stable tag`.
- [`10up/*` cambia de comportamiento o se compromete] → Fijadas por SHA. Las actualizaciones se revisan a mano, leyendo su `deploy.sh`: este diseño depende de cómo tratan `VERSION`, `BUILD_DIR` e `IGNORE_OTHER_FILES`.
- [Un relanzamiento con `workflow_dispatch` que use `main` en algún job] → Todos los jobs toman el commit y la versión de los outputs de `verify`; el follow-up lo comprueba con un relanzamiento real desde `main` antes de activar `WPORG_DEPLOY`.
- [La página del plugin en WordPress.org sale sin icono, banner ni capturas] → Solo si se activa `WPORG_DEPLOY` antes de tenerlos. El follow-up pone los assets como requisito previo; si aun así ocurre, se suben después con el workflow de assets, sin sacar versión.
- [La matriz smoke con wp-env alarga la release unos minutos] → Es aceptable para algo que pasa pocas veces, y cubre el fallo más probable: un zip incompleto.
- [Desfase entre `Tested up to` y la última versión de WordPress] → Plugin Check en latest lo avisa. Actualizarlo es una PR que solo toca el readme, y el workflow de assets la publica sin sacar versión.

## Migration Plan

1. Fusionar este cambio sin versión nueva y con `WPORG_DEPLOY` sin definir. Añadir `skip_specs: true` al `.openspec.yaml` del cambio para que `openspec validate` lo acepte sin deltas.
2. Cuando la v1 esté lista (`plugin-scaffold-display`, `publish-from-editor` y `unlink-and-delete` fusionados), empujar `v0.1.0` (o la versión que toque). La release crea la GitHub Release con el zip.
3. Envío manual del zip pidiendo el slug `atproto-comments` (con el permiso de Bluesky si ya ha llegado) y espera de aprobación. Si el revisor rechaza el slug, se aplica el plan B de la decisión 9 antes de seguir.
4. Mientras se espera la aprobación, preparar el icono, el banner y las capturas, y fusionarlos (con la sección `== Screenshots ==`) antes del paso siguiente.
5. Crear el environment `wordpress-org` con los secrets, poner `WPORG_DEPLOY=true` y relanzar el tag con `workflow_dispatch`.
6. Si los assets llegaron a `main` después del tag, lanzar a mano `wporg-assets.yml`: el relanzamiento del tag despliega los assets del commit del tag (decisión 4), no los de `main`.

Marcha atrás: poner `WPORG_DEPLOY` a `false` corta cualquier publicación en el SVN sin tocar los workflows. Una versión mala en WordPress.org se corrige con otra versión: el SVN no admite volver atrás.
