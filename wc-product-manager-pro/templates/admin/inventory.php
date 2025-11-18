<?php
if (!defined('ABSPATH')) exit;
$summary = $tracker->get_inventory_summary();
$low_stock = $tracker->get_low_stock_products();
$best_sellers = $tracker->get_best_sellers();
?>
<div class="wrap">
    <h1><?php esc_html_e('Inventory Management', 'wc-product-manager-pro'); ?></h1>

    <div class="wcpmp-inventory-grid">
        <div class="wcpmp-card">
            <h3><?php esc_html_e('Inventory Summary', 'wc-product-manager-pro'); ?></h3>
            <ul>
                <li><?php esc_html_e('Total Products:', 'wc-product-manager-pro'); ?> <strong><?php echo esc_html($summary['total_products']); ?></strong></li>
                <li><?php esc_html_e('In Stock:', 'wc-product-manager-pro'); ?> <strong><?php echo esc_html($summary['in_stock']); ?></strong></li>
                <li><?php esc_html_e('Low Stock:', 'wc-product-manager-pro'); ?> <strong><?php echo esc_html($summary['low_stock']); ?></strong></li>
                <li><?php esc_html_e('Out of Stock:', 'wc-product-manager-pro'); ?> <strong><?php echo esc_html($summary['out_of_stock']); ?></strong></li>
                <li><?php esc_html_e('Total Value:', 'wc-product-manager-pro'); ?> <strong><?php echo number_format($summary['total_value'], 2); ?> грн</strong></li>
            </ul>
        </div>

        <div class="wcpmp-card">
            <h3><?php esc_html_e('Best Sellers (30 days)', 'wc-product-manager-pro'); ?></h3>
            <?php if (!empty($best_sellers)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Product', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Sold', 'wc-product-manager-pro'); ?></th>
                            <th><?php esc_html_e('Revenue', 'wc-product-manager-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($best_sellers as $product) : ?>
                            <tr>
                                <td><?php echo esc_html($product->name_uk); ?></td>
                                <td><?php echo esc_html($product->total_sold); ?></td>
                                <td><?php echo number_format($product->total_revenue, 2); ?> грн</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('No sales data.', 'wc-product-manager-pro'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <h2><?php esc_html_e('Low Stock Products', 'wc-product-manager-pro'); ?></h2>
    <?php if (!empty($low_stock)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'wc-product-manager-pro'); ?></th>
                    <th><?php esc_html_e('SKU', 'wc-product-manager-pro'); ?></th>
                    <th><?php esc_html_e('Stock', 'wc-product-manager-pro'); ?></th>
                    <th><?php esc_html_e('Price', 'wc-product-manager-pro'); ?></th>
                    <th><?php esc_html_e('Category', 'wc-product-manager-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock as $product) : ?>
                    <tr>
                        <td><?php echo esc_html($product->name_uk); ?></td>
                        <td><?php echo esc_html($product->sku); ?></td>
                        <td><span class="wcpmp-low-stock"><?php echo esc_html($product->stock_quantity); ?></span></td>
                        <td><?php echo number_format($product->price, 2); ?> грн</td>
                        <td><?php echo esc_html($product->category_name ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e('No low stock products.', 'wc-product-manager-pro'); ?></p>
    <?php endif; ?>
</div>
