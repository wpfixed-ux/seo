<?php
/**
 * Dashboard Widget Class
 * Adds statistics widget to WordPress dashboard
 *
 * @package AI_SEO_Interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIL_Dashboard_Widget {

    /**
     * Initialize dashboard widget
     */
    public static function init() {
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
    }

    /**
     * Add dashboard widget
     */
    public static function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'ail_statistics_widget',
            __('AI SEO Interlinking Statistics', 'ai-seo-interlinking'),
            [__CLASS__, 'render_widget'],
            null,
            null,
            'normal',
            'high'
        );
    }

    /**
     * Render widget content
     */
    public static function render_widget() {
        // Get statistics for last 30 days
        $stats = AIL_Logger::get_statistics(30);
        $lang_stats = AIL_Multilang_Support::get_language_statistics();

        // Get total links count (all time)
        global $wpdb;
        $links_table = $wpdb->prefix . 'ai_interlinking_links';
        $total_links_all_time = $wpdb->get_var("SELECT COUNT(*) FROM {$links_table}");

        ?>
        <div class="ail-dashboard-widget">
            <style>
                .ail-dashboard-widget {
                    padding: 10px 0;
                }
                .ail-stat-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                    margin-bottom: 20px;
                }
                .ail-stat-box {
                    background: #f8f9fa;
                    border-left: 4px solid #2271b1;
                    padding: 15px;
                    border-radius: 4px;
                }
                .ail-stat-box.warning {
                    border-left-color: #d63638;
                }
                .ail-stat-box.success {
                    border-left-color: #00a32a;
                }
                .ail-stat-value {
                    font-size: 28px;
                    font-weight: bold;
                    color: #2271b1;
                    margin: 5px 0;
                }
                .ail-stat-label {
                    font-size: 12px;
                    color: #646970;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .ail-stat-period {
                    font-size: 11px;
                    color: #999;
                    margin-top: 3px;
                }
                .ail-widget-section {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e0e0e0;
                }
                .ail-widget-section h4 {
                    margin: 0 0 10px 0;
                    font-size: 13px;
                    color: #1d2327;
                }
                .ail-lang-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 5px 0;
                    font-size: 12px;
                }
                .ail-lang-name {
                    font-weight: 500;
                }
                .ail-lang-count {
                    color: #2271b1;
                    font-weight: bold;
                }
                .ail-widget-footer {
                    margin-top: 15px;
                    text-align: center;
                }
                .ail-widget-footer a {
                    text-decoration: none;
                }
            </style>

            <!-- Statistics Grid -->
            <div class="ail-stat-grid">
                <div class="ail-stat-box success">
                    <div class="ail-stat-label"><?php _e('Total Links Created', 'ai-seo-interlinking'); ?></div>
                    <div class="ail-stat-value"><?php echo number_format($total_links_all_time); ?></div>
                    <div class="ail-stat-period"><?php _e('All time', 'ai-seo-interlinking'); ?></div>
                </div>

                <div class="ail-stat-box">
                    <div class="ail-stat-label"><?php _e('New Links', 'ai-seo-interlinking'); ?></div>
                    <div class="ail-stat-value"><?php echo number_format($stats['links_created']); ?></div>
                    <div class="ail-stat-period"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></div>
                </div>

                <div class="ail-stat-box">
                    <div class="ail-stat-label"><?php _e('API Calls', 'ai-seo-interlinking'); ?></div>
                    <div class="ail-stat-value"><?php echo number_format($stats['api_calls']); ?></div>
                    <div class="ail-stat-period"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></div>
                </div>

                <div class="ail-stat-box <?php echo $stats['total_cost'] > 5 ? 'warning' : ''; ?>">
                    <div class="ail-stat-label"><?php _e('API Cost', 'ai-seo-interlinking'); ?></div>
                    <div class="ail-stat-value">$<?php echo number_format($stats['total_cost'], 2); ?></div>
                    <div class="ail-stat-period"><?php _e('Last 30 days', 'ai-seo-interlinking'); ?></div>
                </div>
            </div>

            <!-- Tokens Info -->
            <div class="ail-widget-section">
                <h4><?php _e('Token Usage', 'ai-seo-interlinking'); ?></h4>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo number_format($stats['tokens_used']); ?></strong> tokens
                    </div>
                    <div style="font-size: 11px; color: #666;">
                        <?php
                        $avg_per_call = $stats['api_calls'] > 0 ? $stats['tokens_used'] / $stats['api_calls'] : 0;
                        printf(__('~%s tokens per call', 'ai-seo-interlinking'), number_format($avg_per_call, 0));
                        ?>
                    </div>
                </div>
            </div>

            <!-- Languages Stats -->
            <?php if (!empty($lang_stats)): ?>
            <div class="ail-widget-section">
                <h4><?php _e('Links by Language', 'ai-seo-interlinking'); ?></h4>
                <?php foreach (array_slice($lang_stats, 0, 5) as $lang => $data): ?>
                <div class="ail-lang-row">
                    <span class="ail-lang-name"><?php echo esc_html($data['language_name']); ?></span>
                    <span class="ail-lang-count"><?php echo number_format($data['links_count']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Errors -->
            <?php if ($stats['errors'] > 0): ?>
            <div class="ail-widget-section">
                <div style="color: #d63638; font-size: 12px;">
                    <strong><?php _e('Errors:', 'ai-seo-interlinking'); ?></strong>
                    <?php echo number_format($stats['errors']); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-interlinking-logs&status=error'); ?>" style="margin-left: 5px;">
                        <?php _e('View logs', 'ai-seo-interlinking'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="ail-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=ai-seo-interlinking-reports'); ?>" class="button button-primary">
                    <?php _e('View Full Reports', 'ai-seo-interlinking'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-seo-interlinking'); ?>" class="button" style="margin-left: 5px;">
                    <?php _e('Settings', 'ai-seo-interlinking'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Get summary text
     *
     * @return string Summary text
     */
    private static function get_summary_text() {
        $stats = AIL_Logger::get_statistics(7);

        if ($stats['links_created'] === 0) {
            return __('No links created in the last 7 days.', 'ai-seo-interlinking');
        }

        $avg_per_day = $stats['links_created'] / 7;

        return sprintf(
            __('Created %d links (avg. %d per day) in the last 7 days.', 'ai-seo-interlinking'),
            $stats['links_created'],
            round($avg_per_day)
        );
    }
}

// Initialize
AIL_Dashboard_Widget::init();
