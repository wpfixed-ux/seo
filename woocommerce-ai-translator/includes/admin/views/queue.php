<?php
/**
 * Queue View
 *
 * @package WC_AI_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

$queue = new WCAT_Translation_Queue();
$queue_manager = new WCAT_Queue_Manager();
$progress = $queue_manager->get_progress();
?>

<div class="wrap">
    <h1><?php _e('Translation Queue', 'wc-ai-translator'); ?></h1>

    <div class="wcat-queue">
        <!-- Queue Statistics -->
        <div class="wcat-cards-container">
            <div class="wcat-card">
                <h3><?php _e('Total', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($progress['total']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Pending', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($progress['pending']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Processing', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($progress['processing']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Completed', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($progress['completed']); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Failed', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($progress['failed']); ?></p>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="wcat-section">
            <h2><?php _e('Overall Progress', 'wc-ai-translator'); ?></h2>
            <div class="wcat-progress-bar-large">
                <div class="wcat-progress-fill" style="width: <?php echo esc_attr($progress['progress_percentage']); ?>%;"></div>
            </div>
            <p><?php echo esc_html($progress['progress_percentage']); ?>% <?php _e('Complete', 'wc-ai-translator'); ?></p>
        </div>

        <!-- Queue Actions -->
        <div class="wcat-section">
            <h2><?php _e('Queue Actions', 'wc-ai-translator'); ?></h2>
            <p>
                <?php if ($progress['is_paused']): ?>
                    <button type="button" class="button button-primary" id="wcat-resume-queue">
                        <?php _e('Resume Queue', 'wc-ai-translator'); ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="button" id="wcat-pause-queue">
                        <?php _e('Pause Queue', 'wc-ai-translator'); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="button" id="wcat-retry-failed">
                    <?php _e('Retry Failed', 'wc-ai-translator'); ?>
                </button>

                <button type="button" class="button" id="wcat-clear-completed">
                    <?php _e('Clear Completed', 'wc-ai-translator'); ?>
                </button>

                <button type="button" class="button" id="wcat-refresh-queue">
                    <?php _e('Refresh', 'wc-ai-translator'); ?>
                </button>
            </p>
        </div>

        <!-- Queue Items -->
        <div class="wcat-section">
            <h2><?php _e('Queue Items', 'wc-ai-translator'); ?></h2>

            <table class="widefat" id="wcat-queue-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Content', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Type', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Languages', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Status', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Priority', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Attempts', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Created', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Actions', 'wc-ai-translator'); ?></th>
                    </tr>
                </thead>
                <tbody id="wcat-queue-items">
                    <?php
                    $items = $queue->get_queue_items(array('limit' => 100));
                    if (!empty($items)):
                        foreach ($items as $item):
                    ?>
                        <tr data-queue-id="<?php echo esc_attr($item['id']); ?>">
                            <td><?php echo esc_html($item['id']); ?></td>
                            <td>#<?php echo esc_html($item['content_id']); ?></td>
                            <td><?php echo esc_html($item['content_type']); ?></td>
                            <td><?php echo esc_html(strtoupper($item['source_lang']) . ' → ' . strtoupper($item['target_lang'])); ?></td>
                            <td>
                                <span class="wcat-status wcat-status-<?php echo esc_attr($item['status']); ?>">
                                    <?php echo esc_html(ucfirst($item['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($item['priority']); ?></td>
                            <td><?php echo esc_html($item['attempts']); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($item['created_at']))); ?></td>
                            <td>
                                <?php if ($item['status'] === 'pending' || $item['status'] === 'failed'): ?>
                                    <button type="button" class="button button-small wcat-cancel-item" data-queue-id="<?php echo esc_attr($item['id']); ?>">
                                        <?php _e('Cancel', 'wc-ai-translator'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="9"><?php _e('No items in queue.', 'wc-ai-translator'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
