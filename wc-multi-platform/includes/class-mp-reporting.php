<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_MP_Reporting {
    /**
     * Initialize reporting functionality
     */
    public function init() {
        // Add report menu
        add_filter('woocommerce_admin_reports', array($this, 'add_reports'));
        
        // Register AJAX endpoints for report data
        add_action('wp_ajax_wc_mp_get_sales_report', array($this, 'ajax_get_sales_report'));
        add_action('wp_ajax_wc_mp_get_inventory_report', array($this, 'ajax_get_inventory_report'));
        add_action('wp_ajax_wc_mp_get_platform_comparison_report', array($this, 'ajax_get_platform_comparison_report'));
        
        // Add dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Admin menu item
        add_action('admin_menu', array($this, 'add_menu_items'));
    }
    
    /**
     * Add reports to WooCommerce reports section
     */
    public function add_reports($reports) {
        $reports['multi_platform'] = array(
            'title' => __('Multi-Platform', 'wc-multi-platform'),
            'reports' => array(
                'sales_by_platform' => array(
                    'title' => __('Sales by Platform', 'wc-multi-platform'),
                    'description' => __('View sales reports across all platforms.', 'wc-multi-platform'),
                    'callback' => array($this, 'render_sales_by_platform_report'),
                ),
                'inventory' => array(
                    'title' => __('Multi-Platform Inventory', 'wc-multi-platform'),
                    'description' => __('View inventory levels across all platforms.', 'wc-multi-platform'),
                    'callback' => array($this, 'render_inventory_report'),
                ),
                'platform_comparison' => array(
                    'title' => __('Platform Comparison', 'wc-multi-platform'),
                    'description' => __('Compare performance across different platforms.', 'wc-multi-platform'),
                    'callback' => array($this, 'render_platform_comparison_report'),
                ),
            ),
        );
        
        return $reports;
    }
    
    /**
     * Add menu items
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __('Multi-Platform Reports', 'wc-multi-platform'),
            __('MP Reports', 'wc-multi-platform'),
            'manage_woocommerce',
            'wc-mp-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Render the main reports page
     */
    public function render_reports_page() {
        ?>
        <div class="wrap woocommerce">
            <h1><?php _e('Multi-Platform Reports', 'wc-multi-platform'); ?></h1>
            
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="?page=wc-mp-reports&tab=sales" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'sales') ? 'nav-tab-active' : ''; ?>"><?php _e('Sales', 'wc-multi-platform'); ?></a>
                <a href="?page=wc-mp-reports&tab=inventory" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'inventory') ? 'nav-tab-active' : ''; ?>"><?php _e('Inventory', 'wc-multi-platform'); ?></a>
                <a href="?page=wc-mp-reports&tab=comparison" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'comparison') ? 'nav-tab-active' : ''; ?>"><?php _e('Platform Comparison', 'wc-multi-platform'); ?></a>
            </nav>
            
            <div class="woocommerce-reports-wide">
                <?php
                $tab = isset($_GET['tab']) ? $_GET['tab'] : 'sales';
                
                switch ($tab) {
                    case 'inventory':
                        $this->render_inventory_report();
                        break;
                    case 'comparison':
                        $this->render_platform_comparison_report();
                        break;
                    default:
                        $this->render_sales_by_platform_report();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sales by platform report
     */
    public function render_sales_by_platform_report() {
        ?>
        <div class="wc-mp-report">
            <h2><?php _e('Sales by Platform', 'wc-multi-platform'); ?></h2>
            
            <div class="wc-mp-report-filters">
                <form id="sales-report-filter">
                    <label for="date-range"><?php _e('Date Range:', 'wc-multi-platform'); ?></label>
                    <select id="date-range" name="date_range">
                        <option value="7day"><?php _e('Last 7 Days', 'wc-multi-platform'); ?></option>
                        <option value="30day"><?php _e('Last 30 Days', 'wc-multi-platform'); ?></option>
                        <option value="month"><?php _e('This Month', 'wc-multi-platform'); ?></option>
                        <option value="last-month"><?php _e('Last Month', 'wc-multi-platform'); ?></option>
                        <option value="year"><?php _e('This Year', 'wc-multi-platform'); ?></option>
                    </select>
                    
                    <label for="platform"><?php _e('Platform:', 'wc-multi-platform'); ?></label>
                    <select id="platform" name="platform">
                        <option value="all"><?php _e('All Platforms', 'wc-multi-platform'); ?></option>
                        <option value="woocommerce"><?php _e('WooCommerce', 'wc-multi-platform'); ?></option>
                        <option value="tokopedia"><?php _e('Tokopedia', 'wc-multi-platform'); ?></option>
                        <option value="shopee"><?php _e('Shopee', 'wc-multi-platform'); ?></option>
                    </select>
                    
                    <button type="submit" class="button"><?php _e('Filter', 'wc-multi-platform'); ?></button>
                </form>
            </div>
            
            <div class="wc-mp-report-content">
                <div class="wc-mp-report-chart">
                    <canvas id="sales-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="wc-mp-report-summary">
                    <div class="wc-mp-report-card">
                        <h3><?php _e('Total Sales', 'wc-multi-platform'); ?></h3>
                        <div class="wc-mp-report-value" id="total-sales">-</div>
                    </div>
                    
                    <div class="wc-mp-report-card">
                        <h3><?php _e('Orders', 'wc-multi-platform'); ?></h3>
                        <div class="wc-mp-report-value" id="total-orders">-</div>
                    </div>
                    
                    <div class="wc-mp-report-card">
                        <h3><?php _e('Average Order Value', 'wc-multi-platform'); ?></h3>
                        <div class="wc-mp-report-value" id="avg-order-value">-</div>
                    </div>
                </div>
                
                <div class="wc-mp-report-table">
                    <h3><?php _e('Sales by Platform', 'wc-multi-platform'); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Platform', 'wc-multi-platform'); ?></th>
                                <th><?php _e('Orders', 'wc-multi-platform'); ?></th>
                                <th><?php _e('Sales', 'wc-multi-platform'); ?></th>
                                <th><?php _e('Average Order Value', 'wc-multi-platform'); ?></th>
                                <th><?php _e('% of Total', 'wc-multi-platform'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="sales-by-platform-table">
                            <tr>
                                <td colspan="5"><?php _e('Loading...', 'wc-multi-platform'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var salesChart;
                
                // Initialize Chart.js
                function initSalesChart(data) {
                    var ctx = document.getElementById('sales-chart').getContext('2d');
                    
                    if (salesChart) {
                        salesChart.destroy();
                    }
                    
                    salesChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.dates,
                            datasets: data.datasets
                        },
                        options: {
                            responsive: true,
                            title: {
                                display: true,
                                text: '<?php _e('Sales by Platform', 'wc-multi-platform'); ?>'
                            },
                            tooltips: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(tooltipItem, data) {
                                        var label = data.datasets[tooltipItem.datasetIndex].label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(tooltipItem.yLabel);
                                        return label;
                                    }
                                }
                            },
                            scales: {
                                xAxes: [{
                                    display: true,
                                    scaleLabel: {
                                        display: true,
                                        labelString: '<?php _e('Date', 'wc-multi-platform'); ?>'
                                    }
                                }],
                                yAxes: [{
                                    display: true,
                                    scaleLabel: {
                                        display: true,
                                        labelString: '<?php _e('Sales', 'wc-multi-platform'); ?>'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
                                        }
                                    }
                                }]
                            }
                        }
                    });
                }
                
                // Load sales report data
                function loadSalesReport() {
                    var filters = $('#sales-report-filter').serialize();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_mp_get_sales_report',
                            filters: filters,
                            nonce: '<?php echo wp_create_nonce('wc_mp_reports'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                
                                // Update chart
                                initSalesChart(data.chart);
                                
                                // Update summary
                                $('#total-sales').text(data.summary.total_sales);
                                $('#total-orders').text(data.summary.total_orders);
                                $('#avg-order-value').text(data.summary.avg_order_value);
                                
                                // Update table
                                var tableHtml = '';
                                $.each(data.table, function(i, row) {
                                    tableHtml += '<tr>';
                                    tableHtml += '<td>' + row.platform + '</td>';
                                    tableHtml += '<td>' + row.orders + '</td>';
                                    tableHtml += '<td>' + row.sales + '</td>';
                                    tableHtml += '<td>' + row.avg_order_value + '</td>';
                                    tableHtml += '<td>' + row.percentage + '</td>';
                                    tableHtml += '</tr>';
                                });
                                
                                $('#sales-by-platform-table').html(tableHtml);
                            } else {
                                alert(response.data.message || 'Error loading report data');
                            }
                        },
                        error: function() {
                            alert('Error connecting to the server');
                        }
                    });
                }
                
                // Initial load
                loadSalesReport();
                
                // Handle filter form submission
                $('#sales-report-filter').on('submit', function(e) {
                    e.preventDefault();
                    loadSalesReport();
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render inventory report
     */
    public function render_inventory_report() {
        ?>
        <div class="wc-mp-report">
            <h2><?php