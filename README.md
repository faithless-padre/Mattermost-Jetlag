# Mattermost Jetlag — GLPI plugin

GLPI plugin for sending event notifications to Mattermost.  
**Name everywhere:** Mattermost Jetlag. **Plugin key (folder, namespace, tables):** mattermostjetlag.

**Author:** [Faithless Padre](https://github.com/faithless-padre) · **Homepage / docs:** https://github.com/faithless-padre



## Structure

| Directory / file          | Purpose |
|---------------------------|--------|
| `setup.php`               | Plugin init, version, hooks (incl. CSS/JS) |
| `hook.php`                | Install / uninstall |
| `mattermostjetlag.xml`    | Plugin catalog metadata |
| `logo.png`                | Plugin logo (GLPI loads it automatically from plugin root; not set via XML) |
| `front/`                  | CRUD pages (e.g. `*.php`, `*.form.php`) |
| `src/`                    | PHP classes, namespace `GlpiPlugin\Mattermostjetlag` |
| `locales/`                | Gettext translations: `en_GB.po`/`.mo`, `ru_RU.po`/`.mo` (domain: mattermostjetlag) |
| `ajax/`                   | AJAX endpoints |
| `templates/`              | **Twig** templates — use `@mattermostjetlag/` in `TemplateRenderer::display()` |
| `public/css/`             | **CSS** — loaded via `ADD_CSS` (paths relative to `public/`) |
| `public/js/`              | **JavaScript** — loaded via `ADD_JAVASCRIPT` |


## Bootstrap & Twig

- GLPI 11 uses Bootstrap in the core; use Bootstrap classes in your Twig templates and HTML.
- Twig: put templates in `templates/` and render with `TemplateRenderer::getInstance()->display('@mattermostjetlag/your_template.html.twig', $params)`.
- CSS/JS paths in `setup.php` are relative to the plugin **public** directory (e.g. `css/mattermostjetlag.css`, `js/mattermostjetlag.js`).

## Tab separation (config and other multi-tab UIs)

- Each tab has its **own set of files**:
  - **PHP**: logic in a dedicated class (e.g. `src/Config/ConnectivityTab.php`).
  - **Twig**: one template per tab (e.g. `templates/config/connectivity.html.twig`).
  - **CSS**: one file per tab (e.g. `public/css/connectivity.css`), scoped by a root class (e.g. `.mattermostjetlag-config-connectivity`).
  - **JS**: one file per tab (e.g. `public/js/connectivity.js`) for that tab’s behaviour/validation.
- The main entry (e.g. `Config`) only defines tabs and delegates rendering to these tab classes.

## Layout convention

- **No table-based layout** for our own UI: no `<table>`, `<tr>`, `<td>`, `<th>` for structure. Use **div-based layout** (e.g. `.card`, `.card-header`, `.card-body`, flexbox/grid) so the markup stays modern and maintainable.

## Locales

- **en_GB** and **ru_RU** are provided. GLPI loads `.mo` files from `locales/` (domain: mattermostjetlag).
- After editing a `.po` file, recompile: `msgfmt -o locales/ru_RU.mo locales/ru_RU.po` (and similarly for `en_GB`).

## Install

1. Copy or symlink this folder into GLPI `plugins/` as **mattermostjetlag** (folder name must be `mattermostjetlag`).
2. In GLPI: **Setup → Plugins**, install and enable **Mattermost Jetlag**.
3. After renaming from an old `jetlag` install: uninstall the old plugin (removes `glpi_plugin_jetlag_*` tables), then install Mattermost Jetlag (creates `glpi_plugin_mattermostjetlag_*`).

## Troubleshooting

- If the config page or tabs do not load, the environment (e.g. Docker) may not be using these plugin files — fix the volume mount or copy the plugin into the container’s `plugins/` directory.
- Direct tab URL (replace `YOUR_GLPI_BASE` with your GLPI base path):
  ```
  YOUR_GLPI_BASE/ajax/common.tabs.php?_itemtype=GlpiPlugin%5CMattermostjetlag%5CConfig&_glpi_tab=GlpiPlugin%5CMattermostjetlag%5CConfig%242&id=1
  ```


