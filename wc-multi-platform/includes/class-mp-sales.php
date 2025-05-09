<?php
// includes/class-mp-sales.php

if (!defined('ABSPATH')) {
    exit;
}

class WC_MP_Sales {
    private $platforms;
    
    public function __construct($platforms) {
        $this->platforms = $platforms;
        $this->init();
    }
    
    private function init() {
        // Add manual order entry metabox
        add_action('add_meta_boxes', array($this, 'add_order_metabox'));
        
        // Save manual order data
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_manual_order_data'), 10, 2);
        
        // Update order status on platforms when changed in WooCommerce
        add_action('woocommerce_order_status_changed', array($this, 'update_platform_order_status'), 10, 3);
        
        // Add custom order actions
        add_filter('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_wc_mp_sync_to_platform', array($this, 'sync_order_to_platform'));
    }
    
    public function add_order_metabox() {
        add_meta_box(
            'wc_mp_manual_order',
            __('Multi-Platform Order Details', 'wc-multi-platform'),
            array($this, 'render_manual_order_metabox'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    public function render_manual_order_metabox($post) {
        $order = wc_get_order($post->ID);
        $platform_id = $order->get_meta('_wc_mp_platform_id');
        $platform_order_id = $order->get_meta('_wc_mp_platform_order_id');
        
        wp_nonce_field('wc_mp_save_manual_order', 'wc_mp_manual_order_nonce');
        ?>
        <div class="wc_mp_manual_order_fields">
            <p class="form-field">
                <label for="wc_mp_platform_id"><?php _e('Platform', 'wc-multi-platform'); ?></label>
                <select name="wc_mp_platform_id" id="wc_mp_platform_id" class="select short">
                    <option value=""><?php _e('None', 'wc-multi-platform'); ?></option>
                    <?php foreach ($this->platforms as $id => $platform): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($platform_id, $id); ?>>
                            <?php echo esc_html($platform->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p class="form-field">
                <label for="wc_mp_platform_order_id"><?php _e('Platform Order ID', 'wc-multi-platform'); ?></label>
                <input type="text" name="wc_mp_platform_order_id" id="wc_mp_platform_order_id" 
                       value="<?php echo esc_attr($platform_order_id); ?>" class="short">
            </p>
            
            <?php if ($platform_id && $platform_order_id): ?>
                <p>
                    <a href="#" class="button wc_mp_sync_order_status"><?php _e('Sync Status', 'wc-multi-platform'); ?></a>
                </p>
            <?php endif; ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.wc_mp_sync_order_status').on('click', function(e) {
                    e.preventDefault();
                    if (confirm('<?php _e('Are you sure you want to sync this order status with the platform?', 'wc-multi-platform'); ?>')) {
                        window.location.href = '<?php echo admin_url('admin-post.php?action=wc_mp_sync_order_status&order_id=' . $post->ID); ?>';
                    }
                });
            });
        </script>
        <?php
    }
    
    public function save_manual_order_data($order_id, $post) {
        if (!isset($_POST['wc_mp_manual_order_nonce']) || 
            !wp_verify_nonce($_POST['wc_mp_manual_order_nonce'], 'wc_mp_save_manual_order')) {
            return;
        }
        
        $order = wc_get_order($order_id);
        $platform_id = isset($_POST['wc_mp_platform_id']) ? sanitize_text_field($_POST['wc_mp_platform_id']) : '';
        $platform_order_id = isset($_POST['wc_mp_platform_order_id']) ? sanitize_text_field($_POST['wc_mp_platform_order_id']) : '';
        
        $order->update_meta_data('_wc_mp_platform_id', $platform_id);
        $order->update_meta_data('_wc_mp_platform_order_id', $platform_order_id);
        $order->save();
        
        // Add order note if platform was changed
        if ($platform_id && $platform_order_id) {
            $order_note = sprintf(
                __('Order manually associated with %s. Platform order ID: %s', 'wc-multi-platform'),
                $this->platforms[$platform_id]->get_name(),
                $platform_order_id
            );
            $order->add_order_note($order_note);
        }
    }
    
    public function add_order_actions($actions) {
        global $theorder;
        
        $platform_id = $theorder->get_meta('_wc_mp_platform_id');
        $platform_order_id = $theorder->get_meta('_wc_mp_platform_order_id');
        
        if ($platform_id && $platform_order_id) {
            $actions['wc_mp_sync_to_platform'] = __('Sync to Platform', 'wc-multi-platform');
        }
        
        return $actions;
    }
    
    public function sync_order_to_platform($order) {
        $platform_id = $order->get_meta('_wc_mp_platform_id');
        $platform_order_id = $order->get_meta('_wc_mp_platform_order_id');
        
        if ($platform_id && $platform_order_id && isset($this->platforms[$platform_id])) {
            try {
                $status = $order->get_status();
                $this->platforms[$platform_id]->update_order_status($platform_order_id, $status);
                
                $order->add_order_note(sprintf(
                    __('Order status synced to %s as %s', 'wc-multi-platform'),
                    $this->platforms[$platform_id]->get_name(),
                    wc_get_order_status_name($status)
                ));
            } catch (Exception $e) {
                $order->add_order_note(sprintf(
                    __('Failed to sync order status to %s: %s', 'wc-multi-platform'),
                    $this->platforms[$platform_id]->get_name(),
                    $e->getMessage()
                ));
            }
        }
    }
    
    public function update_platform_order_status($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        $platform_id = $order->get_meta('_wc_mp_platform_id');
        
        if ($platform_id && isset($this->platforms[$platform_id])) {
            $platform_enabled = get_option('wc_mp_' . $platform_id . '_enabled', false);
            
            if ($platform_enabled) {
                $platform_order_id = $order->get_meta('_wc_mp_platform_order_id');
                
                try {
                    $this->platforms[$platform_id]->update_order_status($platform_order_id, $new_status);
                    $order->add_order_note(sprintf(
                        __('Order status updated to %s on %s', 'wc-multi-platform'),
                        wc_get_order_status_name($new_status),
                        $this->platforms[$platform_id]->get_name()
                    ));
                } catch (Exception $e) {
                    $order->add_order_note(sprintf(
                        __('Failed to update order status on %s: %s', 'wc-multi-platform'),
                        $this->platforms[$platform_id]->get_name(),
                        $e->getMessage()
                    ));
                    $this->platforms[$platform_id]->log('Order status update failed: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
    
    public function __destruct() {
        // Clean up if needed
    }
}