<?php
/**
 * Content Generator
 *
 * Generates content (articles, products, categories, pages) using Claude AI
 * based on technical specifications from SEO analysis.
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/generators
 */

class SAP_Content_Generator {

    /**
     * Claude AI instance
     *
     * @var SAP_Claude_AI
     */
    private $claude;

    /**
     * Constructor
     */
    public function __construct() {
        $this->claude = new SAP_Claude_AI();
    }

    /**
     * Generate content from technical specification
     *
     * @param int $spec_id Content specification ID
     * @param array $options Generation options
     * @return array|WP_Error Generated content or error
     */
    public function generate_from_spec($spec_id, $options = array()) {
        global $wpdb;

        // Get specification
        $table = $wpdb->prefix . 'sap_content_specs';
        $spec = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $spec_id
        ), ARRAY_A);

        if (!$spec) {
            return new WP_Error('spec_not_found', __('Content specification not found', 'seo-analytics-pro'));
        }

        // Decode JSON fields
        $spec['target_keywords'] = json_decode($spec['target_keywords'], true);
        $spec['lsi_keywords'] = json_decode($spec['lsi_keywords'], true);
        $spec['structure'] = json_decode($spec['structure'], true);
        $spec['technical_specs'] = json_decode($spec['technical_specs'], true);

        // Generate based on content type
        switch ($spec['content_type']) {
            case 'article':
                return $this->generate_article($spec, $options);

            case 'product':
                return $this->generate_product($spec, $options);

            case 'category':
                return $this->generate_category($spec, $options);

            case 'page':
                return $this->generate_page($spec, $options);

            default:
                return new WP_Error('invalid_type', __('Invalid content type', 'seo-analytics-pro'));
        }
    }

    /**
     * Generate article content
     *
     * @param array $spec Technical specification
     * @param array $options Options
     * @return array|WP_Error Generated content
     */
    public function generate_article($spec, $options = array()) {
        $settings = get_option('sap_settings', array());
        $language = $settings['serp_language'] ?? 'ru';

        $prompt = $this->build_article_prompt($spec, $language, $options);

        $response = $this->call_claude($prompt, array(
            'system' => $this->get_article_system_prompt($language),
            'max_tokens' => 8000
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_article_response($response, $spec);
    }

    /**
     * Generate product description
     *
     * @param array $spec Technical specification
     * @param array $options Options
     * @return array|WP_Error Generated content
     */
    public function generate_product($spec, $options = array()) {
        $settings = get_option('sap_settings', array());
        $language = $settings['serp_language'] ?? 'ru';

        $prompt = $this->build_product_prompt($spec, $language, $options);

        $response = $this->call_claude($prompt, array(
            'system' => $this->get_product_system_prompt($language),
            'max_tokens' => 4000
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_product_response($response, $spec);
    }

    /**
     * Generate category description
     *
     * @param array $spec Technical specification
     * @param array $options Options
     * @return array|WP_Error Generated content
     */
    public function generate_category($spec, $options = array()) {
        $settings = get_option('sap_settings', array());
        $language = $settings['serp_language'] ?? 'ru';

        $prompt = $this->build_category_prompt($spec, $language, $options);

        $response = $this->call_claude($prompt, array(
            'system' => $this->get_category_system_prompt($language),
            'max_tokens' => 3000
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_category_response($response, $spec);
    }

    /**
     * Generate page content
     *
     * @param array $spec Technical specification
     * @param array $options Options
     * @return array|WP_Error Generated content
     */
    public function generate_page($spec, $options = array()) {
        $settings = get_option('sap_settings', array());
        $language = $settings['serp_language'] ?? 'ru';

        $prompt = $this->build_page_prompt($spec, $language, $options);

        $response = $this->call_claude($prompt, array(
            'system' => $this->get_page_system_prompt($language),
            'max_tokens' => 6000
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->parse_page_response($response, $spec);
    }

    /**
     * Build article generation prompt
     */
    private function build_article_prompt($spec, $language, $options) {
        $lang_instruction = $language === 'ru' ? 'на русском языке' : ($language === 'uk' ? 'українською мовою' : 'in English');

        $prompt = "Напиши SEO-оптимизированную статью {$lang_instruction}.\n\n";

        // Title and meta
        $prompt .= "## Требования\n\n";
        $prompt .= "**Заголовок (H1):** {$spec['title']}\n\n";

        // Keywords
        if (!empty($spec['target_keywords'])) {
            $primary = $spec['target_keywords'][0] ?? '';
            $secondary = array_slice($spec['target_keywords'], 1, 5);
            $prompt .= "**Главное ключевое слово:** {$primary}\n";
            if (!empty($secondary)) {
                $prompt .= "**Дополнительные ключевые слова:** " . implode(', ', $secondary) . "\n";
            }
        }

        // LSI keywords
        if (!empty($spec['lsi_keywords'])) {
            $lsi_list = array_slice($spec['lsi_keywords'], 0, 20);
            $prompt .= "**LSI-слова (используй естественно в тексте):** " . implode(', ', $lsi_list) . "\n\n";
        }

        // Length
        $length = $spec['recommended_length'] ?? 2000;
        $prompt .= "**Требуемая длина:** {$length} слов\n\n";

        // Structure
        if (!empty($spec['structure'])) {
            $prompt .= "## Структура статьи\n\n";
            if (is_array($spec['structure'])) {
                foreach ($spec['structure'] as $section) {
                    if (isset($section['heading'])) {
                        $prompt .= "- **{$section['heading']}**";
                        if (isset($section['word_count'])) {
                            $prompt .= " (~{$section['word_count']} слов)";
                        }
                        $prompt .= "\n";
                    }
                }
            }
            $prompt .= "\n";
        }

        // Technical specs
        if (!empty($spec['technical_specs'])) {
            $tech = $spec['technical_specs'];

            if (!empty($tech['meta_description'])) {
                $prompt .= "**Мета-описание:** {$tech['meta_description']}\n";
            }

            if (!empty($tech['questions_to_answer'])) {
                $prompt .= "\n**Вопросы, на которые нужно ответить:**\n";
                foreach ($tech['questions_to_answer'] as $q) {
                    $prompt .= "- {$q}\n";
                }
            }
        }

        // Additional instructions
        $prompt .= "\n## Важные указания\n\n";
        $prompt .= "1. Пиши уникальный, полезный контент для читателя\n";
        $prompt .= "2. Используй подзаголовки H2, H3 для структуры\n";
        $prompt .= "3. Добавь списки и таблицы где уместно\n";
        $prompt .= "4. Ключевые слова распредели естественно по тексту\n";
        $prompt .= "5. Избегай переспама ключевыми словами\n";
        $prompt .= "6. Пиши простым, понятным языком\n";

        // Custom prompt from spec
        if (!empty($spec['ai_prompt'])) {
            $prompt .= "\n## Дополнительные требования\n\n";
            $prompt .= $spec['ai_prompt'] . "\n";
        }

        $prompt .= "\n## Формат ответа\n\n";
        $prompt .= "Верни результат в JSON формате:\n";
        $prompt .= "```json\n";
        $prompt .= json_encode(array(
            'title' => 'Заголовок статьи',
            'meta_description' => 'Мета-описание 150-160 символов',
            'content' => 'HTML-контент статьи с заголовками h2, h3, списками, таблицами',
            'excerpt' => 'Краткое описание для анонса (2-3 предложения)',
            'tags' => array('тег1', 'тег2')
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n```";

        return $prompt;
    }

    /**
     * Build product description prompt
     */
    private function build_product_prompt($spec, $language, $options) {
        $lang_instruction = $language === 'ru' ? 'на русском языке' : ($language === 'uk' ? 'українською мовою' : 'in English');

        $prompt = "Напиши SEO-оптимизированное описание товара {$lang_instruction}.\n\n";

        $prompt .= "**Название товара:** {$spec['title']}\n\n";

        // Keywords
        if (!empty($spec['target_keywords'])) {
            $prompt .= "**Ключевые слова:** " . implode(', ', array_slice($spec['target_keywords'], 0, 5)) . "\n\n";
        }

        // LSI
        if (!empty($spec['lsi_keywords'])) {
            $prompt .= "**LSI-слова:** " . implode(', ', array_slice($spec['lsi_keywords'], 0, 15)) . "\n\n";
        }

        $prompt .= "## Требования к описанию\n\n";
        $prompt .= "1. Краткое описание (50-100 слов) - для карточки товара\n";
        $prompt .= "2. Полное описание (300-500 слов) - преимущества, характеристики, применение\n";
        $prompt .= "3. Используй эмоциональные триггеры и выгоды для покупателя\n";
        $prompt .= "4. Добавь призыв к действию\n\n";

        $prompt .= "## Формат ответа\n\n";
        $prompt .= "```json\n";
        $prompt .= json_encode(array(
            'title' => 'SEO-заголовок товара',
            'short_description' => 'Краткое описание для карточки',
            'description' => 'Полное HTML-описание с подзаголовками и списками',
            'meta_description' => 'Мета-описание 150-160 символов',
            'features' => array('Особенность 1', 'Особенность 2'),
            'tags' => array('тег1', 'тег2')
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n```";

        return $prompt;
    }

    /**
     * Build category description prompt
     */
    private function build_category_prompt($spec, $language, $options) {
        $lang_instruction = $language === 'ru' ? 'на русском языке' : ($language === 'uk' ? 'українською мовою' : 'in English');

        $prompt = "Напиши SEO-описание для категории {$lang_instruction}.\n\n";

        $prompt .= "**Название категории:** {$spec['title']}\n\n";

        if (!empty($spec['target_keywords'])) {
            $prompt .= "**Ключевые слова:** " . implode(', ', array_slice($spec['target_keywords'], 0, 5)) . "\n\n";
        }

        $prompt .= "## Требования\n\n";
        $prompt .= "1. Верхний текст (100-150 слов) - над товарами\n";
        $prompt .= "2. Нижний текст (200-400 слов) - под товарами, SEO-текст\n";
        $prompt .= "3. Опиши что найдет покупатель в категории\n";
        $prompt .= "4. Используй ключевые слова естественно\n\n";

        $prompt .= "## Формат ответа\n\n";
        $prompt .= "```json\n";
        $prompt .= json_encode(array(
            'name' => 'Название категории',
            'description_top' => 'Текст над товарами',
            'description_bottom' => 'SEO-текст под товарами',
            'meta_title' => 'Мета-заголовок',
            'meta_description' => 'Мета-описание'
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n```";

        return $prompt;
    }

    /**
     * Build page content prompt
     */
    private function build_page_prompt($spec, $language, $options) {
        $lang_instruction = $language === 'ru' ? 'на русском языке' : ($language === 'uk' ? 'українською мовою' : 'in English');

        $prompt = "Напиши контент для страницы сайта {$lang_instruction}.\n\n";

        $prompt .= "**Заголовок:** {$spec['title']}\n\n";

        if (!empty($spec['target_keywords'])) {
            $prompt .= "**Ключевые слова:** " . implode(', ', $spec['target_keywords']) . "\n\n";
        }

        $length = $spec['recommended_length'] ?? 1000;
        $prompt .= "**Длина:** ~{$length} слов\n\n";

        if (!empty($spec['structure'])) {
            $prompt .= "## Структура\n\n";
            foreach ($spec['structure'] as $section) {
                if (isset($section['heading'])) {
                    $prompt .= "- {$section['heading']}\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "## Формат ответа\n\n";
        $prompt .= "```json\n";
        $prompt .= json_encode(array(
            'title' => 'Заголовок страницы',
            'content' => 'HTML-контент страницы',
            'meta_description' => 'Мета-описание',
            'excerpt' => 'Краткое описание'
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n```";

        return $prompt;
    }

    /**
     * Get system prompt for article generation
     */
    private function get_article_system_prompt($language) {
        if ($language === 'ru') {
            return "Ты - профессиональный SEO-копирайтер. Пишешь уникальные, информативные статьи на русском языке. " .
                   "Твои тексты оптимизированы для поисковых систем, но читаются естественно. " .
                   "Используешь структуру с подзаголовками, списками и таблицами. " .
                   "Отвечаешь только в JSON формате.";
        } elseif ($language === 'uk') {
            return "Ти - професійний SEO-копірайтер. Пишеш унікальні, інформативні статті українською мовою. " .
                   "Твої тексти оптимізовані для пошукових систем, але читаються природно. " .
                   "Відповідаєш тільки в JSON форматі.";
        }

        return "You are a professional SEO copywriter. You write unique, informative articles. " .
               "Your texts are optimized for search engines but read naturally. " .
               "You respond only in JSON format.";
    }

    /**
     * Get system prompt for product generation
     */
    private function get_product_system_prompt($language) {
        if ($language === 'ru') {
            return "Ты - копирайтер интернет-магазина. Пишешь продающие описания товаров на русском языке. " .
                   "Фокусируешься на выгодах для покупателя, используешь эмоциональные триггеры. " .
                   "Отвечаешь только в JSON формате.";
        }

        return "You are an e-commerce copywriter. You write selling product descriptions. " .
               "You focus on customer benefits and use emotional triggers. " .
               "You respond only in JSON format.";
    }

    /**
     * Get system prompt for category generation
     */
    private function get_category_system_prompt($language) {
        if ($language === 'ru') {
            return "Ты - SEO-специалист интернет-магазина. Пишешь описания категорий на русском языке. " .
                   "Тексты информативные и SEO-оптимизированные. " .
                   "Отвечаешь только в JSON формате.";
        }

        return "You are an e-commerce SEO specialist. You write category descriptions. " .
               "You respond only in JSON format.";
    }

    /**
     * Get system prompt for page generation
     */
    private function get_page_system_prompt($language) {
        if ($language === 'ru') {
            return "Ты - веб-копирайтер. Пишешь контент для страниц сайтов на русском языке. " .
                   "Тексты структурированные, информативные и оптимизированные для SEO. " .
                   "Отвечаешь только в JSON формате.";
        }

        return "You are a web copywriter. You write website page content. " .
               "You respond only in JSON format.";
    }

    /**
     * Call Claude AI API
     */
    private function call_claude($prompt, $options = array()) {
        if (!$this->claude->is_configured()) {
            return new WP_Error('no_api_key', __('Claude AI API key not configured', 'seo-analytics-pro'));
        }

        // Implementation would call the Claude API
        // This is a placeholder - actual implementation in SAP_Claude_AI class

        $api_endpoint = 'https://api.anthropic.com/v1/messages';
        $settings = get_option('sap_settings', array());
        $api_key = $settings['claude_ai_api_key'] ?? '';

        $body = array(
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'system' => $options['system'] ?? '',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        $response = wp_remote_post($api_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => wp_json_encode($body),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            return new WP_Error('api_error', $error_message);
        }

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Parse article response from Claude
     */
    private function parse_article_response($response, $spec) {
        $data = $this->parse_json_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        return array(
            'type' => 'article',
            'title' => $data['title'] ?? $spec['title'],
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'tags' => $data['tags'] ?? array(),
            'spec_id' => $spec['id'] ?? 0
        );
    }

    /**
     * Parse product response from Claude
     */
    private function parse_product_response($response, $spec) {
        $data = $this->parse_json_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        return array(
            'type' => 'product',
            'title' => $data['title'] ?? $spec['title'],
            'short_description' => $data['short_description'] ?? '',
            'description' => $data['description'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'features' => $data['features'] ?? array(),
            'tags' => $data['tags'] ?? array(),
            'spec_id' => $spec['id'] ?? 0
        );
    }

    /**
     * Parse category response from Claude
     */
    private function parse_category_response($response, $spec) {
        $data = $this->parse_json_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        return array(
            'type' => 'category',
            'name' => $data['name'] ?? $spec['title'],
            'description_top' => $data['description_top'] ?? '',
            'description_bottom' => $data['description_bottom'] ?? '',
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'spec_id' => $spec['id'] ?? 0
        );
    }

    /**
     * Parse page response from Claude
     */
    private function parse_page_response($response, $spec) {
        $data = $this->parse_json_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        return array(
            'type' => 'page',
            'title' => $data['title'] ?? $spec['title'],
            'content' => $data['content'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'spec_id' => $spec['id'] ?? 0
        );
    }

    /**
     * Parse JSON response
     */
    private function parse_json_response($response) {
        // Extract JSON from markdown code blocks if present
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $json_string = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
            $json_string = $matches[1];
        } else {
            $json_string = $response;
        }

        $data = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Create WordPress post from generated content
     *
     * @param array $content Generated content
     * @param array $options Post options
     * @return int|WP_Error Post ID or error
     */
    public function create_post($content, $options = array()) {
        $defaults = array(
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        );

        $options = wp_parse_args($options, $defaults);

        $post_data = array(
            'post_title' => $content['title'],
            'post_content' => $content['content'],
            'post_excerpt' => $content['excerpt'] ?? '',
            'post_status' => $options['post_status'],
            'post_author' => $options['post_author'],
            'post_type' => 'post'
        );

        if (!empty($options['post_category'])) {
            $post_data['post_category'] = (array) $options['post_category'];
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set meta description (for Yoast SEO or similar)
        if (!empty($content['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $content['meta_description']);
            update_post_meta($post_id, '_sap_meta_description', $content['meta_description']);
        }

        // Set tags
        if (!empty($content['tags'])) {
            wp_set_post_tags($post_id, $content['tags']);
        }

        // Link to spec
        if (!empty($content['spec_id'])) {
            update_post_meta($post_id, '_sap_spec_id', $content['spec_id']);
        }

        return $post_id;
    }

    /**
     * Create WooCommerce product from generated content
     *
     * @param array $content Generated content
     * @param array $options Product options
     * @return int|WP_Error Product ID or error
     */
    public function create_product($content, $options = array()) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('no_woocommerce', __('WooCommerce is not installed', 'seo-analytics-pro'));
        }

        $product = new WC_Product_Simple();

        $product->set_name($content['title']);
        $product->set_description($content['description']);
        $product->set_short_description($content['short_description']);
        $product->set_status($options['status'] ?? 'draft');

        if (!empty($options['price'])) {
            $product->set_regular_price($options['price']);
        }

        if (!empty($options['categories'])) {
            $product->set_category_ids($options['categories']);
        }

        $product_id = $product->save();

        // Set meta description
        if (!empty($content['meta_description'])) {
            update_post_meta($product_id, '_yoast_wpseo_metadesc', $content['meta_description']);
        }

        // Set tags
        if (!empty($content['tags'])) {
            wp_set_object_terms($product_id, $content['tags'], 'product_tag');
        }

        // Link to spec
        if (!empty($content['spec_id'])) {
            update_post_meta($product_id, '_sap_spec_id', $content['spec_id']);
        }

        return $product_id;
    }
}
