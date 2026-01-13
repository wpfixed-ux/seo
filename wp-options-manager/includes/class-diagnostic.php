<?php
/**
 * Класс для диагностики таблицы wp_options
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс WPOM_Diagnostic
 */
class WPOM_Diagnostic {

    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;

    /**
     * Кэш результатов диагностики
     */
    private $cache = array();

    /**
     * Время кэширования (1 час)
     */
    const CACHE_DURATION = 3600;

    /**
     * Получить экземпляр класса (Singleton)
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор
     */
    private function __construct() {
        // Приватный конструктор для Singleton
    }

    /**
     * Получение общей статистики таблицы wp_options
     */
    public function get_general_stats() {
        $cache_key = 'wpom_general_stats';
        $cached = $this->get_cached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $options_table = $wpdb->options;

        // Размер таблицы в MB
        $table_size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
            FROM information_schema.TABLES
            WHERE table_schema = %s
            AND table_name = %s",
            DB_NAME,
            $options_table
        ));

        // Количество записей
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $options_table");

        // Autoload данные
        $autoload_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as count,
                SUM(LENGTH(option_value)) as size
            FROM $options_table
            WHERE autoload = 'yes'"
        );

        $autoload_size_kb = $autoload_stats->size ? round($autoload_stats->size / 1024, 2) : 0;

        // Определение статуса (зеленый/желтый/красный)
        $autoload_status = 'green';
        if ($autoload_size_kb > 800) {
            $autoload_status = 'red';
        } elseif ($autoload_size_kb > 500) {
            $autoload_status = 'yellow';
        }

        $stats = array(
            'table_size_mb' => floatval($table_size),
            'total_records' => intval($total_records),
            'autoload_size_kb' => floatval($autoload_size_kb),
            'autoload_records' => intval($autoload_stats->count),
            'autoload_status' => $autoload_status,
            'recommended_autoload_max' => 800, // KB
        );

        $this->set_cached($cache_key, $stats);

        return $stats;
    }

    /**
     * Анализ по типам данных (группировка по префиксам)
     */
    public function get_data_by_prefix() {
        $cache_key = 'wpom_data_by_prefix';
        $cached = $this->get_cached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $options_table = $wpdb->options;

        // Определение известных префиксов
        $prefixes = array(
            '_transient_' => 'Transients',
            '_site_transient_' => 'Site Transients',
            'wc_' => 'WooCommerce',
            '_wc_' => 'WooCommerce (private)',
            'woocommerce_' => 'WooCommerce Settings',
            'widget_' => 'Widgets',
            'theme_mods_' => 'Theme Modifications',
            'cron' => 'Cron Jobs',
            'rewrite_rules' => 'Rewrite Rules',
            'wpml_' => 'WPML',
            '_wpml_' => 'WPML (private)',
            'elementor_' => 'Elementor',
            '_elementor_' => 'Elementor (private)',
            'litespeed' => 'LiteSpeed Cache',
        );

        $results = array();

        foreach ($prefixes as $prefix => $label) {
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as count,
                    ROUND(SUM(LENGTH(option_value)) / 1024, 2) as size_kb
                FROM $options_table
                WHERE option_name LIKE %s",
                $prefix . '%'
            ));

            if ($stats && $stats->count > 0) {
                $results[$prefix] = array(
                    'label' => $label,
                    'count' => intval($stats->count),
                    'size_kb' => floatval($stats->size_kb),
                    'size_mb' => round($stats->size_kb / 1024, 2),
                );
            }
        }

        // Сортировка по размеру (от большего к меньшему)
        uasort($results, function($a, $b) {
            return $b['size_kb'] <=> $a['size_kb'];
        });

        $this->set_cached($cache_key, $results);

        return $results;
    }

    /**
     * Получение топ проблемных записей
     */
    public function get_top_large_options($limit = 20) {
        $cache_key = 'wpom_top_large_options_' . $limit;
        $cached = $this->get_cached($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $options_table = $wpdb->options;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                option_id,
                option_name,
                ROUND(LENGTH(option_value) / 1024, 2) as size_kb,
                autoload,
                SUBSTRING(option_value, 1, 100) as value_preview
            FROM $options_table
            ORDER BY LENGTH(option_value) DESC
            LIMIT %d",
            $limit
        ));

        $options = array();

        foreach ($results as $row) {
            $source = $this->detect_option_source($row->option_name);

            $options[] = array(
                'option_id' => intval($row->option_id),
                'option_name' => $row->option_name,
                'size_kb' => floatval($row->size_kb),
                'size_mb' => round($row->size_kb / 1024, 2),
                'autoload' => $row->autoload,
                'source' => $source,
                'value_preview' => $row->value_preview,
                'is_large' => $row->size_kb > 100, // > 100 KB
            );
        }

        $this->set_cached($cache_key, $options);

        return $options;
    }

    /**
     * Определение источника опции (какой плагин создал)
     */
    private function detect_option_source($option_name) {
        // Карта префиксов к источникам
        $source_map = array(
            '_transient_' => 'WordPress Transients',
            '_site_transient_' => 'WordPress Site Transients',
            'wc_' => 'WooCommerce',
            '_wc_' => 'WooCommerce',
            'woocommerce_' => 'WooCommerce',
            'widget_' => 'WordPress Widgets',
            'theme_mods_' => 'WordPress Theme',
            'cron' => 'WordPress Cron',
            'rewrite_rules' => 'WordPress Core',
            'wpml_' => 'WPML',
            '_wpml_' => 'WPML',
            'elementor_' => 'Elementor',
            '_elementor_' => 'Elementor',
            'litespeed' => 'LiteSpeed Cache',
            'wpallimport_' => 'WP All Import',
            'yoast' => 'Yoast SEO',
            '_yoast_' => 'Yoast SEO',
            'jetpack_' => 'Jetpack',
            'wordfence_' => 'Wordfence',
            'wp_smush_' => 'Smush',
            'itsec_' => 'iThemes Security',
        );

        foreach ($source_map as $prefix => $source) {
            if (strpos($option_name, $prefix) === 0) {
                return $source;
            }
        }

        // Если не найден известный префикс
        return 'Unknown';
    }

    /**
     * Получение списка аномально больших записей
     */
    public function get_anomalous_options($threshold_kb = 100) {
        global $wpdb;
        $options_table = $wpdb->options;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                option_id,
                option_name,
                ROUND(LENGTH(option_value) / 1024, 2) as size_kb,
                autoload
            FROM $options_table
            WHERE LENGTH(option_value) > %d
            ORDER BY LENGTH(option_value) DESC",
            $threshold_kb * 1024
        ));

        $options = array();

        foreach ($results as $row) {
            $source = $this->detect_option_source($row->option_name);

            $options[] = array(
                'option_id' => intval($row->option_id),
                'option_name' => $row->option_name,
                'size_kb' => floatval($row->size_kb),
                'size_mb' => round($row->size_kb / 1024, 2),
                'autoload' => $row->autoload,
                'source' => $source,
            );
        }

        return $options;
    }

    /**
     * Анализ transient записей
     */
    public function analyze_transients() {
        global $wpdb;
        $options_table = $wpdb->options;

        // Истекшие transients
        $expired = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $options_table as o1
            JOIN $options_table as o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_%'
            AND o1.option_name NOT LIKE '_transient_timeout_%'
            AND o2.option_value < UNIX_TIMESTAMP()"
        );

        // Orphaned transients (без таймаутов)
        $orphaned = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $options_table as o1
            LEFT JOIN $options_table as o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
            WHERE o1.option_name LIKE '_transient_%'
            AND o1.option_name NOT LIKE '_transient_timeout_%'
            AND o2.option_id IS NULL"
        );

        // Общее количество transients
        $total = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $options_table
            WHERE option_name LIKE '_transient_%'
            AND option_name NOT LIKE '_transient_timeout_%'"
        );

        return array(
            'total' => intval($total),
            'expired' => intval($expired),
            'orphaned' => intval($orphaned),
            'cleanable' => intval($expired) + intval($orphaned),
        );
    }

    /**
     * Анализ WooCommerce сессий
     */
    public function analyze_woocommerce_sessions() {
        if (!class_exists('WooCommerce')) {
            return array(
                'enabled' => false,
                'message' => 'WooCommerce not installed',
            );
        }

        global $wpdb;
        $options_table = $wpdb->options;

        // Количество сессий
        $total_sessions = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $options_table
            WHERE option_name LIKE '_wc_session_%'"
        );

        // Размер сессий
        $sessions_size = $wpdb->get_var(
            "SELECT ROUND(SUM(LENGTH(option_value)) / 1024, 2)
            FROM $options_table
            WHERE option_name LIKE '_wc_session_%'"
        );

        return array(
            'enabled' => true,
            'total_sessions' => intval($total_sessions),
            'size_kb' => floatval($sessions_size),
            'size_mb' => round($sessions_size / 1024, 2),
        );
    }

    /**
     * Получение кэшированных данных
     */
    private function get_cached($key) {
        $transient_key = 'wpom_cache_' . $key;
        return get_transient($transient_key);
    }

    /**
     * Установка кэшированных данных
     */
    private function set_cached($key, $data) {
        $transient_key = 'wpom_cache_' . $key;
        set_transient($transient_key, $data, self::CACHE_DURATION);
    }

    /**
     * Очистка кэша диагностики
     */
    public function clear_cache() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_wpom_cache_%'
            OR option_name LIKE '_transient_timeout_wpom_cache_%'"
        );
    }
}
