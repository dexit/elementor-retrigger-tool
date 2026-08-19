## Elementor Submission Re‑Trigger Tool
Contributors: Custom Extension
Donate link: https://example.com/donate
Tags: elementor, forms, bulk, re‑trigger, cron, logs
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 10.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk re‑trigger Elementor Pro form submissions with a visual queue, edit‑payload modal, auto‑save, full payload logging, cron cleanup, and more.

## Description
This plugin adds a powerful tool to the Elementor admin menu that lets you:
* Select any number of form submissions
* Edit the payload before re‑triggering
* Choose which Elementor actions to run (webhook, email, etc.)
* View a live queue with status icons
* Log every action, success or failure, with full debug info
* Schedule daily cleanup of old logs
* Manual cleanup from the settings page

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
* 10.0.0 – Initial release (full payload logging, cron cleanup, visual queue, modal editing, auto‑save).

## Upgrade Notice
* 10.0.0 – No breaking changes. All data is preserved.

