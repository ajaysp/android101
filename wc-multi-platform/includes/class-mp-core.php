<?php
// includes/class-mp-core.php

if (!defined('ABSPATH')) {
    exit;
}

class WC_MP_Core {
    private $platforms = array();
    
    public function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize submodules
        $this->init_submodules();
        
        // Register hooks
        $this->register_hooks();
    }
    
    private function load_dependencies() {
        require_once WC_MP_PLUGIN_DIR . 'includes/class-mp-purchase.php';
        require_once WC_MP_PLUGIN_DIR . 'includes/class-mp-sales.php';
        require_once WC_MP_PLUGIN_DIR . 'includes/class-mp-reporting.php';
        require_once WC_MP_PLUGIN_DIR . 'includes/admin/class-mp-admin.php';
        require_once WC_MP_PLUGIN_DIR . 'includes/platforms/class-mp-platform-abstract.php';
        
        // Load platform implementations
        $this->load_platforms();
    }
    
    private function load_platforms() {
        $platform_files = array(
            'tokopedia' => 'class-mp-platform-tokopedia.php',
            'shopee'    => 'class-mp-platform-shopee.php'
        );
        
        foreach ($platform_files as $platform => $file) {
            $file_path = WC_MP_PLUGIN_DIR . 'includes/platforms/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                
                $class_name = 'WC_MP_Platform_' . ucfirst($platform);
                if (class_exists($class_name)) {
                    $this->platforms[$platform] = new $class_name();
                }
            }
        }
    }
    
    private function init_submodules() {
        // Initialize admin interface
        new WC_MP_Admin($this->platforms);
        
        // Initialize other modules
        new WC_MP_Purchase($this->platforms);
        new WC_MP_Sales($this->platforms);
        new WC_MP_Reporting($this->platforms);
    }
    
    private function register_hooks() {
        add_action('woocommerce_product_options_stock', array($this, 'add_platform_inventory_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_platform_inventory_fields'));
    }
    
    public function add_platform_inventory_fields() {
        global $post;
        
        echo '<div class="options_group">';
        echo '<h3>' . __('Multi-Platform Inventory', 'wc-multi-platform') . '</h3>';
        
        foreach ($this->platforms as $platform_id => $platform) {
            woocommerce_wp_text_input(array(
                'id'          => "wc_mp_{$platform_id}_product_id",
                'label'       => sprintf(__('%s Product ID', 'wc-multi-platform'), $platform->get_name()),
                'description' => __('The product ID on the external platform', 'wc-multi-platform'),
                'desc_tip'    => true,
                'type'        => 'text'
            ));
            
            woocommerce_wp_text_input(array(
                'id'          => "wc_mp_{$platform_id}_stock",
                'label'       => sprintf(__('%s Stock', 'wc-multi-platform'), $platform->get_name()),
                'description' => __('Stock quantity on the external platform', 'wc-multi-platform'),
                'desc_tip'    => true,
                'type'        => 'number'
            ));
        }
        
        echo '</div>';
    }
    
    public function save_platform_inventory_fields($product_id) {
        global $wpdb;
        
        foreach ($this->platforms as $platform_id => $platform) {
            $platform_product_id = isset($_POST["wc_mp_{$platform_id}_product_id"]) ? sanitize_text_field($_POST["wc_mp_{$platform_id}_product_id"]) : '';
            $stock = isset($_POST["wc_mp_{$platform_id}_stock"]) ? intval($_POST["wc_mp_{$platform_id}_stock"]) : 0;
            
            $table_name = $wpdb->prefix . 'wc_mp_platform_inventory';
            
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table_name WHERE product_id = %d AND platform = %s",
                $product_id,
                $platform_id
            ));
            
            if ($existing) {
                $wpdb->update(
                    $table_name,
                    array(
                        'platform_product_id' => $platform_product_id,
                        'stock' => $stock,
                        'last_updated' => current_time('mysql')
                    ),
                    array('id' => $existing->id)
                );
            } else {
                $wpdb->insert(
                    $table_name,
                    array(
                        'product_id' => $product_id,
                        'platform' => $platform_id,
                        'platform_product_id' => $platform_product_id,
                        'stock' => $stock,
                        'last_updated' => current_time('mysql')
                    )
                );
            }
        }
    }
    
    public function get_platforms() {
        return $this->platforms;
    }
}