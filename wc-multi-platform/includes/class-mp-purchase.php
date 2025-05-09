<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_MP_Purchase {
    /**
     * Initialize purchase functionality
     */
    public function init() {
        // Register custom post statuses for purchase orders
        add_action('init', array($this, 'register_post_statuses'));
        
        // Add meta boxes for purchase orders
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Save purchase order data
        add_action('save_post_mp_purchase_order', array($this, 'save_purchase_order'), 10, 3);
        
        // Process purchase order submission
        add_action('admin_post_create_purchase_order', array($this, 'process_purchase_order'));
        
        // Receive products action
        add_action('admin_post_receive_purchase_order', array($this, 'receive_purchase_order'));
        
        // Add purchase menu item
        add_action('admin_menu', array($this, 'add_menu_items'));
    }
    
    /**
     * Register custom post statuses
     */
    public function register_post_statuses() {
        register_post_status('wc-po-draft', array(
            'label' => _x('Draft', 'Purchase order status', 'wc-multi-platform'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Draft <span class="count">(%s)</span>', 'Draft <span class="count">(%s)</span>', 'wc-multi-platform'),
        ));
        
        register_post_status('wc-po-ordered', array(
            'label' => _x('Ordered', 'Purchase order status', 'wc-multi-platform'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Ordered <span class="count">(%s)</span>', 'Ordered <span class="count">(%s)</span>', 'wc-multi-platform'),
        ));
        
        register_post_status('wc-po-received', array(
            'label' => _x('Received', 'Purchase order status', 'wc-multi-platform'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Received <span class="count">(%s)</span>', 'Received <span class="count">(%s)</span>', 'wc-multi-platform'),
        ));
        
        register_post_status('wc-po-cancelled', array(
            'label' => _x('Cancelled', 'Purchase order status', 'wc-multi-platform'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'wc-multi-platform'),
        ));
    }
    
    /**
     * Add meta boxes for purchase orders
     */
    public function add_meta_boxes() {
        add_meta_box(
            'mp_purchase_order_items',
            __('Purchase Order Items', 'wc-multi-platform'),
            array($this, 'render_items_meta_box'),
            'mp_purchase_order',
            'normal',
            'high'
        );
        
        add_meta_box(
            'mp_purchase_order_details',
            __('Purchase Order Details', 'wc-multi-platform'),
            array($this, 'render_details_meta_box'),
            'mp_purchase_order',
            'side',
            'high'
        );
    }
    
    /**
     * Render items meta box
     */
    public function render_items_meta_box($post) {
        // Get purchase order items
        $items = get_post_meta($post->ID, '_purchase_order_items', true);
        
        // Nonce for security
        wp_nonce_field('mp_save_purchase_order', 'mp_purchase_order_nonce');
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Product', 'wc-multi-platform'); ?></th>
                    <th><?php _e('SKU', 'wc-multi-platform'); ?></th>
                    <th><?php _e('Quantity', 'wc-multi-platform'); ?></th>
                    <th><?php _e('Unit Cost', 'wc-multi-platform'); ?></th>
                    <th><?php _e('Total', 'wc-multi-platform'); ?></th>
                </tr>
            </thead>
            <tbody id="purchase_order_items">
                <?php
                if (!empty($items) && is_array($items)) {
                    foreach ($items as $item_id => $item) {
                        ?>
                        <tr class="item">
                            <td>
                                <select name="purchase_order_items[<?php echo $item_id; ?>][product_id]" class="wc-product-search" data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'wc-multi-platform'); ?>" data-action="woocommerce_json_search_products_and_variations">
                                    <?php
                                    $product = wc_get_product($item['product_id']);
                                    if ($product) {
                                        echo '<option value="' . esc_attr($item['product_id']) . '" selected>' . wp_kses_post($product->get_formatted_name()) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <?php 
                                if ($product) {
                                    echo $product->get_sku();
                                }
                                ?>
                            </td>
                            <td>
                                <input type="number" name="purchase_order_items[<?php echo $item_id; ?>][quantity]" value="<?php echo esc_attr($item['quantity']); ?>" min="1" step="1" style="width: 60px;">
                            </td>
                            <td>
                                <input type="number" name="purchase_order_items[<?php echo $item_id; ?>][cost]" value="<?php echo esc_attr($item['cost']); ?>" min="0" step="0.01" style="width: 80px;">
                            </td>
                            <td class="item-total">
                                <?php echo wc_price($item['quantity'] * $item['cost']); ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">
                        <button type="button" class="button add-item"><?php _e('Add Item', 'wc-multi-platform'); ?></button>
                    </td>
                </tr>
            </tfoot>
        </table>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.add-item').on('click', function() {
                    var itemId = 'new_' + Date.now();
                    var html = '<tr class="item">';
                    html += '<td><select name="purchase_order_items[' + itemId + '][product_id]" class="wc-product-search" data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'wc-multi-platform'); ?>" data-action="woocommerce_json_search_products_and_variations"></select></td>';
                    html += '<td></td>';
                    html += '<td><input type="number" name="purchase_order_items[' + itemId + '][quantity]" value="1" min="1" step="1" style="width: 60px;"></td>';
                    html += '<td><input type="number" name="purchase_order_items[' + itemId + '][cost]" value="0" min="0" step="0.01" style="width: 80px;"></td>';
                    html += '<td class="item-total">0</td>';
                    html += '</tr>';
                    
                    $('#purchase_order_items').append(html);
                    $(document.body).trigger('wc-enhanced-select-init');
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render details meta box
     */
    public function render_details_meta_box($post) {
        $supplier_id = get_post_meta($post->ID, '_supplier_id', true);
        $status = get_post_status($post->ID);
        $expected_date = get_post_meta($post->ID, '_expected_date', true);
        $notes = get_post_meta($post->ID, '_notes', true);
        
        ?>
        <p>
            <label for="supplier_id"><?php _e('Supplier:', 'wc-multi-platform'); ?></label><br>
            <select name="supplier_id" id="supplier_id" class="widefat">
                <option value=""><?php _e('Select a supplier', 'wc-multi-platform'); ?></option>
                <?php
                // Get suppliers (this would be a custom taxonomy or post type in a real implementation)
                $suppliers = array(
                    1 => 'Supplier 1',
                    2 => 'Supplier 2',
                    3 => 'Supplier 3',
                );
                
                foreach ($suppliers as $id => $name) {
                    echo '<option value="' . esc_attr($id) . '" ' . selected($id, $supplier_id, false) . '>' . esc_html($name) . '</option>';
                }
                ?>
            </select>
        </p>
        
        <p>
            <label for="expected_date"><?php _e('Expected Date:', 'wc-multi-platform'); ?></label><br>
            <input type="date" name="expected_date" id="expected_date" class="widefat" value="<?php echo esc_attr($expected_date); ?>">
        </p>
        
        <p>
            <label for="notes"><?php _e('Notes:', 'wc-multi-platform'); ?></label><br>
            <textarea name="notes" id="notes" class="widefat" rows="4"><?php echo esc_textarea($notes); ?></textarea>
        </p>
        
        <p>
            <label for="status"><?php _e('Status:', 'wc-multi-platform'); ?></label><br>
            <select name="status" id="status" class="widefat">
                <option value="wc-po-draft" <?php selected($status, 'wc-po-draft'); ?>><?php _e('Draft', 'wc-multi-platform'); ?></option>
                <option value="wc-po-ordered" <?php selected($status, 'wc-po-ordered'); ?>><?php _e('Ordered', 'wc-multi-platform'); ?></option>
                <option value="wc-po-received" <?php selected($status, 'wc-po-received'); ?>><?php _e('Received', 'wc-multi-platform'); ?></option>
                <option value="wc-po-cancelled" <?php selected($status, 'wc-po-cancelled'); ?>><?php _e('Cancelled', 'wc-multi-platform'); ?></option>
            </select>
        </p>
        <?php
    }
    
    /**
     * Save purchase order data
     */
    public function save_purchase_order($post_id, $post, $update) {
        // Check if our nonce is set
        if (!isset($_POST['mp_purchase_order_nonce'])) {
            return;
        }
        
        // Verify that the nonce is valid
        if (!wp_verify_nonce($_POST['mp_purchase_order_nonce'], 'mp_save_purchase_order')) {
            return;
        }
        
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Update purchase order items
        if (isset($_POST['purchase_order_items']) && is_array($_POST['purchase_order_items'])) {
            update_post_meta($post_id, '_purchase_order_items', $_POST['purchase_order_items']);
        }
        
        // Update supplier
        if (isset($_POST['supplier_id'])) {
            update_post_meta($post_id, '_supplier_id', sanitize_text_field($_POST['supplier_id']));
        }
        
        // Update expected date
        if (isset($_POST['expected_date'])) {
            update_post_meta($post_id, '_expected_date', sanitize_text_field($_POST['expected_date']));
        }
        
        // Update notes
        if (isset($_POST['notes'])) {
            update_post_meta($post_id, '_notes', sanitize_textarea_field($_POST['notes']));
        }
        
        // Update status
        if (isset($_POST['status'])) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => sanitize_text_field($_POST['status']),
            ));
        }
    }
    
    /**
     * Process purchase order submission
     */
    public function process_purchase_order() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-multi-platform'));
        }
        
        // Verify nonce
        if (!isset($_POST['mp_purchase_order_nonce']) || !wp_verify_nonce($_POST['mp_purchase_order_nonce'], 'mp_create_purchase_order')) {
            wp_die(__('Invalid nonce.', 'wc-multi-platform'));
        }
        
        // Create purchase order
        $purchase_order_id = wp_insert_post(array(
            'post_title' => sprintf(__('Purchase Order %s', 'wc-multi-platform'), date('Y-m-d H:i:s')),
            'post_type' => 'mp_purchase_order',
            'post_status' => 'wc-po-draft',
        ));
        
        if (is_wp_error($purchase_order_id)) {
            wp_die($purchase_order_id->get_error_message());
        }
        
        // Redirect to edit screen
        wp_redirect(admin_url('post.php?post=' . $purchase_order_id . '&action=edit'));
        exit;
    }
    
    /**
     * Receive purchase order
     */
    public function receive_purchase_order() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-multi-platform'));
        }
        
        // Verify nonce
        if (!isset($_POST['mp_receive_order_nonce']) || !wp_verify_nonce($_POST['mp_receive_order_nonce'], 'mp_receive_purchase_order')) {
            wp_die(__('Invalid nonce.', 'wc-multi-platform'));
        }
        
        // Get purchase order ID
        $purchase_order_id = isset($_POST['purchase_order_id']) ? intval($_POST['purchase_order_id']) : 0;
        
        if (!$purchase_order_id) {
            wp_die(__('Invalid purchase order ID.', 'wc-multi-platform'));
        }
        
        // Update purchase order status
        wp_update_post(array(
            'ID' => $purchase_order_id,
            'post_status' => 'wc-po-received',
        ));
        
        // Get purchase order items
        $items = get_post_meta($purchase_order_id, '_purchase_order_items', true);
        
        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }
                
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                
                // Update stock in WooCommerce
                $product = wc_get_product($product_id);
                
                if ($product && $product->managing_stock()) {
                    // Update stock
                    $new_stock = wc_update_product_stock($product, $quantity, 'increase');
                    
                    // Add note to the purchase order
                    add_post_meta($purchase_order_id, '_stock_update', sprintf(
                        __('Updated stock for %s from %s to %s', 'wc-multi-platform'),
                        $product->get_name(),
                        $new_stock - $quantity,
                        $new_stock
                    ));
                }
            }
        }
        
        // Redirect back to purchase order
        wp_redirect(admin_url('post.php?post=' . $purchase_order_id . '&action=edit&message=1'));
        exit;
    }
    
    /**
     * Add purchase menu item
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __('Purchase Orders', 'wc-multi-platform'),
            __('Purchase Orders', 'wc-multi-platform'),
            'manage_woocommerce',
            'edit.php?post_type=mp_purchase_order'
        );
    }
}