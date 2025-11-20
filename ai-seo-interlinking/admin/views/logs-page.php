<?php
/**
 * Logs Page Template
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

// $logs variable is passed from class-admin.php
?>

<div class="wrap ail-logs-page">
    <h1><?php _e('AI SEO Interlinking Logs', 'ai-seo-interlinking'); ?></h1>

    <div class="ail-logs-filters">
        <form method="get">
            <input type="hidden" name="page" value="ai-seo-interlinking-logs">

            <select name="operation_type">
                <option value=""><?php _e('All Operations', 'ai-seo-interlinking'); ?></option>
                <option value="api_request" <?php selected($_GET['operation_type'] ?? '', 'api_request'); ?>>
                    <?php _e('API Requests', 'ai-seo-interlinking'); ?>
                </option>
                <option value="build_links" <?php selected($_GET['operation_type'] ?? '', 'build_links'); ?>>
                    <?php _e('Build Links', 'ai-seo-interlinking'); ?>
                </option>
                <option value="batch_processing" <?php selected($_GET['operation_type'] ?? '', 'batch_processing'); ?>>
                    <?php _e('Batch Processing', 'ai-seo-interlinking'); ?>
                </option>
            </select>

            <select name="status">
                <option value=""><?php _e('All Statuses', 'ai-seo-interlinking'); ?></option>
                <option value="success" <?php selected($_GET['status'] ?? '', 'success'); ?>>
                    <?php _e('Success', 'ai-seo-interlinking'); ?>
                </option>
                <option value="error" <?php selected($_GET['status'] ?? '', 'error'); ?>>
                    <?php _e('Error', 'ai-seo-interlinking'); ?>
                </option>
            </select>

            <button type="submit" class="button"><?php _e('Filter', 'ai-seo-interlinking'); ?></button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 5%;"><?php _e('ID', 'ai-seo-interlinking'); ?></th>
                <th style="width: 15%;"><?php _e('Operation', 'ai-seo-interlinking'); ?></th>
                <th style="width: 40%;"><?php _e('Details', 'ai-seo-interlinking'); ?></th>
                <th style="width: 10%;"><?php _e('Tokens', 'ai-seo-interlinking'); ?></th>
                <th style="width: 10%;"><?php _e('Cost', 'ai-seo-interlinking'); ?></th>
                <th style="width: 10%;"><?php _e('Status', 'ai-seo-interlinking'); ?></th>
                <th style="width: 10%;"><?php _e('Date', 'ai-seo-interlinking'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="7" style="text-align: center;">
                    <?php _e('No logs found', 'ai-seo-interlinking'); ?>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->id); ?></td>
                    <td>
                        <strong><?php echo esc_html($log->operation_type); ?></strong>
                    </td>
                    <td>
                        <?php
                        $details = json_decode($log->details, true);
                        if (is_array($details)) {
                            echo '<details>';
                            echo '<summary>' . __('View Details', 'ai-seo-interlinking') . '</summary>';
                            echo '<pre>' . esc_html(print_r($details, true)) . '</pre>';
                            echo '</details>';
                        } else {
                            echo esc_html(substr($log->details, 0, 100));
                            if (strlen($log->details) > 100) {
                                echo '...';
                            }
                        }
                        ?>
                    </td>
                    <td><?php echo number_format($log->tokens_used); ?></td>
                    <td>$<?php echo number_format($log->cost, 4); ?></td>
                    <td>
                        <span class="ail-status ail-status-<?php echo esc_attr($log->status); ?>">
                            <?php echo esc_html($log->status); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($log->created_at))); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="ail-logs-actions">
        <button type="button" id="clear-logs" class="button button-secondary">
            <?php _e('Clear All Logs', 'ai-seo-interlinking'); ?>
        </button>
        <span class="description">
            <?php _e('Warning: This action cannot be undone!', 'ai-seo-interlinking'); ?>
        </span>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#clear-logs').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to clear all logs? This cannot be undone!', 'ai-seo-interlinking'); ?>')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'ail_clear_logs',
            nonce: '<?php echo wp_create_nonce('ail_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
});
</script>
