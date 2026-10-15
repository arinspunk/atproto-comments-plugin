# Próximos pasos: plugin de WordPress atproto-comments

Guía para retomar el trabajo. Estado a 14/10/2026.

## Dónde estamos

- **Web component** (`../atproto-comments`): la **1.1.0** está publicada en npm, JSR y GitHub Releases. Acepta textos traducidos con el atributo/propiedad `strings`, y las fechas salen en el idioma de la página. Es la base del plugin.
- **Este repo**: todavía no tiene código ni ningún commit. Los cuatro cambios de OpenSpec ya están creados en `openspec/changes/`:

| Cambio | Estado |
|---|---|
| `plugin-scaffold-display` | Planificado del todo: proposal, 3 specs, design y 22 tareas. Listo para implementar |
| `publish-from-editor` | Solo proposal |
| `unlink-and-delete` | Solo proposal |
| `wporg-release` | Solo proposal |

## Orden

```
0. Primer commit del repo (openspec/, .claude/, esta guía)
1. plugin-scaffold-display   -> el plugin muestra comentarios pegando el vínculo a mano
2. publish-from-editor       -> publicar el post ancla desde el editor
3. unlink-and-delete         -> desvincular y borrar en Bluesky
4. wporg-release             -> CI, zip, SVN y envío a revisión de la v1 completa
```

`unlink-and-delete` depende de `publish-from-editor`, que pone la cuenta y el cliente XRPC.

## Qué hacer en cada paso

Abre Claude Code **dentro de este repo** (`cd atproto-comments-plugin && claude`).

### 0. Primer commit
Pedir: "haz el primer commit con openspec/, .claude/ y PROXIMOS-PASOS.md y súbelo a main".

### 1. plugin-scaffold-display
```
/opsx:apply plugin-scaffold-display
```
- Necesita Docker en marcha para `wp-env`, que levanta WordPress en local.
- Para probar con un hilo real: `at://did:plc:t3q3ylsnsser3o74xz42sdqk/app.bsky.feed.post/3mwso7oovnp2u`.
- Al terminar: revisar y hacer la PR, y después `/opsx:archive plugin-scaffold-display`.

### 2. publish-from-editor
```
/opsx:continue publish-from-editor     (escribe specs y design; repetir hasta tener tasks)
/opsx:apply publish-from-editor
/opsx:archive publish-from-editor
```
Necesitarás una **app password** de la cuenta de AT Protocol para probar: Ajustes → Privacidad y seguridad → Contraseñas de app, en bsky.app o tu cliente.

### 3. unlink-and-delete
Igual que el paso 2: `/opsx:continue` hasta tener tasks, luego `/opsx:apply` y `/opsx:archive`.

### 4. wporg-release
- `/opsx:continue` → `/opsx:apply` → `/opsx:archive`. Este cambio no cambia el comportamiento del plugin, así que su `.openspec.yaml` lleva `skip_specs: true`.
- La primera subida es manual, en https://wordpress.org/plugins/developers/add/. Tras la aprobación, configura los secrets `SVN_USERNAME` y `SVN_PASSWORD` en el repo.

## Decisiones pendientes

- **CI de pull requests:** ¿se saca de `wporg-release` para tenerlo antes de `publish-from-editor`, que es el primer cambio con credenciales? Hay dos opciones: un cambio pequeño `ci-checks` entre el 1 y el 2, o añadirlo como un grupo de tareas al final de `plugin-scaffold-display`. Si no, se queda en el paso 4.
- **Comprobar en la tarea 5.1 del cambio 1:** en WP 6.5, ¿`PluginDocumentSettingPanel` viene de `@wordpress/editor` o de `@wordpress/edit-post`?

## Decisiones ya tomadas (resumen)

Están completas en los artefactos de `openspec/changes/`.

- Una cuenta de AT Protocol para todo el sitio, con app password cifrada (clave derivada de `AUTH_KEY`). El PDS se resuelve desde el DID, nunca se fija `bsky.social`.
- Publicación manual desde un panel del editor de bloques: texto y botón, solo si la entrada ya está publicada. En el editor clásico solo se puede pegar el vínculo.
- Tras publicar: Desvincular, o Borrar de Bluesky (con confirmación y solo si el DID coincide con la cuenta). Borrar la entrada de WordPress no toca Bluesky.
- Frontend automático, con un ajuste para sustituir los comentarios nativos (por defecto) o ponerlos tras el contenido. Tipos de contenido configurables; por defecto, solo entradas.
- WP 6.5+, PHP 7.4+, el componente se carga como Script Module ESM desde el plugin, sin CDN.
- Al pegar el vínculo se aceptan `at://` y URLs con DID. Las URLs con handle llegan en el paso 2.
- Al desinstalar se borran los ajustes y se conservan los vínculos de las entradas.
- Fuera de la v1: OAuth, cuenta por usuario, bloque Gutenberg, facets (menciones, hashtags) y borrado automático.

## Notas útiles

- **Publicar el web component:** el tag `vX.Y.Z` publica en JSR y en GitHub Releases. **npm se publica a mano**, con `npm run build && npm publish` en `../atproto-comments`.
- **jsDelivr** tarda un rato en sincronizar las versiones nuevas: la primera vez el CSS de la 1.1.0 devolvió 404. Si pasa, prueba unpkg o https://www.jsdelivr.com/tools/purge.
- **Límite de profundidad del componente:** `max-depth`, por defecto 6. A partir de ahí aparece "View more on Bluesky".
