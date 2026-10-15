# Proposal

## Why

El plugin tiene que llegar a WordPress.org para que cualquiera pueda instalarlo desde el administrador y recibir actualizaciones. El directorio usa SVN, exige un zip con los artefactos construidos (`build/`, `assets/vendor/`) y tiene sus propios assets (banner, icono, capturas). Este cambio automatiza la publicación desde GitHub para que cada release sea reproducible.

Puede prepararse en paralelo, pero la primera subida debería esperar a que `plugin-scaffold-display` esté completo. Lo ideal es esperar también a `publish-from-editor` y `unlink-and-delete`, para que la revisión de WordPress.org vea la v1 entera.

## What Changes

- GitHub Action de release que, al empujar un tag `vX.Y.Z`:
  - comprueba que la versión coincide en la cabecera del plugin, en `Stable tag` de `readme.txt` y en `package.json`;
  - ejecuta `npm ci && npm run build` y `composer install --no-dev`;
  - genera el zip de distribución con `.distignore` (sin `src/`, tests, ni configuración de desarrollo);
  - lo adjunta a la GitHub Release;
  - lo despliega en el SVN de WordPress.org (`10up/action-wordpress-plugin-deploy` con los secrets `SVN_USERNAME`/`SVN_PASSWORD`).
- Workflow separado para actualizar solo `readme.txt` y los assets de WordPress.org sin publicar una versión.
- Assets de WordPress.org en `.wordpress-org/`: icono, banner y capturas del panel y del frontend.
- Guía en el README del repo (no en `readme.txt`) con el proceso de release y el primer envío manual a revisión. La primera versión se sube a mano por el formulario de WordPress.org; el SVN solo existe tras la aprobación.

## Capabilities

### New Capabilities

### Modified Capabilities

Este cambio no altera el comportamiento del plugin: es distribución y tooling. Cuando se desarrolle, su `.openspec.yaml` debería declarar `skip_specs: true`.

## Impact

- `.github/workflows/` (release), `.distignore` y `.wordpress-org/`. La CI de pull requests (PHPCS, PHPUnit, tests JS, build y Plugin Check) ya existe desde `ci-checks`; el workflow de release puede reutilizarla como requisito previo.
- Secrets del repo: `SVN_USERNAME` y `SVN_PASSWORD` de la cuenta de WordPress.org.
- Proceso manual previo: envío a revisión en https://wordpress.org/plugins/developers/add/ y espera de aprobación del slug `atproto-comments`.
