<?php
/**
 * Batch Translation View
 *
 * @package WC_AI_Translator
 */

if (!defined('ABSPATH')) {
    exit;
}

$scanner = new WCAT_Content_Scanner();
$polylang = new WCAT_Polylang_Translator();
$languages = $polylang->get_languages();
?>

<div class="wrap">
    <h1><?php _e('Batch Translation', 'wc-ai-translator'); ?></h1>

    <div class="wcat-batch-translation">
        <!-- Step 1: Scan Content -->
        <div class="wcat-section">
            <h2><?php _e('Step 1: Scan Content', 'wc-ai-translator'); ?></h2>
            <p><?php _e('Scan your website for content that needs translation.', 'wc-ai-translator'); ?></p>

            <form id="wcat-scan-form">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Content Types', 'wc-ai-translator'); ?></th>
                        <td>
                            <label><input type="checkbox" name="content_types[]" value="post" checked> <?php _e('Posts', 'wc-ai-translator'); ?></label><br>
                            <label><input type="checkbox" name="content_types[]" value="page" checked> <?php _e('Pages', 'wc-ai-translator'); ?></label><br>
                            <label><input type="checkbox" name="content_types[]" value="product" checked> <?php _e('Products', 'wc-ai-translator'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Taxonomies', 'wc-ai-translator'); ?></th>
                        <td>
                            <label><input type="checkbox" name="taxonomies[]" value="category" checked> <?php _e('Categories', 'wc-ai-translator'); ?></label><br>
                            <label><input type="checkbox" name="taxonomies[]" value="post_tag" checked> <?php _e('Tags', 'wc-ai-translator'); ?></label><br>
                            <label><input type="checkbox" name="taxonomies[]" value="product_cat" checked> <?php _e('Product Categories', 'wc-ai-translator'); ?></label><br>
                            <label><input type="checkbox" name="taxonomies[]" value="product_tag" checked> <?php _e('Product Tags', 'wc-ai-translator'); ?></label>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary" id="wcat-scan-button">
                        <?php _e('Scan Website', 'wc-ai-translator'); ?>
                    </button>
                </p>
            </form>

            <div id="wcat-scan-results" style="display: none;">
                <h3><?php _e('Scan Results', 'wc-ai-translator'); ?></h3>
                <div id="wcat-scan-results-content"></div>
            </div>
        </div>

        <!-- Step 2: Select Items -->
        <div class="wcat-section" id="wcat-selection-section" style="display: none;">
            <h2><?php _e('Step 2: Select Items to Translate', 'wc-ai-translator'); ?></h2>
            <p><?php _e('Choose which items you want to translate.', 'wc-ai-translator'); ?></p>

            <div id="wcat-items-list">
                <!-- Items will be populated here via JavaScript -->
            </div>

            <p>
                <button type="button" class="button" id="wcat-select-all"><?php _e('Select All', 'wc-ai-translator'); ?></button>
                <button type="button" class="button" id="wcat-deselect-all"><?php _e('Deselect All', 'wc-ai-translator'); ?></button>
            </p>
        </div>

        <!-- Step 3: Choose Languages -->
        <div class="wcat-section" id="wcat-languages-section" style="display: none;">
            <h2><?php _e('Step 3: Choose Target Languages', 'wc-ai-translator'); ?></h2>
            <p><?php _e('Select the languages you want to translate to.', 'wc-ai-translator'); ?></p>

            <div class="wcat-languages-selection">
                <?php foreach ($languages as $lang): ?>
                    <?php if (!$lang['is_default']): ?>
                        <label>
                            <input type="checkbox" name="target_languages[]" value="<?php echo esc_attr($lang['code']); ?>">
                            <img src="<?php echo esc_url($lang['flag']); ?>" alt="<?php echo esc_attr($lang['name']); ?>" />
                            <?php echo esc_html($lang['name']); ?>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 4: Review and Translate -->
        <div class="wcat-section" id="wcat-review-section" style="display: none;">
            <h2><?php _e('Step 4: Review and Start Translation', 'wc-ai-translator'); ?></h2>
            <div id="wcat-cost-estimate"></div>

            <p>
                <button type="button" class="button button-primary button-large" id="wcat-start-translation">
                    <?php _e('Start Translation', 'wc-ai-translator'); ?>
                </button>
            </p>
        </div>

        <!-- Progress -->
        <div class="wcat-section" id="wcat-progress-section" style="display: none;">
            <h2><?php _e('Translation Progress', 'wc-ai-translator'); ?></h2>
            <div class="wcat-progress-bar-large">
                <div id="wcat-progress-fill" class="wcat-progress-fill" style="width: 0%;"></div>
            </div>
            <p id="wcat-progress-text">0%</p>
            <div id="wcat-progress-messages"></div>
        </div>
    </div>
</div>
