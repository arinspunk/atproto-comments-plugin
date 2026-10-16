# atproto-comments-plugin

[![CI](https://github.com/arinspunk/atproto-comments-plugin/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/arinspunk/atproto-comments-plugin/actions/workflows/ci.yml)

WordPress plugin that shows the replies to an AT Protocol (Bluesky) post as the comments of a post, using the [`atproto-comments`](https://github.com/arinspunk/atproto-comments) web component.

## Development

Requirements: Node 22 (version in `.nvmrc`; with nvm, `nvm use`) and Docker (for `wp-env`). PHP and Composer run inside the containers.

```bash
npm ci
npm run build            # copies the component to assets/vendor/ and builds the editor panel and the settings script
npx wp-env start         # http://localhost:8888 (admin / password)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/atproto-comments composer install
```

### WordPress and PHP combinations

`.wp-env.json` sets the minimum combination: **WordPress 6.5 and PHP 7.4**.

For the current combination (**latest WordPress and PHP 8.3**), create a `.wp-env.override.json` (not versioned):

```json
{ "core": null, "phpVersion": "8.3" }
```

or, without touching files, change only PHP with an environment variable:

```bash
WP_ENV_PHP_VERSION=8.3 npx wp-env start --update
```

After switching combinations, run `npx wp-env start --update`. To go back to the minimum, delete the override and run the command again.

### Quality

```bash
npm run lint:js
npm run test:js                   # Vitest, in Node
npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs
npm run test:php                  # PHPUnit (WordPress test suite in wp-env)
npm run test:php:multisite        # the same suite as multisite
npm run plugin-check              # exits 0 even with errors: check the output
```

## CI

`.github/workflows/ci.yml` runs on every push to `main`, on every pull request against `main`, and manually from the Actions tab. It has four checks, which run in parallel:

| Check | What it does | How to reproduce it locally |
|---|---|---|
| `js` | `npm ci`, `npm run lint:js`, `npm run test:js`, `npm run build` | The same commands |
| `phpcs` | `composer install` and `composer phpcs` with PHP 7.4 on the runner | `npx wp-env run cli --env-cwd=wp-content/plugins/atproto-comments composer phpcs` |
| `phpunit (minimum)` | WordPress 6.5 + PHP 7.4: `npm run test:php` and `npm run test:php:multisite` | Without `.wp-env.override.json`: `npx wp-env start --update` and both commands |
| `phpunit (latest)` | Latest WordPress + PHP 8.3: the same, plus `npm run plugin-check` (fails on any `ERROR`) | With the current-combination override: `npx wp-env start --update`, both commands and `npm run plugin-check` |

Actions are pinned by SHA; Dependabot (`.github/dependabot.yml`) proposes their updates every week.

## Workflow

`main` is protected by the ruleset versioned in `.github/rulesets/main.json`:

- Every change goes in through a **pull request** from a branch, with the four checks green and the branch up to date with `main`. No approvals are required.
- Direct pushes, force-pushes and deleting `main` are not allowed.
- The repository admin can merge a PR **without** green CI (an explicit bypass, which is logged). Use it only in emergencies, such as CI broken by an external outage (wp-env downloads or GitHub Actions), never to skip a failing test.

To change the rules, edit the JSON in a PR and apply it with:

```bash
gh api repos/arinspunk/atproto-comments-plugin/rulesets           # gets the id
gh api -X PUT repos/arinspunk/atproto-comments-plugin/rulesets/<id> --input .github/rulesets/main.json
```

If you rename a workflow job, update the ruleset's required checks in the same PR.

## License

GPLv2 or later. The component bundled in `assets/vendor/` is MIT.
