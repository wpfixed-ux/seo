<?php
if (!defined('ABSPATH')) exit;

$store_manager = new WCPMP_Store_Manager();
$stores = $store_manager->get_stores();

// Get WooCommerce categories
$wc_categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => false
));
?>
<div class="wrap">
    <h1><?php esc_html_e('Import Products', 'wc-product-manager-pro'); ?></h1>

    <div class="wcpmp-import-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="#import-woocommerce" class="nav-tab nav-tab-active"><?php esc_html_e('From WooCommerce', 'wc-product-manager-pro'); ?></a>
            <a href="#import-csv" class="nav-tab"><?php esc_html_e('From CSV File', 'wc-product-manager-pro'); ?></a>
            <a href="#import-store" class="nav-tab"><?php esc_html_e('From Connected Store', 'wc-product-manager-pro'); ?></a>
        </h2>

        <!-- Import from WooCommerce -->
        <div id="import-woocommerce" class="wcpmp-import-tab">
            <div class="wcpmp-card">
                <h3><?php esc_html_e('Import from Local WooCommerce', 'wc-product-manager-pro'); ?></h3>
                <p><?php esc_html_e('Scan and import products from your current WooCommerce store.', 'wc-product-manager-pro'); ?></p>

                <form id="wcpmp-import-woo-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="woo-category"><?php esc_html_e('Category', 'wc-product-manager-pro'); ?></label>
                            </th>
                            <td>
                                <select name="category" id="woo-category">
                                    <option value=""><?php esc_html_e('All Categories', 'wc-product-manager-pro'); ?></option>
                                    <?php foreach ($wc_categories as $cat) : ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo-limit"><?php esc_html_e('Limit', 'wc-product-manager-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="limit" id="woo-limit" value="-1" min="-1">
                                <p class="description"><?php esc_html_e('-1 for all products', 'wc-product-manager-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Options', 'wc-product-manager-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="update_existing" value="1">
                                    <?php esc_html_e('Update existing products', 'wc-product-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Import Products', 'wc-product-manager-pro'); ?></button>
                        <span class="spinner"></span>
                    </p>
                </form>

                <div class="wcpmp-import-result" style="display:none;"></div>
            </div>
        </div>

        <!-- Import from CSV -->
        <div id="import-csv" class="wcpmp-import-tab" style="display:none;">
            <div class="wcpmp-card">
                <h3><?php esc_html_e('Import from CSV File', 'wc-product-manager-pro'); ?></h3>
                <p><?php esc_html_e('Upload a CSV file with your products.', 'wc-product-manager-pro'); ?></p>

                <div class="wcpmp-csv-format">
                    <h4><?php esc_html_e('CSV Format', 'wc-product-manager-pro'); ?></h4>
                    <p><?php esc_html_e('Your CSV should have the following columns:', 'wc-product-manager-pro'); ?></p>
                    <code>sku, name_uk, name_ru, description_uk, description_ru, price, sale_price, stock_quantity, category</code>
                    <p>
                        <a href="#" class="wcpmp-download-template"><?php esc_html_e('Download template', 'wc-product-manager-pro'); ?></a>
                    </p>
                </div>

                <form id="wcpmp-import-csv-form" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv-file"><?php esc_html_e('CSV File', 'wc-product-manager-pro'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="csv_file" id="csv-file" accept=".csv" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="csv-delimiter"><?php esc_html_e('Delimiter', 'wc-product-manager-pro'); ?></label>
                            </th>
                            <td>
                                <select name="delimiter" id="csv-delimiter">
                                    <option value=","><?php esc_html_e('Comma (,)', 'wc-product-manager-pro'); ?></option>
                                    <option value=";"><?php esc_html_e('Semicolon (;)', 'wc-product-manager-pro'); ?></option>
                                    <option value="\t"><?php esc_html_e('Tab', 'wc-product-manager-pro'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Options', 'wc-product-manager-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="has_header" value="1" checked>
                                    <?php esc_html_e('File has header row', 'wc-product-manager-pro'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="update_existing" value="1">
                                    <?php esc_html_e('Update existing products', 'wc-product-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Upload & Import', 'wc-product-manager-pro'); ?></button>
                        <span class="spinner"></span>
                    </p>
                </form>

                <div class="wcpmp-import-result" style="display:none;"></div>
            </div>
        </div>

        <!-- Import from Store -->
        <div id="import-store" class="wcpmp-import-tab" style="display:none;">
            <div class="wcpmp-card">
                <h3><?php esc_html_e('Import from Connected Store', 'wc-product-manager-pro'); ?></h3>
                <p><?php esc_html_e('Import products from your Prom.ua or WooCommerce stores.', 'wc-product-manager-pro'); ?></p>

                <?php if (empty($stores)) : ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e('No stores configured. Please add stores in the Stores section first.', 'wc-product-manager-pro'); ?></p>
                        <p><a href="<?php echo esc_url(admin_url('admin.php?page=wcpmp-stores')); ?>" class="button"><?php esc_html_e('Configure Stores', 'wc-product-manager-pro'); ?></a></p>
                    </div>
                <?php else : ?>
                    <form id="wcpmp-import-store-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="store-id"><?php esc_html_e('Select Store', 'wc-product-manager-pro'); ?></label>
                                </th>
                                <td>
                                    <select name="store_id" id="store-id" required>
                                        <option value=""><?php esc_html_e('— Select Store —', 'wc-product-manager-pro'); ?></option>
                                        <?php foreach ($stores as $store) : ?>
                                            <option value="<?php echo esc_attr($store->id); ?>">
                                                <?php echo esc_html($store->name); ?>
                                                (<?php echo $store->type === 'prom_ua' ? 'Prom.ua' : 'WooCommerce'; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="store-limit"><?php esc_html_e('Limit', 'wc-product-manager-pro'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="limit" id="store-limit" value="100" min="1" max="1000">
                                    <p class="description"><?php esc_html_e('Max products to import per request', 'wc-product-manager-pro'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'wc-product-manager-pro'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="update_existing" value="1">
                                        <?php esc_html_e('Update existing products', 'wc-product-manager-pro'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Import from Store', 'wc-product-manager-pro'); ?></button>
                            <span class="spinner"></span>
                        </p>
                    </form>

                    <div class="wcpmp-import-result" style="display:none;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.wcpmp-import-tab .wcpmp-card {
    max-width: 800px;
}
.wcpmp-csv-format {
    background: #f6f7f7;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid #2271b1;
}
.wcpmp-csv-format code {
    display: block;
    padding: 10px;
    background: #fff;
    margin: 10px 0;
}
.wcpmp-import-result {
    margin-top: 20px;
    padding: 15px;
    border-radius: 4px;
}
.wcpmp-import-result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.wcpmp-import-result.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.wcpmp-import-tab').hide();
        $($(this).attr('href')).show();
    });

    // Import from WooCommerce
    $('#wcpmp-import-woo-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        var $result = $form.siblings('.wcpmp-import-result');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        $.ajax({
            url: wcpmp.ajax_url,
            type: 'POST',
            data: {
                action: 'wcpmp_import_woocommerce',
                nonce: wcpmp.nonce,
                category: $form.find('[name="category"]').val(),
                limit: $form.find('[name="limit"]').val(),
                update_existing: $form.find('[name="update_existing"]').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                } else {
                    $result.removeClass('success').addClass('error').html(response.data).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(wcpmp.strings.error).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Import from CSV
    $('#wcpmp-import-csv-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        var $result = $form.siblings('.wcpmp-import-result');

        var formData = new FormData(this);
        formData.append('action', 'wcpmp_import_csv');
        formData.append('nonce', wcpmp.nonce);

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        $.ajax({
            url: wcpmp.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                } else {
                    $result.removeClass('success').addClass('error').html(response.data).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(wcpmp.strings.error).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Import from Store
    $('#wcpmp-import-store-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $spinner = $form.find('.spinner');
        var $result = $form.siblings('.wcpmp-import-result');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();

        $.ajax({
            url: wcpmp.ajax_url,
            type: 'POST',
            data: {
                action: 'wcpmp_import_store',
                nonce: wcpmp.nonce,
                store_id: $form.find('[name="store_id"]').val(),
                limit: $form.find('[name="limit"]').val(),
                update_existing: $form.find('[name="update_existing"]').is(':checked') ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html(response.data.message).show();
                } else {
                    $result.removeClass('success').addClass('error').html(response.data).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html(wcpmp.strings.error).show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>
