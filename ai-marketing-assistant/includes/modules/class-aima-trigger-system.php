<?php
/**
 * Automated trigger system module
 *
 * @package AIMarketingAssistant
 */

class AIMA_Trigger_System {

    /**
     * Check and process all active triggers
     */
    public function check_triggers() {
        if (!get_option('aima_enable_triggers', 1)) {
            return;
        }

        global $wpdb;
        $triggers_table = $wpdb->prefix . 'aima_triggers';

        $triggers = $wpdb->get_results(
            "SELECT * FROM $triggers_table WHERE is_active = 1"
        );

        foreach ($triggers as $trigger) {
            $this->process_trigger($trigger);
        }
    }

    /**
     * Process individual trigger
     */
    private function process_trigger($trigger) {
        switch ($trigger->trigger_type) {
            case 'birthday':
                $this->process_birthday_trigger($trigger);
                break;
            case 'inactivity':
                $this->process_inactivity_trigger($trigger);
                break;
            case 'holiday':
                $this->process_holiday_trigger($trigger);
                break;
        }
    }

    /**
     * Process birthday trigger
     */
    private function process_birthday_trigger($trigger) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $conditions = json_decode($trigger->conditions, true);
        $days_before = $conditions['days_before'] ?? 0;

        // Calculate target date
        $target_date = date('m-d', strtotime("+{$days_before} days"));

        // Find customers with birthdays
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table
            WHERE DATE_FORMAT(birthday, '%%m-%%d') = %s
            AND (last_campaign_date IS NULL OR last_campaign_date < DATE_SUB(NOW(), INTERVAL 7 DAY))",
            $target_date
        ));

        foreach ($customers as $customer) {
            $this->create_trigger_offer($trigger, $customer);
        }

        // Update last run
        $wpdb->update(
            $wpdb->prefix . 'aima_triggers',
            array('last_run' => current_time('mysql')),
            array('id' => $trigger->id)
        );
    }

    /**
     * Process inactivity trigger
     */
    private function process_inactivity_trigger($trigger) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $conditions = json_decode($trigger->conditions, true);
        $days_inactive = $conditions['days_inactive'] ?? 30;

        // Find inactive customers
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days_inactive} days"));

        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $customers_table
            WHERE last_order_date < %s
            AND total_orders > 1
            AND (last_campaign_date IS NULL OR last_campaign_date < DATE_SUB(NOW(), INTERVAL 7 DAY))
            LIMIT 100",
            $date_threshold
        ));

        foreach ($customers as $customer) {
            $this->create_trigger_offer($trigger, $customer);
        }

        $wpdb->update(
            $wpdb->prefix . 'aima_triggers',
            array('last_run' => current_time('mysql')),
            array('id' => $trigger->id)
        );
    }

    /**
     * Process holiday trigger
     */
    private function process_holiday_trigger($trigger) {
        global $wpdb;
        $customers_table = $wpdb->prefix . 'aima_customers';

        $conditions = json_decode($trigger->conditions, true);
        $holiday_date = $conditions['date']; // Format: MM-DD
        $days_before = $conditions['days_before'] ?? 0;
        $gender = $conditions['gender'] ?? null;

        // Calculate send date
        list($month, $day) = explode('-', $holiday_date);
        $this_year_date = date('Y') . '-' . $holiday_date;
        $send_date = date('m-d', strtotime("-{$days_before} days", strtotime($this_year_date)));

        // Check if today is the send date
        if (date('m-d') !== $send_date) {
            return;
        }

        // Build query
        $query = "SELECT * FROM $customers_table WHERE 1=1";
        $params = array();

        if ($gender) {
            $query .= " AND gender = %s";
            $params[] = $gender;
        }

        $query .= " AND (last_campaign_date IS NULL OR last_campaign_date < DATE_SUB(NOW(), INTERVAL 7 DAY))";
        $query .= " LIMIT 500";

        $customers = empty($params)
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, $params));

        foreach ($customers as $customer) {
            $this->create_trigger_offer($trigger, $customer);
        }

        $wpdb->update(
            $wpdb->prefix . 'aima_triggers',
            array(
                'last_run' => current_time('mysql'),
                'total_sent' => $trigger->total_sent + count($customers)
            ),
            array('id' => $trigger->id)
        );
    }

    /**
     * Create offer from trigger for customer
     */
    private function create_trigger_offer($trigger, $customer) {
        $template = json_decode($trigger->offer_template, true);

        // Get customer purchase patterns
        $analyzer = new AIMA_Customer_Analyzer();
        $patterns = $analyzer->get_customer_purchase_patterns($customer->id);

        // Prepare segment data
        $segment_data = array(
            'name' => $trigger->name,
            'description' => 'Automated trigger: ' . $trigger->trigger_type,
            'customer_count' => 1,
            'purchase_patterns' => $patterns
        );

        // Get recommended products
        $product_ids = array();
        if (!empty($patterns['top_products'])) {
            $product_ids = array_slice(
                array_column($patterns['top_products'], 'product_id'),
                0,
                3
            );
        }

        // Generate personalized offer
        $generator = new AIMA_Offer_Generator();
        $products_data = $generator->get_products_data($product_ids);

        $ai_client = new AIMA_AI_Client();
        $offer_response = $ai_client->generate_offer($segment_data, $products_data, $trigger->trigger_type);

        if (!$offer_response['success']) {
            return false;
        }

        $offer_data = $ai_client->parse_json_response($offer_response['content']);

        if (!$offer_data['success']) {
            return false;
        }

        // Create offer
        $offer_id = AIMA_Database::upsert_offer(array(
            'name' => $trigger->name . ' - ' . $customer->first_name,
            'type' => 'email',
            'subject' => $offer_data['data']['headline'],
            'content' => $offer_data['data']['body'],
            'coupon_code' => $this->generate_coupon_code($trigger->name),
            'discount_value' => $template['discount'] ?? 10,
            'discount_type' => 'percent',
            'status' => 'scheduled',
            'scheduled_at' => current_time('mysql')
        ));

        // Add to email queue
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aima_email_queue',
            array(
                'offer_id' => $offer_id,
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'priority' => 7, // High priority for triggers
                'scheduled_for' => current_time('mysql'),
                'status' => 'queued'
            )
        );

        // Update customer last campaign date
        $wpdb->update(
            $wpdb->prefix . 'aima_customers',
            array('last_campaign_date' => current_time('mysql')),
            array('id' => $customer->id)
        );

        return $offer_id;
    }

    /**
     * Generate unique coupon code
     */
    private function generate_coupon_code($trigger_name) {
        $prefix = strtoupper(substr(sanitize_title($trigger_name), 0, 4));
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return $prefix . '-' . $random;
    }
}
