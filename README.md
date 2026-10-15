# atproto-comments-plugin

Plugin de WordPress que muestra como comentarios de una entrada las respuestas a un post de AT Protocol (Bluesky), con el web component [`atproto-comments`](https://github.com/arinspunk/atproto-comments).

## Desarrollo

Requisitos: Node 20+ y Docker (para `wp-env`). PHP y Composer se ejecutan dentro de los contenedores.

```bash
npm ci
npm run build            # copia el componente a assets/vendor/ y compila el panel del editor
npx wp-env start         # http://localhost:8888 (admin / password)
npx wp-env run cli composer install
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
npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs
npm run test:php                  # PHPUnit (suite de WordPress en wp-env)
npm run test:php:multisite        # la misma suite en modo multisite
npm run test:js
npx wp-env run cli wp plugin check atproto-comments
```

## Licencia

GPLv2 o posterior. El componente incluido en `assets/vendor/` es MIT.
