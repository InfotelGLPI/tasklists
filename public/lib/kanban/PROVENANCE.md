# Provenance of the vendored kanban assets

This directory holds third-party code that was copied into the plugin and then modified.
It is **not** a pristine vendor drop and must never be refreshed by overwriting it with an
upstream release: every file below carries local changes, and `js/Kanban.js` in particular is
the plugin's main HTML sink.

| Path | Origin | State |
|---|---|---|
| `js/Kanban.js` | GLPI core `js/kanban.js`, GLPI 9.5/10 era (`Copyright (C) 2003-2014 by the INDEPNET Development Team`, GPL-2.0-or-later) | Forked, heavily modified. Local edits are marked `//INFOTEL`. No upstream counterpart in GLPI 11, whose kanban was rewritten as the Vue components under `js/src/vue/Kanban/`. |
| `js/kanban-actions.js` | Written for this plugin | Plugin code, not vendored. |
| `js/SearchTokenizer/*` | GLPI core `js/modules/SearchTokenizer/`, same era | Copied so `Kanban.js` could keep classic `<script>` loading instead of the ES module import the core version uses (see the commented-out `import` at the top of `Kanban.js`). |
| `css/kanban.css`, `images/dragger.png` | [Kanban-jQuery](https://github.com/craig-davey96/Kanban-jQuery) (see `README.md` and `LICENSE`) | Modified for the GLPI 11 look. Upstream publishes no version tags; the copy predates the plugin's git history in this repository. |

No upstream version or commit id was recorded when these files were taken, and none can be
recovered after the fact. The copy was moved to `public/lib/` on 2025-05-21 and has been edited
since (last touched 2026-08-23).

## Consequences for maintenance

* `js/Kanban.js` builds card markup by string interpolation. Two contracts are load-bearing and
  are enforced on the PHP side by `src/TaskType.php`:
  * `title_tooltip` is interpolated as `title="${escapeHtml(card['title_tooltip'])}"`, so the
    server must send it as **plain text** - escaping it server-side shows the entities.
  * `content` is interpolated raw into `<div class="kanban-item-content">`, so the server must
    escape it **exactly once**.
* Any change to these files is a change to the plugin, not to a dependency: it has to be
  reviewed like plugin code, and a security audit of the plugin has to cover this directory.
* The licence-headers CI check excludes `public/lib`, so the GLPI plugin header is deliberately
  absent from these files; their own upstream headers are kept instead.
