<?php
/*
Plugin Name: WooCommerce Order to Slack Notifier
Description: Sends Slack notifications when new WooCommerce orders are received
Version: 1.2
Author: Shelton Chiramal
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item to WordPress admin
add_action('admin_menu', 'wc_slack_notifier_menu');
function wc_slack_notifier_menu() {
    add_options_page(
        'WC Slack Notifier Settings',
        'WC Slack Notifier',
        'manage_options',
        'wc-slack-notifier',
        'wc_slack_notifier_settings_page'
    );
}

// Create the settings page
function wc_slack_notifier_settings_page() {
    ?>
    <div class="wrap">
        <h2>WooCommerce Slack Notifier Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_slack_notifier_options');
            do_settings_sections('wc-slack-notifier');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'wc_slack_notifier_settings_init');
function wc_slack_notifier_settings_init() {
    register_setting('wc_slack_notifier_options', 'wc_slack_webhook_url');

    add_settings_section(
        'wc_slack_notifier_section',
        'Webhook Settings',
        'wc_slack_notifier_section_callback',
        'wc-slack-notifier'
    );

    add_settings_field(
        'wc_slack_webhook_url',
        'Slack Webhook URL',
        'wc_slack_webhook_url_callback',
        'wc-slack-notifier',
        'wc_slack_notifier_section'
    );
}

function wc_slack_notifier_section_callback() {
    echo '<p>Enter your Slack webhook URL below:</p>';
}

function wc_slack_webhook_url_callback() {
    $webhook_url = get_option('wc_slack_webhook_url');
    echo '<input type="text" id="wc_slack_webhook_url" name="wc_slack_webhook_url" value="' . esc_attr($webhook_url) . '" style="width: 500px;" />';
}

// ... (previous code remains the same until send_order_to_slack function)
function send_order_to_slack($order_id) {
    $webhook_url = get_option('wc_slack_webhook_url');
    if (empty($webhook_url)) {
        return;
    }

    $order = wc_get_order($order_id);
    $order_data = $order->get_data();
    $status = $order->get_status();

    // Define color codes for different statuses
    $status_colors = [
        'pending' => '#FFA500',    // Orange for Pending Payment
        'processing' => '#36A64F', // Green for Processing
        'cancelled' => '#FF0000',  // Red for Cancelled
        'default' => '#808080'     // Grey for other statuses
    ];

    // Get color based on status
    $color = isset($status_colors[$status]) ? $status_colors[$status] : $status_colors['default'];

    // Format the message
    $message = sprintf(
        "*Order #%s - %s*\n" .
        "👤 Customer: %s %s\n" .
        "📧 Email: %s\n" .
        "💰 Total: %s\n" .
        "🔄 Status: `%s`\n" .        // Added raw status for debugging
        "🔗 <%s|View Order>",
        $order->get_order_number() - 433,
        ucfirst($status),
        $order_data['billing']['first_name'],
        $order_data['billing']['last_name'],
        $order_data['billing']['email'],
        $order->get_formatted_order_total(),
        $status,                     // Raw status value
        admin_url('post.php?post=' . $order_id . '&action=edit')
    );

    // Prepare the payload using Slack's attachment format for colors
    $payload = json_encode([
        'attachments' => [
            [
                'color' => $color,
                'text' => $message,
                'fallback' => strip_tags($message)
            ]
        ]
    ]);

    // Send to Slack
    wp_remote_post($webhook_url, [
        'body' => $payload,
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}
