<?php
if (!defined('ABSPATH')) exit;
$settings = WCPMP_Settings::get_all();
?>
<div class="wrap">
    <h1><?php esc_html_e('Product Manager Settings', 'wc-product-manager-pro'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('wcpmp_settings'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#api-keys" class="nav-tab nav-tab-active"><?php esc_html_e('API Keys', 'wc-product-manager-pro'); ?></a>
            <a href="#general" class="nav-tab"><?php esc_html_e('General', 'wc-product-manager-pro'); ?></a>
            <a href="#email" class="nav-tab"><?php esc_html_e('Email', 'wc-product-manager-pro'); ?></a>
            <a href="#ai" class="nav-tab"><?php esc_html_e('AI Settings', 'wc-product-manager-pro'); ?></a>
        </h2>

        <!-- API Keys -->
        <div id="api-keys" class="wcpmp-settings-tab">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcpmp_openai_api_key"><?php esc_html_e('OpenAI API Key', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="wcpmp_openai_api_key" name="wcpmp_openai_api_key" value="<?php echo esc_attr($settings['openai_api_key']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Used for SEO descriptions and email content generation.', 'wc-product-manager-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_openai_model"><?php esc_html_e('OpenAI Model', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <select id="wcpmp_openai_model" name="wcpmp_openai_model">
                            <?php foreach (WCPMP_Settings::get_openai_models() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['openai_model'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_gemini_api_key"><?php esc_html_e('Gemini API Key', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="wcpmp_gemini_api_key" name="wcpmp_gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Used for image generation and background changes.', 'wc-product-manager-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_telegram_bot_token"><?php esc_html_e('Telegram Bot Token', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="wcpmp_telegram_bot_token" name="wcpmp_telegram_bot_token" value="<?php echo esc_attr($settings['telegram_bot_token']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Get from @BotFather on Telegram.', 'wc-product-manager-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- General -->
        <div id="general" class="wcpmp-settings-tab" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcpmp_default_language"><?php esc_html_e('Default Language', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <select id="wcpmp_default_language" name="wcpmp_default_language">
                            <?php foreach (WCPMP_Settings::get_languages() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['default_language'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_sync_interval"><?php esc_html_e('Sync Interval', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <select id="wcpmp_sync_interval" name="wcpmp_sync_interval">
                            <?php foreach (WCPMP_Settings::get_sync_intervals() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['sync_interval'], $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Global Sync', 'wc-product-manager-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wcpmp_global_sync_enabled" value="1" <?php checked($settings['global_sync_enabled'], 1); ?>>
                            <?php esc_html_e('Enable automatic synchronization', 'wc-product-manager-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email -->
        <div id="email" class="wcpmp-settings-tab" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcpmp_email_from_name"><?php esc_html_e('From Name', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wcpmp_email_from_name" name="wcpmp_email_from_name" value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_email_from_address"><?php esc_html_e('From Email', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="wcpmp_email_from_address" name="wcpmp_email_from_address" value="<?php echo esc_attr($settings['email_from_address']); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- AI Settings -->
        <div id="ai" class="wcpmp-settings-tab" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wcpmp_seo_description_length"><?php esc_html_e('SEO Description Length', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wcpmp_seo_description_length" name="wcpmp_seo_description_length" value="<?php echo esc_attr($settings['seo_description_length']); ?>" min="100" max="300">
                        <p class="description"><?php esc_html_e('Maximum characters for meta descriptions.', 'wc-product-manager-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcpmp_ai_temperature"><?php esc_html_e('AI Temperature', 'wc-product-manager-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wcpmp_ai_temperature" name="wcpmp_ai_temperature" value="<?php echo esc_attr($settings['ai_temperature']); ?>" min="0" max="1" step="0.1">
                        <p class="description"><?php esc_html_e('0 = focused, 1 = creative', 'wc-product-manager-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.wcpmp-settings-tab').hide();
        $($(this).attr('href')).show();
    });
});
</script>
