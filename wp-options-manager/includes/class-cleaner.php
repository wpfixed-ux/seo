<?php
/**
 * Класс для безопасной очистки таблицы wp_options
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Класс WPOM_Cleaner
 */
class WPOM_Cleaner {

    /**
     * Единственный экземпляр класса
     */
    private static $instance = null;

    /**
     * Список критических опций, которые нельзя удалять
     */
    private $protected_options = array(
        'siteurl',
        'home',
        'blogname',
        'blogdescription',
        'users_can_register',
        'admin_email',
        'start_of_week',
        'use_balanceTags',
        'use_smilies',
        'require_name_email',
        'comments_notify',
        'posts_per_rss',
        'rss_use_excerpt',
        'mailserver_url',
        'mailserver_login',
        'mailserver_pass',
        'mailserver_port',
        'default_category',
        'default_comment_status',
        'default_ping_status',
        'default_pingback_flag',
        'posts_per_page',
        'date_format',
        'time_format',
        'links_updated_date_format',
        'comment_moderation',
        'moderation_notify',
        'permalink_structure',
        'rewrite_rules',
        'hack_file',
        'blog_charset',
        'moderation_keys',
        'active_plugins',
        'category_base',
        'ping_sites',
        'comment_max_links',
        'gmt_offset',
        'default_email_category',
        'recently_edited',
        'template',
        'stylesheet',
        'comment_registration',
        'html_type',
        'use_trackback',
        'default_role',
        'db_version',
        'uploads_use_yearmonth_folders',
        'upload_path',
        'blog_public',
        'default_link_category',
        'show_on_front',
        'tag_base',
        'show_avatars',
        'avatar_rating',
        'upload_url_path',
        'thumbnail_size_w',
        'thumbnail_size_h',
        'thumbnail_crop',
        'medium_size_w',
        'medium_size_h',
        'avatar_default',
        'large_size_w',
        'large_size_h',
        'image_default_link_type',
        'image_default_size',
        'image_default_align',
        'close_comments_for_old_posts',
        'close_comments_days_old',
        'thread_comments',
        'thread_comments_depth',
        'page_comments',
        'comments_per_page',
        'default_comments_page',
        'comment_order',
        'sticky_posts',
        'widget_categories',
        'widget_text',
        'widget_rss',
        'uninstall_plugins',
        'timezone_string',
        'page_for_posts',
        'page_on_front',
        'default_post_format',
        'link_manager_enabled',
        'finished_splitting_shared_terms',
        'site_icon',
        'medium_large_size_w',
        'medium_large_size_h',
        'wp_page_for_privacy_policy',
        'show_comments_cookies_opt_in',
        'admin_email_lifespan',
        'disallowed_keys',
        'comment_previously_approved',
        'auto_plugin_theme_update_emails',
        'auto_update_core_dev',
        'auto_update_core_minor',
        'auto_update_core_major',
    );

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
     * Очистка истекших transients
     */
    public function clean_expired_transients($create_backup = true) {
        global $wpdb;
        $options_table = $wpdb->options;

        // Начало транзакции
        $wpdb->query('START TRANSACTION');

        try {
            // Получаем список истекших transients
            $expired_transients = $wpdb->get_results(
                "SELECT o1.option_id, o1.option_name, o1.option_value, o1.autoload
                FROM $options_table as o1
                JOIN $options_table as o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
                WHERE o1.option_name LIKE '_transient_%'
                AND o1.option_name NOT LIKE '_transient_timeout_%'
                AND o2.option_value < UNIX_TIMESTAMP()"
            );

            $deleted_count = 0;
            $size_freed = 0;
            $log_id = null;

            if (!empty($expired_transients)) {
                // Создаем лог операции
                $log_id = WPOM_Database::log_operation(
                    'clean_expired_transients',
                    array('type' => 'expired_transients', 'count' => count($expired_transients)),
                    0,
                    0,
                    'in_progress'
                );

                foreach ($expired_transients as $transient) {
                    $size_freed += strlen($transient->option_value);

                    // Создаем резервную копию
                    if ($create_backup) {
                        WPOM_Database::backup_option(
                            $transient->option_id,
                            $transient->option_name,
                            $transient->option_value,
                            $transient->autoload,
                            $log_id
                        );
                    }

                    // Удаляем transient и его timeout
                    $timeout_name = '_transient_timeout_' . substr($transient->option_name, 11);
                    delete_transient(substr($transient->option_name, 11));

                    $deleted_count++;
                }

                // Обновляем лог
                $wpdb->update(
                    $wpdb->prefix . 'wpo_logs',
                    array(
                        'items_affected' => $deleted_count,
                        'size_freed' => $size_freed,
                        'status' => 'completed'
                    ),
                    array('id' => $log_id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            }

            // Оптимизация таблицы
            $wpdb->query("OPTIMIZE TABLE $options_table");

            // Завершение транзакции
            $wpdb->query('COMMIT');

            // Записываем новое состояние в историю
            WPOM_Database::record_current_state();

            return array(
                'success' => true,
                'deleted' => $deleted_count,
                'size_freed' => $size_freed,
                'log_id' => $log_id,
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Очистка orphaned transients (без таймаутов)
     */
    public function clean_orphaned_transients($create_backup = true) {
        global $wpdb;
        $options_table = $wpdb->options;

        $wpdb->query('START TRANSACTION');

        try {
            // Получаем список orphaned transients
            $orphaned_transients = $wpdb->get_results(
                "SELECT o1.option_id, o1.option_name, o1.option_value, o1.autoload
                FROM $options_table as o1
                LEFT JOIN $options_table as o2 ON o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o1.option_name, 12))
                WHERE o1.option_name LIKE '_transient_%'
                AND o1.option_name NOT LIKE '_transient_timeout_%'
                AND o2.option_id IS NULL"
            );

            $deleted_count = 0;
            $size_freed = 0;
            $log_id = null;

            if (!empty($orphaned_transients)) {
                $log_id = WPOM_Database::log_operation(
                    'clean_orphaned_transients',
                    array('type' => 'orphaned_transients', 'count' => count($orphaned_transients)),
                    0,
                    0,
                    'in_progress'
                );

                foreach ($orphaned_transients as $transient) {
                    $size_freed += strlen($transient->option_value);

                    if ($create_backup) {
                        WPOM_Database::backup_option(
                            $transient->option_id,
                            $transient->option_name,
                            $transient->option_value,
                            $transient->autoload,
                            $log_id
                        );
                    }

                    // Удаляем orphaned transient
                    $wpdb->delete($options_table, array('option_id' => $transient->option_id), array('%d'));

                    $deleted_count++;
                }

                $wpdb->update(
                    $wpdb->prefix . 'wpo_logs',
                    array(
                        'items_affected' => $deleted_count,
                        'size_freed' => $size_freed,
                        'status' => 'completed'
                    ),
                    array('id' => $log_id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            }

            $wpdb->query("OPTIMIZE TABLE $options_table");
            $wpdb->query('COMMIT');

            WPOM_Database::record_current_state();

            return array(
                'success' => true,
                'deleted' => $deleted_count,
                'size_freed' => $size_freed,
                'log_id' => $log_id,
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Очистка WooCommerce сессий
     */
    public function clean_woocommerce_sessions($force = false, $create_backup = true) {
        if (!class_exists('WooCommerce')) {
            return array(
                'success' => false,
                'error' => 'WooCommerce not installed',
            );
        }

        global $wpdb;
        $options_table = $wpdb->options;

        $wpdb->query('START TRANSACTION');

        try {
            if ($force) {
                // Полная очистка всех сессий
                $sessions = $wpdb->get_results(
                    "SELECT option_id, option_name, option_value, autoload
                    FROM $options_table
                    WHERE option_name LIKE '_wc_session_%'"
                );
            } else {
                // Только истекшие сессии (старше 48 часов)
                $sessions = $wpdb->get_results(
                    "SELECT option_id, option_name, option_value, autoload
                    FROM $options_table
                    WHERE option_name LIKE '_wc_session_%'
                    AND option_name IN (
                        SELECT option_name
                        FROM $options_table
                        WHERE option_name LIKE '_wc_session_%'
                        AND option_value LIKE '%\"expiry\";i:%'
                    )"
                );
            }

            $deleted_count = 0;
            $size_freed = 0;
            $log_id = null;

            if (!empty($sessions)) {
                $log_id = WPOM_Database::log_operation(
                    'clean_wc_sessions',
                    array('type' => $force ? 'all_sessions' : 'expired_sessions', 'count' => count($sessions)),
                    0,
                    0,
                    'in_progress'
                );

                foreach ($sessions as $session) {
                    $size_freed += strlen($session->option_value);

                    if ($create_backup) {
                        WPOM_Database::backup_option(
                            $session->option_id,
                            $session->option_name,
                            $session->option_value,
                            $session->autoload,
                            $log_id
                        );
                    }

                    $wpdb->delete($options_table, array('option_id' => $session->option_id), array('%d'));
                    $deleted_count++;
                }

                $wpdb->update(
                    $wpdb->prefix . 'wpo_logs',
                    array(
                        'items_affected' => $deleted_count,
                        'size_freed' => $size_freed,
                        'status' => 'completed'
                    ),
                    array('id' => $log_id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            }

            $wpdb->query("OPTIMIZE TABLE $options_table");
            $wpdb->query('COMMIT');

            WPOM_Database::record_current_state();

            return array(
                'success' => true,
                'deleted' => $deleted_count,
                'size_freed' => $size_freed,
                'log_id' => $log_id,
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Очистка по паттерну
     */
    public function clean_by_pattern($pattern, $create_backup = true) {
        global $wpdb;
        $options_table = $wpdb->options;

        // Проверка на защищенные опции
        if ($this->is_protected_pattern($pattern)) {
            return array(
                'success' => false,
                'error' => 'Cannot delete protected options',
            );
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Получаем список опций по паттерну
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id, option_name, option_value, autoload
                FROM $options_table
                WHERE option_name LIKE %s",
                $pattern
            ));

            // Фильтруем защищенные опции
            $options = array_filter($options, function($option) {
                return !in_array($option->option_name, $this->protected_options);
            });

            $deleted_count = 0;
            $size_freed = 0;
            $log_id = null;

            if (!empty($options)) {
                $log_id = WPOM_Database::log_operation(
                    'clean_by_pattern',
                    array('pattern' => $pattern, 'count' => count($options)),
                    0,
                    0,
                    'in_progress'
                );

                foreach ($options as $option) {
                    $size_freed += strlen($option->option_value);

                    if ($create_backup) {
                        WPOM_Database::backup_option(
                            $option->option_id,
                            $option->option_name,
                            $option->option_value,
                            $option->autoload,
                            $log_id
                        );
                    }

                    $wpdb->delete($options_table, array('option_id' => $option->option_id), array('%d'));
                    $deleted_count++;
                }

                $wpdb->update(
                    $wpdb->prefix . 'wpo_logs',
                    array(
                        'items_affected' => $deleted_count,
                        'size_freed' => $size_freed,
                        'status' => 'completed'
                    ),
                    array('id' => $log_id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
            }

            $wpdb->query("OPTIMIZE TABLE $options_table");
            $wpdb->query('COMMIT');

            WPOM_Database::record_current_state();

            return array(
                'success' => true,
                'deleted' => $deleted_count,
                'size_freed' => $size_freed,
                'log_id' => $log_id,
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Предпросмотр удаления по паттерну
     */
    public function preview_pattern_cleanup($pattern) {
        global $wpdb;
        $options_table = $wpdb->options;

        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, ROUND(LENGTH(option_value) / 1024, 2) as size_kb, autoload
            FROM $options_table
            WHERE option_name LIKE %s
            LIMIT 100",
            $pattern
        ));

        // Фильтруем защищенные опции
        $options = array_filter($options, function($option) {
            return !in_array($option->option_name, $this->protected_options);
        });

        $total_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM $options_table
            WHERE option_name LIKE %s",
            $pattern
        ));

        $total_size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value))
            FROM $options_table
            WHERE option_name LIKE %s",
            $pattern
        ));

        return array(
            'options' => array_values($options),
            'total_count' => intval($total_count),
            'total_size_kb' => round($total_size / 1024, 2),
            'protected_count' => intval($total_count) - count($options),
        );
    }

    /**
     * Проверка, является ли паттерн защищенным
     */
    private function is_protected_pattern($pattern) {
        // Проверяем, не совпадает ли паттерн с защищенными опциями
        foreach ($this->protected_options as $protected) {
            if (fnmatch($pattern, $protected)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Переключение autoload для больших опций
     */
    public function disable_autoload_for_large_options($threshold_kb = 100) {
        global $wpdb;
        $options_table = $wpdb->options;

        $updated_count = 0;

        $large_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_id, option_name
            FROM $options_table
            WHERE autoload = 'yes'
            AND LENGTH(option_value) > %d",
            $threshold_kb * 1024
        ));

        foreach ($large_options as $option) {
            // Не трогаем защищенные опции
            if (in_array($option->option_name, $this->protected_options)) {
                continue;
            }

            $result = $wpdb->update(
                $options_table,
                array('autoload' => 'no'),
                array('option_id' => $option->option_id),
                array('%s'),
                array('%d')
            );

            if ($result) {
                $updated_count++;
            }
        }

        if ($updated_count > 0) {
            WPOM_Database::log_operation(
                'disable_autoload',
                array('threshold_kb' => $threshold_kb, 'count' => $updated_count),
                $updated_count,
                0,
                'completed'
            );

            WPOM_Database::record_current_state();
        }

        return array(
            'success' => true,
            'updated' => $updated_count,
        );
    }

    /**
     * Получение списка известных плагинов с их паттернами
     */
    public static function get_known_plugins() {
        return array(
            'elementor' => array(
                'name' => 'Elementor',
                'patterns' => array('elementor%', '_elementor%'),
                'file' => 'elementor/elementor.php',
            ),
            'rank-math' => array(
                'name' => 'Rank Math SEO',
                'patterns' => array('rank_math%', 'rank-math%'),
                'file' => 'seo-by-rank-math/rank-math.php',
            ),
            'yoast' => array(
                'name' => 'Yoast SEO',
                'patterns' => array('wpseo%', '_yoast%'),
                'file' => 'wordpress-seo/wp-seo.php',
            ),
            'wpml' => array(
                'name' => 'WPML',
                'patterns' => array('wpml%', '_wpml%', 'icl%'),
                'file' => 'sitepress-multilingual-cms/sitepress.php',
            ),
            'litespeed' => array(
                'name' => 'LiteSpeed Cache',
                'patterns' => array('litespeed%'),
                'file' => 'litespeed-cache/litespeed-cache.php',
            ),
            'wpallimport' => array(
                'name' => 'WP All Import',
                'patterns' => array('wpallimport%', 'wp_all_import%'),
                'file' => 'wp-all-import-pro/wp-all-import-pro.php',
            ),
            'wordfence' => array(
                'name' => 'Wordfence',
                'patterns' => array('wordfence%', 'wf%'),
                'file' => 'wordfence/wordfence.php',
            ),
            'jetpack' => array(
                'name' => 'Jetpack',
                'patterns' => array('jetpack%'),
                'file' => 'jetpack/jetpack.php',
            ),
        );
    }

    /**
     * Проверка активности плагина
     */
    public function is_plugin_active($plugin_file) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return is_plugin_active($plugin_file);
    }

    /**
     * Анализ опций конкретного плагина
     */
    public function analyze_plugin_options($plugin_slug) {
        $known_plugins = self::get_known_plugins();

        if (!isset($known_plugins[$plugin_slug])) {
            return array(
                'success' => false,
                'error' => 'Unknown plugin',
            );
        }

        $plugin_info = $known_plugins[$plugin_slug];
        $is_active = $this->is_plugin_active($plugin_info['file']);

        global $wpdb;
        $options_table = $wpdb->options;

        $all_options = array();
        $total_count = 0;
        $total_size = 0;

        foreach ($plugin_info['patterns'] as $pattern) {
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, ROUND(LENGTH(option_value) / 1024, 2) as size_kb, autoload
                FROM $options_table
                WHERE option_name LIKE %s
                LIMIT 50",
                $pattern
            ));

            $all_options = array_merge($all_options, $options);

            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                FROM $options_table
                WHERE option_name LIKE %s",
                $pattern
            ));

            $size = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(LENGTH(option_value))
                FROM $options_table
                WHERE option_name LIKE %s",
                $pattern
            ));

            $total_count += intval($count);
            $total_size += intval($size);
        }

        return array(
            'success' => true,
            'plugin_name' => $plugin_info['name'],
            'is_active' => $is_active,
            'can_clean' => !$is_active,
            'total_count' => $total_count,
            'total_size_kb' => round($total_size / 1024, 2),
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'options' => $all_options,
            'warning' => $is_active ? 'Plugin is active! Deactivate it before cleaning.' : '',
        );
    }

    /**
     * Очистка опций неактивного плагина
     */
    public function clean_plugin_options($plugin_slug, $create_backup = true) {
        $known_plugins = self::get_known_plugins();

        if (!isset($known_plugins[$plugin_slug])) {
            return array(
                'success' => false,
                'error' => 'Unknown plugin',
            );
        }

        $plugin_info = $known_plugins[$plugin_slug];

        // Проверяем, что плагин неактивен
        if ($this->is_plugin_active($plugin_info['file'])) {
            return array(
                'success' => false,
                'error' => 'Cannot delete options of active plugin! Please deactivate it first.',
            );
        }

        global $wpdb;
        $options_table = $wpdb->options;

        $wpdb->query('START TRANSACTION');

        try {
            $total_deleted = 0;
            $total_size_freed = 0;

            $log_id = WPOM_Database::log_operation(
                'clean_plugin_options',
                array('plugin' => $plugin_info['name'], 'patterns' => $plugin_info['patterns']),
                0,
                0,
                'in_progress'
            );

            foreach ($plugin_info['patterns'] as $pattern) {
                $options = $wpdb->get_results($wpdb->prepare(
                    "SELECT option_id, option_name, option_value, autoload
                    FROM $options_table
                    WHERE option_name LIKE %s",
                    $pattern
                ));

                // Фильтруем защищенные опции
                $options = array_filter($options, function($option) {
                    return !in_array($option->option_name, $this->protected_options);
                });

                foreach ($options as $option) {
                    $total_size_freed += strlen($option->option_value);

                    if ($create_backup) {
                        WPOM_Database::backup_option(
                            $option->option_id,
                            $option->option_name,
                            $option->option_value,
                            $option->autoload,
                            $log_id
                        );
                    }

                    $wpdb->delete($options_table, array('option_id' => $option->option_id), array('%d'));
                    $total_deleted++;
                }
            }

            $wpdb->update(
                $wpdb->prefix . 'wpo_logs',
                array(
                    'items_affected' => $total_deleted,
                    'size_freed' => $total_size_freed,
                    'status' => 'completed'
                ),
                array('id' => $log_id),
                array('%d', '%d', '%s'),
                array('%d')
            );

            $wpdb->query("OPTIMIZE TABLE $options_table");
            $wpdb->query('COMMIT');

            WPOM_Database::record_current_state();

            return array(
                'success' => true,
                'deleted' => $total_deleted,
                'size_freed' => $total_size_freed,
                'size_freed_kb' => round($total_size_freed / 1024, 2),
                'log_id' => $log_id,
                'plugin_name' => $plugin_info['name'],
            );

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }
}
