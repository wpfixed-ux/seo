<?php
/**
 * Import admin page
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin_Import {

    /**
     * Render import page
     */
    public function render_page() {
        if (isset($_POST['upload_csv'])) {
            $this->process_csv_upload();
        }

        global $wpdb;
        $imports_table = $wpdb->prefix . 'aima_import_logs';
        $recent_imports = $wpdb->get_results(
            "SELECT * FROM $imports_table ORDER BY started_at DESC LIMIT 10"
        );

        include AIMA_ADMIN_DIR . 'views/import.php';
    }

    /**
     * Process CSV upload
     */
    public function process_csv_upload() {
        check_admin_referer('aima_import_csv');

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('File upload failed', 'ai-marketing-assistant')
            );
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $file_name = sanitize_file_name($_FILES['csv_file']['name']);

        // Validate file type
        $file_type = mime_content_type($file);
        if (!in_array($file_type, array('text/csv', 'text/plain', 'application/vnd.ms-excel'))) {
            return array(
                'success' => false,
                'message' => __('Invalid file type. Please upload a CSV file.', 'ai-marketing-assistant')
            );
        }

        // Create import log
        global $wpdb;
        $imports_table = $wpdb->prefix . 'aima_import_logs';

        $wpdb->insert(
            $imports_table,
            array(
                'file_name' => $file_name,
                'status' => 'processing'
            ),
            array('%s', '%s')
        );

        $import_id = $wpdb->insert_id;

        // Process CSV
        $result = $this->parse_csv($file, $import_id);

        // Update import log
        $wpdb->update(
            $imports_table,
            array(
                'total_rows' => $result['total_rows'],
                'processed_rows' => $result['processed_rows'],
                'successful_rows' => $result['successful_rows'],
                'failed_rows' => $result['failed_rows'],
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'error_log' => !empty($result['errors']) ? wp_json_encode($result['errors']) : null
            ),
            array('id' => $import_id),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result['successful_rows'] > 0) {
            add_settings_error(
                'aima_messages',
                'aima_message',
                sprintf(
                    __('Successfully imported %d customers', 'ai-marketing-assistant'),
                    $result['successful_rows']
                ),
                'updated'
            );
        }

        return array(
            'success' => true,
            'message' => sprintf(
                __('Import completed. %d successful, %d failed', 'ai-marketing-assistant'),
                $result['successful_rows'],
                $result['failed_rows']
            ),
            'data' => $result
        );
    }

    /**
     * Parse CSV file
     */
    private function parse_csv($file, $import_id) {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return array(
                'success' => false,
                'message' => __('Unable to read file', 'ai-marketing-assistant')
            );
        }

        $result = array(
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'errors' => array()
        );

        // Get header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $result;
        }

        // Map CSV columns
        $column_map = $this->map_csv_columns($header);

        // Process rows
        $row_number = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            $result['total_rows']++;

            try {
                $customer_data = $this->parse_csv_row($row, $column_map);

                if ($customer_data) {
                    // Check if customer exists
                    $existing_customer = AIMA_Database::get_customer_by_email($customer_data['email']);

                    if ($existing_customer) {
                        // Update existing customer
                        $customer_id = $existing_customer->id;

                        // Merge purchase data
                        if (!empty($customer_data['purchases'])) {
                            foreach ($customer_data['purchases'] as $purchase) {
                                $purchase['customer_id'] = $customer_id;
                                $purchase['source'] = 'import';
                                AIMA_Database::add_purchase($purchase);
                            }
                        }
                    } else {
                        // Create new customer
                        $customer_id = AIMA_Database::upsert_customer(array(
                            'email' => $customer_data['email'],
                            'phone' => $customer_data['phone'] ?? null,
                            'first_name' => $customer_data['first_name'] ?? null,
                            'last_name' => $customer_data['last_name'] ?? null,
                            'source' => 'import'
                        ));

                        // Add purchases
                        if (!empty($customer_data['purchases'])) {
                            foreach ($customer_data['purchases'] as $purchase) {
                                $purchase['customer_id'] = $customer_id;
                                $purchase['source'] = 'import';
                                AIMA_Database::add_purchase($purchase);
                            }
                        }
                    }

                    $result['successful_rows']++;
                } else {
                    $result['failed_rows']++;
                    $result['errors'][] = sprintf(__('Row %d: Invalid data', 'ai-marketing-assistant'), $row_number);
                }

                $result['processed_rows']++;

            } catch (Exception $e) {
                $result['failed_rows']++;
                $result['errors'][] = sprintf(
                    __('Row %d: %s', 'ai-marketing-assistant'),
                    $row_number,
                    $e->getMessage()
                );
            }
        }

        fclose($handle);

        return $result;
    }

    /**
     * Map CSV columns to internal fields
     */
    private function map_csv_columns($header) {
        $map = array();

        foreach ($header as $index => $column) {
            $column_lower = strtolower(trim($column));

            // Map common column names
            if (in_array($column_lower, array('email', 'e-mail', 'customer_email'))) {
                $map['email'] = $index;
            } elseif (in_array($column_lower, array('phone', 'telephone', 'mobile'))) {
                $map['phone'] = $index;
            } elseif (in_array($column_lower, array('first_name', 'firstname', 'name'))) {
                $map['first_name'] = $index;
            } elseif (in_array($column_lower, array('last_name', 'lastname', 'surname'))) {
                $map['last_name'] = $index;
            } elseif (in_array($column_lower, array('product', 'product_name', 'products'))) {
                $map['products'] = $index;
            } elseif (in_array($column_lower, array('order_date', 'purchase_date', 'date'))) {
                $map['order_date'] = $index;
            } elseif (in_array($column_lower, array('amount', 'total', 'price'))) {
                $map['amount'] = $index;
            }
        }

        return $map;
    }

    /**
     * Parse CSV row into customer data
     */
    private function parse_csv_row($row, $column_map) {
        if (empty($column_map['email']) || empty($row[$column_map['email']])) {
            return null;
        }

        $email = sanitize_email($row[$column_map['email']]);
        if (!is_email($email)) {
            return null;
        }

        $data = array(
            'email' => $email,
            'phone' => isset($column_map['phone']) && !empty($row[$column_map['phone']])
                ? sanitize_text_field($row[$column_map['phone']])
                : null,
            'first_name' => isset($column_map['first_name']) && !empty($row[$column_map['first_name']])
                ? sanitize_text_field($row[$column_map['first_name']])
                : null,
            'last_name' => isset($column_map['last_name']) && !empty($row[$column_map['last_name']])
                ? sanitize_text_field($row[$column_map['last_name']])
                : null,
            'purchases' => array()
        );

        // Parse purchase data if available
        if (isset($column_map['products']) && !empty($row[$column_map['products']])) {
            $products = sanitize_text_field($row[$column_map['products']]);
            $order_date = isset($column_map['order_date']) && !empty($row[$column_map['order_date']])
                ? $row[$column_map['order_date']]
                : current_time('mysql');
            $amount = isset($column_map['amount']) && !empty($row[$column_map['amount']])
                ? floatval($row[$column_map['amount']])
                : 0;

            $data['purchases'][] = array(
                'product_id' => 0,
                'product_name' => $products,
                'category' => null,
                'quantity' => 1,
                'price' => $amount,
                'total' => $amount,
                'order_date' => $order_date
            );
        }

        return $data;
    }
}
