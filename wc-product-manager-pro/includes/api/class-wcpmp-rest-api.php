<?php
/**
 * REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCPMP_REST_API {

    private $namespace = 'wcpmp/v1';

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Products
        register_rest_route($this->namespace, '/products', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_products'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_product'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));

        register_rest_route($this->namespace, '/products/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_product'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_product'),
                'permission_callback' => array($this, 'check_permission')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_product'),
                'permission_callback' => array($this, 'check_permission')
            )
        ));

        // Stores
        register_rest_route($this->namespace, '/stores', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stores'),
            'permission_callback' => array($this, 'check_permission')
        ));

        // Orders
        register_rest_route($this->namespace, '/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'check_permission')
        ));

        // Sync
        register_rest_route($this->namespace, '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'trigger_sync'),
            'permission_callback' => array($this, 'check_permission')
        ));

        // Statistics
        register_rest_route($this->namespace, '/statistics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_statistics'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }

    /**
     * Check API permission
     */
    public function check_permission() {
        return current_user_can('manage_wcpmp');
    }

    /**
     * Get products
     */
    public function get_products($request) {
        global $wpdb;

        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $search = $request->get_param('search');
        $category = $request->get_param('category');

        $where = '1=1';
        $values = array();

        if ($search) {
            $where .= ' AND (name_uk LIKE %s OR sku LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        if ($category) {
            $where .= ' AND category_id = %d';
            $values[] = intval($category);
        }

        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM {$wpdb->prefix}wcpmp_products WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $products = $wpdb->get_results($wpdb->prepare($sql, $values));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products WHERE $where",
            array_slice($values, 0, -2)
        ));

        return rest_ensure_response(array(
            'products' => $products,
            'total' => intval($total),
            'pages' => ceil($total / $per_page)
        ));
    }

    /**
     * Get single product
     */
    public function get_product($request) {
        global $wpdb;

        $id = $request->get_param('id');

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcpmp_products WHERE id = %d",
            $id
        ));

        if (!$product) {
            return new WP_Error('not_found', 'Product not found', array('status' => 404));
        }

        // Get store mappings
        $product->stores = $wpdb->get_results($wpdb->prepare("
            SELECT ps.*, s.name as store_name, s.type
            FROM {$wpdb->prefix}wcpmp_product_stores ps
            JOIN {$wpdb->prefix}wcpmp_stores s ON ps.store_id = s.id
            WHERE ps.product_id = %d
        ", $id));

        return rest_ensure_response($product);
    }

    /**
     * Create product
     */
    public function create_product($request) {
        global $wpdb;

        $data = $request->get_json_params();

        $result = $wpdb->insert($wpdb->prefix . 'wcpmp_products', array(
            'sku' => sanitize_text_field($data['sku']),
            'name_uk' => sanitize_text_field($data['name_uk']),
            'name_ru' => sanitize_text_field($data['name_ru'] ?? ''),
            'description_uk' => wp_kses_post($data['description_uk'] ?? ''),
            'description_ru' => wp_kses_post($data['description_ru'] ?? ''),
            'price' => floatval($data['price']),
            'sale_price' => floatval($data['sale_price'] ?? 0) ?: null,
            'stock_quantity' => intval($data['stock_quantity'] ?? 0),
            'category_id' => intval($data['category_id'] ?? 0) ?: null,
            'attributes' => json_encode($data['attributes'] ?? array())
        ));

        if ($result === false) {
            return new WP_Error('create_failed', 'Failed to create product', array('status' => 500));
        }

        return rest_ensure_response(array(
            'id' => $wpdb->insert_id,
            'message' => 'Product created'
        ));
    }

    /**
     * Update product
     */
    public function update_product($request) {
        global $wpdb;

        $id = $request->get_param('id');
        $data = $request->get_json_params();

        $update = array();

        if (isset($data['name_uk'])) $update['name_uk'] = sanitize_text_field($data['name_uk']);
        if (isset($data['name_ru'])) $update['name_ru'] = sanitize_text_field($data['name_ru']);
        if (isset($data['description_uk'])) $update['description_uk'] = wp_kses_post($data['description_uk']);
        if (isset($data['description_ru'])) $update['description_ru'] = wp_kses_post($data['description_ru']);
        if (isset($data['price'])) $update['price'] = floatval($data['price']);
        if (isset($data['sale_price'])) $update['sale_price'] = floatval($data['sale_price']) ?: null;
        if (isset($data['stock_quantity'])) $update['stock_quantity'] = intval($data['stock_quantity']);

        if (empty($update)) {
            return new WP_Error('no_data', 'No data to update', array('status' => 400));
        }

        $result = $wpdb->update($wpdb->prefix . 'wcpmp_products', $update, array('id' => $id));

        return rest_ensure_response(array(
            'updated' => $result !== false,
            'message' => 'Product updated'
        ));
    }

    /**
     * Delete product
     */
    public function delete_product($request) {
        global $wpdb;

        $id = $request->get_param('id');

        // Delete store mappings
        $wpdb->delete($wpdb->prefix . 'wcpmp_product_stores', array('product_id' => $id));

        // Delete product
        $result = $wpdb->delete($wpdb->prefix . 'wcpmp_products', array('id' => $id));

        return rest_ensure_response(array(
            'deleted' => $result !== false
        ));
    }

    /**
     * Get stores
     */
    public function get_stores($request) {
        global $wpdb;

        $stores = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wcpmp_stores ORDER BY name"
        );

        return rest_ensure_response($stores);
    }

    /**
     * Get orders
     */
    public function get_orders($request) {
        $orders = new WCPMP_Orders();
        return rest_ensure_response($orders->get_orders(array(
            'page' => $request->get_param('page') ?: 1,
            'per_page' => $request->get_param('per_page') ?: 20,
            'store_id' => $request->get_param('store_id'),
            'status' => $request->get_param('status')
        )));
    }

    /**
     * Trigger sync
     */
    public function trigger_sync($request) {
        $sync = new WCPMP_Sync_Manager();
        $store_id = $request->get_param('store_id');

        if ($store_id) {
            $result = $sync->sync_store($store_id);
        } else {
            $sync->run_scheduled_sync();
            $result = array('message' => 'Full sync triggered');
        }

        return rest_ensure_response($result);
    }

    /**
     * Get statistics
     */
    public function get_statistics($request) {
        $warehouse = new WCPMP_Warehouse();
        $orders = new WCPMP_Orders();
        $tracker = new WCPMP_Inventory_Tracker();

        return rest_ensure_response(array(
            'inventory' => $tracker->get_inventory_summary(),
            'sales' => $warehouse->get_sales_stats(),
            'orders' => $orders->get_statistics()
        ));
    }
}
