<?php
/**
 * Logs View
 *
 * @package WC_AI_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

$log = new WCAT_Translation_Log();
$statistics = $log->get_statistics();
?>

<div class="wrap">
    <h1><?php _e('Translation Logs', 'wc-ai-translator'); ?></h1>

    <div class="wcat-logs">
        <!-- Statistics -->
        <div class="wcat-cards-container">
            <div class="wcat-card">
                <h3><?php _e('Total Translations', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($statistics['total']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Successful', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($statistics['success']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Failed', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($statistics['failed']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Tokens Used', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo number_format($statistics['tokens_used']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Total Cost', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number">$<?php echo number_format($statistics['total_cost'], 2); ?></p>
            </div>
        </div>

        <!-- Actions -->
        <div class="wcat-section">
            <h2><?php _e('Actions', 'wc-ai-translator'); ?></h2>
            <p>
                <button type="button" class="button" id="wcat-refresh-logs">
                    <?php _e('Refresh Logs', 'wc-ai-translator'); ?>
                </button>
                <button type="button" class="button" id="wcat-export-logs">
                    <?php _e('Export to CSV', 'wc-ai-translator'); ?>
                </button>
                <button type="button" class="button button-link-delete" id="wcat-clear-logs-button">
                    <?php _e('Clear All Logs', 'wc-ai-translator'); ?>
                </button>
            </p>
        </div>

        <!-- Logs Table -->
        <div class="wcat-section">
            <h2><?php _e('Translation History', 'wc-ai-translator'); ?></h2>

            <table class="widefat" id="wcat-logs-table">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Content', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Type', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Languages', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Action', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Status', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Tokens', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Cost', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Message', 'wc-ai-translator'); ?></th>
                    </tr>
                </thead>
                <tbody id="wcat-logs-items">
                    <?php
                    $logs = $log->get_logs(array('limit' => 100));
                    if (!empty($logs)):
                        foreach ($logs as $log_entry):
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log_entry['created_at']))); ?></td>
                            <td>#<?php echo esc_html($log_entry['content_id']); ?></td>
                            <td><?php echo esc_html($log_entry['content_type']); ?></td>
                            <td><?php echo esc_html(strtoupper($log_entry['source_lang']) . ' → ' . strtoupper($log_entry['target_lang'])); ?></td>
                            <td><?php echo esc_html($log_entry['action']); ?></td>
                            <td>
                                <span class="wcat-status wcat-status-<?php echo esc_attr($log_entry['status']); ?>">
                                    <?php echo esc_html(ucfirst($log_entry['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($log_entry['tokens_used']); ?></td>
                            <td>$<?php echo number_format($log_entry['cost'], 4); ?></td>
                            <td><?php echo esc_html($log_entry['message']); ?></td>
                        </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="9"><?php _e('No logs available.', 'wc-ai-translator'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
