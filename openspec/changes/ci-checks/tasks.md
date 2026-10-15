# Tasks

## 1. Preparación

- [x] 1.1 Crear `.nvmrc` con `22` y verificar que `node -v` con esa versión ejecuta `npm ci && npm run lint:js && npm run test:js && npm run build` sin errores
- [x] 1.2 Añadir el script `plugin-check` a `package.json` con las exclusiones de la skill `wp-env`, actualizar la skill para que use `npm run plugin-check`, y verificar en wp-env (combinación actual) que termina con "No errors found"; comprobar además, añadiendo temporalmente un archivo oculto o un `error_log()` sin justificar, qué código de salida devuelve con errores y anotar en el design si hace falta buscar `ERROR` en la salida

## 2. Workflow

- [x] 2.1 Crear `.github/workflows/ci.yml` con los triggers (`push` y `pull_request` a `main`, `workflow_dispatch`), `concurrency` con `cancel-in-progress`, `permissions: contents: read` y el job `js` (con `name: js`) (`npm ci`, `lint:js`, `test:js`, `build`, Node desde `.nvmrc` con caché de npm); fijar las acciones por SHA con comentario de versión; verificar con `actionlint` (p. ej. `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:latest`) que no hay errores
- [x] 2.2 Añadir el job `phpcs` (`name: phpcs`; `shivammathur/setup-php` con PHP 7.4 y caché de Composer, `composer install`, `composer phpcs`) y verificar con `actionlint`
- [x] 2.3 Añadir el job `phpunit` con la matriz `minimum`/`latest` y `name: phpunit (${{ matrix.env }})` (la entrada `latest` escribe `.wp-env.override.json`), `npm ci`, `npm run build`, `wp-env start`, `composer install` en `tests-cli`, `test:php` y `test:php:multisite`, y Plugin Check solo en `latest` (fallando solo con errores, según lo comprobado en 1.2); verificar con `actionlint` y reproduciendo en local cada entrada de la matriz con los mismos comandos
- [x] 2.4 Crear `.github/dependabot.yml` para `github-actions` con frecuencia semanal y verificar con `actionlint`, o con el validador de esquema de Dependabot, que el archivo es válido

## 3. Documentación

- [x] 3.1 Añadir al `README.md` del repo una sección de CI (qué jobs hay, qué se comprueba en cada combinación y cómo reproducir cada uno en local), la insignia del workflow y el flujo de trabajo (ramas y PR, `main` protegida, cuándo usar el bypass de admin), y actualizar `PROXIMOS-PASOS.md` (decisión de la CI resuelta y archivados por PR); verificar que los comandos documentados coinciden con los del workflow
- [x] 3.2 Quitar la CI de pull requests del alcance de `openspec/changes/wporg-release/proposal.md` (apuntando a `ci-checks`) y verificar con `openspec validate wporg-release` que la propuesta sigue siendo válida, o que el error previo no cambia

## 4. Verificación en GitHub

- [ ] 4.1 Subir los cambios en una rama, abrir una PR contra `main` y verificar que los tres jobs (`js`, `phpcs` y las dos entradas de `phpunit`) terminan en verde y que el total queda por debajo de 10 minutos
- [ ] 4.2 En esa misma PR, empujar un commit temporal que rompa un test de PHP, verificar que solo falla `phpunit` (en las dos entradas) y revertirlo hasta volver a verde

## 5. Protección de `main`

- [ ] 5.1 Crear `.github/rulesets/main.json` (rama por defecto; PR obligatoria con 0 aprobaciones; checks `js`, `phpcs`, `phpunit (minimum)` y `phpunit (latest)` de GitHub Actions con la rama al día; sin force-push ni borrado; bypass del rol admin en modo `pull_request`), subirlo a la PR y verificar que es JSON válido y que los nombres de los checks coinciden exactamente con los que muestra `gh pr checks`
- [ ] 5.2 Aplicar el ruleset con `gh api` y verificar con `gh api repos/arinspunk/atproto-comments-plugin/rules/branches/main` que `main` tiene las reglas, y con `gh pr view --json mergeStateStatus` que la PR solo se puede fusionar con los cuatro checks en verde
- [ ] 5.3 Fusionar la PR con la CI en verde y verificar que `main` contiene el merge y que la CI se ejecuta también en el push resultante a `main`

## Workflow follow-up

- Archivar el cambio con `/opsx:archive ci-checks` en una rama con su PR, ya bajo la protección de `main`.
