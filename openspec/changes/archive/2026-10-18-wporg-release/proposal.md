# Proposal

## Why

El plugin tiene que llegar a WordPress.org para que cualquiera pueda instalarlo desde el administrador y recibir actualizaciones. El directorio usa SVN, exige un zip con los artefactos construidos (`build/`, `assets/vendor/`) y tiene sus propios assets (banner, icono, capturas). Este cambio automatiza la publicación desde GitHub para que cada release sea reproducible.

Puede prepararse en paralelo, pero la primera subida debería esperar a que `plugin-scaffold-display` esté completo. Lo ideal es esperar también a `publish-from-editor` y `unlink-and-delete`, para que la revisión de WordPress.org vea la v1 entera.

## What Changes

- GitHub Action de release que, al empujar un tag `vX.Y.Z` (o al relanzar un tag a mano):
  - comprueba que el tag está en `main` y que la versión coincide en los cuatro sitios donde vive (cabecera y constante de `atproto-comments.php`, `Stable tag` de `readme.txt` y `package.json`/`package-lock.json`);
  - vuelve a pasar la CI sobre el commit del tag;
  - ejecuta `npm ci && npm run build` y genera el zip de distribución con `.distignore` (sin `src/`, tests, `vendor/` ni configuración de desarrollo; sin `composer install`, porque el plugin no tiene dependencias PHP de ejecución);
  - instala el zip en WordPress 6.5 y en la última versión como prueba de humo;
  - lo adjunta a la GitHub Release;
  - lo despliega en el SVN de WordPress.org (`10up/action-wordpress-plugin-deploy` con los secrets `SVN_USERNAME`/`SVN_PASSWORD`), solo cuando se active tras la aprobación del slug.
- Comprobación de coherencia de versión también en la CI de pull requests.
- Workflow separado para actualizar solo `readme.txt` y los assets de WordPress.org (`.wordpress-org/`) sin publicar una versión.
- Guía en el README del repo (no en `readme.txt`) con el proceso de release, el primer envío manual a revisión y el formato de los assets de WordPress.org. La primera versión se sube a mano por el formulario de WordPress.org; el SVN solo existe tras la aprobación.

Fuera de este cambio: crear el icono, el banner y las capturas. No bloquean nada (no van en el zip ni en el primer envío) y se suben después con el workflow de assets, antes de activar el despliegue al SVN.

## Capabilities

### New Capabilities

### Modified Capabilities

Este cambio no altera el comportamiento del plugin: es distribución y tooling. Su `.openspec.yaml` declara `skip_specs: true`.

## Impact

- `.github/workflows/` (release y assets; `ci.yml` pasa a ser también reutilizable), `scripts/` (comprobación de versión, empaquetado y prueba de humo) y `.distignore`. La CI de pull requests (PHPCS, PHPUnit, tests JS, build y Plugin Check) ya existe desde `ci-checks`; el workflow de release la reutiliza como requisito previo.
- Environment de GitHub `wordpress-org` con los secrets `SVN_USERNAME` y `SVN_PASSWORD` de la cuenta de WordPress.org, y variable de repositorio `WPORG_DEPLOY`.
- Proceso manual previo: envío a revisión en https://wordpress.org/plugins/developers/add/ y espera de aprobación del slug `atproto-comments`.
