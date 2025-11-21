<?php
/**
 * Cleaner страница
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

$diagnostic = WPOM_Diagnostic::get_instance();
$transients = $diagnostic->analyze_transients();
$wc_sessions = $diagnostic->analyze_woocommerce_sessions();
?>

<div class="wrap wpom-cleaner">
    <h1><?php _e('Advanced Cleaner', 'wp-options-manager'); ?></h1>

    <div class="wpom-grid">
        <!-- Очистка Transients -->
        <div class="wpom-card">
            <h2><?php _e('Transients Cleanup', 'wp-options-manager'); ?></h2>

            <div class="wpom-clean-section">
                <div class="wpom-clean-info">
                    <h3><?php _e('Expired Transients', 'wp-options-manager'); ?></h3>
                    <p><?php printf(__('Found <strong>%d</strong> expired transients that can be safely removed.', 'wp-options-manager'), $transients['expired']); ?></p>
                </div>
                <button class="button button-primary wpom-btn-clean-transients" data-type="expired">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clean Expired', 'wp-options-manager'); ?>
                </button>
            </div>

            <hr>

            <div class="wpom-clean-section">
                <div class="wpom-clean-info">
                    <h3><?php _e('Orphaned Transients', 'wp-options-manager'); ?></h3>
                    <p><?php printf(__('Found <strong>%d</strong> orphaned transients (without timeout records).', 'wp-options-manager'), $transients['orphaned']); ?></p>
                </div>
                <button class="button button-primary wpom-btn-clean-transients" data-type="orphaned">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clean Orphaned', 'wp-options-manager'); ?>
                </button>
            </div>

            <hr>

            <div class="wpom-clean-section">
                <div class="wpom-clean-info">
                    <h3><?php _e('Clean All Transients', 'wp-options-manager'); ?></h3>
                    <p><?php printf(__('Total <strong>%d</strong> transients can be cleaned.', 'wp-options-manager'), $transients['cleanable']); ?></p>
                    <div class="wpom-alert wpom-alert-info">
                        <?php _e('This will create a backup of all deleted records.', 'wp-options-manager'); ?>
                    </div>
                </div>
                <button class="button button-primary wpom-btn-clean-all-transients">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clean All', 'wp-options-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Очистка WooCommerce -->
        <?php if ($wc_sessions['enabled']): ?>
            <div class="wpom-card">
                <h2><?php _e('WooCommerce Sessions', 'wp-options-manager'); ?></h2>

                <div class="wpom-clean-section">
                    <div class="wpom-clean-info">
                        <h3><?php _e('Clean Expired Sessions', 'wp-options-manager'); ?></h3>
                        <p><?php printf(__('Found <strong>%d</strong> sessions (<strong>%s MB</strong>).', 'wp-options-manager'), $wc_sessions['total_sessions'], number_format($wc_sessions['size_mb'], 2)); ?></p>
                        <p><?php _e('This will remove only expired sessions, preserving active shopping carts.', 'wp-options-manager'); ?></p>
                    </div>
                    <button class="button button-primary wpom-btn-clean-wc-sessions" data-force="false">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clean Expired Sessions', 'wp-options-manager'); ?>
                    </button>
                </div>

                <hr>

                <div class="wpom-clean-section">
                    <div class="wpom-clean-info">
                        <h3><?php _e('Force Clean All Sessions', 'wp-options-manager'); ?></h3>
                        <div class="wpom-alert wpom-alert-danger">
                            <strong><?php _e('Warning!', 'wp-options-manager'); ?></strong>
                            <?php _e('This will delete ALL WooCommerce sessions including active shopping carts. Use with caution!', 'wp-options-manager'); ?>
                        </div>
                    </div>
                    <button class="button button-secondary wpom-btn-clean-wc-sessions" data-force="true">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Force Clean All', 'wp-options-manager'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Очистка по паттерну -->
        <div class="wpom-card wpom-pattern-cleaner">
            <h2><?php _e('Clean by Pattern', 'wp-options-manager'); ?></h2>

            <div class="wpom-pattern-form">
                <div class="wpom-form-group">
                    <label for="wpom-pattern-input"><?php _e('Option Name Pattern', 'wp-options-manager'); ?></label>
                    <input type="text" id="wpom-pattern-input" class="regular-text" placeholder="example: _wpml_batch_report%" />
                    <p class="description">
                        <?php _e('Use % as wildcard. Example: _wpml_% will match all options starting with _wpml_', 'wp-options-manager'); ?>
                    </p>
                </div>

                <div class="wpom-form-actions">
                    <button class="button button-secondary wpom-btn-preview-pattern">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview', 'wp-options-manager'); ?>
                    </button>
                    <button class="button button-primary wpom-btn-clean-pattern" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clean', 'wp-options-manager'); ?>
                    </button>
                </div>
            </div>

            <div id="wpom-pattern-preview" class="wpom-pattern-preview" style="display: none;">
                <h3><?php _e('Preview Results', 'wp-options-manager'); ?></h3>
                <div id="wpom-pattern-preview-content"></div>
            </div>
        </div>

        <!-- Оптимизация Autoload -->
        <div class="wpom-card">
            <h2><?php _e('Autoload Optimization', 'wp-options-manager'); ?></h2>

            <div class="wpom-clean-section">
                <div class="wpom-clean-info">
                    <h3><?php _e('Disable Autoload for Large Options', 'wp-options-manager'); ?></h3>
                    <p><?php _e('Automatically disable autoload for options larger than specified threshold.', 'wp-options-manager'); ?></p>

                    <div class="wpom-form-group">
                        <label for="wpom-autoload-threshold"><?php _e('Threshold (KB)', 'wp-options-manager'); ?></label>
                        <input type="number" id="wpom-autoload-threshold" class="small-text" value="100" min="10" max="1000" />
                    </div>

                    <div class="wpom-alert wpom-alert-info">
                        <?php _e('This will set autoload=no for large options, reducing the amount of data loaded on every page.', 'wp-options-manager'); ?>
                    </div>
                </div>
                <button class="button button-primary wpom-btn-disable-autoload">
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Optimize Autoload', 'wp-options-manager'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Результаты операции -->
    <div id="wpom-operation-result" class="wpom-operation-result" style="display: none;"></div>

    <div class="wpom-actions">
        <a href="<?php echo admin_url('admin.php?page=wp-options-manager'); ?>" class="button">
            &larr; <?php _e('Back to Dashboard', 'wp-options-manager'); ?>
        </a>
    </div>

    <!-- Лоадер -->
    <div id="wpom-loader" class="wpom-loader" style="display: none;">
        <div class="wpom-loader-spinner"></div>
        <p><?php _e('Processing...', 'wp-options-manager'); ?></p>
    </div>
</div>
