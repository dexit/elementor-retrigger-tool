## Elementor Submission Re‑Trigger Tool
Contributors: Custom Extension
Donate link: https://example.com/donate
Tags: elementor, forms, bulk, re‑trigger, cron, logs
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 10.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk re‑trigger Elementor Pro form submissions with a visual queue, edit‑payload modal, auto‑save, full payload logging, cron cleanup, and more.

## Description
This plugin adds a powerful, **production-ready** tool to the Elementor admin menu that lets you:
* Select any number of form submissions
* Edit the payload before re‑triggering (with enhanced modal interface)
* Choose which Elementor actions to run (webhook, email, etc.)
* View a live queue with status icons
* **View Request/Response data** in professional CodeMirror editor
* Professional **WP_List_Table** logs with pagination, search, sorting, and filtering
* **Import/Export** settings and logs (JSON/CSV formats)
* Log every action, success or failure, with full debug info
* Schedule daily cleanup of old logs
* Manual cleanup from the settings page
* **Extensible** with comprehensive action and filter hooks
* **Translation-ready** with full i18n support

## Installation
1. Upload the `elementor-retrigger-tool` folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins page.
3. Go to **Elementor → Re‑Trigger Tool** to start using it.

## Frequently Asked Questions
**Q: Does this work with Elementor Free?**  
A: No, it requires Elementor Pro because it uses the Pro form submission API.

**Q: Can I run the tool on a staging site?**  
A: Absolutely. The plugin is safe to use on any WordPress installation that has Elementor Pro.

**Q: How do I change the log retention period?**  
A: Go to **Elementor → Re‑Trigger Tool → Settings** and set the desired number of days.

## Changelog

### 10.1.1 (2025-12-18)
* **Fix:** Added missing endif for class_exists check
* **Enhancement:** WordPress development standards compliance
* **New:** Comprehensive action/filter hooks system (7 actions, 4 filters)
* **New:** Complete developer documentation (HOOKS.md)
* **New:** Translation support with text domain
* **New:** Clean uninstall script with data retention option

### 10.1.0 (2025-12-18)
* **New:** CodeMirror integration for professional JSON viewing
* **New:** Enhanced modals with tabbed interface (Request/Response/Raw)
* **New:** Request/Response data capture and logging
* **New:** Import/Export system (Settings JSON, Logs JSON/CSV)
* **New:** Professional WP_List_Table implementation for logs
* **New:** Database schema v2.0 (request_data, response_data columns)
* **Enhancement:** Two-button detail view (Debug + Request/Response)
* **Enhancement:** Export statistics dashboard
* **Enhancement:** Professional admin UI with tabs

### 10.0.0 (2025-12-17)
* Initial release (full payload logging, cron cleanup, visual queue, modal editing, auto‑save)

## Upgrade Notice

### 10.1.1
* Syntax fix and WordPress standards compliance. Safe upgrade with backward compatibility.

### 10.1.0
* Major feature release! Database will auto-upgrade to v2.0. All existing data is preserved. Adds CodeMirror, Import/Export, and enhanced logging.

### 10.0.0
* No breaking changes. All data is preserved.

