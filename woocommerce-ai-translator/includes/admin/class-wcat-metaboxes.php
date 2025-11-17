<?php
/**
 * Metaboxes for manual translation
 *
 * @package WC_AI_Translator
 */

class WCAT_Metaboxes {

    /**
     * Plugin name
     */
    private $plugin_name;

    /**
     * Plugin version
     */
    private $version;

    /**
     * Constructor
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Add translation metaboxes
     */
    public function add_translation_metaboxes() {
        $settings = get_option('wcat_settings');
        $content_types = isset($settings['content_types']) ? $settings['content_types'] : array('post', 'page', 'product');

        foreach ($content_types as $post_type) {
            add_meta_box(
                'wcat_translation',
                __('AI Translation', 'wc-ai-translator'),
                array($this, 'render_translation_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render translation metabox
     */
    public function render_translation_metabox($post) {
        wp_nonce_field('wcat_translation_metabox', 'wcat_translation_nonce');

        $polylang = new WCAT_Polylang_Translator();

        if (!$polylang->is_polylang_active()) {
            echo '<p>' . __('Polylang is not active.', 'wc-ai-translator') . '</p>';
            return;
        }

        $languages = $polylang->get_languages();
        $current_lang = $polylang->get_post_language($post->ID);
        $translations = $polylang->get_post_translations($post->ID);
        $missing_translations = $polylang->get_missing_translations($post->ID);

        ?>
        <div class="wcat-metabox">
            <p>
                <strong><?php _e('Current Language:', 'wc-ai-translator'); ?></strong><br>
                <?php
                foreach ($languages as $lang) {
                    if ($lang['code'] === $current_lang) {
                        echo esc_html($lang['name']);
                        break;
                    }
                }
                ?>
            </p>

            <?php if (!empty($translations)): ?>
                <p>
                    <strong><?php _e('Existing Translations:', 'wc-ai-translator'); ?></strong>
                </p>
                <ul>
                    <?php foreach ($translations as $lang_code => $translation_id): ?>
                        <?php if ($translation_id && $lang_code !== $current_lang): ?>
                            <li>
                                <?php
                                foreach ($languages as $lang) {
                                    if ($lang['code'] === $lang_code) {
                                        echo esc_html($lang['name']);
                                        break;
                                    }
                                }
                                ?>
                                - <a href="<?php echo get_edit_post_link($translation_id); ?>" target="_blank"><?php _e('Edit', 'wc-ai-translator'); ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($missing_translations)): ?>
                <p>
                    <strong><?php _e('Translate to:', 'wc-ai-translator'); ?></strong>
                </p>
                <?php foreach ($missing_translations as $lang_code): ?>
                    <?php
                    $lang_name = '';
                    foreach ($languages as $lang) {
                        if ($lang['code'] === $lang_code) {
                            $lang_name = $lang['name'];
                            break;
                        }
                    }
                    ?>
                    <p>
                        <button type="button" class="button wcat-translate-single" data-post-id="<?php echo esc_attr($post->ID); ?>" data-target-lang="<?php echo esc_attr($lang_code); ?>">
                            <?php echo sprintf(__('Translate to %s', 'wc-ai-translator'), esc_html($lang_name)); ?>
                        </button>
                    </p>
                <?php endforeach; ?>

                <p>
                    <button type="button" class="button button-primary wcat-translate-all" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php _e('Translate to All Languages', 'wc-ai-translator'); ?>
                    </button>
                </p>
            <?php else: ?>
                <p><?php _e('All translations are up to date.', 'wc-ai-translator'); ?></p>
            <?php endif; ?>

            <div class="wcat-metabox-status" style="margin-top: 15px; display: none;">
                <p class="wcat-status-message"></p>
            </div>
        </div>
        <?php
    }

    /**
     * Save translation metabox
     */
    public function save_translation_metabox($post_id) {
        // Verify nonce
        if (!isset($_POST['wcat_translation_nonce']) || !wp_verify_nonce($_POST['wcat_translation_nonce'], 'wcat_translation_metabox')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Additional metabox save logic can be added here if needed
    }
}
