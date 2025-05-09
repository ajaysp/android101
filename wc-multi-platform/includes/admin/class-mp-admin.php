<?php
// includes/admin/class-mp-admin.php

if (!defined('ABSPATH')) {
    exit;
}

class WC_MP_Admin {
    private $platforms;
    
    public function __construct($platforms) {
        $this->platforms = $platforms;
        $this->init();
    }
    
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Multi-Platform Settings', 'wc-multi-platform'),
            __('Multi-Platform', 'wc-multi-platform'),
            'manage_woocommerce',
            'wc-multi-platform',
            array($this, 'render_settings_page')
        );
    }
    
    public function render_settings_page() {
        include WC_MP_PLUGIN_DIR . 'includes/admin/views/settings.php';
    }
    
    public function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_wc-multi-platform') {
            return;
        }
        
        wp_enqueue_style(
            'wc-mp-admin-css',
            WC_MP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_MP_VERSION
        );
        
        wp_enqueue_script(
            'wc-mp-admin-js',
            WC_MP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_MP_VERSION,
            true
        );
    }
}