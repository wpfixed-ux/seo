<?php
/**
 * Diagnostic страница
 *
 * @package WP_Options_Manager
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

$diagnostic = WPOM_Diagnostic::get_instance();
$stats = $diagnostic->get_general_stats();
$data_by_prefix = $diagnostic->get_data_by_prefix();
$top_options = $diagnostic->get_top_large_options(20);
?>

<div class="wrap wpom-diagnostic">
    <h1><?php _e('Detailed Diagnostic', 'wp-options-manager'); ?></h1>

    <div class="wpom-grid">
        <!-- Данные по префиксам -->
        <div class="wpom-card">
            <h2><?php _e('Data by Type', 'wp-options-manager'); ?></h2>
            <table class="wpom-table widefat">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'wp-options-manager'); ?></th>
                        <th><?php _e('Count', 'wp-options-manager'); ?></th>
                        <th><?php _e('Size (KB)', 'wp-options-manager'); ?></th>
                        <th><?php _e('Size (MB)', 'wp-options-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data_by_prefix)): ?>
                        <?php foreach ($data_by_prefix as $prefix => $data): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($data['label']); ?></strong>
                                    <br>
                                    <small class="wpom-text-muted"><?php echo esc_html($prefix); ?>*</small>
                                </td>
                                <td><?php echo esc_html(number_format($data['count'])); ?></td>
                                <td><?php echo esc_html(number_format($data['size_kb'], 2)); ?></td>
                                <td>
                                    <?php if ($data['size_mb'] > 1): ?>
                                        <span class="wpom-badge wpom-badge-warning"><?php echo esc_html(number_format($data['size_mb'], 2)); ?></span>
                                    <?php else: ?>
                                        <?php echo esc_html(number_format($data['size_mb'], 2)); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4"><?php _e('No data found', 'wp-options-manager'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Топ больших опций -->
        <div class="wpom-card">
            <h2><?php _e('Top 20 Largest Options', 'wp-options-manager'); ?></h2>
            <div class="wpom-table-wrapper">
                <table class="wpom-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Option Name', 'wp-options-manager'); ?></th>
                            <th><?php _e('Size (KB)', 'wp-options-manager'); ?></th>
                            <th><?php _e('Autoload', 'wp-options-manager'); ?></th>
                            <th><?php _e('Source', 'wp-options-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_options)): ?>
                            <?php foreach ($top_options as $option): ?>
                                <tr class="<?php echo $option['is_large'] ? 'wpom-row-warning' : ''; ?>">
                                    <td>
                                        <strong><?php echo esc_html($option['option_name']); ?></strong>
                                        <?php if ($option['is_large']): ?>
                                            <span class="wpom-badge wpom-badge-danger"><?php _e('Large', 'wp-options-manager'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($option['size_mb'] > 1): ?>
                                            <strong><?php echo esc_html(number_format($option['size_mb'], 2)); ?> MB</strong>
                                        <?php else: ?>
                                            <?php echo esc_html(number_format($option['size_kb'], 2)); ?> KB
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($option['autoload'] === 'yes'): ?>
                                            <span class="wpom-badge wpom-badge-warning"><?php _e('Yes', 'wp-options-manager'); ?></span>
                                        <?php else: ?>
                                            <span class="wpom-badge wpom-badge-success"><?php _e('No', 'wp-options-manager'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($option['source']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4"><?php _e('No data found', 'wp-options-manager'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wpom-actions">
        <a href="<?php echo admin_url('admin.php?page=wp-options-manager'); ?>" class="button">
            &larr; <?php _e('Back to Dashboard', 'wp-options-manager'); ?>
        </a>
        <button class="button button-secondary wpom-btn-refresh-diagnostic">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh Data', 'wp-options-manager'); ?>
        </button>
    </div>
</div>
