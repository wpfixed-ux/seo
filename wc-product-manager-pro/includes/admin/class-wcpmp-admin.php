<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Admin {

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Product Manager', 'wc-product-manager-pro'),
            __('Product Manager', 'wc-product-manager-pro'),
            'manage_wcpmp',
            'wcpmp-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-store',
            56
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Dashboard', 'wc-product-manager-pro'),
            __('Dashboard', 'wc-product-manager-pro'),
            'manage_wcpmp',
            'wcpmp-dashboard',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Products', 'wc-product-manager-pro'),
            __('Products', 'wc-product-manager-pro'),
            'manage_wcpmp_products',
            'wcpmp-products',
            array($this, 'render_products')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Import', 'wc-product-manager-pro'),
            __('Import', 'wc-product-manager-pro'),
            'manage_wcpmp_products',
            'wcpmp-import',
            array($this, 'render_import')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Stores', 'wc-product-manager-pro'),
            __('Stores', 'wc-product-manager-pro'),
            'manage_wcpmp_stores',
            'wcpmp-stores',
            array($this, 'render_stores')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Orders', 'wc-product-manager-pro'),
            __('Orders', 'wc-product-manager-pro'),
            'manage_wcpmp',
            'wcpmp-orders',
            array($this, 'render_orders')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Inventory', 'wc-product-manager-pro'),
            __('Inventory', 'wc-product-manager-pro'),
            'manage_wcpmp',
            'wcpmp-inventory',
            array($this, 'render_inventory')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('CRM', 'wc-product-manager-pro'),
            __('CRM', 'wc-product-manager-pro'),
            'manage_wcpmp_crm',
            'wcpmp-crm',
            array($this, 'render_crm')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Campaigns', 'wc-product-manager-pro'),
            __('Campaigns', 'wc-product-manager-pro'),
            'manage_wcpmp_crm',
            'wcpmp-campaigns',
            array($this, 'render_campaigns')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Telegram Bot', 'wc-product-manager-pro'),
            __('Telegram Bot', 'wc-product-manager-pro'),
            'manage_wcpmp',
            'wcpmp-telegram',
            array($this, 'render_telegram')
        );

        add_submenu_page(
            'wcpmp-dashboard',
            __('Settings', 'wc-product-manager-pro'),
            __('Settings', 'wc-product-manager-pro'),
            'manage_wcpmp_settings',
            'wcpmp-settings',
            array($this, 'render_settings')
        );
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wcpmp') === false) {
            return;
        }

        wp_enqueue_style(
            'wcpmp-admin',
            WCPMP_ASSETS_URL . 'css/admin.css',
            array(),
            WCPMP_VERSION
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wcpmp') === false) {
            return;
        }

        wp_enqueue_script(
            'wcpmp-admin',
            WCPMP_ASSETS_URL . 'js/admin.js',
            array('jquery'),
            WCPMP_VERSION,
            true
        );

        wp_localize_script('wcpmp-admin', 'wcpmp', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcpmp_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'wc-product-manager-pro'),
                'processing' => __('Processing...', 'wc-product-manager-pro'),
                'success' => __('Success!', 'wc-product-manager-pro'),
                'error' => __('Error occurred', 'wc-product-manager-pro'),
            )
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Keys
        register_setting('wcpmp_settings', 'wcpmp_openai_api_key');
        register_setting('wcpmp_settings', 'wcpmp_openai_model');
        register_setting('wcpmp_settings', 'wcpmp_gemini_api_key');
        register_setting('wcpmp_settings', 'wcpmp_telegram_bot_token');

        // General
        register_setting('wcpmp_settings', 'wcpmp_default_language');
        register_setting('wcpmp_settings', 'wcpmp_sync_interval');
        register_setting('wcpmp_settings', 'wcpmp_global_sync_enabled');

        // Email
        register_setting('wcpmp_settings', 'wcpmp_email_from_name');
        register_setting('wcpmp_settings', 'wcpmp_email_from_address');

        // AI
        register_setting('wcpmp_settings', 'wcpmp_seo_description_length');
        register_setting('wcpmp_settings', 'wcpmp_ai_temperature');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $warehouse = new WCPMP_Warehouse();
        $crm = new WCPMP_CRM();
        $sync = new WCPMP_Sync_Manager();

        $data = array(
            'warehouse' => $warehouse->get_dashboard_data(),
            'crm' => $crm->get_dashboard(),
            'sync' => $sync->get_sync_status()
        );

        include WCPMP_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render products page
     */
    public function render_products() {
        include WCPMP_PLUGIN_DIR . 'templates/admin/products.php';
    }

    /**
     * Render import page
     */
    public function render_import() {
        include WCPMP_PLUGIN_DIR . 'templates/admin/import.php';
    }

    /**
     * Render stores page
     */
    public function render_stores() {
        $store_manager = new WCPMP_Store_Manager();
        $stores = $store_manager->get_stores();

        include WCPMP_PLUGIN_DIR . 'templates/admin/stores.php';
    }

    /**
     * Render orders page
     */
    public function render_orders() {
        $orders = new WCPMP_Orders();

        include WCPMP_PLUGIN_DIR . 'templates/admin/orders.php';
    }

    /**
     * Render inventory page
     */
    public function render_inventory() {
        $tracker = new WCPMP_Inventory_Tracker();

        include WCPMP_PLUGIN_DIR . 'templates/admin/inventory.php';
    }

    /**
     * Render CRM page
     */
    public function render_crm() {
        $crm = new WCPMP_CRM();

        include WCPMP_PLUGIN_DIR . 'templates/admin/crm.php';
    }

    /**
     * Render campaigns page
     */
    public function render_campaigns() {
        $campaigns = new WCPMP_Email_Campaigns();

        include WCPMP_PLUGIN_DIR . 'templates/admin/campaigns.php';
    }

    /**
     * Render telegram page
     */
    public function render_telegram() {
        $bot = new WCPMP_Telegram_Bot();
        $kb = new WCPMP_Knowledge_Base();

        include WCPMP_PLUGIN_DIR . 'templates/admin/telegram.php';
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        include WCPMP_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * AJAX handler for updating prices
     */
    public function ajax_update_prices() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_id = intval($_POST['product_id']);
        $prices = $_POST['prices'] ?? array();

        if (empty($prices)) {
            wp_send_json_error(__('No prices provided', 'wc-product-manager-pro'));
        }

        $store_manager = new WCPMP_Store_Manager();
        $result = $store_manager->update_prices($product_id, $prices);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for bulk publish
     */
    public function ajax_bulk_publish() {
        check_ajax_referer('wcpmp_nonce', 'nonce');

        if (!current_user_can('manage_wcpmp_products')) {
            wp_send_json_error(__('Permission denied', 'wc-product-manager-pro'));
        }

        $product_ids = array_map('intval', $_POST['product_ids'] ?? array());
        $store_ids = array_map('intval', $_POST['store_ids'] ?? array());

        if (empty($product_ids) || empty($store_ids)) {
            wp_send_json_error(__('Invalid parameters', 'wc-product-manager-pro'));
        }

        global $wpdb;
        $prom_ua = new WCPMP_Prom_UA();
        $woo_api = new WCPMP_WooCommerce_API();

        $results = array();
        $errors = array();

        foreach ($product_ids as $product_id) {
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wcpmp_products WHERE id = %d",
                $product_id
            ));

            if (!$product) continue;

            foreach ($store_ids as $store_id) {
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT type FROM {$wpdb->prefix}wcpmp_stores WHERE id = %d",
                    $store_id
                ));

                if (!$store) continue;

                // Simulate publish call
                $_POST['product_id'] = $product_id;
                $_POST['store_ids'] = array($store_id);

                if ($store->type === 'prom_ua') {
                    $product_data = $prom_ua->prepare_product_data($product, $store_id);
                } else {
                    $product_data = $woo_api->prepare_product_data($product, $store_id);
                }

                $results[] = array(
                    'product_id' => $product_id,
                    'store_id' => $store_id,
                    'status' => 'queued'
                );
            }
        }

        wp_send_json_success(array(
            'queued' => count($results),
            'errors' => $errors
        ));
    }
}
