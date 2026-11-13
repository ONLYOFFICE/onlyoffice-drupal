# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Drupal contrib module `onlyoffice` ("ONLYOFFICE Connector") that opens Media-module files in ONLYOFFICE Docs (Document Server) for viewing and co-editing, plus the bundled submodule `modules/onlyoffice-form` (`onlyoffice_form`) for creating, publishing and collecting PDF forms. ONLYOFFICE Docs is a separate server: the browser loads its editor, Docs calls back into Drupal, and Drupal calls Docs for commands and conversions.

## Branches

- `develop` holds the lowest supported Drupal major version. Support for newer Drupal versions is built in a separate branch, which receives `develop` by merging. `main` holds released code. Each branch's supported core versions are in `core_version_requirement` in its `*.info.yml` files and `drupal/core` in its `composer.json`.
- `develop` deliberately uses the APIs of the newer Drupal versions (hook classes, plugin attributes, newer core APIs with BC fallbacks) so the branches differ only in version constraints. Keep it that way and don't flag it.

## Setup and commands

Clone with `--recurse-submodules`: `assets/document-templates` (blank file templates) is a git submodule and must be present. `composer install` also installs CaptainHook git hooks (Conventional Commits check on `commit-msg`, `composer phpcs` on `pre-commit`).

```bash
npm run lint:css      # stylelint      (lint:css:fix to autofix)
npm run lint:js       # eslint + prettier for JS and YAML (lint:js:fix to autofix)
composer phpcs        # Drupal + DrupalPractice standards
```

CI runs the same checks (`.github/workflows/lint.yml`). Lints don't show that a change works: verify changes on a Drupal site connected to a real ONLYOFFICE Docs instance.

## Structure

- `src/Hook/`, `modules/onlyoffice-form/src/Hook/` — hook implementations as `#[Hook]` methods. The `.module` files hold only `#[LegacyHook]` wrappers that call those methods, plus template preprocess functions. A new hook needs both; logic goes in the method.
- `src/OnlyofficeDocumentHelper.php` — editor config, document key, file helpers.
- `src/OnlyofficeUrlHelper.php` — every URL handed to Docs (signed link keys, internal address rewriting).
- `src/OnlyofficeDocumentService.php` — every request from Drupal to Docs (JWT signing, internal address, certificate option, timeouts).
- `src/OnlyofficeAppConfig.php` — supported formats and which are editable.
- `src/Controller/` — editor page and save-as, callback (saving), download.
- `src/Form/SettingsForm.php` — settings and the connection check on save.
- `modules/onlyoffice-form/` — form media source, form field type/widget/formatter, submission entity, its own callback.

## Rules

- Docs calls Drupal without a session, so the download, callback and empty-file routes are `_access: 'TRUE'`. They are protected by two independent checks: the signed link key in the URL (`OnlyofficeUrlHelper::createLinkToken()`/`decodeLinkToken()`) and the Docs JWT when a secret is configured. Any change to these routes must keep both.
- Saving writes a new File entity and a new media revision; never overwrite the existing file. The document key is derived from the file, so a new file gives a new key.
- Send requests to Docs through `OnlyofficeDocumentService` and build URLs for Docs through `OnlyofficeUrlHelper`, so internal addresses, JWT and certificate settings apply.
- A new setting needs: default in `config/install`, schema in `config/schema`, the field in `SettingsForm`, an update hook in `onlyoffice.install` for existing sites, and the settings table in `README.md`.
- Don't edit files inside `assets/document-templates`; it belongs to the ONLYOFFICE/document-templates repo.
- Add user-visible changes to the top section of `CHANGELOG.md`. Release CI takes the version from the first version number in that file, so a release needs a `## X.Y.Z` heading at the top.
- Commit messages follow Conventional Commits (`feat:`, `fix(form):`, `chore:`, `ci:` …).

## Coding practices

- Before writing new code, check whether Drupal core already provides what you need through a public API or service. If it does, use it and keep the change small. Avoid `@internal` code.
- Write code for the lowest PHP version the current branch supports. Work it out from the oldest Drupal core version in `core_version_requirement` in `onlyoffice.info.yml`. Polyfills that ship with Drupal core can be used.
- Every Drupal API you use must still exist in the newest supported core. An API newer than the oldest supported core is allowed only where the oldest core never reaches it or has a fallback, as the existing code does: `#[Hook]` methods with `#[LegacyHook]` wrappers, `DeprecationHelper::backwardsCompatibleCall()`, `InstallRequirementsInterface` next to `hook_requirements`. Check the Drupal change records for anything recently added or deprecated.
- In controllers, forms and plugins, inject services through `create()`; hook classes get theirs through autowiring. Don't add new `\Drupal::` calls there. The static methods of `OnlyofficeUrlHelper`, `OnlyofficeDocumentHelper` and `OnlyofficeAppConfig` use `\Drupal::` because they are static; don't spread that pattern to new classes.
- Before relying on an ONLYOFFICE Docs API option, event or command, check in the Docs API documentation which Docs version added it. The oldest Docs version the module accepts is checked in `OnlyofficeDocumentService::checkConnection()` and in `templates/onlyoffice-editor.html.twig`.
- Build every editor config with `OnlyofficeDocumentHelper::createEditorConfig()`, which applies the editor settings and signs it.
- Decide what a user may do from media entity access (`access('view')`, `access('update')`, `createAccess()`), and re-check it in the media callback before saving.
- The form submodule uses the main module's helpers, services and `OnlyofficeCallbackTrait` instead of copying them. Put shared ONLYOFFICE logic in the main module.

## Conventions

- Each JS/CSS bundle is a library in the module's `*.libraries.yml`, except Docs' `api.js`: that is the `onlyoffice/onlyoffice.api` library, built from the configured address in `OnlyofficeHooks::libraryInfoBuild()`. Attach libraries with `#attached`. The one exception is the standalone editor page template, which loads `api.js` with its own `<script>` tag.
- Write new JavaScript as vanilla DOM code; the lint rules warn on every jQuery use (older form code still uses it). Attach behaviour through `Drupal.behaviors` with `once()`, as `js/onlyoffice-settings.js` does, and pass data from PHP through `drupalSettings`.
- Give every new PHP, JS and CSS file the GPL license header. Copy it from an existing file of the same type; the PHP and JS/CSS headers differ.
- Translations: each module is its own translation project with its own `translations/` folder. Add every new `t()`, `Drupal.t()` or `|t` string to each `.po` file of its module, translate it yourself, and recompile the `.mo` file next to it; commit both. A human reviewer checks the translations. File names use Drupal langcodes (`zh-hans`).
