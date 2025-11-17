<?php
/**
 * Dashboard View
 *
 * @package WC_AI_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

$scanner = new WCAT_Content_Scanner();
$queue_manager = new WCAT_Queue_Manager();
$log = new WCAT_Translation_Log();

$statistics = $scanner->get_translation_statistics();
$queue_progress = $queue_manager->get_progress();
$log_stats = $log->get_statistics();
$polylang = new WCAT_Polylang_Translator();
$languages = $polylang->get_languages();
?>

<div class="wrap">
    <h1><?php _e('WooCommerce AI Translator - Dashboard', 'wc-ai-translator'); ?></h1>

    <div class="wcat-dashboard">
        <!-- Overview Cards -->
        <div class="wcat-cards-container">
            <div class="wcat-card">
                <h3><?php _e('Total Content', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($statistics['total_translatable']); ?></p>
                <p class="wcat-card-label"><?php _e('Translatable Items', 'wc-ai-translator'); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Missing Translations', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($statistics['total_missing']); ?></p>
                <p class="wcat-card-label"><?php _e('Items', 'wc-ai-translator'); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Queue Status', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number"><?php echo esc_html($queue_progress['pending']); ?></p>
                <p class="wcat-card-label"><?php _e('Pending', 'wc-ai-translator'); ?></p>
            </div>

            <div class="wcat-card">
                <h3><?php _e('Total Cost', 'wc-ai-translator'); ?></h3>
                <p class="wcat-card-number">$<?php echo number_format($log_stats['total_cost'], 2); ?></p>
                <p class="wcat-card-label"><?php _e('Spent on Translations', 'wc-ai-translator'); ?></p>
            </div>
        </div>

        <!-- Languages -->
        <div class="wcat-section">
            <h2><?php _e('Available Languages', 'wc-ai-translator'); ?></h2>
            <div class="wcat-languages">
                <?php foreach ($languages as $lang): ?>
                    <div class="wcat-language-item">
                        <img src="<?php echo esc_url($lang['flag']); ?>" alt="<?php echo esc_attr($lang['name']); ?>" />
                        <span><?php echo esc_html($lang['name']); ?></span>
                        <?php if ($lang['is_default']): ?>
                            <span class="wcat-badge"><?php _e('Default', 'wc-ai-translator'); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content Breakdown -->
        <div class="wcat-section">
            <h2><?php _e('Content Translation Status', 'wc-ai-translator'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Content Type', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Total', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Translated', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Missing', 'wc-ai-translator'); ?></th>
                        <th><?php _e('Progress', 'wc-ai-translator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statistics['content_types'] as $type => $data): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></td>
                            <td><?php echo esc_html($data['total']); ?></td>
                            <td><?php echo esc_html($data['translated']); ?></td>
                            <td><?php echo esc_html($data['missing']); ?></td>
                            <td>
                                <?php
                                $percentage = $data['total'] > 0 ? round(($data['translated'] / $data['total']) * 100) : 0;
                                ?>
                                <div class="wcat-progress-bar">
                                    <div class="wcat-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                                </div>
                                <span><?php echo esc_html($percentage); ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Activity -->
        <div class="wcat-section">
            <h2><?php _e('Recent Translation Activity', 'wc-ai-translator'); ?></h2>
            <?php
            $recent_logs = $log->get_logs(array('limit' => 10));
            if (!empty($recent_logs)):
            ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'wc-ai-translator'); ?></th>
                            <th><?php _e('Content', 'wc-ai-translator'); ?></th>
                            <th><?php _e('Languages', 'wc-ai-translator'); ?></th>
                            <th><?php _e('Status', 'wc-ai-translator'); ?></th>
                            <th><?php _e('Cost', 'wc-ai-translator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log_entry): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($log_entry['created_at']))); ?></td>
                                <td><?php echo esc_html($log_entry['content_type'] . ' #' . $log_entry['content_id']); ?></td>
                                <td><?php echo esc_html(strtoupper($log_entry['source_lang']) . ' → ' . strtoupper($log_entry['target_lang'])); ?></td>
                                <td>
                                    <span class="wcat-status wcat-status-<?php echo esc_attr($log_entry['status']); ?>">
                                        <?php echo esc_html(ucfirst($log_entry['status'])); ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($log_entry['cost'], 4); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No translation activity yet.', 'wc-ai-translator'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="wcat-section">
            <h2><?php _e('Quick Actions', 'wc-ai-translator'); ?></h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-ai-translator-batch'); ?>" class="button button-primary">
                    <?php _e('Start Batch Translation', 'wc-ai-translator'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-ai-translator-queue'); ?>" class="button">
                    <?php _e('View Queue', 'wc-ai-translator'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-ai-translator-settings'); ?>" class="button">
                    <?php _e('Configure Settings', 'wc-ai-translator'); ?>
                </a>
            </p>
        </div>
    </div>
</div>
