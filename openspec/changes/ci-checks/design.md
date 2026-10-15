# Design

## Context

Las comprobaciones ya existen como scripts y pasan en local (ver la skill `.claude/skills/wp-env/SKILL.md`):

- `npm run lint:js`, `npm run test:js` (Vitest, en Node) y `npm run build`.
- `composer phpcs` (WPCS + PHPCompatibilityWP, `testVersion 7.4-`).
- `npm run test:php` y `npm run test:php:multisite`, que ejecutan PHPUnit en el contenedor `tests-cli` de wp-env.
- `wp plugin check atproto-comments` en el contenedor `cli`, con una lista de exclusiones; sin ella escanea `node_modules/` y no termina.

Restricciones que condicionan el diseño:

- wp-env levanta una sola versión de WordPress y de PHP a la vez. `.wp-env.json` fija la mínima (WP 6.5, PHP 7.4) y la actual se elige con un `.wp-env.override.json` (`{ "core": null, "phpVersion": "8.3" }`), no versionado.
- `composer.json` fija `config.platform.php` a 7.4, así que el `composer.lock` es instalable en PHP 7.4 y en 8.3 (comprobado en local con PHPUnit 9.6).
- Vitest 5 y Vite 8 exigen Node 20.19+ o 22.12+. En local se usa Node 22.
- Arrancar wp-env cuesta entre uno y tres minutos por combinación, sobre todo por las descargas de WordPress, temas y plugins.

## Goals / Non-Goals

**Goals:**
- Que un push o una PR con un fallo en cualquiera de las dos combinaciones quede en rojo, con el job que falla identificable de un vistazo.
- Que la CI ejecute exactamente los mismos comandos que se usan en local, para que un fallo se reproduzca con la skill `wp-env`.
- Tiempo total razonable (objetivo: menos de 10 minutos) ejecutando los jobs en paralelo.

**Non-Goals:**
- Zip, SVN, releases y assets de WordPress.org (`wporg-release`).
- Tests end-to-end en navegador (Playwright). Se verificaron a mano en `plugin-scaffold-display`; automatizarlos sería un cambio aparte.
- Matrices más amplias (PHP 8.0–8.2, versiones intermedias de WP). Los extremos cubren la compatibilidad declarada.
- Aprobaciones obligatorias o `CODEOWNERS`: hay un único mantenedor.

## Decisions

### 1. Un único workflow con tres jobs independientes

`.github/workflows/ci.yml` con los jobs `js`, `phpcs` y `phpunit`. Corren en paralelo y cada uno se ve por separado en la PR.

- **`js`** (Node, sin Docker): `npm ci`, `lint:js`, `test:js`, `build`.
- **`phpcs`** (PHP 7.4 en el runner con `shivammathur/setup-php`, sin Docker): `composer install` y `composer phpcs`. PHP 7.4 porque es la versión del `composer.lock` y PHPCompatibility ya comprueba la compatibilidad hacia arriba.
- **`phpunit`** (Docker con wp-env), con matriz de dos entradas: `minimum` (sin override: WP 6.5 + PHP 7.4) y `latest` (escribe `.wp-env.override.json` con `core: null` y `phpVersion: "8.3"`). Cada entrada: `npm ci`, `npm run build` (el plugin necesita `build/` y `assets/vendor/` para cargar), `wp-env start`, `composer install` en `tests-cli`, `test:php` y `test:php:multisite`.

Cada job lleva un `name` fijo (`js`, `phpcs`, `phpunit (minimum)`, `phpunit (latest)`), porque el ruleset de la decisión 7 exige los checks por nombre y no debe romperse si cambia la estructura interna del workflow.

Alternativas descartadas:
- *Un job por comprobación y combinación (multisite y Plugin Check en jobs propios):* cada job pagaría otro arranque de wp-env, y casi duplicaría el tiempo de máquina sin dar más información.
- *Ejecutar PHPCS dentro de wp-env:* funcionaría, pero obliga a esperar al arranque de Docker para una comprobación que tarda segundos.
- *Workflows separados por tipo:* más archivos que mantener y triggers duplicados, sin ventaja para un repo de este tamaño.

### 2. Qué se verifica contra cada versión

| Comprobación | WP 6.5 + PHP 7.4 | Última WP + PHP 8.3 |
|---|---|---|
| PHPUnit (sitio único y multisite) | ✓ | ✓ |
| Plugin Check | — | ✓ |
| PHPCS | (PHP 7.4 en el runner; no depende de WP) | — |
| Lint, tests JS y build | (Node; no depende de WP ni de PHP) | — |

Plugin Check solo en la última versión porque es la que usa la revisión de WordPress.org, y sus reglas van con la versión del plugin `plugin-check`, no con la de WordPress. Va como paso final de la entrada `latest` de la matriz, para reutilizar su wp-env (`if: matrix.env == 'latest'`).

Alternativa descartada: Plugin Check en las dos combinaciones. Duplicaría el resultado, y las versiones recientes de `plugin-check` pueden dejar de soportar WP 6.5.

### 3. Plugin Check: misma invocación que en local y fallo solo por errores

Se ejecuta con las exclusiones documentadas en la skill `wp-env`, movidas a un script de npm (`npm run plugin-check`) para que la CI y el uso local no diverjan. La CI falla si el informe contiene algún `ERROR`; los `WARNING` se muestran en el log pero no fallan, igual que en el criterio de `plugin-scaffold-display` (se revisan uno a uno en la PR). `wp plugin check` devuelve código de salida 0 aunque encuentre errores (comprobado con plugin-check 2.1.0 y un archivo oculto), así que el paso de la CI guarda la salida y falla si alguna fila tiene la columna de tipo `ERROR`. Si el propio comando falla (p. ej. WP-CLI no encuentra el plugin), su código de salida distinto de cero también hace fallar el paso. Las exclusiones incluyen `.git`: sin ella el escaneo pasa de unos 7 s a más de 2 minutos.

