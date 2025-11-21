<?php
/**
 * Campaign Analytics and Tracking Module
 *
 * Handles tracking of email opens, link clicks, and conversions
 *
 * @package AIMarketingAssistant
 */

class AIMA_Campaign_Analytics {

    /**
     * Track email open via pixel
     *
     * @param int $offer_id Campaign ID
     * @param string $email Customer email
     */
    public function track_open($offer_id, $email) {
        global $wpdb;

        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';
        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';
        $customers_table = $wpdb->prefix . 'aima_customers';

        // Get customer ID
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $customers_table WHERE email = %s",
            $email
        ));

        if (!$customer) {
            return false;
        }

        // Check if already opened
        $recipient = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $recipients_table WHERE offer_id = %d AND customer_id = %d",
            $offer_id,
            $customer->id
        ));

        if (!$recipient) {
            return false;
        }

        // Track first open time
        $is_first_open = empty($recipient->opened_at);

        if ($is_first_open) {
            $wpdb->update(
                $recipients_table,
                array('opened_at' => current_time('mysql')),
                array(
                    'offer_id' => $offer_id,
                    'customer_id' => $customer->id
                )
            );

            // Update unique opens count
            $wpdb->query($wpdb->prepare(
                "UPDATE $analytics_table SET unique_opens = unique_opens + 1 WHERE offer_id = %d",
                $offer_id
            ));
        }

        // Always increment total opens count
        $wpdb->query($wpdb->prepare(
            "UPDATE $analytics_table SET opened_count = opened_count + 1 WHERE offer_id = %d",
            $offer_id
        ));

        // Update campaign offers table
        $offers_table = $wpdb->prefix . 'aima_offers';
        $wpdb->query($wpdb->prepare(
            "UPDATE $offers_table SET opened_count = opened_count + 1 WHERE id = %d",
            $offer_id
        ));

        // Recalculate rates
        $this->update_campaign_rates($offer_id);

        return true;
    }

    /**
     * Track link click
     *
     * @param string $tracking_code Unique tracking code
     * @param string $email Customer email (optional)
     */
    public function track_click($tracking_code, $email = null) {
        global $wpdb;

        $links_table = $wpdb->prefix . 'aima_tracking_links';
        $click_table = $wpdb->prefix . 'aima_click_tracking';
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';
        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';
        $customers_table = $wpdb->prefix . 'aima_customers';

        // Get tracking link info
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $links_table WHERE tracking_code = %s",
            $tracking_code
        ));

        if (!$link) {
            return false;
        }

        // Get customer ID if email provided
        $customer_id = null;
        if ($email) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $customers_table WHERE email = %s",
                $email
            ));
            if ($customer) {
                $customer_id = $customer->id;
            }
        }

        // Check if this customer already clicked this link
        $is_first_click = true;
        if ($customer_id) {
            $existing_click = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $click_table WHERE tracking_code = %s AND customer_id = %d",
                $tracking_code,
                $customer_id
            ));
            $is_first_click = ($existing_click == 0);
        }

        // Record click event
        $wpdb->insert($click_table, array(
            'tracking_code' => $tracking_code,
            'customer_id' => $customer_id,
            'email' => $email,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'clicked_at' => current_time('mysql')
        ));

        // Update link click counts
        $wpdb->query($wpdb->prepare(
            "UPDATE $links_table SET click_count = click_count + 1 WHERE tracking_code = %s",
            $tracking_code
        ));

        if ($is_first_click) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $links_table SET unique_clicks = unique_clicks + 1 WHERE tracking_code = %s",
                $tracking_code
            ));
        }

        // Update campaign analytics
        $wpdb->query($wpdb->prepare(
            "UPDATE $analytics_table SET clicked_count = clicked_count + 1 WHERE offer_id = %d",
            $link->offer_id
        ));

        if ($is_first_click && $customer_id) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $analytics_table SET unique_clicks = unique_clicks + 1 WHERE offer_id = %d",
                $link->offer_id
            ));

            // Update recipient clicked status
            $wpdb->update(
                $recipients_table,
                array('clicked_at' => current_time('mysql')),
                array(
                    'offer_id' => $link->offer_id,
                    'customer_id' => $customer_id
                )
            );
        }

        // Update campaign offers table
        $offers_table = $wpdb->prefix . 'aima_offers';
        $wpdb->query($wpdb->prepare(
            "UPDATE $offers_table SET clicked_count = clicked_count + 1 WHERE id = %d",
            $link->offer_id
        ));

        // Recalculate rates
        $this->update_campaign_rates($link->offer_id);

        // Store tracking code in session for conversion attribution
        if (!session_id()) {
            session_start();
        }
        $_SESSION['aima_tracking_code'] = $tracking_code;
        $_SESSION['aima_offer_id'] = $link->offer_id;

        return $link->original_url;
    }

    /**
     * Track conversion (purchase)
     *
     * @param int $order_id WooCommerce order ID
     */
    public function track_conversion($order_id) {
        global $wpdb;

        // Check if WooCommerce order
        if (!function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $email = $order->get_billing_email();
        $total = $order->get_total();

        // Get customer
        $customers_table = $wpdb->prefix . 'aima_customers';
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $customers_table WHERE email = %s",
            $email
        ));

        if (!$customer) {
            return false;
        }

        // Try to get tracking info from session
        $tracking_code = null;
        $offer_id = null;

        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['aima_tracking_code'])) {
            $tracking_code = $_SESSION['aima_tracking_code'];
            $offer_id = $_SESSION['aima_offer_id'];
        }

        // If no session, try to find most recent campaign click
        if (!$offer_id) {
            $click_table = $wpdb->prefix . 'aima_click_tracking';
            $links_table = $wpdb->prefix . 'aima_tracking_links';

            $recent_click = $wpdb->get_row($wpdb->prepare(
                "SELECT l.offer_id, c.tracking_code
                FROM $click_table c
                INNER JOIN $links_table l ON c.tracking_code = l.tracking_code
                WHERE c.customer_id = %d
                AND c.clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY c.clicked_at DESC
                LIMIT 1",
                $customer->id
            ));

            if ($recent_click) {
                $offer_id = $recent_click->offer_id;
                $tracking_code = $recent_click->tracking_code;
            }
        }

        if (!$offer_id) {
            return false; // No attribution possible
        }

        // Record conversion
        $conversion_table = $wpdb->prefix . 'aima_conversion_tracking';
        $wpdb->insert($conversion_table, array(
            'offer_id' => $offer_id,
            'customer_id' => $customer->id,
            'order_id' => $order_id,
            'order_total' => $total,
            'commission' => $total, // Can be adjusted for actual commission
            'attribution_type' => 'last_click',
            'tracking_code' => $tracking_code,
            'converted_at' => current_time('mysql')
        ));

        // Update analytics
        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';
        $wpdb->query($wpdb->prepare(
            "UPDATE $analytics_table
            SET converted_count = converted_count + 1,
                total_revenue = total_revenue + %f
            WHERE offer_id = %d",
            $total,
            $offer_id
        ));

        // Update recipient conversion status
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';
        $wpdb->update(
            $recipients_table,
            array('converted_at' => current_time('mysql')),
            array(
                'offer_id' => $offer_id,
                'customer_id' => $customer->id
            )
        );

        // Update campaign offers table
        $offers_table = $wpdb->prefix . 'aima_offers';
        $wpdb->query($wpdb->prepare(
            "UPDATE $offers_table SET converted_count = converted_count + 1 WHERE id = %d",
            $offer_id
        ));

        // Recalculate rates
        $this->update_campaign_rates($offer_id);

        // Clear session tracking
        unset($_SESSION['aima_tracking_code']);
        unset($_SESSION['aima_offer_id']);

        return true;
    }

    /**
     * Generate tracking pixel URL for email opens
     *
     * @param int $offer_id Campaign ID
     * @param string $email Customer email
     * @return string Tracking pixel URL
     */
    public function get_tracking_pixel($offer_id, $email) {
        $base_url = home_url('/');
        $params = array(
            'aima_track' => 'open',
            'offer' => $offer_id,
            'email' => base64_encode($email),
            'timestamp' => time()
        );

        return add_query_arg($params, $base_url) . '&aima_pixel=1';
    }

    /**
     * Generate tracking URL for links
     *
     * @param int $offer_id Campaign ID
     * @param string $url Original URL
     * @param string $link_text Link text/description
     * @return string Tracking URL
     */
    public function get_tracking_url($offer_id, $url, $link_text = '') {
        global $wpdb;

        $links_table = $wpdb->prefix . 'aima_tracking_links';

        // Generate unique tracking code
        $tracking_code = $this->generate_tracking_code();

        // Store tracking link
        $wpdb->insert($links_table, array(
            'offer_id' => $offer_id,
            'tracking_code' => $tracking_code,
            'original_url' => $url,
            'link_text' => $link_text,
            'created_at' => current_time('mysql')
        ));

        // Return tracking redirect URL
        $base_url = home_url('/');
        return add_query_arg(array(
            'aima_track' => 'click',
            'code' => $tracking_code
        ), $base_url);
    }

    /**
     * Add UTM parameters to URL
     *
     * @param string $url Original URL
     * @param int $offer_id Campaign ID
     * @param string $content Content identifier
     * @return string URL with UTM parameters
     */
    public function add_utm_params($url, $offer_id, $content = '') {
        $params = array(
            'utm_source' => 'email',
            'utm_medium' => 'campaign',
            'utm_campaign' => 'aima_' . $offer_id,
            'utm_content' => $content
        );

        return add_query_arg($params, $url);
    }

    /**
     * Initialize campaign analytics record
     *
     * @param int $offer_id Campaign ID
     */
    public function init_campaign_analytics($offer_id) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $analytics_table WHERE offer_id = %d",
            $offer_id
        ));

        if ($exists == 0) {
            $wpdb->insert($analytics_table, array(
                'offer_id' => $offer_id,
                'sent_count' => 0,
                'delivered_count' => 0,
                'opened_count' => 0,
                'unique_opens' => 0,
                'clicked_count' => 0,
                'unique_clicks' => 0,
                'converted_count' => 0,
                'total_revenue' => 0.00
            ));
        }
    }

    /**
     * Update campaign rates and metrics
     *
     * @param int $offer_id Campaign ID
     */
    public function update_campaign_rates($offer_id) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $analytics_table WHERE offer_id = %d",
            $offer_id
        ));

        if (!$stats || $stats->sent_count == 0) {
            return;
        }

        // Calculate rates
        $open_rate = ($stats->unique_opens / $stats->sent_count) * 100;
        $click_rate = ($stats->unique_clicks / $stats->sent_count) * 100;
        $conversion_rate = ($stats->converted_count / $stats->sent_count) * 100;
        $revenue_per_email = $stats->total_revenue / $stats->sent_count;

        // Calculate ROI (assuming campaign cost is stored somewhere, for now use 0)
        $campaign_cost = 0; // Could be retrieved from offers table
        $roi = $campaign_cost > 0 ? (($stats->total_revenue - $campaign_cost) / $campaign_cost) * 100 : 0;

        // Update analytics
        $wpdb->update(
            $analytics_table,
            array(
                'open_rate' => round($open_rate, 2),
                'click_rate' => round($click_rate, 2),
                'conversion_rate' => round($conversion_rate, 2),
                'roi' => round($roi, 2),
                'revenue_per_email' => round($revenue_per_email, 2)
            ),
            array('offer_id' => $offer_id)
        );
    }

    /**
     * Increment sent count for campaign
     *
     * @param int $offer_id Campaign ID
     */
    public function increment_sent_count($offer_id) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';

        // Initialize if not exists
        $this->init_campaign_analytics($offer_id);

        $wpdb->query($wpdb->prepare(
            "UPDATE $analytics_table SET sent_count = sent_count + 1, delivered_count = delivered_count + 1 WHERE offer_id = %d",
            $offer_id
        ));
    }

    /**
     * Get campaign analytics
     *
     * @param int $offer_id Campaign ID
     * @return object|null Analytics data
     */
    public function get_campaign_analytics($offer_id) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $analytics_table WHERE offer_id = %d",
            $offer_id
        ));
    }

    /**
     * Get top performing campaigns
     *
     * @param int $limit Number of campaigns to return
     * @return array Campaign analytics data
     */
    public function get_top_campaigns($limit = 10) {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'aima_campaign_analytics';
        $offers_table = $wpdb->prefix . 'aima_offers';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, o.name, o.type, o.sent_at
            FROM $analytics_table a
            INNER JOIN $offers_table o ON a.offer_id = o.id
            WHERE a.sent_count > 0
            ORDER BY a.total_revenue DESC
            LIMIT %d",
            $limit
        ));
    }

    /**
     * Generate unique tracking code
     *
     * @return string Tracking code
     */
    private function generate_tracking_code() {
        return substr(md5(uniqid(rand(), true)), 0, 32);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * Handle tracking requests (opens and clicks)
     */
    public function handle_tracking_request() {
        if (!isset($_GET['aima_track'])) {
            return;
        }

        $action = sanitize_text_field($_GET['aima_track']);

        if ($action === 'open' && isset($_GET['offer']) && isset($_GET['email'])) {
            $offer_id = intval($_GET['offer']);
            $email = base64_decode($_GET['email']);

            $this->track_open($offer_id, $email);

            // Return 1x1 transparent pixel
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }

        if ($action === 'click' && isset($_GET['code'])) {
            $tracking_code = sanitize_text_field($_GET['code']);
            $email = isset($_GET['email']) ? base64_decode($_GET['email']) : null;

            $url = $this->track_click($tracking_code, $email);

            if ($url) {
                wp_redirect($url);
                exit;
            }
        }
    }
}
