<?php
/*
Plugin Name: WooCommerce Order to Slack Notifier
Description: Sends Slack notifications when new WooCommerce orders are received
Version: 1.1
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

// Hook into WooCommerce new order
add_action('woocommerce_new_order', 'send_order_to_slack');
function send_order_to_slack($order_id) {
    $webhook_url = get_option('wc_slack_webhook_url');
    if (empty($webhook_url)) {
        return;
    }

    $order = wc_get_order($order_id);
    $order_data = $order->get_data();
    $status = $order->get_status();

    // Format the message
    $message = sprintf(
        "📦 New Order #%s\n" .
        "👤 Customer: %s %s\n" .
        "📧 Email: %s\n" .
        "🔄 Status: `%s`\n" .
        "🔗 View Order: %s",
        $order->get_order_number(),
        $order_data['billing']['first_name'],
        $order_data['billing']['last_name'],
        $order_data['billing']['email'],
        $status,
        admin_url('post.php?post=' . $order_id . '&action=edit')
    );

    // Prepare the payload
    $payload = json_encode([
        'text' => $message,
    ]);

    // Send to Slack
    wp_remote_post($webhook_url, [
        'body' => $payload,
        'headers' => ['Content-Type' => 'application/json'],
    ]);
}
