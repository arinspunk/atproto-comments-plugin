---
name: flujo-pr
description: Flujo de ramas, pull requests y CI de este repo, con `main` protegida. Úsala cuando el usuario pida "abre la PR", "sube esto", "mira la CI", "por qué falla la CI", "relanza el job", "fusiona", "empieza un cambio nuevo", o al terminar un cambio de OpenSpec que hay que llevar a `main`.
---

# Ramas, PR y CI

`main` está protegida por un ruleset (`.github/rulesets/main.json`, id `25198336` en GitHub). Qué hace cada check, el ruleset y el bypass están en la sección "CI" y "Flujo de trabajo" del `README.md`; esta skill dice qué hacer en cada caso.

## Reglas que no se negocian

- **Nunca** se trabaja ni se hace push en `main`: GitHub rechaza el push, incluso al admin.
- Todo entra por PR con los cuatro checks en verde (`js`, `phpcs`, `phpunit (minimum)`, `phpunit (latest)`) y la rama al día con `main`.
- **El usuario hace los commits, los push, las PR y las fusiones.** Tú preparas los cambios y le das los comandos exactos; espera a que diga "hecho" o "listo". Puedes ejecutar por tu cuenta lo de solo lectura (`gh pr checks`, `gh run view`) y relanzar un job fallido por red.

## Empezar un cambio

1. Desde `main` actualizada: `git switch main && git pull`.
2. Rama con el nombre del cambio de OpenSpec: `git switch -c <nombre-del-cambio>`.
3. Implementa con `/opsx:apply <nombre-del-cambio>`.

## Llevarlo a `main`

1. Antes de proponer el commit, revisa `git status`: no deben entrar `.wp-env.override.json`, archivos `*.off` ni nada de `node_modules/`, `vendor/`, `build/`, `assets/vendor/` (están en `.gitignore`, pero los renombrados no).
2. Da al usuario los comandos:
   ```bash
   git add -A
   git commit -m "<tipo>(<ámbito>): <resumen>"
   git push -u origin <rama>
   gh pr create --base main --fill
   ```
3. **Archiva en la misma rama, antes de fusionar:** cuando todas las tareas estén hechas y la CI en verde, ejecuta `/opsx:archive <cambio>` y que el usuario suba ese commit a la misma PR. Así cada cambio es una sola PR. (Excepción: un cambio cuya última tarea sea la propia fusión, como `ci-checks`, se archiva en una PR aparte.)
4. Fusión, siempre squash: `gh pr merge <n> --squash --delete-branch`. Después verifica que la CI corre también en el push a `main`: `gh run list --branch main --limit 1`.

## Seguir la CI

- Tras un push, espera unos 15 s antes de `gh pr checks --watch`; si no, dice "no checks reported".
- Para ver por qué falla: `gh run view <run-id> --log-failed` (el id sale en la URL de `gh pr checks` o en `gh run list --branch <rama> --limit 1`).
- Un job dura unos 2 minutos; en paralelo, el total es de unos 2–3 minutos.

### ¿Fallo real o de red?

| Señal | Qué es | Qué hacer |
|---|---|---|
| `phpunit (…)` falla en menos de 1 minuto, con `ETIMEDOUT` o `RequestError` en `wp-env start`, y los avisos "wp-env start failed (attempt 3 of 3)" | Descargas de WordPress, temas o plugins caídas (el workflow ya reintenta 3 veces) | `gh run rerun <run-id> --failed`. No toques código |
| Falla `phpunit` en una sola entrada | Diferencia entre WP 6.5/PHP 7.4 y la última/PHP 8.3 | Reprodúcelo en local con esa combinación (skill `wp-env`) |
| Falla `phpcs` con "Tabs must be used to indent lines" en código pegado desde el chat | El copiado convirtió tabuladores en espacios | `composer phpcbf` en wp-env, o reescribe con tabuladores |
| Falla el paso "Plugin Check" | Hay filas `ERROR` en el informe (el comando devuelve 0; la CI las busca) | `npm run plugin-check` en local con la combinación actual |

## Cambiar la CI o el ruleset

- Los nombres de los jobs son checks obligatorios. Si renombras o añades uno, actualiza `.github/rulesets/main.json` **en la misma PR**, y aplica el ruleset (con confirmación del usuario) antes de fusionar; si no, la PR espera un check que no existe:
  ```bash
  gh api -X PUT repos/arinspunk/atproto-comments-plugin/rulesets/25198336 --input .github/rulesets/main.json
  gh api repos/arinspunk/atproto-comments-plugin/rules/branches/main   # verificar
  ```
- Acciones fijadas por SHA con comentario de versión; Dependabot propone las actualizaciones. Al cambiar una a mano, elige una versión publicada hace más de dos semanas.
- Valida el workflow con `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:1.7.7`.

## Bypass del admin

El admin puede fusionar una PR sin la CI en verde (no hacer push directo). Solo para caídas externas que impiden que la CI termine (GitHub Actions o las descargas de wp-env caídas tras relanzar), **nunca** para saltarse un test que falla. Si el usuario lo pide, recuérdaselo antes de darle el comando (`gh pr merge <n> --squash --admin`).
