<?php
/**
 * Класс для метабоксов
 *
 * @package AI_Image_Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIMG_Metaboxes {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_metaboxes'));
    }

    /**
     * Добавление метабоксов
     */
    public function add_metaboxes() {
        // Метабокс для постов
        add_meta_box(
            'aimg_post_generator',
            __('AI Image Generator', 'ai-image-generator'),
            array($this, 'render_post_metabox'),
            'post',
            'side',
            'default'
        );

        // Метабокс для страниц
        add_meta_box(
            'aimg_page_generator',
            __('AI Image Generator', 'ai-image-generator'),
            array($this, 'render_post_metabox'),
            'page',
            'side',
            'default'
        );

        // Метабокс для товаров WooCommerce
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'aimg_product_generator',
                __('AI Background Replace', 'ai-image-generator'),
                array($this, 'render_product_metabox'),
                'product',
                'side',
                'default'
            );
        }
    }

    /**
     * Рендер метабокса для постов
     */
    public function render_post_metabox($post) {
        $generated = get_post_meta($post->ID, '_aimg_generated', true);
        $prompt = get_post_meta($post->ID, '_aimg_prompt', true);
        $generated_date = get_post_meta($post->ID, '_aimg_generated_date', true);
        ?>
        <div class="aimg-metabox">
            <p><?php _e('Генерация изображения на основе содержания статьи через AI', 'ai-image-generator'); ?></p>

            <?php if ($generated && has_post_thumbnail($post->ID)): ?>
                <div class="aimg-info">
                    <p class="aimg-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Изображение сгенерировано', 'ai-image-generator'); ?>
                    </p>
                    <?php if ($generated_date): ?>
                        <p class="aimg-meta"><?php echo sprintf(__('Дата: %s', 'ai-image-generator'), date_i18n(get_option('date_format'), strtotime($generated_date))); ?></p>
                    <?php endif; ?>
                    <?php if ($prompt): ?>
                        <details>
                            <summary><?php _e('Показать промпт', 'ai-image-generator'); ?></summary>
                            <p class="aimg-prompt"><?php echo esc_html($prompt); ?></p>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <button type="button" class="button button-primary button-large aimg-generate-post-image" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <span class="dashicons dashicons-format-image"></span>
                <?php _e($generated ? 'Перегенерировать изображение' : 'Сгенерировать изображение', 'ai-image-generator'); ?>
            </button>

            <div class="aimg-loader" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e('Генерация изображения... Это может занять до минуты.', 'ai-image-generator'); ?></p>
            </div>

            <div class="aimg-result" style="display: none;"></div>

            <div class="aimg-help">
                <p class="description">
                    <?php _e('Плагин проанализирует содержание статьи и сгенерирует подходящее изображение через AI.', 'ai-image-generator'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Рендер метабокса для товаров
     */
    public function render_product_metabox($post) {
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        ?>
        <div class="aimg-metabox aimg-product-metabox">
            <?php if ($thumbnail_id): ?>
                <p><?php _e('Замена фона изображения товара:', 'ai-image-generator'); ?></p>

                <div class="aimg-current-image">
                    <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
                </div>

                <div class="aimg-background-selector">
                    <label><?php _e('Выберите тип фона:', 'ai-image-generator'); ?></label>
                    <select class="aimg-background-type">
                        <?php
                        $backgrounds = AIMG_Background_Remover::get_background_types();
                        foreach ($backgrounds as $key => $label) {
                            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <button type="button" class="button button-primary button-large aimg-replace-background" data-attachment-id="<?php echo esc_attr($thumbnail_id); ?>" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php _e('Заменить фон', 'ai-image-generator'); ?>
                </button>

                <div class="aimg-loader" style="display: none;">
                    <div class="spinner is-active"></div>
                    <p><?php _e('Обработка изображения... Это может занять до минуты.', 'ai-image-generator'); ?></p>
                </div>

                <div class="aimg-result" style="display: none;"></div>

                <div class="aimg-help">
                    <p class="description">
                        <?php _e('Плагин удалит фон с изображения и применит новый фон по вашему выбору.', 'ai-image-generator'); ?>
                    </p>
                </div>
            <?php else: ?>
                <p class="description">
                    <?php _e('Сначала загрузите изображение товара в качестве Featured Image.', 'ai-image-generator'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
