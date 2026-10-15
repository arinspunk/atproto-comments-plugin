---
name: wp-env
description: Arrancar, parar, cambiar de versión y usar el WordPress local (wp-env + Docker) de este plugin. Úsala cuando el usuario pida "arranca el docker", "levanta WordPress", "para el entorno", "cambia a WP 6.5 / a la última versión", "pasa los tests de PHP", "ejecuta Plugin Check" o algo similar.
---

# Entorno local con wp-env

El entorno es `@wordpress/env` (dependencia de desarrollo en `package.json`), sobre Docker. PHP y Composer **no** están instalados en el host: todo lo de PHP se ejecuta dentro de los contenedores.

## Cómo está montado

- `.wp-env.json` mapea el repo como `wp-content/plugins/atproto-comments`. Los cambios en PHP se ven al recargar; el JS del panel necesita `npm run build` (o `npm start` en modo watch).
- WordPress vive en `~/.wp-env/<hash>/` y la base de datos en volúmenes de Docker: nada de eso está en el repo.
- Dos sitios: desarrollo en http://localhost:8888 y tests en http://localhost:8889. Usuario `admin`, contraseña `password`.
- Contenedores: `cli` (WP-CLI del sitio de desarrollo) y `tests-cli` (PHPUnit). Ambos tienen `composer`.
- `afterStart` activa `atproto-comments` y `plugin-check`, y deja `classic-editor` inactivo.

## Arrancar

1. Comprueba que Docker está en marcha: `docker info --format '{{.ServerVersion}}'`. Si falla, pide al usuario que abra Docker Desktop.
2. Si no existe `node_modules/`, ejecuta `npm ci`. Si no existen `build/` o `assets/vendor/`, ejecuta `npm run build`.
3. `npx wp-env start` (tarda 1–3 minutos; usa un timeout largo).
4. Si no existe `vendor/`: `npx wp-env run tests-cli --env-cwd=wp-content/plugins/atproto-comments composer install`.

Parar: `npx wp-env stop` (conserva los datos). Borrar todo: `npx wp-env destroy` (pide confirmación al usuario antes).

## Combinaciones de WordPress y PHP

- **Mínima** (por defecto, `.wp-env.json`): WP 6.5 + PHP 7.4.
- **Actual**: crea `.wp-env.override.json` (no versionado) con `{ "core": null, "phpVersion": "8.3" }`.
- Para cambiar: crear o borrar el override y ejecutar `npx wp-env start --update`.
- Tras subir de versión, ejecuta `npx wp-env run cli wp core update-db`. Si no, el admin redirige a la pantalla de actualizar la base de datos y los scripts del navegador se quedan esperando.
- Comprueba la combinación con `npx wp-env run cli wp core version` y `npx wp-env run cli php -v`.

## Comandos habituales

| Para | Comando |
|---|---|
| WP-CLI | `npx wp-env run cli wp <comando>` |
| PHPCS | `npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs` |
| PHPUnit | `npm run test:php` |
| PHPUnit multisite | `npm run test:php:multisite` |
| Tests JS (Vitest, en el host) | `npm run test:js` |
| Lint JS | `npm run lint:js` |
| Generar el `.pot` | `npm run make-pot` |

### Plugin Check

Sin exclusiones escanea `node_modules/` y no termina. Usa:

```bash
npx wp-env run cli wp plugin check atproto-comments \
  --exclude-directories=node_modules,vendor,tests,scripts,openspec,.claude \
  --exclude-files=PROXIMOS-PASOS.md,composer.json,composer.lock,package.json,package-lock.json,phpcs.xml.dist,phpunit.xml.dist,.wp-env.json,.wp-env.override.json,eslint.config.cjs,.gitignore,README.md,.DS_Store,.phpunit.result.cache
```

## Trampas conocidas

- **Nunca** `wp plugin uninstall atproto-comments` sin `--skip-delete`: la carpeta del plugin es el repo y la borraría.
- La salida de `wp-env run` incluye líneas `ℹ`/`✔` y retornos de carro (`\r`). Para usar un valor (p. ej. un ID con `--porcelain`), filtra con `tr -d '\r' | grep -v '^ℹ \|^✔ '`.
- Los mu-plugins del sitio de tests (`tests-cli`) se cargan también en PHPUnit: no dejes ahí mu-plugins de prueba.
- Las URLs `/?p=ID` redirigen al enlace permanente: usa `curl -L`.
- `WP_TESTS_MULTISITE=1` lo convierte en constante `tests/php/bootstrap.php`; por eso funciona `npm run test:php:multisite`.
