# Translations

This directory contains language files for the Elementor Re-Trigger Tool plugin.

## Translation Files

### POT Template
- `elementor-retrigger-tool.pot` - Translation template (generated from source code)

### Available Translations
Place your `.mo` and `.po` files here in the format:
- `elementor-retrigger-tool-{locale}.po` - Portable Object file (human-editable)
- `elementor-retrigger-tool-{locale}.mo` - Machine Object file (compiled)

## Generating Translations

### Using WP-CLI:
```bash
wp i18n make-pot . languages/elementor-retrigger-tool.pot
```

### Using POEdit:
1. Open POEdit
2. Create new translation from POT file
3. Translate strings
4. Save as `.po` and `.mo` files

### Online Tools:
- [Loco Translate Plugin](https://wordpress.org/plugins/loco-translate/)
- [Translate WordPress](https://translate.wordpress.org/)

## Text Domain

The text domain for this plugin is: `elementor-retrigger-tool`

## Example Translations

### Spanish (es_ES)
- `elementor-retrigger-tool-es_ES.po`
- `elementor-retrigger-tool-es_ES.mo`

### French (fr_FR)
- `elementor-retrigger-tool-fr_FR.po`
- `elementor-retrigger-tool-fr_FR.mo`

### German (de_DE)
- `elementor-retrigger-tool-de_DE.po`
- `elementor-retrigger-tool-de_DE.mo`

## Contributing Translations

If you'd like to contribute a translation:
1. Generate `.po` file from the `.pot` template
2. Translate all strings
3. Compile to `.mo` file
4. Submit via pull request or email

## Loading Custom Translations

WordPress will automatically load translation files from this directory when placed in the correct format.

For manual loading or custom locations, use the `load_plugin_textdomain` filter.
