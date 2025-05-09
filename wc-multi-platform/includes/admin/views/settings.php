<?php
// includes/admin/views/settings.php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap">
    <h1><?php esc_html_e('WooCommerce Multi-Platform Settings', 'wc-multi-platform'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('wc_mp_settings'); ?>
        <?php do_settings_sections('wc-multi-platform'); ?>
        
        <h2 class="title"><?php esc_html_e('Platform Settings', 'wc-multi-platform'); ?></h2>
        
        <?php foreach ($this->platforms as $platform_id => $platform): ?>
        <div class="wc-mp-platform-settings" id="wc-mp-<?php echo esc_attr($platform_id); ?>-settings">
            <h3><?php echo esc_html($platform->get_name()); ?></h3>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="wc_mp_<?php echo esc_attr($platform_id); ?>_enabled">
                                <?php esc_html_e('Enable', 'wc-multi-platform'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" name="wc_mp_<?php echo esc_attr($platform_id); ?>_enabled" id="wc_mp_<?php echo esc_attr($platform_id); ?>_enabled" value="1" <?php checked(get_option('wc_mp_' . $platform_id . '_enabled'), 1); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wc_mp_<?php echo esc_attr($platform_id); ?>_api_key">
                                <?php esc_html_e('API Key', 'wc-multi-platform'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="wc_mp_<?php echo esc_attr($platform_id); ?>_api_key" id="wc_mp_<?php echo esc_attr($platform_id); ?>_api_key" value="<?php echo esc_attr(get_option('wc_mp_' . $platform_id . '_api_key')); ?>">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
        
        <?php submit_button(); ?>
    </form>
</div>