Alternativa descartada: fallar también con avisos. Algunos avisos son informativos o se justifican, y bloquearían PRs sin un cambio real.

### 4. Triggers, concurrencia y permisos

- `push` a `main`, `pull_request` contra `main` y `workflow_dispatch`.
- `concurrency` por rama con `cancel-in-progress: true`, para no acumular ejecuciones al empujar varias veces seguidas.
- `permissions: contents: read` en todo el workflow: la CI no escribe en el repo ni necesita secrets.

Alternativa descartada: ejecutar en todas las ramas en `push`. Con las PRs ya cubiertas, duplicaría ejecuciones.

### 5. Acciones fijadas por SHA y actualizadas con Dependabot

Las acciones de terceros (`actions/checkout`, `actions/setup-node`, `shivammathur/setup-php`) se fijan por SHA completo con un comentario de versión. `.github/dependabot.yml` propone actualizaciones semanales para `github-actions`.

Alternativa descartada: fijar por etiqueta mayor (`@v4`). Es más cómodo, pero una etiqueta puede moverse; con un plugin que va a guardar credenciales, se prefiere la cadena de suministro fijada. Dependabot quita el coste de mantenerla.

Dependabot solo para `github-actions`: las dependencias de npm y Composer van con versión exacta a propósito y se actualizan a mano.

### 6. Node desde `.nvmrc` y cachés

`.nvmrc` con `22`. `actions/setup-node` lo lee (`node-version-file`) con caché de npm. `setup-php` no tiene caché de Composer propia, y `composer install` tarda unos segundos, así que no se añade `actions/cache`. No se cachean las descargas de wp-env: el directorio `~/.wp-env` depende de la ruta del proyecto y de la configuración, y una caché mal invalidada daría falsos verdes.

Alternativa descartada: fijar Node directamente en el workflow. Con `.nvmrc`, la versión local y la de CI salen de un mismo sitio.

### 7. Protección de `main` con un ruleset versionado

Un ruleset de rama sobre `main` (la rama por defecto), con:

- **Pull request obligatoria**, con 0 aprobaciones: con un único mantenedor, exigir una aprobación bloquearía todo, porque GitHub no deja aprobar las PR propias.
- **Checks obligatorios:** `js`, `phpcs`, `phpunit (minimum)` y `phpunit (latest)`, de la integración de GitHub Actions, con la rama al día respecto a `main` antes de fusionar.
- **Sin force-push ni borrado** de `main`.
- **Bypass:** el rol de admin del repo, en modo `pull_request`. El admin puede fusionar una PR sin la CI en verde de forma explícita (queda registrado), pero no puede hacer push directo a `main`. Así la excepción existe para emergencias, como una CI rota por una descarga externa, sin reabrir el atajo del push directo.

El JSON del ruleset se versiona en `.github/rulesets/main.json` y se aplica con `gh api` (`POST`, o `PUT` sobre el id para actualizarlo). Así la configuración se revisa en una PR como el resto, y se puede recrear si se borra.

Orden: el ruleset se aplica después de que la CI haya corrido al menos una vez en la PR de este cambio. Si los checks obligatorios no existen todavía, GitHub bloquea la fusión esperando unos checks que nunca llegan.

Alternativas descartadas:
- *Protección de rama clásica (branch protection):* sigue funcionando, pero los rulesets son el mecanismo actual de GitHub, admiten bypass por rol de forma explícita, se pueden ver de forma pública y se exportan e importan como JSON.
- *Bypass en modo `always`:* permitiría al admin hacer push directo a `main`, que es justo el hábito que se quiere cerrar.
- *Sin bypass:* una caída externa (descargas de wp-env, GitHub Actions) bloquearía cualquier corrección urgente.
- *Configurarlo a mano en la web:* no queda rastro en el repo ni se puede revisar.

## Risks / Trade-offs

- [`plugin-check` y los temas se descargan en su última versión, así que una regla nueva de Plugin Check puede poner la CI en rojo sin tocar el código] → Es lo que interesa saber antes de enviar a WordPress.org. Si bloquea una PR urgente, se puede relanzar con `workflow_dispatch` tras corregir, o fijar temporalmente la versión del zip en `.wp-env.json`.
- [Fallos de red al descargar WordPress o plugins en wp-env (falsos rojos)] → Reintentar el job. Si se repite, añadir un reintento del arranque.
- [El arranque de wp-env alarga cada PR unos minutos] → Jobs en paralelo y cancelación de ejecuciones obsoletas.
- [El nombre de la carpeta del checkout en el runner difiere del local] → El mapeo de `.wp-env.json` monta `.` como `wp-content/plugins/atproto-comments`, independiente del nombre de la carpeta.
- [El ruleset exige checks por nombre: si se renombra un job, la PR queda esperando un check que ya no existe] → Nombres fijos en el workflow, y cualquier cambio de nombre se hace en la misma PR que actualiza `.github/rulesets/main.json`.
- [Cada archivado de OpenSpec y cada cambio menor de documentación también necesita PR y CI] → Es el coste aceptado de no saltarse la CI. El bypass de admin cubre las emergencias.

## Migration Plan

1. La PR de este cambio introduce el workflow; la CI corre por primera vez en ella.
2. Con los cuatro checks en verde, se aplica el ruleset con `gh api` y se fusiona la PR ya bajo la protección.

Para retirarla, se desactiva el ruleset (`enforcement: disabled`) o se borra con `gh api`, y se borra el workflow.
