# Elementor Pro 4.21 Forms Module Reference

> Technical reference for integrating with Elementor Pro Forms module.
> Used by the Retrigger Tool to replay form submissions.

## Table of Contents
- [Architecture](#architecture)
- [Database Tables](#database-tables)
- [Form Actions](#form-actions)
- [Form Fields](#form-fields)
- [Form Record Class](#form-record-class)
- [Hooks Reference](#hooks-reference)
- [Settings Keys](#settings-keys)

---

## Architecture

```
modules/forms/
├── module.php                    # Main module entry
├── registrars/
│   ├── form-actions-registrar.php
│   └── form-fields-registrar.php
├── classes/
│   ├── action-base.php           # Abstract action class
│   ├── form-record.php           # Submission data container
│   ├── ajax-handler.php          # Form submission handler
│   └── integration-base.php      # Third-party integrations
├── actions/
│   ├── email.php
│   ├── email2.php
│   ├── webhook.php
│   ├── redirect.php
│   ├── mailchimp.php
│   ├── slack.php
│   ├── discord.php
│   └── ...
├── fields/
│   ├── field-base.php
│   ├── upload.php
│   ├── date.php
│   └── ...
├── submissions/
│   ├── component.php
│   ├── actions/
│   │   └── save-to-database.php
│   └── database/
│       ├── query.php
│       ├── migration.php
│       └── repositories/
│           └── form-snapshot-repository.php
└── widgets/
    ├── form.php
    └── login.php
```

## Database Tables

### `{prefix}_e_submissions`

Main submissions table.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `main_meta_id` | bigint(20) | FK to e_submissions_values (primary email field) |
| `post_id` | bigint(20) | Page/post containing the form |
| `referer` | text | Page URL where form was submitted |
| `referer_title` | varchar(300) | Page title |
| `element_id` | varchar(20) | Elementor widget ID |
| `form_name` | varchar(100) | Form display name |
| `campaign_id` | bigint(20) | Reserved |
| `user_id` | bigint(20) | WP user ID (0 if guest) |
| `user_ip` | varchar(46) | Remote IP address |
| `user_agent` | text | Browser user agent |
| `actions_count` | int(11) | Total actions to run |
| `actions_succeeded_count` | int(11) | Actions that completed |
| `status` | varchar(20) | 'new' or 'trash' |
| `is_read` | tinyint(1) | 0 or 1 |
| `meta` | longtext | JSON with edit_post_id |
| `created_at` | datetime | Submission timestamp |
| `updated_at` | datetime | Last update timestamp |

### `{prefix}_e_submissions_values`

Normalized field values.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `submission_id` | bigint(20) | FK to e_submissions |
| `key` | varchar(100) | Field custom_id |
| `value` | longtext | Field value |

### `{prefix}_e_submissions_actions_log`

Action execution log.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `submission_id` | bigint(20) | FK to e_submissions |
| `action_name` | varchar(100) | Action slug |
| `action_label` | varchar(100) | Action display name |
| `status` | varchar(20) | 'success' or 'failed' |
| `log` | text | Error message or null |
| `created_at` | datetime | Execution timestamp |

---

## Form Actions

### Built-in Actions

| Slug | Class | Description |
|------|-------|-------------|
| `email` | `Email` | Send notification email |
| `email2` | `Email2` | Send second email |
| `redirect` | `Redirect` | Redirect after submit |
| `webhook` | `Webhook` | POST to external URL |
| `mailchimp` | `Mailchimp` | Add to Mailchimp list |
| `drip` | `Drip` | Add to Drip |
| `activecampaign` | `Activecampaign` | Add to ActiveCampaign |
| `getresponse` | `Getresponse` | Add to GetResponse |
| `convertkit` | `Convertkit` | Add to ConvertKit |
| `mailerlite` | `Mailerlite` | Add to MailerLite |
| `slack` | `Slack` | Send to Slack channel |
| `discord` | `Discord` | Send to Discord webhook |
| `save-to-database` | `Save_To_Database` | Store in submissions |

### Action Base Class

```php
namespace ElementorPro\Modules\Forms\Classes;

abstract class Action_Base {
    // Required methods
    abstract public function get_name();      // 'webhook'
    abstract public function get_label();     // 'Webhook'
    abstract public function run($record, $ajax_handler);
    abstract public function register_settings_section($form);
    abstract public function on_export($element);

    // Optional
    public function get_id() {
        return $this->get_name();
    }
}
```

### Webhook Action Settings

**Widget Settings Keys:**

| Key | Type | Description |
|-----|------|-------------|
| `webhooks` | string | Webhook URL (supports dynamic tags) |
| `webhooks_advanced_data` | string | 'yes' or 'no' - include detailed payload |

**Payload Formats:**

Simple mode (`webhooks_advanced_data` = 'no'):
```json
{
  "field_id": "value",
  "form_id": "abc123",
  "form_name": "Contact Form"
}
```

Advanced mode (`webhooks_advanced_data` = 'yes'):
```json
{
  "form": {
    "id": "abc123",
    "name": "Contact Form"
  },
  "fields": {
    "email": {
      "id": "email",
      "type": "email",
      "title": "Email",
      "value": "user@example.com",
      "raw_value": "user@example.com"
    }
  },
  "meta": {
    "date": {"title": "Date", "value": "2026-08-19"},
    "time": {"title": "Time", "value": "14:30:00"},
    "page_url": {"title": "Page URL", "value": "https://..."},
    "remote_ip": {"title": "Remote IP", "value": "192.168.1.1"}
  }
}
```

### Email Action Settings

| Key | Type | Default |
|-----|------|---------|
| `email_to` | string | `get_option('admin_email')` |
| `email_subject` | string | "New message from {site_name}" |
| `email_content` | string | `[all-fields]` |
| `email_from` | string | "email@{site_domain}" |
| `email_from_name` | string | `get_bloginfo('name')` |
| `email_reply_to` | string | Field ID |
| `email_to_cc` | string | CC addresses |
| `email_to_bcc` | string | BCC addresses |
| `email_content_type` | string | 'html' or 'plain' |

**Email2** uses same keys with `_2` suffix:
- `email_to_2`, `email_subject_2`, etc.

---

## Form Fields

### Built-in Field Types

| Type | Class | Description |
|------|-------|-------------|
| `text` | - | Text input |
| `email` | - | Email input |
| `textarea` | - | Multi-line text |
| `url` | - | URL input |
| `tel` | `Tel` | Phone input |
| `number` | `Number` | Numeric input |
| `date` | `Date` | Date picker |
| `time` | `Time` | Time picker |
| `select` | - | Dropdown |
| `radio` | - | Radio buttons |
| `checkbox` | - | Checkboxes |
| `acceptance` | `Acceptance` | Terms checkbox |
| `upload` | `Upload` | File upload |
| `hidden` | - | Hidden field |
| `step` | `Step` | Multi-step divider |
| `recaptcha` | - | reCAPTCHA v2 |
| `recaptcha_v3` | - | reCAPTCHA v3 |
| `honeypot` | - | Spam honeypot |
| `html` | - | Custom HTML |

### Upload Field Settings

| Key | Type | Description |
|-----|------|-------------|
| `attachment_type` | string | 'link', 'attach', or 'both' |
| `file_sizes` | int | Max file size in MB |
| `file_types` | string | Comma-separated extensions |
| `allow_multiple_upload` | string | 'yes' or '' |
| `max_files` | int | Max files if multiple |

**Attachment Types:**
- `link` - Store file, send URL in email
- `attach` - Attach to email, delete after send
- `both` - Store file AND attach to email

**Default Allowed Types:**
```
jpg,jpeg,png,gif,pdf,doc,docx,ppt,pptx,odt,avi,ogg,m4a,mov,mp3,mp4,mpg,wav,wmv
```

**Blacklisted Extensions:**
```
php,php3,php4,php5,php6,php7,phps,phtml,shtml,pht,swf,html,asp,aspx,
cmd,csh,bat,htm,hta,jar,exe,com,js,lnk,htaccess,htpasswd,ps1,ps2,
py,rb,tmp,cgi,svg,php2,phtm,phar,hphp,phpt,svgz
```

**Upload Path:**
```
wp-content/uploads/elementor/forms/{uniqid}.{ext}
```

---

## Form Record Class

### Properties

```php
class Form_Record {
    protected $sent_data;      // Raw $_POST data
    protected $fields;         // Processed field array
    protected $form_type;      // 'form' or 'login'
    protected $form_settings;  // Widget settings
    protected $files = [];     // Upload files
    protected $meta = [];      // Form metadata
}
```

### Field Array Structure

```php
$fields[$custom_id] = [
    'id'         => 'email',           // Field custom_id
    'type'       => 'email',           // Field type
    'title'      => 'Email Address',   // Label
    'value'      => 'user@example.com', // Sanitized value
    'raw_value'  => 'user@example.com', // Original value
    'required'   => true,

    // Upload fields only:
    'file_sizes'      => 5,
    'file_types'      => 'jpg,png,pdf',
    'max_files'       => 3,
    'attachment_type' => 'link'
];
```

### Files Array Structure

```php
$files[$field_id] = [
    'url'  => ['https://site.com/uploads/file1.jpg', 'https://...'],
    'path' => ['/var/www/html/wp-content/uploads/elementor/forms/file1.jpg', '...']
];
```

### Key Methods

```php
// Get formatted data for webhook/email
$record->get_formatted_data($with_meta = false);
// Returns: ['Email' => 'user@example.com', 'Name' => 'John']

// Get widget setting
$record->get_form_settings('webhooks');

// Get raw property
$record->get('fields');
$record->get('files');
$record->get('meta');
$record->get('form_settings');

// Replace shortcodes
$record->replace_setting_shortcodes('Hello [field id="name"]');
// Returns: 'Hello John'

// Get specific field(s)
$record->get_field(['type' => 'email']);

// Update field value
$record->update_field('email', 'value', 'new@email.com');

// Add uploaded file
$record->add_file('upload_field', 0, ['path' => '...', 'url' => '...']);
```

---

## Hooks Reference

### Validation Hooks

```php
// Bypass all validation
add_filter('elementor_pro/forms/validation/skip_all', function($skip, $record, $ajax_handler) {
    return false;
}, 10, 3);

// Per-field-type validation
add_action('elementor_pro/forms/validation/{field_type}', function($field, $record, $ajax_handler) {
    if (/* invalid */) {
        $ajax_handler->add_error($field['id'], 'Error message');
    }
}, 10, 3);

// Global validation
add_action('elementor_pro/forms/validation', function($record, $ajax_handler) {
    // Validate entire form
}, 10, 2);
```

### Processing Hooks

```php
// Per-field-type processing
add_action('elementor_pro/forms/process/{field_type}', function($field, $record, $ajax_handler) {
    // Process field before actions run
}, 10, 3);

// Global processing
add_action('elementor_pro/forms/process', function($record, $ajax_handler) {
    // Process all fields
}, 10, 2);
```

### Action Hooks

```php
// Register custom action
add_action('elementor_pro/forms/actions/register', function($registrar) {
    $registrar->register(new My_Custom_Action());
});

// After action runs
add_action('elementor_pro/forms/actions/after_run', function($action, $exception) {
    // $exception is null on success
}, 10, 2);

// Webhook-specific
add_filter('elementor_pro/forms/webhooks/request_args', function($args, $record) {
    $args['headers']['X-Custom'] = 'value';
    return $args;
}, 10, 2);

add_action('elementor_pro/forms/webhooks/response', function($response, $record) {
    // Handle webhook response
}, 10, 2);
```

### Sanitization Hooks

```php
// Custom field type sanitization
add_filter('elementor_pro/forms/sanitize/{field_type}', function($value, $field) {
    return sanitize_text_field($value);
}, 10, 2);
```

### Upload Hooks

```php
// Change upload directory
add_filter('elementor_pro/forms/upload_path', function($path) {
    return $path . '/custom';
});

// Change upload URL
add_filter('elementor_pro/forms/upload_url', function($url, $filename) {
    return $url;
}, 10, 2);

// Modify blacklist
add_filter('elementor_pro/forms/filetypes/blacklist', function($blacklist) {
    $blacklist[] = 'xml';
    return $blacklist;
});
```

---

## Settings Keys

### Complete Widget Settings Map

| Key | Action | Description |
|-----|--------|-------------|
| `submit_actions` | - | Array of enabled action slugs |
| `form_name` | - | Form display name |
| `form_fields` | - | Array of field configurations |
| `webhooks` | webhook | Webhook URL |
| `webhooks_advanced_data` | webhook | Advanced payload mode |
| `email_to` | email | To address(es) |
| `email_subject` | email | Subject line |
| `email_content` | email | Message body |
| `email_from` | email | From address |
| `email_from_name` | email | From name |
| `email_reply_to` | email | Reply-to field ID |
| `email_to_cc` | email | CC address(es) |
| `email_to_bcc` | email | BCC address(es) |
| `email_content_type` | email | 'html' or 'plain' |
| `email_to_2` | email2 | Second email to |
| `email_subject_2` | email2 | Second email subject |
| ... | email2 | (Same pattern with _2 suffix) |
| `redirect_to` | redirect | Redirect URL |
| `form_metadata` | email | Metadata to include |
| `submissions_metadata` | save-to-database | Metadata to store |

### Querying Submissions

```php
use ElementorPro\Modules\Forms\Submissions\Database\Query;

$query = Query::get_instance();

// Get single submission
$submission = $query->get_submission($id);
// Returns: ['data' => [...], 'id' => 123, ...]

// Get paginated list
$result = $query->get_submissions([
    'page' => 1,
    'per_page' => 20,
    'filters' => [
        'form_name' => 'Contact Form',
        'status' => 'new'
    ],
    'order' => [
        'field' => 'created_at',
        'direction' => 'DESC'
    ],
    'with_meta' => true,
    'with_form_fields' => false
]);

// Add action log
$query->add_action_log(
    $submission_id,
    $action_instance,  // Action_Base instance
    'success',         // 'success' or 'failed'
    null               // Error message or null
);

// Update submission
$query->update_submission($id, [
    'is_read' => 1,
    'status' => 'trash'
]);
```

---

## Mock Record for Retrigger

When replaying a submission, create a mock record:

```php
$mock_record = new class($fields, $settings, $post_id, $element_id, $meta) {
    public function get_form_settings($key = null) {
        return $key ? ($this->settings[$key] ?? null) : $this->settings;
    }

    public function get_formatted_data($flat = false) {
        return $this->fields;
    }

    public function get($key) {
        switch ($key) {
            case 'fields':
                return $this->build_fields_array();
            case 'sent_data':
                return $this->fields;
            case 'files':
                return [];
            case 'meta':
                return $this->meta;
            case 'form_settings':
                return $this->settings;
        }
        return null;
    }

    public function replace_setting_shortcodes($s) {
        foreach ($this->fields as $k => $v) {
            $s = str_replace("[field id=\"$k\"]", $v, $s);
        }
        return $s;
    }

    public function get_form_args() {
        return ['post_id' => $this->post_id, 'form_id' => $this->element_id];
    }

    public function get_form_meta($keys) {
        return array_intersect_key($this->meta, array_flip($keys));
    }
};
```

---

## Version History

| Elementor Pro | Changes |
|---------------|---------|
| 4.21.x | Current reference version |
| 3.5.0 | Deprecated `add_form_action()`, use `actions_registrar->register()` |
| 3.5.0 | Deprecated `add_form_field_type()`, use `fields_registrar->register()` |
| 2.0.0 | Added per-field-type validation/process hooks |
| 1.0.0 | Initial forms module |
