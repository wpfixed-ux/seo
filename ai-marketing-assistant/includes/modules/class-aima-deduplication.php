<?php
/**
 * Order deduplication system
 *
 * @package AIMarketingAssistant
 */

class AIMA_Deduplication {

    /**
     * Check if order already exists (deduplicate)
     */
    public function is_duplicate_order($email, $products, $order_date, $total) {
        if (!get_option('aima_enable_deduplication', 1)) {
            return false;
        }

        $hash = $this->generate_order_hash($email, $products, $order_date, $total);

        global $wpdb;
        $table = $wpdb->prefix . 'aima_order_hashes';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_hash = %s",
            $hash
        ));

        return !empty($existing);
    }

    /**
     * Register order hash
     */
    public function register_order($email, $products, $order_date, $total, $source = 'woocommerce') {
        $hash = $this->generate_order_hash($email, $products, $order_date, $total);

        global $wpdb;
        $table = $wpdb->prefix . 'aima_order_hashes';

        $wpdb->insert(
            $table,
            array(
                'order_hash' => $hash,
                'customer_email' => $email,
                'order_date' => $order_date,
                'source' => $source
            ),
            array('%s', '%s', '%s', '%s')
        );

        return $hash;
    }

    /**
     * Generate unique order hash
     */
    private function generate_order_hash($email, $products, $order_date, $total) {
        // Normalize products
        if (is_array($products)) {
            sort($products);
            $products_str = implode('|', $products);
        } else {
            $products_str = $products;
        }

        // Create hash from key components
        $data = strtolower($email) . '|' . $products_str . '|' . date('Y-m-d', strtotime($order_date)) . '|' . round($total, 2);

        return hash('sha256', $data);
    }

    /**
     * Process order with deduplication
     */
    public function process_order($order_data) {
        $email = $order_data['email'];
        $products = $order_data['products'];
        $order_date = $order_data['order_date'];
        $total = $order_data['total'];
        $source = $order_data['source'] ?? 'import';

        // Check for duplicate
        if ($this->is_duplicate_order($email, $products, $order_date, $total)) {
            return array(
                'success' => false,
                'reason' => 'duplicate',
                'message' => 'Order already exists from source: ' . $source
            );
        }

        // Get or create customer
        $customer = AIMA_Database::get_customer_by_email($email);

        if (!$customer) {
            $customer_id = AIMA_Database::upsert_customer(array(
                'email' => $email,
                'first_name' => $order_data['first_name'] ?? null,
                'last_name' => $order_data['last_name'] ?? null,
                'phone' => $order_data['phone'] ?? null,
                'source' => $source
            ));
        } else {
            $customer_id = $customer->id;
        }

        // Add purchases
        if (is_array($products)) {
            foreach ($products as $product) {
                AIMA_Database::add_purchase(array(
                    'customer_id' => $customer_id,
                    'product_id' => $product['id'] ?? 0,
                    'product_name' => $product['name'],
                    'category' => $product['category'] ?? null,
                    'quantity' => $product['quantity'] ?? 1,
                    'price' => $product['price'] ?? 0,
                    'total' => $product['total'] ?? 0,
                    'order_date' => $order_date,
                    'source' => $source
                ));
            }
        }

        // Register order hash
        $this->register_order($email, $products, $order_date, $total, $source);

        // Update customer analytics
        $analyzer = new AIMA_Customer_Analyzer();
        $analytics = $analyzer->calculate_customer_analytics($customer_id);

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aima_customers',
            array(
                'total_orders' => $analytics['total_orders'],
                'total_spent' => $analytics['total_spent'],
                'last_order_date' => $analytics['last_order_date'],
                'first_order_date' => $analytics['first_order_date']
            ),
            array('id' => $customer_id),
            array('%d', '%f', '%s', '%s'),
            array('%d')
        );

        return array(
            'success' => true,
            'customer_id' => $customer_id,
            'message' => 'Order processed successfully'
        );
    }

    /**
     * Import orders from external CRM
     */
    public function import_crm_orders($orders_data, $source = 'crm_api') {
        $results = array(
            'total' => count($orders_data),
            'imported' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'errors_list' => array()
        );

        foreach ($orders_data as $order) {
            try {
                $result = $this->process_order(array_merge($order, array('source' => $source)));

                if ($result['success']) {
                    $results['imported']++;
                } else if ($result['reason'] === 'duplicate') {
                    $results['duplicates']++;
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['errors_list'][] = array(
                    'order' => $order,
                    'error' => $e->getMessage()
                );
            }
        }

        return $results;
    }
}
