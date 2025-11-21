<?php
/**
 * Страница настроек
 *
 * @package AI_Image_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = AI_Image_Generator::get_settings();

// Сохранение настроек
if (isset($_POST['aimg_save_settings']) && check_admin_referer('aimg_settings_nonce')) {
    $settings['gemini_api_key'] = sanitize_text_field($_POST['gemini_api_key']);
    $settings['gemini_model'] = sanitize_text_field($_POST['gemini_model']);
    $settings['image_api_provider'] = sanitize_text_field($_POST['image_api_provider']);
    $settings['image_api_key'] = sanitize_text_field($_POST['image_api_key']);
    $settings['bg_removal_provider'] = sanitize_text_field($_POST['bg_removal_provider']);
    $settings['bg_removal_api_key'] = sanitize_text_field($_POST['bg_removal_api_key']);
    $settings['watermark_enabled'] = isset($_POST['watermark_enabled']);
    $settings['watermark_text'] = sanitize_text_field($_POST['watermark_text']);
    $settings['watermark_position'] = sanitize_text_field($_POST['watermark_position']);
    $settings['watermark_opacity'] = intval($_POST['watermark_opacity']);

    update_option('aimg_settings', $settings);

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Настройки сохранены!', 'ai-image-generator') . '</p></div>';
}
?>

<div class="wrap aimg-settings">
    <h1><?php _e('AI Image Generator - Настройки', 'ai-image-generator'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('aimg_settings_nonce'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-api" class="nav-tab nav-tab-active"><?php _e('API Ключи', 'ai-image-generator'); ?></a>
            <a href="#tab-watermark" class="nav-tab"><?php _e('Водяной знак', 'ai-image-generator'); ?></a>
            <a href="#tab-about" class="nav-tab"><?php _e('О плагине', 'ai-image-generator'); ?></a>
        </h2>

        <div id="tab-api" class="aimg-tab-content">
            <table class="form-table">
                <tr>
                    <th colspan="2"><h3><?php _e('Gemini API (анализ контента)', 'ai-image-generator'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><label for="gemini_api_key"><?php _e('Gemini API Key', 'ai-image-generator'); ?></label></th>
                    <td>
                        <input type="text" name="gemini_api_key" id="gemini_api_key" value="<?php echo esc_attr($settings['gemini_api_key']); ?>" class="regular-text" />
                        <p class="description"><?php _e('Получите ключ на', 'ai-image-generator'); ?> <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gemini_model"><?php _e('Модель Gemini', 'ai-image-generator'); ?></label></th>
                    <td>
                        <select name="gemini_model" id="gemini_model">
                            <?php foreach (AIMG_Gemini_API::get_available_models() as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['gemini_model'], $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php _e('Image Generation API', 'ai-image-generator'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><label for="image_api_provider"><?php _e('Провайдер', 'ai-image-generator'); ?></label></th>
                    <td>
                        <select name="image_api_provider" id="image_api_provider">
                            <?php foreach (AIMG_Image_Generator::get_providers() as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['image_api_provider'], $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="image_api_key"><?php _e('API Key', 'ai-image-generator'); ?></label></th>
                    <td>
                        <input type="text" name="image_api_key" id="image_api_key" value="<?php echo esc_attr($settings['image_api_key']); ?>" class="regular-text" />
                        <p class="description">
                            Stability AI: <a href="https://platform.stability.ai/account/keys" target="_blank">получить ключ</a><br>
                            OpenAI: <a href="https://platform.openai.com/api-keys" target="_blank">получить ключ</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2"><h3><?php _e('Background Removal API (для товаров)', 'ai-image-generator'); ?></h3></th>
                </tr>
                <tr>
                    <th scope="row"><label for="bg_removal_provider"><?php _e('Провайдер', 'ai-image-generator'); ?></label></th>
                    <td>
                        <select name="bg_removal_provider" id="bg_removal_provider">
                            <option value="removebg" <?php selected($settings['bg_removal_provider'], 'removebg'); ?>>Remove.bg</option>
                            <option value="clipdrop" <?php selected($settings['bg_removal_provider'], 'clipdrop'); ?>>ClipDrop</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bg_removal_api_key"><?php _e('API Key', 'ai-image-generator'); ?></label></th>
                    <td>
                        <input type="text" name="bg_removal_api_key" id="bg_removal_api_key" value="<?php echo esc_attr($settings['bg_removal_api_key']); ?>" class="regular-text" />
                        <p class="description">
                            Remove.bg: <a href="https://www.remove.bg/api" target="_blank">получить ключ</a><br>
                            ClipDrop: <a href="https://clipdrop.co/apis" target="_blank">получить ключ</a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-watermark" class="aimg-tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="watermark_enabled"><?php _e('Включить водяной знак', 'ai-image-generator'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="watermark_enabled" id="watermark_enabled" value="1" <?php checked($settings['watermark_enabled'], true); ?> />
                            <?php _e('Применять водяной знак ко всем сгенерированным изображениям', 'ai-image-generator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="watermark_text"><?php _e('Текст водяного знака', 'ai-image-generator'); ?></label></th>
                    <td>
                        <input type="text" name="watermark_text" id="watermark_text" value="<?php echo esc_attr($settings['watermark_text']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="watermark_position"><?php _e('Позиция', 'ai-image-generator'); ?></label></th>
                    <td>
                        <select name="watermark_position" id="watermark_position">
                            <option value="top-left" <?php selected($settings['watermark_position'], 'top-left'); ?>><?php _e('Сверху слева', 'ai-image-generator'); ?></option>
                            <option value="top-right" <?php selected($settings['watermark_position'], 'top-right'); ?>><?php _e('Сверху справа', 'ai-image-generator'); ?></option>
                            <option value="bottom-left" <?php selected($settings['watermark_position'], 'bottom-left'); ?>><?php _e('Снизу слева', 'ai-image-generator'); ?></option>
                            <option value="bottom-right" <?php selected($settings['watermark_position'], 'bottom-right'); ?>><?php _e('Снизу справа', 'ai-image-generator'); ?></option>
                            <option value="center" <?php selected($settings['watermark_position'], 'center'); ?>><?php _e('По центру', 'ai-image-generator'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="watermark_opacity"><?php _e('Прозрачность (%)', 'ai-image-generator'); ?></label></th>
                    <td>
                        <input type="number" name="watermark_opacity" id="watermark_opacity" value="<?php echo esc_attr($settings['watermark_opacity']); ?>" min="0" max="100" />
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-about" class="aimg-tab-content" style="display: none;">
            <h3><?php _e('О плагине', 'ai-image-generator'); ?></h3>
            <p><?php _e('AI Image Generator - плагин для автоматической генерации изображений для статей и замены фона товаров.', 'ai-image-generator'); ?></p>
            <p><strong><?php _e('Версия:', 'ai-image-generator'); ?></strong> <?php echo AIMG_VERSION; ?></p>
            <p><strong><?php _e('Возможности:', 'ai-image-generator'); ?></strong></p>
            <ul>
                <li><?php _e('Генерация изображений для статей на основе их содержания', 'ai-image-generator'); ?></li>
                <li><?php _e('Удаление фона с изображений товаров', 'ai-image-generator'); ?></li>
                <li><?php _e('Замена фона: белый, градиентный, 3D, AI-генерированный', 'ai-image-generator'); ?></li>
                <li><?php _e('Настраиваемый водяной знак', 'ai-image-generator'); ?></li>
            </ul>
        </div>

        <?php submit_button(__('Сохранить настройки', 'ai-image-generator'), 'primary', 'aimg_save_settings'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Переключение табов
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.aimg-tab-content').hide();
        $(target).show();
    });
});
</script>
