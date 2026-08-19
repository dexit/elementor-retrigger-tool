## Elementor Submission Re‑Trigger Tool
Contributors: Custom Extension
Donate link: https://example.com/donate
Tags: elementor, forms, bulk, re‑trigger, cron, logs
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 12.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk re‑trigger Elementor Pro form submissions with a visual queue, edit‑payload modal, auto‑save, full payload logging, cron cleanup, and more. **Now supports both Classic and Atomic forms!**

## Description
This plugin adds a powerful tool to the Elementor admin menu that lets you:
* Select any number of form submissions
* Edit the payload before re‑triggering
* Choose which Elementor actions to run (webhook, email, etc.)
* View a live queue with status icons
* Log every action, success or failure, with full debug info
* Schedule daily cleanup of old logs
* Manual cleanup from the settings page
* **NEW: Supports both Elementor Classic Forms and Atomic Forms**
* **NEW: Enhanced logging with execution timing and form type info**

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

**Q: Does this work with Elementor Atomic Forms (new form builder)?**
A: Yes! Version 12.0.0 adds full support for both Classic and Atomic forms. The tool automatically detects the form type and uses the appropriate action runner.

## Changelog
* 12.0.0 – Major: Added support for both Elementor Pro Classic and Atomic forms. Enhanced logging with execution timing, form type detection, and form name. Shows form type badge in Action Settings modal.
* 11.0.1 – Fix: Webhook URL override now works correctly (changed `webhook_url` to `webhooks` to match Elementor Pro).
* 11.0.0 – Added action settings modal, request/response logging, webhook debug capture.
* 10.0.0 – Initial release (full payload logging, cron cleanup, visual queue, modal editing, auto‑save).

## Upgrade Notice
* 12.0.0 – Added Atomic forms support and enhanced logging. No breaking changes.

