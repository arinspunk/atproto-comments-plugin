# atproto-comments-plugin

[![CI](https://github.com/arinspunk/atproto-comments-plugin/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/arinspunk/atproto-comments-plugin/actions/workflows/ci.yml)

Plugin de WordPress que muestra como comentarios de una entrada las respuestas a un post de AT Protocol (Bluesky), con el web component [`atproto-comments`](https://github.com/arinspunk/atproto-comments).

## Desarrollo

Requisitos: Node 22 (versión en `.nvmrc`; con nvm, `nvm use`) y Docker (para `wp-env`). PHP y Composer se ejecutan dentro de los contenedores.

```bash
npm ci
npm run build            # copia el componente a assets/vendor/ y compila el panel del editor
npx wp-env start         # http://localhost:8888 (admin / password)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/atproto-comments composer install
```

### Combinaciones de WordPress y PHP

`.wp-env.json` fija la combinación mínima: **WordPress 6.5 y PHP 7.4**.

Para la combinación actual (**última versión de WordPress y PHP 8.3**), crea un `.wp-env.override.json` (no se versiona):

```json
{ "core": null, "phpVersion": "8.3" }
```

o, sin tocar archivos, cambia solo PHP con una variable de entorno:

```bash
WP_ENV_PHP_VERSION=8.3 npx wp-env start --update
```

Después de cambiar de combinación, ejecuta `npx wp-env start --update`. Para volver a la mínima, borra el override y repite el comando.

### Calidad

```bash
npm run lint:js
npm run test:js                   # Vitest, en Node
npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs
npm run test:php                  # PHPUnit (suite de WordPress en wp-env)
npm run test:php:multisite        # la misma suite en modo multisite
npm run plugin-check              # devuelve 0 aunque haya errores: revisa la salida
```

## CI

`.github/workflows/ci.yml` se ejecuta en cada push a `main`, en cada pull request contra `main` y a mano desde la pestaña Actions. Tiene cuatro checks, que corren en paralelo:

| Check | Qué hace | Cómo reproducirlo en local |
|---|---|---|
| `js` | `npm ci`, `npm run lint:js`, `npm run test:js`, `npm run build` | Los mismos comandos |
| `phpcs` | `composer install` y `composer phpcs` con PHP 7.4 en el runner | `npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs` |
| `phpunit (minimum)` | WordPress 6.5 + PHP 7.4: `npm run test:php` y `npm run test:php:multisite` | Sin `.wp-env.override.json`: `npx wp-env start --update` y los dos comandos |
| `phpunit (latest)` | Última versión de WordPress + PHP 8.3: lo mismo, y `npm run plugin-check` (falla si hay algún `ERROR`) | Con el override de la combinación actual: `npx wp-env start --update`, los dos comandos y `npm run plugin-check` |

Las acciones están fijadas por SHA; Dependabot (`.github/dependabot.yml`) propone sus actualizaciones cada semana.

## Flujo de trabajo

`main` está protegida por el ruleset versionado en `.github/rulesets/main.json`:

- Todo cambio entra por **pull request** desde una rama, con los cuatro checks en verde y la rama al día con `main`. No hacen falta aprobaciones.
- No se permite push directo, force-push ni borrar `main`.
- El admin del repo puede fusionar una PR **sin** la CI en verde (bypass explícito, queda registrado). Úsalo solo en emergencias, como una CI rota por una caída externa (descargas de wp-env o GitHub Actions), nunca para saltarse un test que falla.

Para cambiar las reglas, edita el JSON en una PR y aplícalo con:

```bash
gh api repos/arinspunk/atproto-comments-plugin/rulesets           # obtiene el id
gh api -X PUT repos/arinspunk/atproto-comments-plugin/rulesets/<id> --input .github/rulesets/main.json
```

Si renombras un job del workflow, actualiza en la misma PR los checks obligatorios del ruleset.

## Licencia

GPLv2 o posterior. El componente incluido en `assets/vendor/` es MIT.
