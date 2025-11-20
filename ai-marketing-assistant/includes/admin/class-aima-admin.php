<?php
/**
 * Admin functionality
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin {

    /**
     * Register admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Marketing Assistant', 'ai-marketing-assistant'),
            __('AI Marketing', 'ai-marketing-assistant'),
            'manage_options',
            'aima-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-area',
            30
        );

        add_submenu_page(
            'aima-dashboard',
            __('Dashboard', 'ai-marketing-assistant'),
            __('Dashboard', 'ai-marketing-assistant'),
            'manage_options',
            'aima-dashboard',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'aima-dashboard',
            __('Analytics', 'ai-marketing-assistant'),
            __('Analytics', 'ai-marketing-assistant'),
            'manage_options',
            'aima-analytics',
            array(new AIMA_Admin_Analytics(), 'render_page')
        );

        add_submenu_page(
            'aima-dashboard',
            __('Customer Segments', 'ai-marketing-assistant'),
            __('Segments', 'ai-marketing-assistant'),
            'manage_options',
            'aima-segments',
            array(new AIMA_Admin_Segments(), 'render_page')
        );

        add_submenu_page(
            'aima-dashboard',
            __('Offers & Campaigns', 'ai-marketing-assistant'),
            __('Offers', 'ai-marketing-assistant'),
            'manage_options',
            'aima-offers',
            array(new AIMA_Admin_Offers(), 'render_page')
        );

        add_submenu_page(
            'aima-dashboard',
            __('Import Customers', 'ai-marketing-assistant'),
            __('Import', 'ai-marketing-assistant'),
            'manage_options',
            'aima-import',
            array(new AIMA_Admin_Import(), 'render_page')
        );

        add_submenu_page(
            'aima-dashboard',
            __('Settings', 'ai-marketing-assistant'),
            __('Settings', 'ai-marketing-assistant'),
            'manage_options',
            'aima-settings',
            array(new AIMA_Admin_Settings(), 'render_page')
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $analytics = AIMA_Database::get_analytics_summary(30);
        $top_products = AIMA_Database::get_top_products(5, 30);
        $segments = AIMA_Database::get_segments('active');
        $recent_offers = AIMA_Database::get_offers('sent');

        include AIMA_ADMIN_DIR . 'views/dashboard.php';
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'aima-') === false) {
            return;
        }

        wp_enqueue_style(
            'aima-admin-style',
            AIMA_ASSETS_URL . 'css/admin.css',
            array(),
            AIMA_VERSION
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'aima-') === false) {
            return;
        }

        wp_enqueue_script(
            'aima-admin-script',
            AIMA_ASSETS_URL . 'js/admin.js',
            array('jquery'),
            AIMA_VERSION,
            true
        );

        wp_localize_script('aima-admin-script', 'aimaAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aima-ajax-nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'ai-marketing-assistant'),
                'processing' => __('Processing...', 'ai-marketing-assistant'),
                'error' => __('An error occurred. Please try again.', 'ai-marketing-assistant'),
            )
        ));
    }

    /**
     * Handle CSV import AJAX
     */
    public function handle_csv_import() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        $import_handler = new AIMA_Admin_Import();
        $result = $import_handler->process_csv_upload();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle generate offer AJAX
     */
    public function handle_generate_offer() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();

        $generator = new AIMA_Offer_Generator();
        $offer = $generator->generate_personalized_offer($segment_id, $product_ids);

        if ($offer) {
            wp_send_json_success(array('offer' => $offer));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate offer', 'ai-marketing-assistant')));
        }
    }

    /**
     * Handle send campaign AJAX
     */
    public function handle_send_campaign() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;
        $send_method = isset($_POST['send_method']) ? sanitize_text_field($_POST['send_method']) : 'email';

        if ($send_method === 'email') {
            $campaign = new AIMA_Email_Campaign();
            $result = $campaign->send_campaign($offer_id);
        } else {
            $telegram = new AIMA_Telegram_Bot();
            $result = $telegram->send_campaign($offer_id);
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle update segment AJAX
     */
    public function handle_update_segment() {
        check_ajax_referer('aima-ajax-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'ai-marketing-assistant')));
        }

        $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;

        $segmentation = new AIMA_Segmentation_Engine();
        $result = $segmentation->update_segment($segment_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Segment updated successfully', 'ai-marketing-assistant'),
                'customer_count' => $result
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update segment', 'ai-marketing-assistant')));
        }
    }

    /**
     * Handle test API connection AJAX
     */
    public function handle_test_api() {
        $settings = new AIMA_Admin_Settings();
        $settings->test_api_connection();
    }

    /**
     * Handle get provider models AJAX
     */
    public function handle_get_models() {
        $settings = new AIMA_Admin_Settings();
        $settings->get_provider_models();
    }
}
