<?php
/**
 * Класс для работы с базой данных
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс WPOM_Database
 */
class WPOM_Database {

    /**
     * Создание таблиц при активации плагина
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Таблица для логов операций
        $logs_table = $wpdb->prefix . 'wpo_logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            operation_details longtext NOT NULL,
            items_affected int(11) NOT NULL DEFAULT 0,
            size_freed bigint(20) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY operation_type (operation_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($logs_sql);

        // Таблица для истории изменений таблицы wp_options
        $history_table = $wpdb->prefix . 'wpo_history';
        $history_sql = "CREATE TABLE IF NOT EXISTS $history_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            table_size bigint(20) NOT NULL DEFAULT 0,
            total_records int(11) NOT NULL DEFAULT 0,
            autoload_size bigint(20) NOT NULL DEFAULT 0,
            autoload_records int(11) NOT NULL DEFAULT 0,
            recorded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";

        dbDelta($history_sql);

        // Таблица для резервных копий удаленных записей
        $backup_table = $wpdb->prefix . 'wpo_backup';
        $backup_sql = "CREATE TABLE IF NOT EXISTS $backup_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            option_id bigint(20) NOT NULL,
            option_name varchar(191) NOT NULL,
            option_value longtext NOT NULL,
            autoload varchar(20) NOT NULL DEFAULT 'yes',
            deleted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY option_name (option_name),
            KEY deleted_at (deleted_at),
            KEY log_id (log_id)
        ) $charset_collate;";

        dbDelta($backup_sql);

        // Записываем текущее состояние таблицы в историю
        self::record_current_state();
    }

    /**
     * Запись текущего состояния таблицы wp_options
     */
    public static function record_current_state() {
        global $wpdb;

        $options_table = $wpdb->options;
        $history_table = $wpdb->prefix . 'wpo_history';

        // Получаем статистику таблицы
        $table_size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(((data_length + index_length) / 1024), 2)
            FROM information_schema.TABLES
            WHERE table_schema = %s
            AND table_name = %s",
            DB_NAME,
            $options_table
        ));

        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $options_table");

        $autoload_stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as count,
                SUM(LENGTH(option_value)) as size
            FROM $options_table
            WHERE autoload = 'yes'"
        );

        // Записываем в историю
        $wpdb->insert(
            $history_table,
            array(
                'table_size' => $table_size ? intval($table_size) : 0,
                'total_records' => intval($total_records),
                'autoload_size' => $autoload_stats->size ? intval($autoload_stats->size) : 0,
                'autoload_records' => $autoload_stats->count ? intval($autoload_stats->count) : 0,
            ),
            array('%d', '%d', '%d', '%d')
        );
    }

    /**
     * Логирование операции
     */
    public static function log_operation($operation_type, $operation_details, $items_affected = 0, $size_freed = 0, $status = 'completed') {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'wpo_logs';

        $wpdb->insert(
            $logs_table,
            array(
                'operation_type' => sanitize_text_field($operation_type),
                'operation_details' => wp_json_encode($operation_details),
                'items_affected' => intval($items_affected),
                'size_freed' => intval($size_freed),
                'status' => sanitize_text_field($status),
            ),
            array('%s', '%s', '%d', '%d', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * Создание резервной копии записи перед удалением
     */
    public static function backup_option($option_id, $option_name, $option_value, $autoload, $log_id = null) {
        global $wpdb;

        $backup_table = $wpdb->prefix . 'wpo_backup';

        $wpdb->insert(
            $backup_table,
            array(
                'option_id' => intval($option_id),
                'option_name' => sanitize_text_field($option_name),
                'option_value' => $option_value,
                'autoload' => sanitize_text_field($autoload),
                'log_id' => $log_id ? intval($log_id) : null,
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );

        return $wpdb->insert_id;
    }

    /**
     * Очистка старых резервных копий (старше 7 дней)
     */
    public static function cleanup_old_backups() {
        global $wpdb;

        $backup_table = $wpdb->prefix . 'wpo_backup';

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $backup_table WHERE deleted_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                7
            )
        );

        return $deleted;
    }

    /**
     * Получение логов операций
     */
    public static function get_logs($limit = 50, $offset = 0) {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'wpo_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        return $logs;
    }

    /**
     * Получение истории изменений таблицы
     */
    public static function get_history($days = 30) {
        global $wpdb;

        $history_table = $wpdb->prefix . 'wpo_history';

        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $history_table
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY recorded_at ASC",
            $days
        ));

        return $history;
    }

    /**
     * Восстановление записи из резервной копии
     */
    public static function restore_option($backup_id) {
        global $wpdb;

        $backup_table = $wpdb->prefix . 'wpo_backup';

        // Получаем данные из бэкапа
        $backup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $backup_table WHERE id = %d",
            $backup_id
        ));

        if (!$backup) {
            return false;
        }

        // Восстанавливаем опцию
        $result = $wpdb->replace(
            $wpdb->options,
            array(
                'option_name' => $backup->option_name,
                'option_value' => $backup->option_value,
                'autoload' => $backup->autoload,
            ),
            array('%s', '%s', '%s')
        );

        if ($result) {
            // Удаляем запись из бэкапа
            $wpdb->delete($backup_table, array('id' => $backup_id), array('%d'));
            return true;
        }

        return false;
    }
}
