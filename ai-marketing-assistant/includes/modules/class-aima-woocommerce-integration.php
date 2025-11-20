<?php
/**
 * WooCommerce integration module
 *
 * @package AIMarketingAssistant
 */

class AIMA_WooCommerce_Integration {

    /**
     * Track new order
     */
    public function track_new_order($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Get or create customer
        $billing_email = $order->get_billing_email();

        if (empty($billing_email)) {
            return;
        }

        $customer_data = array(
            'email' => $billing_email,
            'phone' => $order->get_billing_phone(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'source' => 'woocommerce'
        );

        $customer_id = AIMA_Database::upsert_customer($customer_data);

        // Add order items to purchase history
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            // Get product categories
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
            $category = !empty($categories) ? $categories[0] : null;

            $purchase_data = array(
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'category' => $category,
                'quantity' => $item->get_quantity(),
                'price' => $item->get_subtotal() / $item->get_quantity(),
                'total' => $item->get_total(),
                'order_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'source' => 'woocommerce'
            );

            AIMA_Database::add_purchase($purchase_data);
        }
    }

    /**
     * Process completed order
     */
    public function process_completed_order($order_id) {
        // Update customer analytics when order is completed
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $customer = AIMA_Database::get_customer_by_email($order->get_billing_email());

        if (!$customer) {
            return;
        }

        // Recalculate customer analytics
        $analyzer = new AIMA_Customer_Analyzer();
        $analytics = $analyzer->calculate_customer_analytics($customer->id);

        // Update customer record
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $wpdb->update(
            $customers_table,
            array(
                'total_orders' => $analytics['total_orders'],
                'total_spent' => $analytics['total_spent'],
                'last_order_date' => $analytics['last_order_date'],
                'first_order_date' => $analytics['first_order_date']
            ),
            array('id' => $customer->id),
            array('%d', '%f', '%s', '%s'),
            array('%d')
        );

        // Update customer segments
        $segmentation = new AIMA_Segmentation_Engine();
        $segmentation->update_all_segments();
    }

    /**
     * Show Telegram subscription on thank you page
     */
    public function show_telegram_subscription($order_id) {
        $telegram_bot_token = get_option('aima_telegram_bot_token');

        if (empty($telegram_bot_token)) {
            return;
        }

        $order = wc_get_order($order_id);
        $customer = AIMA_Database::get_customer_by_email($order->get_billing_email());

        if (!$customer) {
            return;
        }

        // Check if customer already subscribed to Telegram
        global $wpdb;
        $telegram_table = $wpdb->prefix . 'aima_telegram_subscribers';

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $telegram_table WHERE customer_id = %d AND is_active = 1",
            $customer->id
        ));

        if ($subscription) {
            return; // Already subscribed
        }

        // Get bot username
        $telegram_bot = new AIMA_Telegram_Bot();
        $bot_info = $telegram_bot->get_me();

        if (!$bot_info['success']) {
            return;
        }

        $bot_username = $bot_info['result']['username'] ?? '';

        if (empty($bot_username)) {
            return;
        }

        ?>
        <div class="woocommerce-order-telegram-subscription" style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; text-align: center;">
            <h3 style="margin-top: 0; color: white;">🎁 Получай эксклюзивные предложения в Telegram!</h3>
            <p style="font-size: 16px; margin: 15px 0;">
                Подпишись на наш бот и получай персональные скидки, информацию о новых товарах и специальные предложения только для постоянных клиентов.
            </p>
            <a href="https://t.me/<?php echo esc_attr($bot_username); ?>?start=customer_<?php echo esc_attr($customer->id); ?>"
               target="_blank"
               style="display: inline-block; background: white; color: #667eea; padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 18px; margin-top: 15px;">
                📱 Подписаться в Telegram
            </a>
        </div>
        <?php
    }

    /**
     * Add customer referral source tracking
     */
    public function track_customer_source() {
        if (!is_checkout() || !WC()->session) {
            return;
        }

        // Track UTM parameters and referral
        $utm_source = isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : '';
        $utm_campaign = isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : '';
        $referrer = wp_get_referer();

        if ($utm_source || $utm_campaign || $referrer) {
            WC()->session->set('aima_customer_source', array(
                'utm_source' => $utm_source,
                'utm_campaign' => $utm_campaign,
                'referrer' => $referrer,
                'landing_page' => home_url($_SERVER['REQUEST_URI'])
            ));
        }
    }

    /**
     * Get recommended products for customer
     */
    public function get_recommended_products($customer_id, $limit = 10) {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'aima_purchase_history';

        // Get customer's purchase history
        $purchased_categories = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT category
            FROM $purchases_table
            WHERE customer_id = %d AND category IS NOT NULL",
            $customer_id
        ));

        if (empty($purchased_categories)) {
            return array();
        }

        // Get products from favorite categories that customer hasn't purchased
        $purchased_product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_id FROM $purchases_table WHERE customer_id = %d",
            $customer_id
        ));

        $exclude_ids = !empty($purchased_product_ids) ? $purchased_product_ids : array(0);

        // Query WooCommerce products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'post__not_in' => $exclude_ids,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'name',
                    'terms' => $purchased_categories
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_stock_status',
                    'value' => 'instock'
                )
            )
        );

        $products = new WP_Query($args);
        $recommendations = array();

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                $recommendations[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'permalink' => $product->get_permalink(),
                    'image' => wp_get_attachment_url($product->get_image_id())
                );
            }
            wp_reset_postdata();
        }

        return $recommendations;
    }

    /**
     * Add upsell recommendations widget
     */
    public function add_upsell_widget() {
        if (!is_user_logged_in()) {
            return;
        }

        $current_user = wp_get_current_user();
        $customer = AIMA_Database::get_customer_by_email($current_user->user_email);

        if (!$customer) {
            return;
        }

        $recommendations = $this->get_recommended_products($customer->id, 4);

        if (empty($recommendations)) {
            return;
        }

        ?>
        <div class="aima-recommendations-widget" style="margin: 30px 0; padding: 30px; background: #f8f9fa; border-radius: 10px;">
            <h3 style="margin-top: 0; color: #333;">🎯 Рекомендуем специально для вас</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <?php foreach ($recommendations as $product): ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo esc_url($product['image']); ?>" alt="<?php echo esc_attr($product['name']); ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 10px;" />
                        <?php endif; ?>
                        <h4 style="font-size: 16px; margin: 10px 0;"><?php echo esc_html($product['name']); ?></h4>
                        <p style="font-size: 20px; font-weight: bold; color: #667eea; margin: 10px 0;">
                            <?php echo number_format($product['price'], 0, ',', ' '); ?> ₽
                        </p>
                        <a href="<?php echo esc_url($product['permalink']); ?>" style="display: inline-block; background: #667eea; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-size: 14px;">
                            Подробнее
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
