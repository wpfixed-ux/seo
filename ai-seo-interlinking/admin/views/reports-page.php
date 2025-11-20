<?php
/**
 * Reports Page Template
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = AIL_Logger::get_statistics(30);
$lang_stats = AIL_Multilang_Support::get_language_statistics();
$operation_summary = AIL_Logger::get_operation_summary(30);

global $wpdb;
$links_table = $wpdb->prefix . 'ai_interlinking_links';

// Get top linked pages
$top_inbound = $wpdb->get_results(
    "SELECT target_post_id, COUNT(*) as link_count
    FROM {$links_table}
    GROUP BY target_post_id
    ORDER BY link_count DESC
    LIMIT 10"
);

$top_outbound = $wpdb->get_results(
    "SELECT source_post_id, COUNT(*) as link_count
    FROM {$links_table}
    GROUP BY source_post_id
    ORDER BY link_count DESC
    LIMIT 10"
);
?>

<div class="wrap ail-reports-page">
    <h1><?php _e('AI SEO Interlinking Reports', 'ai-seo-interlinking'); ?></h1>

    <!-- Statistics Cards -->
    <div class="ail-stats-cards">
        <div class="ail-stat-card">
            <h3><?php _e('Total Links Created', 'ai-seo-interlinking'); ?></h3>
            <div class="ail-stat-value"><?php echo number_format($stats['links_created']); ?></div>
            <p class="ail-stat-label"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></p>
        </div>

        <div class="ail-stat-card">
            <h3><?php _e('API Calls', 'ai-seo-interlinking'); ?></h3>
            <div class="ail-stat-value"><?php echo number_format($stats['api_calls']); ?></div>
            <p class="ail-stat-label"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></p>
        </div>

        <div class="ail-stat-card">
            <h3><?php _e('Tokens Used', 'ai-seo-interlinking'); ?></h3>
            <div class="ail-stat-value"><?php echo number_format($stats['tokens_used']); ?></div>
            <p class="ail-stat-label"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></p>
        </div>

        <div class="ail-stat-card">
            <h3><?php _e('Estimated Cost', 'ai-seo-interlinking'); ?></h3>
            <div class="ail-stat-value">$<?php echo number_format($stats['total_cost'], 2); ?></div>
            <p class="ail-stat-label"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></p>
        </div>
    </div>

    <!-- Language Statistics -->
    <?php if (AIL_Multilang_Support::is_multilang_enabled()): ?>
    <div class="ail-section">
        <h2><?php _e('Statistics by Language', 'ai-seo-interlinking'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Language', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Links', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Keywords', 'ai-seo-interlinking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lang_stats as $lang => $data): ?>
                <tr>
                    <td><?php echo esc_html($data['language_name']); ?></td>
                    <td><?php echo number_format($data['links_count']); ?></td>
                    <td><?php echo number_format($data['keywords_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Operation Summary -->
    <div class="ail-section">
        <h2><?php _e('Operations Summary', 'ai-seo-interlinking'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Operation Type', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Count', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Tokens', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Cost', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Errors', 'ai-seo-interlinking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operation_summary as $operation): ?>
                <tr>
                    <td><?php echo esc_html($operation['operation_type']); ?></td>
                    <td><?php echo number_format($operation['count']); ?></td>
                    <td><?php echo number_format($operation['total_tokens']); ?></td>
                    <td>$<?php echo number_format($operation['total_cost'], 4); ?></td>
                    <td><?php echo number_format($operation['errors']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Linked Pages (Inbound) -->
    <div class="ail-section">
        <h2><?php _e('Top Pages by Inbound Links', 'ai-seo-interlinking'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Page', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Type', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Inbound Links', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Actions', 'ai-seo-interlinking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_inbound as $item): ?>
                <?php
                $post = get_post($item->target_post_id);
                if (!$post) continue;
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($post->post_title); ?></strong>
                    </td>
                    <td><?php echo esc_html($post->post_type); ?></td>
                    <td><?php echo number_format($item->link_count); ?></td>
                    <td>
                        <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                            <?php _e('View', 'ai-seo-interlinking'); ?>
                        </a> |
                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                            <?php _e('Edit', 'ai-seo-interlinking'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Linked Pages (Outbound) -->
    <div class="ail-section">
        <h2><?php _e('Top Pages by Outbound Links', 'ai-seo-interlinking'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Page', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Type', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Outbound Links', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Actions', 'ai-seo-interlinking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_outbound as $item): ?>
                <?php
                $post = get_post($item->source_post_id);
                if (!$post) continue;
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($post->post_title); ?></strong>
                    </td>
                    <td><?php echo esc_html($post->post_type); ?></td>
                    <td><?php echo number_format($item->link_count); ?></td>
                    <td>
                        <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                            <?php _e('View', 'ai-seo-interlinking'); ?>
                        </a> |
                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                            <?php _e('Edit', 'ai-seo-interlinking'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Orphan Pages -->
    <?php
    $all_posts = get_posts([
        'post_type' => ['post', 'page', 'product'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $linked_posts = $wpdb->get_col("SELECT DISTINCT target_post_id FROM {$links_table}");
    $orphan_posts = array_diff($all_posts, $linked_posts);
    ?>

    <?php if (!empty($orphan_posts)): ?>
    <div class="ail-section">
        <h2><?php _e('Orphan Pages (No Inbound Links)', 'ai-seo-interlinking'); ?></h2>
        <p class="description">
            <?php printf(__('Found %d pages without inbound links', 'ai-seo-interlinking'), count($orphan_posts)); ?>
        </p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Page', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Type', 'ai-seo-interlinking'); ?></th>
                    <th><?php _e('Actions', 'ai-seo-interlinking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($orphan_posts, 0, 20) as $post_id): ?>
                <?php
                $post = get_post($post_id);
                if (!$post) continue;
                ?>
                <tr>
                    <td><?php echo esc_html($post->post_title); ?></td>
                    <td><?php echo esc_html($post->post_type); ?></td>
                    <td>
                        <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                            <?php _e('View', 'ai-seo-interlinking'); ?>
                        </a> |
                        <a href="<?php echo get_edit_post_link($post->ID); ?>">
                            <?php _e('Edit', 'ai-seo-interlinking'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="ail-actions">
        <button type="button" class="button" onclick="window.location.reload();">
            <?php _e('Refresh Statistics', 'ai-seo-interlinking'); ?>
        </button>
    </div>
</div>
