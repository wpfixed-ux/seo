<?php
/**
 * Offer generator module
 *
 * @package AIMarketingAssistant
 */

class AIMA_Offer_Generator {

    private $ai_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_client = new AIMA_AI_Client();
    }

    /**
     * Generate personalized offer
     */
    public function generate_personalized_offer($segment_id, $product_ids = array(), $campaign_type = 'promotional') {
        // Get segment data
        global $wpdb;
        $segments_table = $wpdb->prefix . 'aima_segments';
        $segment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $segments_table WHERE id = %d",
            $segment_id
        ));

        if (!$segment) {
            return array(
                'success' => false,
                'error' => __('Segment not found', 'ai-marketing-assistant')
            );
        }

        // Get segment customers for analysis
        $customers = AIMA_Database::get_segment_customers($segment_id);

        // Analyze segment purchase patterns
        $purchase_patterns = $this->analyze_segment_patterns($customers);

        $segment_data = array(
            'name' => $segment->name,
            'description' => $segment->description,
            'customer_count' => $segment->customer_count,
            'purchase_patterns' => $purchase_patterns
        );

        // Get product data
        $products_data = $this->get_products_data($product_ids);

        // If no products specified, recommend based on segment patterns
        if (empty($products_data) && !empty($purchase_patterns['top_products'])) {
            $recommended_ids = array_slice(
                array_column($purchase_patterns['top_products'], 'product_id'),
                0,
                5
            );
            $products_data = $this->get_products_data($recommended_ids);
        }

        // Generate offer using AI
        $ai_response = $this->ai_client->generate_offer($segment_data, $products_data, $campaign_type);

        if (!$ai_response['success']) {
            return $ai_response;
        }

        // Parse AI response
        $offer_data = $this->ai_client->parse_json_response($ai_response['content']);

        if (!$offer_data['success']) {
            return $offer_data;
        }

        // Generate coupon code
        $coupon_code = $this->generate_coupon_code($segment->name);

        // Create WooCommerce coupon if available
        if (class_exists('WooCommerce')) {
            $discount_amount = $offer_data['data']['discount_suggestion'] ?? 10;
            $this->create_woocommerce_coupon($coupon_code, $discount_amount, $product_ids);
        }

        // Prepare final offer
        $offer = array(
            'headline' => $offer_data['data']['headline'] ?? '',
            'subheadline' => $offer_data['data']['subheadline'] ?? '',
            'body' => $offer_data['data']['body'] ?? '',
            'cta_text' => $offer_data['data']['cta_text'] ?? 'Купить сейчас',
            'urgency' => $offer_data['data']['urgency'] ?? '',
            'personalization_note' => $offer_data['data']['personalization_note'] ?? '',
            'banner_concept' => $offer_data['data']['banner_concept'] ?? '',
            'coupon_code' => $coupon_code,
            'discount_value' => $offer_data['data']['discount_suggestion'] ?? 10,
            'discount_type' => 'percent',
            'products' => $products_data,
            'segment_id' => $segment_id
        );

        return array(
            'success' => true,
            'offer' => $offer
        );
    }

    /**
     * Analyze segment purchase patterns
     */
    private function analyze_segment_patterns($customers) {
        if (empty($customers)) {
            return array();
        }

        global $wpdb;
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        $customer_ids = array_column($customers, 'id');
        $ids_placeholder = implode(',', array_fill(0, count($customer_ids), '%d'));

        // Get top products for this segment
        $top_products = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, product_name, category,
                    COUNT(*) as purchase_count,
                    SUM(total) as total_revenue
             FROM $purchases_table
             WHERE customer_id IN ($ids_placeholder)
             GROUP BY product_id
             ORDER BY purchase_count DESC
             LIMIT 10",
            $customer_ids
        ));

        // Get top categories
        $top_categories = $wpdb->get_results($wpdb->prepare(
            "SELECT category, COUNT(*) as purchase_count
             FROM $purchases_table
             WHERE customer_id IN ($ids_placeholder) AND category IS NOT NULL
             GROUP BY category
             ORDER BY purchase_count DESC
             LIMIT 5",
            $customer_ids
        ));

        // Calculate average order value
        $avg_order = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(order_total) FROM (
                SELECT SUM(total) as order_total
                FROM $purchases_table
                WHERE customer_id IN ($ids_placeholder)
                GROUP BY order_id
            ) as orders",
            $customer_ids
        ));

        // Calculate average purchase frequency
        $avg_frequency = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(order_count) FROM (
                SELECT COUNT(DISTINCT order_id) as order_count
                FROM $purchases_table
                WHERE customer_id IN ($ids_placeholder)
                GROUP BY customer_id
            ) as customer_orders",
            $customer_ids
        ));

        return array(
            'top_products' => $top_products,
            'top_categories' => array_column($top_categories, 'category'),
            'avg_order_value' => round($avg_order, 2),
            'frequency_days' => $avg_frequency ? round(90 / $avg_frequency, 0) : 30
        );
    }

    /**
     * Get products data
     */
    private function get_products_data($product_ids) {
        if (empty($product_ids) || !class_exists('WooCommerce')) {
            return array();
        }

        $products_data = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $products_data[] = array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'description' => wp_trim_words($product->get_description(), 30),
                'short_description' => $product->get_short_description(),
                'image_url' => wp_get_attachment_url($product->get_image_id()),
                'permalink' => $product->get_permalink(),
                'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'))
            );
        }

        return $products_data;
    }

    /**
     * Generate unique coupon code
     */
    private function generate_coupon_code($segment_name) {
        $prefix = strtoupper(substr(sanitize_title($segment_name), 0, 4));
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return $prefix . '-' . $random;
    }

    /**
     * Create WooCommerce coupon
     */
    private function create_woocommerce_coupon($coupon_code, $discount_amount, $product_ids = array()) {
        if (!class_exists('WooCommerce')) {
            return false;
        }

        // Check if coupon already exists
        $existing_coupon = new WC_Coupon($coupon_code);
        if ($existing_coupon->get_id()) {
            return $existing_coupon->get_id();
        }

        // Create new coupon
        $coupon = new WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount($discount_amount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1000);
        $coupon->set_usage_limit_per_user(1);

        // Set expiry date (30 days from now)
        $expiry_date = date('Y-m-d', strtotime('+30 days'));
        $coupon->set_date_expires($expiry_date);

        // Restrict to specific products if provided
        if (!empty($product_ids)) {
            $coupon->set_product_ids($product_ids);
        }

        $coupon->save();

        return $coupon->get_id();
    }

    /**
     * Generate email content from offer
     */
    public function generate_email_content($offer, $customer_data = array()) {
        $personalization = !empty($customer_data['first_name'])
            ? "Привет, {$customer_data['first_name']}!"
            : $offer['personalization_note'];

        // Build email HTML
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

        // Header with headline
        $html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">';
        $html .= '<h1 style="margin: 0; font-size: 28px;">' . esc_html($offer['headline']) . '</h1>';
        if (!empty($offer['subheadline'])) {
            $html .= '<p style="margin: 10px 0 0; font-size: 16px; opacity: 0.9;">' . esc_html($offer['subheadline']) . '</p>';
        }
        $html .= '</div>';

        // Personalization
        if (!empty($personalization)) {
            $html .= '<div style="padding: 20px; background: #f8f9fa;">';
            $html .= '<p style="margin: 0; font-size: 16px; color: #333;">' . esc_html($personalization) . '</p>';
            $html .= '</div>';
        }

        // Body content
        $html .= '<div style="padding: 30px;">';
        $html .= '<div style="font-size: 16px; line-height: 1.6; color: #333;">' . wpautop(esc_html($offer['body'])) . '</div>';

        // Products
        if (!empty($offer['products'])) {
            $html .= '<div style="margin: 30px 0;">';
            foreach ($offer['products'] as $product) {
                $html .= '<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; align-items: center;">';

                if (!empty($product['image_url'])) {
                    $html .= '<img src="' . esc_url($product['image_url']) . '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; margin-right: 15px;" />';
                }

                $html .= '<div>';
                $html .= '<h3 style="margin: 0 0 5px; font-size: 18px;">' . esc_html($product['name']) . '</h3>';
                $html .= '<p style="margin: 0; color: #666; font-size: 14px;">' . esc_html($product['short_description']) . '</p>';
                $html .= '<p style="margin: 10px 0 0; font-size: 20px; font-weight: bold; color: #667eea;">' . number_format($product['price'], 0, ',', ' ') . ' ₽</p>';
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Coupon
        if (!empty($offer['coupon_code'])) {
            $html .= '<div style="background: #fff3cd; border: 2px dashed #ffc107; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;">';
            $html .= '<p style="margin: 0 0 10px; font-size: 14px; color: #856404;">Используйте промокод:</p>';
            $html .= '<p style="margin: 0; font-size: 24px; font-weight: bold; color: #856404; letter-spacing: 2px;">' . esc_html($offer['coupon_code']) . '</p>';
            $html .= '<p style="margin: 10px 0 0; font-size: 14px; color: #856404;">Скидка ' . esc_html($offer['discount_value']) . '%</p>';
            $html .= '</div>';
        }

        // Urgency
        if (!empty($offer['urgency'])) {
            $html .= '<div style="background: #fee; border-left: 4px solid #f44336; padding: 15px; margin: 20px 0;">';
            $html .= '<p style="margin: 0; color: #c62828; font-weight: bold;">⏰ ' . esc_html($offer['urgency']) . '</p>';
            $html .= '</div>';
        }

        // CTA Button
        $shop_url = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/shop');
        $html .= '<div style="text-align: center; margin: 30px 0;">';
        $html .= '<a href="' . esc_url($shop_url) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; padding: 15px 40px; border-radius: 50px; font-size: 18px; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">';
        $html .= esc_html($offer['cta_text']);
        $html .= '</a>';
        $html .= '</div>';

        $html .= '</div>';

        // Footer
        $html .= '<div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">';
        $html .= '<p style="margin: 0;">© ' . date('Y') . ' ' . get_bloginfo('name') . '. Все права защищены.</p>';
        $html .= '<p style="margin: 10px 0 0;"><a href="{unsubscribe_url}" style="color: #666;">Отписаться от рассылки</a></p>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
