# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Drupal 10 contrib module (`onlyoffice`, "ONLYOFFICE Connector") that opens Media-module files in ONLYOFFICE Docs (Document Server) for viewing/co-editing, plus a bundled submodule `modules/onlyoffice-form` (`onlyoffice_form`) for creating, publishing and collecting PDF forms. Document Server is a separate service that must be able to reach Drupal and vice versa.

## Commands

Lint checks, mirroring `.github/workflows/lint.yml`:

```bash
npm run lint:css        # stylelint "**/*.css"   (lint:css:fix to autofix)
npm run lint:js         # eslint: JS + YAML, Prettier enforced as an error (lint:js:fix to autofix)
composer phpcs          # phpcs --standard=Drupal,DrupalPractice on php,module,inc,install,test,profile,theme,css,info,txt,md,yml
```

Lint a single file: `npx eslint path/to/file.js`, `npx stylelint path/to/file.css`, or `phpcs --standard=Drupal,DrupalPractice path/to/File.php`.

Notes:
- Commit messages follow Conventional Commits (`type: subject`, e.g. `ci:`, `chore:`, `style:`, `fix:`); `captainhook.json` enforces this on `commit-msg` and runs `composer phpcs` on `pre-commit`.
- `.prettierignore` excludes `*.yml`, so YAML is checked by `eslint-plugin-yml` (2-space indent) rather than Prettier. phpcs also checks YAML/CSS/Markdown except `CHANGELOG.md`, `CLAUDE.md` and `README.md`.
- ESLint runs with `eslint-plugin-no-jquery` (all rules) — write vanilla DOM code, not jQuery. `jsdoc/flat/recommended` reports missing or malformed JSDoc as warnings; use `@return`/`@prop` tag names.
- PHP CI target is 8.1.

## Architecture

### Request flow between Drupal and Document Server

1. **Entry points.** `OnlyofficeHooks::entityOperation()` (`hook_entity_operation`) adds "View/Edit in ONLYOFFICE" to media operations (only for `file` and `onlyoffice_pdf_form` media sources with a supported extension). It links to `onlyoffice.editor` (`/admin/media/{media}/onlyoffice-editor`, `OnlyofficeEditorController`), which returns the standalone HTML page `onlyoffice-editor.html.twig`. That template loads Document Server's `api.js` with its own `<script>` tag and creates `DocsAPI.DocEditor` inline. Field formatters (`OnlyofficePreviewFormatter` via `OnlyofficeBaseFormatter`, and `OnlyofficeFormFormatter` in the submodule) embed the editor inside normal pages instead, passing per-editor config via `drupalSettings.onlyofficeData[<editor_id>]`, which `js/onlyoffice-editor.js` turns into `new DocsAPI.DocEditor(...)`.
2. **api.js loading for formatters.** `OnlyofficeHooks::libraryInfoBuild()` (`hook_library_info_build`) dynamically defines the `onlyoffice/onlyoffice.api` library from the configured `doc_server_url` + `/web-apps/apps/api/documents/api.js`; the formatters attach it together with `onlyoffice/onlyoffice.editor`.
3. **Editor config.** `OnlyofficeDocumentHelper::createEditorConfig()` builds the DocsAPI config (signed with the JWT secret when set). The document `key` is `file uuid + base64(changed time)` (`getEditingKey`), so a new saved file version gives a new key.
4. **Signed, unauthenticated URLs.** Document Server calls back without a Drupal session, so `onlyoffice.download` and `onlyoffice.callback` (and `onlyoffice_form.callback`) are `_access: 'TRUE'` routes. Their protection is `OnlyofficeUrlHelper::signLinkParameters()`/`verifyLinkKey()`: parameters are joined with `?` and HMAC-SHA256-signed with the site hash salt + private key into the `{key}` path segment. Callback keys carry the media UUID; download keys carry the file UUID and the user id, and the download controller switches to that user and checks file `download` access. In addition, when `doc_server_jwt` is configured, a JWT is required: callbacks read it from the body `token` or the header, downloads read it from the header only. The header name comes from `$settings['onlyoffice_jwt_header']` (default `Authorization`). Any change to these routes must keep both checks.
5. **Saving.** `OnlyofficeCallbackController` maps Document Server status codes through the `OnlyofficeCallbackStatus` enum, which the form module's callback also uses; on `MustSave`/`Corrupted` it switches the current user to the callback's `actions[0].userid`, checks media `update` access, downloads `body->url`, writes a **new** File entity next to the original, and saves the media as a new revision.

