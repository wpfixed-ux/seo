<?php
if (!defined('ABSPATH')) exit;

$products_table = new WCPMP_Products_Table();
$products_table->prepare_items();
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Products', 'wc-product-manager-pro'); ?></h1>
    <a href="?page=wcpmp-products&action=add" class="page-title-action"><?php esc_html_e('Add New', 'wc-product-manager-pro'); ?></a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="wcpmp-products">
        <?php
        $products_table->search_box(__('Search Products', 'wc-product-manager-pro'), 'search');
        $products_table->display();
        ?>
    </form>
</div>
