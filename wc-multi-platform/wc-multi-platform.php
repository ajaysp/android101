<?php
/**
 * Plugin Name: WooCommerce Multi-Platform Integration
 * Description: Extends WooCommerce with purchase, sales, and reporting functionality across multiple platforms.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wc-multi-platform
 * Requires WooCommerce: 4.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_MP_VERSION', '1.0.0');
define('WC_MP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_MP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function wc_mp_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_mp_woocommerce_missing_notice');
        return false;
    }
    return true;
}

// Admin notice for missing WooCommerce
function wc_mp_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WooCommerce Multi-Platform Integration requires WooCommerce to be installed and activated.', 'wc-multi-platform'); ?></p>
    </div>
    <?php
}

// Initialize plugin
function wc_mp_init() {
    if (!wc_mp_check_woocommerce()) {
        return;
    }
    
    // Include required files
    require_once WC_MP_PLUGIN_DIR . 'includes/class-mp-core.php';
    
    // Initialize the core class
    $wc_mp_core = new WC_MP_Core();
    $wc_mp_core->init();
}
add_action('plugins_loaded', 'wc_mp_init');

// Activation hook
register_activation_hook(__FILE__, 'wc_mp_activate');
function wc_mp_activate() {
    if (!wc_mp_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce Multi-Platform Integration requires WooCommerce to be installed and activated.', 'wc-multi-platform'));
    }
    
    // Create custom tables if needed
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table for tracking platform inventory
    $table_name = $wpdb->prefix . 'wc_mp_platform_inventory';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        product_id mediumint(9) NOT NULL,
        platform varchar(50) NOT NULL,
        platform_product_id varchar(100) NOT NULL,
        stock int(11) NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY platform_product (platform, platform_product_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wc_mp_deactivate');
function wc_mp_deactivate() {
    // Clean up if needed
}