### Hook implementations

Hook logic lives in `src/Hook/OnlyofficeHooks.php` and `modules/onlyoffice-form/src/Hook/OnlyofficeFormHooks.php` as `#[Hook]` methods; both classes are autowired services named by their class in the `*.services.yml` files. For Drupal 10, `onlyoffice.module` and `onlyoffice_form.module` keep `#[LegacyHook]` procedural wrappers that only call `\Drupal::service(<class>::class)-><method>()`. A new hook needs both the method and the wrapper; put the logic in the method only. The same applies to the `onlyoffice_form_file_upload` preprocess: `OnlyofficeFormHooks::preprocessFileUpload()` is registered as the theme hook's `initial preprocess` (Drupal 11.3+), and `template_preprocess_onlyoffice_form_file_upload()` calls it on older versions.

### Formats and templates

`OnlyofficeAppConfig::SUPPORTED_FORMATS` maps extension → document type (`word`/`cell`/`slide`/`pdf`/`diagram`) and marks which formats are editable (`edit => TRUE`: docx, xlsx, pptx, pdf); `getDocumentType()` and `isEditable()` read it. Blank-file templates come from the `assets/document-templates` git submodule (ONLYOFFICE/document-templates, `<lang>/new.<ext>`), resolved by `OnlyofficeDocumentHelper::getNewTemplatePath()`, which keeps its own hard-coded list of template extensions (docx, xlsx, pptx, pdf).

### onlyoffice_form submodule

Depends on `onlyoffice` and reuses its helpers, settings (`onlyoffice.settings`) and `onlyoffice/onlyoffice.editor` library. On install it creates a media type backed by the `onlyoffice_pdf_form` media source plugin and the `onlyoffice_form_submission` content entity (table defined in `hook_schema()` in `onlyoffice_form.install`). It also provides the `onlyoffice_form` field type/widget/formatter for placing forms on content. Its own callback controller (`onlyoffice_form.callback`) is used for filling forms on public pages. It maps statuses through the main module's `OnlyofficeCallbackStatus` enum: it logs `Editing`, saves on force-save, and acknowledges `NotFound`/`MustSave`/`Corrupted`/`Closed` without saving, since a filled form is only kept through "Complete & Submit"; unknown statuses get a 400 "Unknown status". On force-save with `forcesavetype === 3` ("Complete & Submit") it stores the filled PDF under `public://onlyoffice_forms/submissions/<media uuid>/` and creates a submission entity. For anonymous users it also sets a shared-tempstore flag (`submission_<media id>_<doc key>`), which the formatter checks. Admin UI is under `/admin/content/onlyoffice-form`, gated by the `administer onlyoffice forms` permission.

### Translations

`translations/*.po` are shipped with compiled `.mo` files next to them; both are committed. `onlyoffice.info.yml` points Drupal's interface-translation server pattern at these `.po` files.

## Release packaging

The version is the first dotted version number found in `CHANGELOG.md` (keep the newest entry at the top). Pushing a `v*` tag triggers `release.yml`, which uses `git archive` for the repo plus a separate `git archive` of the templates submodule (excluding its `AUTHORS.md`, `LICENSE`, `README.md`), zips them as `onlyoffice-drupal-<version>.zip`, and uses the top CHANGELOG section as release notes. `artifact.yml` builds the same tree (uploaded as a workflow artifact, not zipped) when a PR to `main` is opened or reopened, or on manual dispatch. Files marked `export-ignore` in `.gitattributes` are left out of the package.
