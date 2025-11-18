<?php
/**
 * Excel Importer for products
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_Excel_Importer {

    /**
     * Expected columns in Excel file
     *
     * @var array
     */
    private $columns = array(
        'name' => array('Название', 'Name', 'Товар', 'Product'),
        'category' => array('Категория', 'Category', 'Раздел'),
        'description' => array('Описание', 'Description'),
        'price' => array('Цена', 'Price', 'Стоимость'),
        'price_unit' => array('Единица', 'Unit', 'Ед.изм.', 'Ед.'),
        'in_stock' => array('Наличие', 'In Stock', 'Есть', 'Stock'),
        'sku' => array('Артикул', 'SKU', 'Код'),
        'tags' => array('Теги', 'Tags', 'Метки'),
    );

    /**
     * Import products from Excel file
     *
     * @param string $file_path Path to uploaded file
     * @param int $producer_id Producer ID
     * @return array|WP_Error Import result
     */
    public function import($file_path, $producer_id) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Try to use simple CSV/Excel parsing
            return $this->import_simple($file_path, $producer_id);
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            return $this->process_data($data, $producer_id);
        } catch (Exception $e) {
            return new WP_Error('import_error', $e->getMessage());
        }
    }

    /**
     * Simple import without PhpSpreadsheet (CSV fallback)
     */
    private function import_simple($file_path, $producer_id) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $data = array();
            if (($handle = fopen($file_path, 'r')) !== false) {
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    $data[] = $row;
                }
                fclose($handle);
            }
            return $this->process_data($data, $producer_id);
        }

        // For xlsx without PhpSpreadsheet, try basic XML parsing
        return $this->import_xlsx_simple($file_path, $producer_id);
    }

    /**
     * Simple XLSX import using XML parsing
     */
    private function import_xlsx_simple($file_path, $producer_id) {
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            return new WP_Error('import_error', 'Cannot open Excel file');
        }

        // Read shared strings
        $strings = array();
        $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($strings_xml) {
            $xml = simplexml_load_string($strings_xml);
            foreach ($xml->si as $si) {
                $strings[] = (string)$si->t;
            }
        }

        // Read sheet data
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet_xml) {
            $zip->close();
            return new WP_Error('import_error', 'Cannot read worksheet');
        }

        $xml = simplexml_load_string($sheet_xml);
        $data = array();
        $current_row = array();
        $row_index = 0;

        foreach ($xml->sheetData->row as $row) {
            $current_row = array();
            foreach ($row->c as $cell) {
                $value = '';
                $type = (string)$cell['t'];

                if ($type === 's') {
                    // Shared string
                    $index = (int)$cell->v;
                    $value = isset($strings[$index]) ? $strings[$index] : '';
                } else {
                    $value = (string)$cell->v;
                }

                $current_row[] = $value;
            }
            $data[] = $current_row;
        }

        $zip->close();

        return $this->process_data($data, $producer_id);
    }

    /**
     * Process imported data
     *
     * @param array $data Parsed data rows
     * @param int $producer_id Producer ID
     * @return array Import result
     */
    private function process_data($data, $producer_id) {
        if (empty($data) || count($data) < 2) {
            return new WP_Error('import_error', 'File is empty or has no data rows');
        }

        // First row is header
        $headers = array_map('trim', $data[0]);
        $column_map = $this->map_columns($headers);

        if (!isset($column_map['name'])) {
            return new WP_Error('import_error', 'Required column "Название" not found');
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'sap_catalog_products';

        $imported = 0;
        $updated = 0;
        $errors = array();
        $now = current_time('mysql');

        // Process each row
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $product_data = $this->parse_row($row, $column_map);

            if (empty($product_data['name'])) {
                $errors[] = "Row " . ($i + 1) . ": Missing product name";
                continue;
            }

            // Find category
            $category_id = 1; // Default category
            if (!empty($product_data['category'])) {
                $category_id = $this->find_category($product_data['category']) ?: 1;
            }

            // Check if product exists (by name and producer)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $products_table WHERE producer_id = %d AND name = %s AND status != 'deleted'",
                $producer_id,
                $product_data['name']
            ));

            $insert_data = array(
                'producer_id' => $producer_id,
                'category_id' => $category_id,
                'name' => $product_data['name'],
                'description' => $product_data['description'] ?? '',
                'price' => floatval($product_data['price'] ?? 0),
                'price_unit' => $product_data['price_unit'] ?? 'шт',
                'in_stock' => $this->parse_stock($product_data['in_stock'] ?? '1'),
                'sku' => $product_data['sku'] ?? '',
                'tags' => $product_data['tags'] ?? '',
                'status' => 'active',
                'updated_at' => $now,
            );

            if ($existing) {
                // Update existing
                $wpdb->update($products_table, $insert_data, array('id' => $existing));
                $updated++;
            } else {
                // Insert new
                $insert_data['created_at'] = $now;
                $wpdb->insert($products_table, $insert_data);
                $imported++;
            }
        }

        // Update category counts
        $this->update_all_category_counts();

        return array(
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'total_processed' => $imported + $updated,
        );
    }

    /**
     * Map header columns to field names
     */
    private function map_columns($headers) {
        $map = array();

        foreach ($headers as $index => $header) {
            $header_lower = mb_strtolower(trim($header));

            foreach ($this->columns as $field => $variants) {
                foreach ($variants as $variant) {
                    if (mb_strtolower($variant) === $header_lower) {
                        $map[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Parse row data according to column map
     */
    private function parse_row($row, $column_map) {
        $data = array();

        foreach ($column_map as $field => $index) {
            if (isset($row[$index])) {
                $value = trim($row[$index]);

                // Clean price
                if ($field === 'price') {
                    $value = preg_replace('/[^\d.,]/', '', str_replace(',', '.', $value));
                }

                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Find category by name
     */
    private function find_category($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE status = 'active' AND (name LIKE %s OR slug = %s)",
            '%' . $wpdb->esc_like($name) . '%',
            sanitize_title($name)
        ));
    }

    /**
     * Parse stock value
     */
    private function parse_stock($value) {
        $value_lower = mb_strtolower(trim($value));

        $true_values = array('1', 'да', 'yes', 'true', 'есть', '+', 'в наличии');
        $false_values = array('0', 'нет', 'no', 'false', 'нету', '-', 'нет в наличии');

        if (in_array($value_lower, $true_values)) {
            return true;
        }

        if (in_array($value_lower, $false_values)) {
            return false;
        }

        return !empty($value);
    }

    /**
     * Update all category product counts
     */
    private function update_all_category_counts() {
        global $wpdb;
        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $categories = $wpdb->get_results("SELECT id FROM $categories_table", ARRAY_A);

        foreach ($categories as $cat) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $products_table WHERE category_id = %d AND status = 'active'",
                $cat['id']
            ));

            $wpdb->update($categories_table, array('products_count' => $count), array('id' => $cat['id']));
        }
    }

    /**
     * Generate import template
     *
     * @return string CSV content
     */
    public static function generate_template() {
        $headers = array('Название', 'Категория', 'Описание', 'Цена', 'Ед.изм.', 'Наличие', 'Артикул', 'Теги');
        $example = array('Сыр домашний', 'Сыры', 'Натуральный домашний сыр из коровьего молока', '250', 'кг', 'Да', 'CH001', 'сыр, молочные, натуральный');

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        fputcsv($output, $example);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
