# Próximos pasos: plugin de WordPress atproto-comments

Guía para retomar el trabajo. Estado a 15/10/2026.

## Dónde estamos

- **Web component** (`../atproto-comments`): la **1.1.0** está publicada en npm, JSR y GitHub Releases. Acepta textos traducidos con el atributo/propiedad `strings`, y las fechas salen en el idioma de la página. Es la base del plugin.
- **Este repo**: el plugin ya muestra los comentarios pegando el vínculo a mano (`plugin-scaffold-display`, archivado). Las specs de lo construido están en `openspec/specs/`.

| Cambio | Estado |
|---|---|
| `plugin-scaffold-display` | Hecho y archivado |
| `ci-checks` | CI en GitHub Actions y protección de `main` |
| `publish-from-editor` | Solo proposal |
| `unlink-and-delete` | Solo proposal |
| `wporg-release` | Solo proposal |

## Orden

```
1. plugin-scaffold-display   -> hecho
2. ci-checks                 -> CI en cada PR y main protegida
3. publish-from-editor       -> publicar el post ancla desde el editor
4. unlink-and-delete         -> desvincular y borrar en Bluesky
5. wporg-release             -> zip, SVN y envío a revisión de la v1 completa
```

`unlink-and-delete` depende de `publish-from-editor`, que pone la cuenta y el cliente XRPC.

## Cómo se trabaja

- Abre Claude Code **dentro de este repo** (`cd atproto-comments-plugin && claude`).
- `main` está protegida: **todo cambio va en una rama con su PR**, y solo se fusiona con la CI en verde. Eso incluye los archivados de OpenSpec (`/opsx:archive`), que se hacen en la rama del cambio antes de fusionar o en una PR propia.
- Para levantar WordPress en local, pide "arranca el docker" (skill `wp-env`). Necesita Docker en marcha.

## Qué hacer en cada paso

### 2. ci-checks
```
/opsx:apply ci-checks
/opsx:archive ci-checks
```

### 3. publish-from-editor
```
/opsx:continue publish-from-editor     (escribe specs y design; repetir hasta tener tasks)
/opsx:apply publish-from-editor
/opsx:archive publish-from-editor
```
Necesitarás una **app password** de la cuenta de AT Protocol para probar: Ajustes → Privacidad y seguridad → Contraseñas de app, en bsky.app o tu cliente.

### 4. unlink-and-delete
Igual que el paso 3: `/opsx:continue` hasta tener tasks, luego `/opsx:apply` y `/opsx:archive`.

### 5. wporg-release
- `/opsx:continue` → `/opsx:apply` → `/opsx:archive`. Este cambio no cambia el comportamiento del plugin, así que su `.openspec.yaml` lleva `skip_specs: true`.
- La primera subida es manual, en https://wordpress.org/plugins/developers/add/. Tras la aprobación, configura los secrets `SVN_USERNAME` y `SVN_PASSWORD` en el repo.

## Decisiones ya tomadas (resumen)

Están completas en los artefactos de `openspec/changes/`.

- Una cuenta de AT Protocol para todo el sitio, con app password cifrada (clave derivada de `AUTH_KEY`). El PDS se resuelve desde el DID, nunca se fija `bsky.social`.
- Publicación manual desde un panel del editor de bloques: texto y botón, solo si la entrada ya está publicada. En el editor clásico solo se puede pegar el vínculo.
- Tras publicar: Desvincular, o Borrar de Bluesky (con confirmación y solo si el DID coincide con la cuenta). Borrar la entrada de WordPress no toca Bluesky.
- Frontend automático, con un ajuste para sustituir los comentarios nativos (por defecto) o ponerlos tras el contenido. Tipos de contenido configurables; por defecto, solo entradas.
- WP 6.5+, PHP 7.4+, el componente se carga como Script Module ESM desde el plugin, sin CDN.
- Al pegar el vínculo se aceptan `at://` y URLs con DID. Las URLs con handle llegan en el paso 2.
- Al desinstalar se borran los ajustes y se conservan los vínculos de las entradas.
- La CI de pull requests va en su propio cambio (`ci-checks`), antes de `publish-from-editor`, y `main` exige PR con la CI en verde (con bypass del admin al fusionar, para emergencias).
- En WP 6.5, `PluginDocumentSettingPanel` viene de `@wordpress/edit-post`; en 6.6+, de `@wordpress/editor`. El panel usa el primero que exista.
- Fuera de la v1: OAuth, cuenta por usuario, bloque Gutenberg, facets (menciones, hashtags) y borrado automático.

## Notas útiles

- **Publicar el web component:** el tag `vX.Y.Z` publica en JSR y en GitHub Releases. **npm se publica a mano**, con `npm run build && npm publish` en `../atproto-comments`.
- **jsDelivr** tarda un rato en sincronizar las versiones nuevas: la primera vez el CSS de la 1.1.0 devolvió 404. Si pasa, prueba unpkg o https://www.jsdelivr.com/tools/purge.
- **Límite de profundidad del componente:** `max-depth`, por defecto 6. A partir de ahí aparece "View more on Bluesky".
