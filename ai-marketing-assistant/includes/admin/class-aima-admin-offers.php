<?php
/**
 * Offers admin page
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin_Offers {

    /**
     * Render offers page
     */
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $offer_id = isset($_GET['offer_id']) ? intval($_GET['offer_id']) : 0;

        switch ($action) {
            case 'edit':
                $this->render_edit_offer($offer_id);
                break;
            case 'create':
                $this->render_create_offer();
                break;
            case 'view':
                $this->render_view_offer($offer_id);
                break;
            default:
                $this->render_offers_list();
                break;
        }
    }

    /**
     * Render offers list
     */
    private function render_offers_list() {
        if (isset($_POST['save_offer'])) {
            $this->save_offer();
        }

        $offers = AIMA_Database::get_offers();
        $segments = AIMA_Database::get_segments('active');

        include AIMA_ADMIN_DIR . 'views/offers-list.php';
    }

    /**
     * Render create offer form
     */
    private function render_create_offer() {
        $segments = AIMA_Database::get_segments('active');

        // Get WooCommerce products
        $products = array();
        if (class_exists('WooCommerce')) {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );
            $product_query = new WP_Query($args);
            $products = $product_query->posts;
        }

        include AIMA_ADMIN_DIR . 'views/offer-edit.php';
    }

    /**
     * Render edit offer form
     */
    private function render_edit_offer($offer_id) {
        $offer = AIMA_Database::get_offer($offer_id);

        if (!$offer) {
            wp_die(__('Offer not found', 'ai-marketing-assistant'));
        }

        $segments = AIMA_Database::get_segments('active');

        $products = array();
        if (class_exists('WooCommerce')) {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );
            $product_query = new WP_Query($args);
            $products = $product_query->posts;
        }

        include AIMA_ADMIN_DIR . 'views/offer-edit.php';
    }

    /**
     * Render view offer statistics
     */
    private function render_view_offer($offer_id) {
        $offer = AIMA_Database::get_offer($offer_id);

        if (!$offer) {
            wp_die(__('Offer not found', 'ai-marketing-assistant'));
        }

        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted
            FROM $recipients_table
            WHERE offer_id = %d",
            $offer_id
        ));

        include AIMA_ADMIN_DIR . 'views/offer-stats.php';
    }

    /**
     * Save offer
     */
    private function save_offer() {
        check_admin_referer('aima_save_offer');

        $offer_id = isset($_POST['offer_id']) ? intval($_POST['offer_id']) : 0;

        $data = array(
            'name' => sanitize_text_field($_POST['offer_name']),
            'type' => sanitize_text_field($_POST['offer_type']),
            'subject' => sanitize_text_field($_POST['offer_subject']),
            'banner_url' => esc_url_raw($_POST['offer_banner_url']),
            'content' => wp_kses_post($_POST['offer_content']),
            'products' => wp_json_encode($_POST['offer_products']),
            'coupon_code' => sanitize_text_field($_POST['offer_coupon_code']),
            'discount_value' => floatval($_POST['offer_discount_value']),
            'discount_type' => sanitize_text_field($_POST['offer_discount_type']),
            'segment_id' => intval($_POST['offer_segment_id']),
            'status' => sanitize_text_field($_POST['offer_status']),
            'scheduled_at' => !empty($_POST['offer_scheduled_at']) ? sanitize_text_field($_POST['offer_scheduled_at']) : null
        );

        $result = AIMA_Database::upsert_offer($data, $offer_id);

        if ($result) {
            add_settings_error(
                'aima_messages',
                'aima_message',
                __('Offer saved successfully', 'ai-marketing-assistant'),
                'updated'
            );
        }
    }
}
