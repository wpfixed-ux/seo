<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap wcpmp-dashboard">
    <h1><?php esc_html_e('Product Manager Dashboard', 'wc-product-manager-pro'); ?></h1>

    <div class="wcpmp-dashboard-widgets">
        <!-- Inventory Summary -->
        <div class="wcpmp-widget">
            <h2><?php esc_html_e('Inventory Summary', 'wc-product-manager-pro'); ?></h2>
            <div class="wcpmp-stats-grid">
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value"><?php echo esc_html($data['warehouse']['inventory']['total_products']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Total Products', 'wc-product-manager-pro'); ?></span>
                </div>
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value wcpmp-success"><?php echo esc_html($data['warehouse']['inventory']['in_stock']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('In Stock', 'wc-product-manager-pro'); ?></span>
                </div>
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value wcpmp-warning"><?php echo esc_html($data['warehouse']['inventory']['low_stock']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Low Stock', 'wc-product-manager-pro'); ?></span>
                </div>
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value wcpmp-danger"><?php echo esc_html($data['warehouse']['inventory']['out_of_stock']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Out of Stock', 'wc-product-manager-pro'); ?></span>
                </div>
            </div>
            <p class="wcpmp-total-value">
                <?php esc_html_e('Total Stock Value:', 'wc-product-manager-pro'); ?>
                <strong><?php echo number_format($data['warehouse']['inventory']['total_value'], 2); ?> грн</strong>
            </p>
        </div>

        <!-- CRM Summary -->
        <div class="wcpmp-widget">
            <h2><?php esc_html_e('CRM Summary', 'wc-product-manager-pro'); ?></h2>
            <div class="wcpmp-stats-grid">
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value"><?php echo esc_html($data['crm']['total_customers']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Total Customers', 'wc-product-manager-pro'); ?></span>
                </div>
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value"><?php echo esc_html($data['crm']['subscribed']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Subscribed', 'wc-product-manager-pro'); ?></span>
                </div>
                <div class="wcpmp-stat">
                    <span class="wcpmp-stat-value"><?php echo count($data['crm']['segments']); ?></span>
                    <span class="wcpmp-stat-label"><?php esc_html_e('Segments', 'wc-product-manager-pro'); ?></span>
                </div>
            </div>
        </div>

        <!-- Sync Status -->
        <div class="wcpmp-widget">
            <h2><?php esc_html_e('Sync Status', 'wc-product-manager-pro'); ?></h2>
            <p>
                <?php esc_html_e('Last Sync:', 'wc-product-manager-pro'); ?>
                <strong><?php echo esc_html($data['sync']['last_sync']); ?></strong>
            </p>
            <p>
                <?php esc_html_e('Pending:', 'wc-product-manager-pro'); ?>
                <strong><?php echo esc_html($data['sync']['pending_sync']); ?> <?php esc_html_e('products', 'wc-product-manager-pro'); ?></strong>
            </p>
            <button class="button button-primary wcpmp-sync-all"><?php esc_html_e('Sync All Stores', 'wc-product-manager-pro'); ?></button>
        </div>

        <!-- Recent Orders -->
        <div class="wcpmp-widget wcpmp-widget-wide">
            <h2><?php esc_html_e('Recent Orders', 'wc-product-manager-pro'); ?></h2>
            <?php if (!empty($data['warehouse']['recent_orders'])) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Store', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Customer', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Total', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Status', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Date', 'wc-product-manager-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['warehouse']['recent_orders'] as $order) : ?>
                            <tr>
                                <td>#<?php echo esc_html($order->external_order_id); ?></td>
                                <td><?php echo esc_html($order->store_name); ?></td>
                                <td><?php echo esc_html($order->customer_name); ?></td>
                                <td><?php echo number_format($order->total, 2); ?> грн</td>
                                <td><span class="wcpmp-status wcpmp-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html($order->status); ?></span></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order->order_date))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No recent orders.', 'wc-product-manager-pro'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Low Stock Products -->
        <div class="wcpmp-widget wcpmp-widget-wide">
            <h2><?php esc_html_e('Low Stock Products', 'wc-product-manager-pro'); ?></h2>
            <?php if (!empty($data['warehouse']['low_stock'])) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('SKU', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Stock', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Category', 'wc-product-manager-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($data['warehouse']['low_stock'], 0, 10) as $product) : ?>
                            <tr>
                                <td><?php echo esc_html($product->name_uk); ?></td>
                                <td><?php echo esc_html($product->sku); ?></td>
                                <td><span class="wcpmp-low-stock"><?php echo esc_html($product->stock_quantity); ?></span></td>
                                <td><?php echo esc_html($product->category_name ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No low stock products.', 'wc-product-manager-pro'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
