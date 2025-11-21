<?php
/**
 * Dashboard страница
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

$diagnostic = WPOM_Diagnostic::get_instance();
$stats = $diagnostic->get_general_stats();
$transients = $diagnostic->analyze_transients();
$wc_sessions = $diagnostic->analyze_woocommerce_sessions();
?>

<div class="wrap wpom-dashboard">
    <h1><?php _e('WP Options Table Manager - Dashboard', 'wp-options-manager'); ?></h1>

    <div class="wpom-grid">
        <!-- Общая статистика -->
        <div class="wpom-card wpom-stats-card">
            <h2><?php _e('Table Statistics', 'wp-options-manager'); ?></h2>
            <div class="wpom-stats-grid">
                <div class="wpom-stat-item">
                    <div class="wpom-stat-label"><?php _e('Table Size', 'wp-options-manager'); ?></div>
                    <div class="wpom-stat-value"><?php echo esc_html($stats['table_size_mb']); ?> MB</div>
                </div>
                <div class="wpom-stat-item">
                    <div class="wpom-stat-label"><?php _e('Total Records', 'wp-options-manager'); ?></div>
                    <div class="wpom-stat-value"><?php echo esc_html(number_format($stats['total_records'])); ?></div>
                </div>
                <div class="wpom-stat-item">
                    <div class="wpom-stat-label"><?php _e('Autoload Size', 'wp-options-manager'); ?></div>
                    <div class="wpom-stat-value wpom-status-<?php echo esc_attr($stats['autoload_status']); ?>">
                        <?php echo esc_html($stats['autoload_size_kb']); ?> KB
                    </div>
                    <div class="wpom-stat-hint">
                        <?php _e('Recommended: < 800 KB', 'wp-options-manager'); ?>
                    </div>
                </div>
                <div class="wpom-stat-item">
                    <div class="wpom-stat-label"><?php _e('Autoload Records', 'wp-options-manager'); ?></div>
                    <div class="wpom-stat-value"><?php echo esc_html(number_format($stats['autoload_records'])); ?></div>
                </div>
            </div>

            <?php if ($stats['autoload_status'] === 'red'): ?>
                <div class="wpom-alert wpom-alert-danger">
                    <strong><?php _e('Critical:', 'wp-options-manager'); ?></strong>
                    <?php _e('Autoload size is too large! This can significantly slow down your site.', 'wp-options-manager'); ?>
                </div>
            <?php elseif ($stats['autoload_status'] === 'yellow'): ?>
                <div class="wpom-alert wpom-alert-warning">
                    <strong><?php _e('Warning:', 'wp-options-manager'); ?></strong>
                    <?php _e('Autoload size is approaching the limit. Consider optimization.', 'wp-options-manager'); ?>
                </div>
            <?php else: ?>
                <div class="wpom-alert wpom-alert-success">
                    <strong><?php _e('Good:', 'wp-options-manager'); ?></strong>
                    <?php _e('Autoload size is within acceptable limits.', 'wp-options-manager'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Быстрые действия -->
        <div class="wpom-card wpom-quick-actions">
            <h2><?php _e('Quick Actions', 'wp-options-manager'); ?></h2>

            <div class="wpom-action-item">
                <div class="wpom-action-info">
                    <strong><?php _e('Clean Expired Transients', 'wp-options-manager'); ?></strong>
                    <p><?php printf(__('Found %d expired transients', 'wp-options-manager'), $transients['expired']); ?></p>
                </div>
                <button class="button button-primary wpom-btn-clean-transients" data-type="expired">
                    <?php _e('Clean Now', 'wp-options-manager'); ?>
                </button>
            </div>

            <div class="wpom-action-item">
                <div class="wpom-action-info">
                    <strong><?php _e('Clean Orphaned Transients', 'wp-options-manager'); ?></strong>
                    <p><?php printf(__('Found %d orphaned transients', 'wp-options-manager'), $transients['orphaned']); ?></p>
                </div>
                <button class="button button-primary wpom-btn-clean-transients" data-type="orphaned">
                    <?php _e('Clean Now', 'wp-options-manager'); ?>
                </button>
            </div>

            <?php if ($wc_sessions['enabled']): ?>
                <div class="wpom-action-item">
                    <div class="wpom-action-info">
                        <strong><?php _e('Clean WooCommerce Sessions', 'wp-options-manager'); ?></strong>
                        <p><?php printf(__('Found %d sessions (%s MB)', 'wp-options-manager'), $wc_sessions['total_sessions'], $wc_sessions['size_mb']); ?></p>
                    </div>
                    <button class="button button-primary wpom-btn-clean-wc-sessions" data-force="false">
                        <?php _e('Clean Expired', 'wp-options-manager'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="wpom-action-item">
                <div class="wpom-action-info">
                    <strong><?php _e('Disable Autoload for Large Options', 'wp-options-manager'); ?></strong>
                    <p><?php _e('Optimize autoload by disabling it for large options (>100KB)', 'wp-options-manager'); ?></p>
                </div>
                <button class="button button-secondary wpom-btn-disable-autoload">
                    <?php _e('Optimize', 'wp-options-manager'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Ссылки на другие страницы -->
    <div class="wpom-navigation-cards">
        <a href="<?php echo admin_url('admin.php?page=wpom-diagnostic'); ?>" class="wpom-nav-card">
            <span class="dashicons dashicons-search"></span>
            <h3><?php _e('Detailed Diagnostic', 'wp-options-manager'); ?></h3>
            <p><?php _e('View detailed analysis of your wp_options table', 'wp-options-manager'); ?></p>
        </a>

        <a href="<?php echo admin_url('admin.php?page=wpom-cleaner'); ?>" class="wpom-nav-card">
            <span class="dashicons dashicons-trash"></span>
            <h3><?php _e('Advanced Cleaner', 'wp-options-manager'); ?></h3>
            <p><?php _e('Clean specific data patterns and manage options', 'wp-options-manager'); ?></p>
        </a>
    </div>

    <!-- Лоадер -->
    <div id="wpom-loader" class="wpom-loader" style="display: none;">
        <div class="wpom-loader-spinner"></div>
        <p><?php _e('Processing...', 'wp-options-manager'); ?></p>
    </div>
</div>
