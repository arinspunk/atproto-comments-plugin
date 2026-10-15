# Proposal

## Why

Hoy, para mostrar comentarios de AT Protocol en WordPress hay que cargar a mano el web component `atproto-comments` y escribir su etiqueta en cada plantilla. Este primer cambio crea el plugin y cubre el uso básico: el editor pega el vínculo al post de Bluesky en la entrada y los comentarios aparecen solos, en el idioma del sitio. Es la base sobre la que se construirán publicar desde el editor (`publish-from-editor`), desvincular y borrar (`unlink-and-delete`) y el despliegue en WordPress.org (`wporg-release`).

## What Changes

- Nuevo plugin `atproto-comments` (GPLv2+, WP ≥ 6.5, PHP ≥ 7.4) que incluye el `dist/` del componente `atproto-comments@^1.1.0` de npm, sin CDN.
- Página de ajustes: AppView, profundidad máxima, posición (sustituir los comentarios nativos o ir tras el contenido) y tipos de contenido donde se activa (por defecto, solo entradas).
- Meta de entrada `_atproto_thread_uri` editable desde el editor de bloques (panel lateral) y desde el editor clásico (metabox). Acepta un `at://` o una URL de app (bsky.app, mu.social…) con DID y la normaliza a `at://`.
- Inserción automática de `<atproto-comments>` en las entradas vinculadas, con el idioma del sitio (`es_ES` → `es-ES`) y todas las cadenas de la interfaz traducibles con `__()`.
- JS y CSS cargados solo en las páginas que muestran comentarios.
- `readme.txt` en formato WordPress.org, con la sección "External services" y una nota de privacidad.
- Al desinstalar se borran los ajustes y se conservan los vínculos de las entradas.

## Capabilities

### New Capabilities
- `plugin-settings`: ajustes del plugin, sus valores por defecto y validación, y la limpieza al desinstalar.
- `thread-linking`: vincular una entrada con su post ancla de AT Protocol (almacenamiento, entrada manual en ambos editores, validación y normalización). Los cambios posteriores la ampliarán con publicar y desvincular.
- `comments-display`: cuándo, dónde y con qué atributos se muestra el componente en el frontend, incluidos idioma, cadenas y carga de assets.

### Modified Capabilities

## Impact

- Repo nuevo `atproto-comments-plugin`: primer código. Archivo principal del plugin, PHP en `includes/`, JS del editor en `src/`, `readme.txt`, `uninstall.php`, `package.json` y `composer.json`.
- Dependencias: `atproto-comments` (npm, runtime, se copia a `assets/vendor/`), `@wordpress/scripts` (build del panel) y, en desarrollo, `@wordpress/env`, PHPCS + WPCS y PHPUnit.
- Servicio externo: el navegador del visitante consulta el AppView configurado (por defecto `https://public.api.bsky.app`). En este cambio el servidor no hace llamadas externas.
- Datos: opción `atproto_comments_settings` y meta `_atproto_thread_uri`.
