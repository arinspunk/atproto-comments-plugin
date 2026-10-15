# Proposal

## Why

Hoy las comprobaciones de calidad (PHPCS, PHPUnit, tests y lint de JS, build, Plugin Check) solo se ejecutan a mano, y en local solo hay una combinación de WordPress y PHP levantada a la vez. Durante `plugin-scaffold-display`, varios fallos solo aparecían en una combinación concreta (el script `react-jsx-runtime` inexistente en WP 6.5, el modo multisite). El siguiente cambio, `publish-from-editor`, es el primero que guarda credenciales y hace llamadas del servidor a la red, así que conviene que cada push y cada pull request pasen las comprobaciones de forma automática antes de empezarlo.

## What Changes

- Workflow de GitHub Actions que se ejecuta en cada push a `main`, en cada pull request contra `main` y a mano (`workflow_dispatch`):
  - **JS:** `npm ci`, `npm run lint:js`, `npm run test:js` y `npm run build`.
  - **PHPCS:** `composer phpcs` con PHP en el runner, sin Docker.
  - **PHPUnit:** con wp-env, en la combinación mínima (WP 6.5 + PHP 7.4) y en la actual (última versión de WP + PHP 8.3), en modo sitio único y en multisite.
  - **Plugin Check:** en la combinación actual, con las mismas exclusiones que se usan en local; falla si hay errores.
- Protección de `main` con un ruleset de GitHub: todo cambio entra por pull request con los jobs de la CI en verde, sin force-push ni borrado. El admin del repo puede saltarse la regla de forma explícita al fusionar una PR, para emergencias. Sin aprobaciones obligatorias (con un único mantenedor no podría aprobar sus propias PR). El ruleset se versiona en el repo.
- Mantenimiento de las versiones de las acciones con Dependabot.
- Versión de Node fijada en el repo (`.nvmrc`), usada por la CI y en local.
- Documentación en el `README.md` del repo: qué comprueba la CI, cómo reproducir cada comprobación en local y el flujo de trabajo con ramas y PR.
- Se saca la CI de pull requests del alcance de `wporg-release`, que se queda con el zip, el SVN y el despliegue.

## Capabilities

### New Capabilities

### Modified Capabilities

Este cambio no altera el comportamiento del plugin: es tooling. Su `.openspec.yaml` declara `skip_specs: true`.

## Impact

- Nuevos: `.github/workflows/ci.yml`, `.github/dependabot.yml`, `.github/rulesets/main.json` y `.nvmrc`.
- **Flujo de trabajo:** deja de poderse hacer push directo a `main`; cada cambio, incluidos los archivados de OpenSpec, va en una rama con su PR.
- `README.md` del repo (sección de CI) y `openspec/changes/wporg-release/proposal.md` (se quita la CI de su alcance).
- Sin cambios en el código del plugin ni en sus dependencias de runtime. Sin secrets: la CI no necesita credenciales.
- Configuración del repo en GitHub (`arinspunk/atproto-comments-plugin`, público): un ruleset sobre `main`.
