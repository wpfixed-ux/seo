<?php
/**
 * Dashboard view
 *
 * @package AIMarketingAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('AI Marketing Assistant - Dashboard', 'ai-marketing-assistant'); ?></h1>

    <div class="aima-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">

        <!-- Total Customers -->
        <div class="aima-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px; color: #666; font-size: 14px; text-transform: uppercase;"><?php _e('Total Customers', 'ai-marketing-assistant'); ?></h3>
            <p style="margin: 0; font-size: 36px; font-weight: bold; color: #667eea;"><?php echo number_format($analytics['total_customers']); ?></p>
        </div>

        <!-- New Customers -->
        <div class="aima-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px; color: #666; font-size: 14px; text-transform: uppercase;"><?php _e('New Customers (30d)', 'ai-marketing-assistant'); ?></h3>
            <p style="margin: 0; font-size: 36px; font-weight: bold; color: #52c41a;"><?php echo number_format($analytics['new_customers']); ?></p>
        </div>

        <!-- Total Orders -->
        <div class="aima-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px; color: #666; font-size: 14px; text-transform: uppercase;"><?php _e('Total Orders (30d)', 'ai-marketing-assistant'); ?></h3>
            <p style="margin: 0; font-size: 36px; font-weight: bold; color: #faad14;"><?php echo number_format($analytics['total_orders']); ?></p>
        </div>

        <!-- Total Revenue -->
        <div class="aima-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px; color: #666; font-size: 14px; text-transform: uppercase;"><?php _e('Revenue (30d)', 'ai-marketing-assistant'); ?></h3>
            <p style="margin: 0; font-size: 36px; font-weight: bold; color: #f5222d;"><?php echo number_format($analytics['total_revenue'], 0, ',', ' ') . ' ₽'; ?></p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">

        <!-- Top Products -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2><?php _e('Top Products (30 days)', 'ai-marketing-assistant'); ?></h2>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'ai-marketing-assistant'); ?></th>
                        <th><?php _e('Sales', 'ai-marketing-assistant'); ?></th>
                        <th><?php _e('Revenue', 'ai-marketing-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($top_products)): ?>
                        <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo esc_html($product->product_name); ?></td>
                                <td><?php echo number_format($product->total_quantity); ?></td>
                                <td><?php echo number_format($product->total_revenue, 0, ',', ' ') . ' ₽'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><?php _e('No data available', 'ai-marketing-assistant'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Segments -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h2><?php _e('Customer Segments', 'ai-marketing-assistant'); ?></h2>
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Segment', 'ai-marketing-assistant'); ?></th>
                        <th><?php _e('Customers', 'ai-marketing-assistant'); ?></th>
                        <th><?php _e('Actions', 'ai-marketing-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($segments)): ?>
                        <?php foreach (array_slice($segments, 0, 5) as $segment): ?>
                            <tr>
                                <td><?php echo esc_html($segment->name); ?></td>
                                <td><?php echo number_format($segment->customer_count); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=aima-segments&action=edit&segment_id=' . $segment->id); ?>" class="button button-small">
                                        <?php _e('Edit', 'ai-marketing-assistant'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><?php _e('No segments created', 'ai-marketing-assistant'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="<?php echo admin_url('admin.php?page=aima-segments'); ?>" class="button button-primary" style="margin-top: 15px;">
                <?php _e('Manage Segments', 'ai-marketing-assistant'); ?>
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h2><?php _e('Quick Actions', 'ai-marketing-assistant'); ?></h2>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=aima-offers&action=create'); ?>" class="button button-primary button-large">
                🎁 <?php _e('Create New Offer', 'ai-marketing-assistant'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=aima-segments&action=create'); ?>" class="button button-large">
                👥 <?php _e('Create Segment', 'ai-marketing-assistant'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=aima-import'); ?>" class="button button-large">
                📤 <?php _e('Import Customers', 'ai-marketing-assistant'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=aima-analytics'); ?>" class="button button-large">
                📊 <?php _e('View Analytics', 'ai-marketing-assistant'); ?>
            </a>
        </div>
    </div>
</div>
