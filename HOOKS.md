# Action and Filter Hooks Reference

This document lists all available WordPress action and filter hooks provided by the Elementor Re-Trigger Tool plugin.

**Version:** 10.1.0
**Last Updated:** 2025-12-18

---

## Table of Contents

1. [Action Hooks](#action-hooks)
2. [Filter Hooks](#filter-hooks)
3. [Usage Examples](#usage-examples)
4. [Third-Party Integration](#third-party-integration)

---

## Action Hooks

Action hooks allow you to execute custom code at specific points during plugin execution.

### `elementor_retrigger_loaded`

Fires after the plugin is fully loaded and initialized.

**Since:** 10.1.0

**Parameters:** None

**Example:**
```php
add_action( 'elementor_retrigger_loaded', function() {
    // Your custom code here
    error_log( 'Elementor Re-Trigger Tool loaded!' );
} );
```

---

### `elementor_retrigger_before_execute`

Fires immediately before a re-trigger operation starts.

**Since:** 10.1.0

**Parameters:**
- `int $submission_id` - The submission ID being retriggered
- `array $target_actions` - Array of action slugs to execute
- `array|null $custom_fields` - Custom field data (if any)
- `string $webhook_url` - Webhook URL override (if any)

**Example:**
```php
add_action( 'elementor_retrigger_before_execute', function( $submission_id, $target_actions, $custom_fields, $webhook_url ) {
    error_log( "Re-triggering submission #{$submission_id}" );

    // Send notification
    wp_mail(
        'admin@example.com',
        'Re-Trigger Started',
        "Submission #{$submission_id} is being retriggered."
    );
}, 10, 4 );
```

---

### `elementor_retrigger_after_execute`

Fires after a re-trigger operation completes successfully.

**Since:** 10.1.0

**Parameters:**
- `int $submission_id` - The submission ID
- `array $executed` - Array of successfully executed action slugs
- `array $target_actions` - Array of requested actions

**Example:**
```php
add_action( 'elementor_retrigger_after_execute', function( $submission_id, $executed, $target_actions ) {
    // Log successful execution
    error_log( "Successfully executed: " . implode( ', ', $executed ) );

    // Send success notification
    if ( in_array( 'webhook', $executed ) ) {
        // Webhook was successfully executed
        do_something_custom();
    }
}, 10, 3 );
```

---

### `elementor_retrigger_before_log`

Fires immediately before a log entry is saved to the database.

**Since:** 10.1.0

**Parameters:**
- `array $data` - Log data to be inserted
- `int $submission_id` - Submission ID

**Example:**
```php
add_action( 'elementor_retrigger_before_log', function( $data, $submission_id ) {
    // Custom logging to external system
    if ( $data['status'] === 'failed' ) {
        send_to_error_tracking_service( $data );
    }
}, 10, 2 );
```

---

### `elementor_retrigger_after_log`

Fires after a log entry is saved to the database.

**Since:** 10.1.0

**Parameters:**
- `int $log_id` - The inserted log ID
- `array $data` - Log data that was inserted
- `int $submission_id` - Submission ID

**Example:**
```php
add_action( 'elementor_retrigger_after_log', function( $log_id, $data, $submission_id ) {
    // Update external dashboard
    update_analytics_dashboard( [
        'log_id' => $log_id,
        'status' => $data['status'],
        'actions' => $data['actions'],
    ] );
}, 10, 3 );
```

---

### `elementor_retrigger_before_uninstall`

Fires before plugin data is removed during uninstallation.

**Since:** 10.1.0

**Parameters:** None

**Example:**
```php
add_action( 'elementor_retrigger_before_uninstall', function() {
    // Export data before deletion
    $logs = get_all_retrigger_logs();
    export_to_backup_service( $logs );
} );
```

---

### `elementor_retrigger_after_uninstall`

Fires after plugin data is removed during uninstallation.

**Since:** 10.1.0

**Parameters:**
- `bool $keep_data` - Whether data was kept or removed

**Example:**
```php
add_action( 'elementor_retrigger_after_uninstall', function( $keep_data ) {
    if ( ! $keep_data ) {
        // Cleanup external services
        cleanup_third_party_integrations();
    }
} );
```

---

## Filter Hooks

Filter hooks allow you to modify data before it's processed.

### `elementor_retrigger_submission_data`

Filters the submission data retrieved from Elementor before processing.

**Since:** 10.1.0

**Parameters:**
- `array $data` - Submission data from Elementor
- `int $submission_id` - Submission ID

**Return:** `array` - Modified submission data

**Example:**
```php
add_filter( 'elementor_retrigger_submission_data', function( $data, $submission_id ) {
    // Add custom meta data
    $data['custom_meta'] = get_custom_submission_meta( $submission_id );

    return $data;
}, 10, 2 );
```

---

### `elementor_retrigger_formatted_fields`

Filters the formatted field data before creating the mock record.

**Since:** 10.1.0

**Parameters:**
- `array $formatted_fields` - Form field data
- `int $submission_id` - Submission ID
- `array|null $custom_fields` - Custom fields (if provided)

**Return:** `array` - Modified field data

**Example:**
```php
add_filter( 'elementor_retrigger_formatted_fields', function( $formatted_fields, $submission_id, $custom_fields ) {
    // Modify field values
    if ( isset( $formatted_fields['email'] ) ) {
        // Add custom validation or transformation
        $formatted_fields['email'] = strtolower( $formatted_fields['email'] );
    }

    // Add computed fields
    $formatted_fields['submission_date'] = current_time( 'mysql' );

    return $formatted_fields;
}, 10, 3 );
```

---

### `elementor_retrigger_widget_settings`

Filters the widget settings before re-trigger execution.

**Since:** 10.1.0

**Parameters:**
- `array $widget_settings` - Form widget settings
- `int $submission_id` - Submission ID
- `string $webhook_url` - Webhook URL override

**Return:** `array` - Modified widget settings

**Example:**
```php
add_filter( 'elementor_retrigger_widget_settings', function( $widget_settings, $submission_id, $webhook_url ) {
    // Override email settings based on submission
    $submission_data = get_submission_data( $submission_id );

    if ( $submission_data['form_name'] === 'VIP Contact Form' ) {
        $widget_settings['email_to'] = 'vip@example.com';
    }

    return $widget_settings;
}, 10, 3 );
```

---

### `elementor_retrigger_log_data`

Filters the log data before it's saved to the database.

**Since:** 10.1.0

**Parameters:**
- `array $data` - Log data to be inserted
- `int $submission_id` - Submission ID

**Return:** `array` - Modified log data

**Example:**
```php
add_filter( 'elementor_retrigger_log_data', function( $data, $submission_id ) {
    // Add custom log fields
    $data['custom_field'] = 'custom_value';

    // Modify message based on status
    if ( $data['status'] === 'success' ) {
        $data['message'] .= ' - Notification sent';
    }

    return $data;
}, 10, 2 );
```

---

## Usage Examples

### Example 1: Send Slack Notification on Failure

```php
/**
 * Send Slack notification when re-trigger fails
 */
add_action( 'elementor_retrigger_after_log', function( $log_id, $data, $submission_id ) {
    if ( $data['status'] === 'failed' ) {
        $webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';

        $message = [
            'text' => '⚠️ Elementor Re-Trigger Failed',
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        [
                            'title' => 'Submission ID',
                            'value' => $submission_id,
                            'short' => true
                        ],
                        [
                            'title' => 'Actions',
                            'value' => $data['actions'],
                            'short' => true
                        ],
                        [
                            'title' => 'Error',
                            'value' => $data['message'],
                            'short' => false
                        ]
                    ]
                ]
            ]
        ];

        wp_remote_post( $webhook_url, [
            'body' => json_encode( $message ),
            'headers' => [ 'Content-Type' => 'application/json' ]
        ] );
    }
}, 10, 3 );
```

### Example 2: Custom Field Transformation

```php
/**
 * Transform phone numbers to E.164 format before re-triggering
 */
add_filter( 'elementor_retrigger_formatted_fields', function( $fields, $submission_id, $custom_fields ) {
    if ( isset( $fields['phone'] ) ) {
        // Remove all non-numeric characters
        $phone = preg_replace( '/[^0-9]/', '', $fields['phone'] );

        // Add country code if missing
        if ( strlen( $phone ) === 10 ) {
            $phone = '+1' . $phone; // Assume US
        }

        $fields['phone'] = $phone;
    }

    return $fields;
}, 10, 3 );
```

### Example 3: Dynamic Webhook URL Based on Form

```php
/**
 * Route different forms to different webhooks
 */
add_filter( 'elementor_retrigger_widget_settings', function( $settings, $submission_id, $webhook_url ) {
    // Get submission data
    $query = \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance();
    $submission = $query->get_submission( $submission_id );

    if ( ! $submission ) {
        return $settings;
    }

    $form_name = $submission['data']['form_name'] ?? '';

    // Route based on form name
    switch ( $form_name ) {
        case 'Contact Form':
            $settings['webhooks'] = 'https://api.example.com/contact';
            break;
        case 'Sales Inquiry':
            $settings['webhooks'] = 'https://api.example.com/sales';
            break;
        case 'Support Request':
            $settings['webhooks'] = 'https://api.example.com/support';
            break;
    }

    return $settings;
}, 10, 3 );
```

### Example 4: Add Custom Metadata to Logs

```php
/**
 * Add user information to log entries
 */
add_filter( 'elementor_retrigger_log_data', function( $data, $submission_id ) {
    // Add current user info
    $current_user = wp_get_current_user();

    if ( $current_user->exists() ) {
        $user_info = [
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_email' => $current_user->user_email,
        ];

        // Add to debug info
        $data['full_debug'] .= "\n\nTriggered by: " . json_encode( $user_info, JSON_PRETTY_PRINT );
    }

    return $data;
}, 10, 2 );
```

### Example 5: Prevent Re-Trigger Based on Conditions

```php
/**
 * Prevent re-trigger for certain submissions
 */
add_action( 'elementor_retrigger_before_execute', function( $submission_id, $target_actions, $custom_fields, $webhook_url ) {
    // Get submission data
    $query = \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance();
    $submission = $query->get_submission( $submission_id );

    if ( ! $submission ) {
        return;
    }

    // Check if submission is too old
    $created_at = strtotime( $submission['data']['created_at'] );
    $days_old = ( time() - $created_at ) / DAY_IN_SECONDS;

    if ( $days_old > 30 ) {
        wp_die( 'Cannot re-trigger submissions older than 30 days.' );
    }

    // Check for test submissions
    foreach ( $submission['data']['values'] as $field ) {
        if ( $field['key'] === 'email' && strpos( $field['value'], '@test.com' ) !== false ) {
            wp_die( 'Cannot re-trigger test submissions.' );
        }
    }
}, 10, 4 );
```

---

## Third-Party Integration

### Integrating with Your Plugin

You can create a separate integration plugin that hooks into the Elementor Re-Trigger Tool:

```php
<?php
/**
 * Plugin Name: My Custom Elementor Re-Trigger Integration
 * Description: Custom integration with Elementor Re-Trigger Tool
 * Version: 1.0.0
 */

class My_Retrigger_Integration {

    public function __construct() {
        // Only initialize if main plugin is active
        add_action( 'elementor_retrigger_loaded', [ $this, 'init' ] );
    }

    public function init() {
        // Add custom hooks
        add_filter( 'elementor_retrigger_formatted_fields', [ $this, 'modify_fields' ], 10, 3 );
        add_action( 'elementor_retrigger_after_execute', [ $this, 'send_notification' ], 10, 3 );
    }

    public function modify_fields( $fields, $submission_id, $custom_fields ) {
        // Your custom logic
        return $fields;
    }

    public function send_notification( $submission_id, $executed, $target_actions ) {
        // Your custom logic
    }
}

new My_Retrigger_Integration();
```

---

## Best Practices

### Hook Priority

- Use priority `10` (default) for most hooks
- Use priority `5` or lower for early execution
- Use priority `20` or higher for late execution

### Error Handling

Always include error handling in your hooks:

```php
add_action( 'elementor_retrigger_after_execute', function( $submission_id, $executed, $target_actions ) {
    try {
        // Your code
    } catch ( Exception $e ) {
        error_log( 'Hook error: ' . $e->getMessage() );
    }
}, 10, 3 );
```

### Performance

- Avoid heavy operations in hooks that fire frequently
- Use caching when possible
- Consider using background processing for intensive tasks

---

## Support

For more information or support:
- [Plugin Repository](https://github.com/your-repo)
- [Documentation](https://example.com/docs)
- [Issue Tracker](https://github.com/your-repo/issues)

---

**Note:** This hooks reference is for version 10.1.0. Check the latest version for any new or updated hooks.
