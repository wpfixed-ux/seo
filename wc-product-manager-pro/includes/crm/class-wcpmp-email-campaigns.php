<?php
/**
 * Email Campaigns with AI personalization
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_Email_Campaigns {

    private $openai;

    public function __construct() {
        $this->openai = new WCPMP_OpenAI();
    }

    /**
     * Get all campaigns
     */
    public function get_all($args = array()) {
        global $wpdb;

        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'status' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $values = array();

        if ($args['status']) {
            $where .= ' AND status = %s';
            $values[] = $args['status'];
        }

        $offset = ($args['page'] - 1) * $args['per_page'];

        $sql = "
            SELECT c.*, s.name as segment_name
            FROM {$wpdb->prefix}wcpmp_campaigns c
            LEFT JOIN {$wpdb->prefix}wcpmp_segments s ON c.segment_id = s.id
            WHERE $where
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $values[] = $args['per_page'];
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Get recent campaigns
     */
    public function get_recent($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT c.*, s.name as segment_name
            FROM {$wpdb->prefix}wcpmp_campaigns c
            LEFT JOIN {$wpdb->prefix}wcpmp_segments s ON c.segment_id = s.id
            ORDER BY c.created_at DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get single campaign
     */
    public function get($campaign_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_campaigns WHERE id = %d",
            $campaign_id
        ));
    }

    /**
     * Create campaign
     */
    public function create($data) {
        global $wpdb;

        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'subject_uk' => sanitize_text_field($data['subject_uk']),
            'subject_ru' => sanitize_text_field($data['subject_ru'] ?? ''),
            'content_uk' => wp_kses_post($data['content_uk']),
            'content_ru' => wp_kses_post($data['content_ru'] ?? ''),
            'segment_id' => intval($data['segment_id'] ?? 0) ?: null,
            'status' => 'draft',
            'ai_generated' => isset($data['ai_generated']) ? 1 : 0
        );

        // Coupon settings
        if (!empty($data['coupon_code'])) {
            $insert_data['coupon_code'] = sanitize_text_field($data['coupon_code']);
            $insert_data['discount_type'] = sanitize_text_field($data['discount_type'] ?? 'percent');
            $insert_data['discount_value'] = floatval($data['discount_value'] ?? 0);
        }

        // Schedule
        if (!empty($data['scheduled_at'])) {
            $insert_data['scheduled_at'] = $data['scheduled_at'];
            $insert_data['status'] = 'scheduled';
        }

        $result = $wpdb->insert($wpdb->prefix . 'wcpmp_campaigns', $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create campaign', 'wc-product-manager-pro'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update campaign
     */
    public function update($campaign_id, $data) {
        global $wpdb;

        $update = array();

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['subject_uk'])) {
            $update['subject_uk'] = sanitize_text_field($data['subject_uk']);
        }
        if (isset($data['subject_ru'])) {
            $update['subject_ru'] = sanitize_text_field($data['subject_ru']);
        }
        if (isset($data['content_uk'])) {
            $update['content_uk'] = wp_kses_post($data['content_uk']);
        }
        if (isset($data['content_ru'])) {
            $update['content_ru'] = wp_kses_post($data['content_ru']);
        }
        if (isset($data['segment_id'])) {
            $update['segment_id'] = intval($data['segment_id']) ?: null;
        }
        if (isset($data['scheduled_at'])) {
            $update['scheduled_at'] = $data['scheduled_at'];
        }
        if (isset($data['status'])) {
            $update['status'] = sanitize_text_field($data['status']);
        }

        return $wpdb->update(
            $wpdb->prefix . 'wcpmp_campaigns',
            $update,
            array('id' => $campaign_id)
        );
    }

    /**
     * Delete campaign
     */
    public function delete($campaign_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wcpmp_campaigns', array('id' => $campaign_id));
    }

    /**
     * Generate AI content for campaign
     */
    public function generate_ai_content($segment_id, $campaign_type, $products = array()) {
        $segments = new WCPMP_Segments();
        $segment = $segments->get($segment_id);

        $segment_info = $segment ? $segment->name . ': ' . $segment->description : 'All customers';

        // Get featured products if not provided
        if (empty($products)) {
            global $wpdb;
            $products = $wpdb->get_results("
                SELECT id, name_uk, price, sale_price
                FROM {$wpdb->prefix}wcpmp_products
                WHERE stock_quantity > 0
                ORDER BY RAND()
                LIMIT 5
            ", ARRAY_A);
        }

        // Generate for both languages
        $content_uk = $this->openai->generate_email_content($segment_info, $campaign_type, $products, 'uk');
        $content_ru = $this->openai->generate_email_content($segment_info, $campaign_type, $products, 'ru');

        if (is_wp_error($content_uk)) {
            return $content_uk;
        }

        return array(
            'uk' => $content_uk,
            'ru' => is_wp_error($content_ru) ? $content_uk : $content_ru
        );
    }

    /**
     * Send campaign
     */
    public function send($campaign_id) {
        global $wpdb;

        $campaign = $this->get($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', __('Campaign not found', 'wc-product-manager-pro'));
        }

        if ($campaign->status === 'sent') {
            return new WP_Error('already_sent', __('Campaign already sent', 'wc-product-manager-pro'));
        }

        // Get recipients
        if ($campaign->segment_id) {
            $segments = new WCPMP_Segments();
            $customers = $segments->get_customers($campaign->segment_id);
        } else {
            $customers = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}wcpmp_customers WHERE subscribed = 1"
            );
        }

        if (empty($customers)) {
            return new WP_Error('no_recipients', __('No recipients found', 'wc-product-manager-pro'));
        }

        $sent = 0;
        $from_name = get_option('wcpmp_email_from_name', get_bloginfo('name'));
        $from_email = get_option('wcpmp_email_from_address', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $from_name <$from_email>"
        );

        foreach ($customers as $customer) {
            // Get appropriate language content
            $language = $customer->language ?: 'uk';
            $subject = $language === 'uk' ? $campaign->subject_uk : ($campaign->subject_ru ?: $campaign->subject_uk);
            $content = $language === 'uk' ? $campaign->content_uk : ($campaign->content_ru ?: $campaign->content_uk);

            // Replace placeholders
            $content = $this->replace_placeholders($content, $customer, $campaign);
            $subject = $this->replace_placeholders($subject, $customer, $campaign);

            // Send email
            $result = wp_mail($customer->email, $subject, $content, $headers);

            if ($result) {
                $sent++;
            }

            // Rate limiting
            usleep(100000); // 0.1 second delay
        }

        // Update campaign status
        $wpdb->update($wpdb->prefix . 'wcpmp_campaigns', array(
            'status' => 'sent',
            'sent_at' => current_time('mysql'),
            'sent_count' => $sent
        ), array('id' => $campaign_id));

        return $sent;
    }

    /**
     * Replace placeholders in content
     */
    private function replace_placeholders($content, $customer, $campaign) {
        $replacements = array(
            '{customer_name}' => trim($customer->first_name . ' ' . $customer->last_name) ?: $customer->email,
            '{first_name}' => $customer->first_name ?: '',
            '{last_name}' => $customer->last_name ?: '',
            '{email}' => $customer->email,
            '{coupon_code}' => $campaign->coupon_code ?: '',
            '{discount_value}' => $campaign->discount_value ?: '',
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Process scheduled campaigns (cron)
     */
    public function process_scheduled() {
        global $wpdb;

        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}wcpmp_campaigns
            WHERE status = 'scheduled' AND scheduled_at <= %s
        ", current_time('mysql')));

        foreach ($campaigns as $campaign) {
            $this->send($campaign->id);
        }
    }

    /**
     * Get campaign statistics
     */
    public function get_statistics($campaign_id) {
        $campaign = $this->get($campaign_id);
        if (!$campaign) {
            return null;
        }

        return array(
            'sent' => $campaign->sent_count,
            'opened' => $campaign->open_count,
            'clicked' => $campaign->click_count,
            'open_rate' => $campaign->sent_count > 0 ? round(($campaign->open_count / $campaign->sent_count) * 100, 2) : 0,
            'click_rate' => $campaign->sent_count > 0 ? round(($campaign->click_count / $campaign->sent_count) * 100, 2) : 0
        );
    }
}
