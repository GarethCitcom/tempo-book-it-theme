# Tempo Studio Manager template overrides

This directory is the Tempo Studio Manager plugin's template-override location
(`{theme}/dsb/{path}`, resolved by the plugin's `Support\TemplateLoader`).

It is intentionally empty. The plugin owns all booking-shaped UI and its
templates are designed to look right on any theme — overriding them here is a
**last resort only** (see the project responsibility split in CLAUDE.md).
Never restyle `.dsb-*` internals from the theme.

To override a plugin template, mirror its path under this directory, e.g.:

```
plugin:  templates/booking/screens/discover.php
theme:   dsb/booking/screens/discover.php
```
