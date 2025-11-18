<?php
/**
 * Products WP_List_Table
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WCPMP_Products_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'product',
            'plural' => 'products',
            'ajax' => true
        ));
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'name' => __('Product Name', 'wc-product-manager-pro'),
            'sku' => __('SKU', 'wc-product-manager-pro'),
            'price' => __('Price', 'wc-product-manager-pro'),
            'stock' => __('Stock', 'wc-product-manager-pro'),
            'stores' => __('Stores', 'wc-product-manager-pro'),
            'seo' => __('SEO', 'wc-product-manager-pro'),
            'sync' => __('Last Sync', 'wc-product-manager-pro')
        );
    }

    public function get_sortable_columns() {
        return array(
            'name' => array('name_uk', false),
            'sku' => array('sku', false),
            'price' => array('price', false),
            'stock' => array('stock_quantity', false)
        );
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        // Build query
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';

        $where = '1=1';
        $values = array();

        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $where .= ' AND (name_uk LIKE %s OR sku LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        if (!empty($_REQUEST['category'])) {
            $where .= ' AND category_id = %d';
            $values[] = intval($_REQUEST['category']);
        }

        $offset = ($current_page - 1) * $per_page;

        // Get items
        $sql = "SELECT * FROM {$wpdb->prefix}wcpmp_products WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $per_page;
        $values[] = $offset;

        $this->items = $wpdb->get_results($wpdb->prepare($sql, $values));

        // Get total
        $total_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wcpmp_products WHERE $where";
        $total = $wpdb->get_var($wpdb->prepare($total_sql, array_slice($values, 0, -2)));

        $this->set_pagination_args(array(
            'total_items' => $total,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ));
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="products[]" value="%d" />', $item->id);
    }

    public function column_name($item) {
        $actions = array(
            'edit' => sprintf('<a href="?page=wcpmp-products&action=edit&id=%d">%s</a>', $item->id, __('Edit', 'wc-product-manager-pro')),
            'generate_seo' => sprintf('<a href="#" class="wcpmp-generate-seo" data-id="%d">%s</a>', $item->id, __('Generate SEO', 'wc-product-manager-pro')),
            'delete' => sprintf('<a href="?page=wcpmp-products&action=delete&id=%d" onclick="return confirm(\'%s\')">%s</a>', $item->id, __('Are you sure?', 'wc-product-manager-pro'), __('Delete', 'wc-product-manager-pro'))
        );

        return sprintf('<strong>%s</strong> %s', esc_html($item->name_uk), $this->row_actions($actions));
    }

    public function column_sku($item) {
        return esc_html($item->sku);
    }

    public function column_price($item) {
        $price = number_format($item->price, 2) . ' грн';
        if ($item->sale_price && $item->sale_price < $item->price) {
            $price = '<del>' . number_format($item->price, 2) . '</del> ' . number_format($item->sale_price, 2) . ' грн';
        }
        return $price;
    }

    public function column_stock($item) {
        $class = $item->stock_quantity > 10 ? 'in-stock' : ($item->stock_quantity > 0 ? 'low-stock' : 'out-of-stock');
        return sprintf('<span class="%s">%d</span>', $class, $item->stock_quantity);
    }

    public function column_stores($item) {
        global $wpdb;

        $stores = $wpdb->get_results($wpdb->prepare("
            SELECT s.name, ps.is_published
            FROM {$wpdb->prefix}wcpmp_product_stores ps
            JOIN {$wpdb->prefix}wcpmp_stores s ON ps.store_id = s.id
            WHERE ps.product_id = %d
        ", $item->id));

        if (empty($stores)) {
            return '<span class="not-published">' . __('Not published', 'wc-product-manager-pro') . '</span>';
        }

        $output = '';
        foreach ($stores as $store) {
            $icon = $store->is_published ? '✓' : '○';
            $output .= "<span title='{$store->name}'>{$icon}</span> ";
        }

        return $output;
    }

    public function column_seo($item) {
        $has_seo = !empty($item->seo_description_uk);
        return $has_seo
            ? '<span class="seo-yes">✓</span>'
            : '<span class="seo-no">✗</span>';
    }

    public function column_sync($item) {
        if (!$item->last_synced) {
            return __('Never', 'wc-product-manager-pro');
        }

        return human_time_diff(strtotime($item->last_synced), current_time('timestamp')) . ' ' . __('ago', 'wc-product-manager-pro');
    }

    public function get_bulk_actions() {
        return array(
            'generate_seo' => __('Generate SEO Descriptions', 'wc-product-manager-pro'),
            'publish' => __('Publish to Stores', 'wc-product-manager-pro'),
            'sync' => __('Sync Inventory', 'wc-product-manager-pro'),
            'delete' => __('Delete', 'wc-product-manager-pro')
        );
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') return;

        global $wpdb;
        $categories = $wpdb->get_results(
            "SELECT id, name_uk FROM {$wpdb->prefix}wcpmp_categories ORDER BY name_uk"
        );

        $current = isset($_REQUEST['category']) ? intval($_REQUEST['category']) : 0;

        echo '<div class="alignleft actions">';
        echo '<select name="category">';
        echo '<option value="">' . __('All Categories', 'wc-product-manager-pro') . '</option>';
        foreach ($categories as $cat) {
            $selected = $current == $cat->id ? 'selected' : '';
            echo "<option value='{$cat->id}' $selected>" . esc_html($cat->name_uk) . "</option>";
        }
        echo '</select>';
        submit_button(__('Filter', 'wc-product-manager-pro'), '', 'filter_action', false);
        echo '</div>';
    }
}
