<?php
// includes/platforms/class-mp-platform-abstract.php

if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_MP_Platform_Abstract {
    protected $name;
    protected $id;
    
    abstract public function sync_product($product_id);
    abstract public function sync_inventory($product_id, $quantity);
    abstract public function update_order_status($order_id, $status);
    
    public function get_name() {
        return $this->name;
    }
    
    public function get_id() {
        return $this->id;
    }
    
    protected function log($message, $level = 'info') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'wc-multi-platform-' . $this->id));
        }
    }
}