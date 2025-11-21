<?php
/**
 * AI Client with multi-provider support (Claude, OpenAI, Kimi)
 *
 * @package AIMarketingAssistant
 */

class AIMA_AI_Client {

    private $provider;
    private $api_key;
    private $model;
    private $api_url;

    /**
     * Available AI providers and their models
     */
    private $providers = array(
        'anthropic' => array(
            'name' => 'Claude (Anthropic)',
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'models' => array(
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Latest)',
                'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet',
                'claude-3-opus-20240229' => 'Claude 3 Opus',
                'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                'claude-3-haiku-20240307' => 'Claude 3 Haiku'
            )
        ),
        'openai' => array(
            'name' => 'OpenAI',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'models' => array(
                'gpt-4o' => 'GPT-4o (Latest)',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
                'gpt-4' => 'GPT-4',
                'gpt-4-0125-preview' => 'GPT-4 0125 Preview',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K'
            )
        ),
        'kimi' => array(
            'name' => 'Kimi (Moonshot AI)',
            'api_url' => 'https://api.moonshot.cn/v1/chat/completions',
            'models' => array(
                'moonshot-v1-8k' => 'Moonshot v1 8K',
                'moonshot-v1-32k' => 'Moonshot v1 32K',
                'moonshot-v1-128k' => 'Moonshot v1 128K'
            )
        )
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->provider = get_option('aima_ai_provider', 'anthropic');
        $this->api_key = get_option('aima_ai_api_key');
        $this->model = get_option('aima_ai_model', $this->get_default_model());
        $this->api_url = $this->providers[$this->provider]['api_url'] ?? '';
    }

    /**
     * Get available providers
     */
    public function get_providers() {
        return $this->providers;
    }

    /**
     * Get models for specific provider
     */
    public function get_models($provider = null) {
        $provider = $provider ?? $this->provider;
        return $this->providers[$provider]['models'] ?? array();
    }

    /**
     * Get default model for current provider
     */
    private function get_default_model() {
        $models = $this->get_models($this->provider);
        return !empty($models) ? array_key_first($models) : '';
    }

    /**
     * Generate marketing content
     */
    public function generate_content($prompt, $context = array()) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => __('API key not configured', 'ai-marketing-assistant')
            );
        }

        $system_prompt = $this->build_system_prompt();
        $user_message = $this->build_user_message($prompt, $context);

        $response = $this->call_api($system_prompt, $user_message);

        return $response;
    }

    /**
     * Generate personalized offer content
     */
    public function generate_offer($segment_data, $products_data, $campaign_type = 'promotional') {
        $prompt = $this->build_offer_prompt($segment_data, $products_data, $campaign_type);

        return $this->generate_content($prompt, array(
            'segment' => $segment_data,
            'products' => $products_data,
            'type' => $campaign_type
        ));
    }

    /**
     * Generate email subject line
     */
    public function generate_subject_line($offer_content, $segment_data) {
        $prompt = "Создай цепляющую тему письма (subject line) для email рассылки на русском языке.\n\n";
        $prompt .= "Целевая аудитория: {$segment_data['name']}\n";
        $prompt .= "Описание сегмента: {$segment_data['description']}\n\n";
        $prompt .= "Контент оффера:\n{$offer_content}\n\n";
        $prompt .= "Требования:\n";
        $prompt .= "- Максимум 50 символов\n";
        $prompt .= "- Персонализированная и цепляющая\n";
        $prompt .= "- Вызывает желание открыть письмо\n";
        $prompt .= "- Включает элемент срочности или выгоды\n\n";
        $prompt .= "Верни только текст темы письма без кавычек.";

        $response = $this->generate_content($prompt);

        if ($response['success']) {
            return trim($response['content']);
        }

        return 'Специальное предложение для вас';
    }

    /**
     * Build system prompt
     */
    private function build_system_prompt() {
        return "Ты - опытный AI маркетолог и копирайтер, специализирующийся на персонализированных маркетинговых кампаниях для e-commerce.\n\n" .
               "Твои задачи:\n" .
               "1. Анализировать поведение и предпочтения клиентов\n" .
               "2. Создавать персонализированные офферы и рекламные материалы\n" .
               "3. Писать убедительные тексты для email рассылок\n" .
               "4. Генерировать цепляющие заголовки и призывы к действию\n" .
               "5. Оптимизировать контент под конкретные сегменты аудитории\n\n" .
               "Стиль коммуникации:\n" .
               "- Дружелюбный и персональный\n" .
               "- Убедительный без навязчивости\n" .
               "- Фокус на выгоде для клиента\n" .
               "- Использование социальных доказательств\n" .
               "- Создание срочности и эксклюзивности\n\n" .
               "Язык: Русский";
    }

    /**
     * Build user message
     */
    private function build_user_message($prompt, $context) {
        $message = $prompt;

        if (!empty($context)) {
            $message .= "\n\nКонтекст:\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $message;
    }

    /**
     * Build offer prompt
     */
    private function build_offer_prompt($segment_data, $products_data, $campaign_type) {
        $prompt = "Создай персонализированный маркетинговый оффер для email рассылки.\n\n";

        // Segment information
        $prompt .= "## Целевой сегмент\n";
        $prompt .= "Название: {$segment_data['name']}\n";
        $prompt .= "Описание: {$segment_data['description']}\n";
        $prompt .= "Количество клиентов: {$segment_data['customer_count']}\n";

        if (!empty($segment_data['purchase_patterns'])) {
            $prompt .= "\nПаттерны покупок:\n";
            $prompt .= "- Средний чек: {$segment_data['purchase_patterns']['avg_order_value']} руб.\n";
            $prompt .= "- Частота покупок: каждые {$segment_data['purchase_patterns']['frequency_days']} дней\n";

            if (!empty($segment_data['purchase_patterns']['top_categories'])) {
                $prompt .= "- Любимые категории: " . implode(', ', $segment_data['purchase_patterns']['top_categories']) . "\n";
            }
        }

        // Products information
        $prompt .= "\n## Рекомендуемые товары\n";
        foreach ($products_data as $index => $product) {
            $prompt .= ($index + 1) . ". {$product['name']}\n";
            $prompt .= "   Цена: {$product['price']} руб.\n";
            if (!empty($product['description'])) {
                $prompt .= "   Описание: {$product['description']}\n";
            }
            $prompt .= "\n";
        }

        // Campaign type
        $prompt .= "\n## Тип кампании\n";
        switch ($campaign_type) {
            case 'promotional':
                $prompt .= "Промо-акция с ограниченным предложением\n";
                break;
            case 'reactivation':
                $prompt .= "Реактивация неактивных клиентов\n";
                break;
            case 'cross_sell':
                $prompt .= "Кросс-продажа дополнительных товаров\n";
                break;
            case 'loyalty':
                $prompt .= "Программа лояльности для постоянных клиентов\n";
                break;
            default:
                $prompt .= "Стандартная промо-кампания\n";
        }

        $prompt .= "\n## Задача\n";
        $prompt .= "Создай структурированный маркетинговый оффер в формате JSON со следующими полями:\n\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "headline": "Цепляющий заголовок (до 100 символов)",'."\n";
        $prompt .= '  "subheadline": "Подзаголовок с усилением предложения (до 150 символов)",'."\n";
        $prompt .= '  "body": "Основной текст письма с описанием выгоды (2-3 абзаца)",'."\n";
        $prompt .= '  "cta_text": "Текст кнопки призыва к действию",'."\n";
        $prompt .= '  "urgency": "Текст создающий срочность (например: \'Только до конца недели!\')",'."\n";
        $prompt .= '  "personalization_note": "Персональное обращение к клиенту",'."\n";
        $prompt .= '  "discount_suggestion": "Рекомендуемая скидка в процентах (число от 5 до 30)",'."\n";
        $prompt .= '  "banner_concept": "Описание концепции баннера для дизайнера"'."\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        $prompt .= "Верни только валидный JSON без дополнительных комментариев.";

        return $prompt;
    }

    /**
     * Call AI API (unified for all providers)
     */
    private function call_api($system_prompt, $user_message, $max_tokens = 2000) {
        switch ($this->provider) {
            case 'anthropic':
                return $this->call_anthropic_api($system_prompt, $user_message, $max_tokens);
            case 'openai':
                return $this->call_openai_api($system_prompt, $user_message, $max_tokens);
            case 'kimi':
                return $this->call_kimi_api($system_prompt, $user_message, $max_tokens);
            default:
                return array(
                    'success' => false,
                    'error' => 'Unknown provider: ' . $this->provider
                );
        }
    }

    /**
     * Call Anthropic (Claude) API
     */
    private function call_anthropic_api($system_prompt, $user_message, $max_tokens) {
        $body = array(
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'system' => $system_prompt,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $user_message
                )
            )
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'provider' => 'anthropic'
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => "API error: {$response_code}",
                'details' => $response_body,
                'provider' => 'anthropic'
            );
        }

        $data = json_decode($response_body, true);

        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format',
                'provider' => 'anthropic'
            );
        }

        return array(
            'success' => true,
            'content' => $data['content'][0]['text'],
            'usage' => $data['usage'] ?? array(),
            'provider' => 'anthropic',
            'model' => $this->model
        );
    }

    /**
     * Call OpenAI API
     */
    private function call_openai_api($system_prompt, $user_message, $max_tokens) {
        $body = array(
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_message
                )
            ),
            'temperature' => 0.7
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'provider' => 'openai'
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => "API error: {$response_code}",
                'details' => $response_body,
                'provider' => 'openai'
            );
        }

        $data = json_decode($response_body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format',
                'provider' => 'openai'
            );
        }

        return array(
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? array(),
            'provider' => 'openai',
            'model' => $this->model
        );
    }

    /**
     * Call Kimi (Moonshot AI) API
     */
    private function call_kimi_api($system_prompt, $user_message, $max_tokens) {
        $body = array(
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_message
                )
            ),
            'temperature' => 0.7
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'provider' => 'kimi'
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => "API error: {$response_code}",
                'details' => $response_body,
                'provider' => 'kimi'
            );
        }

        $data = json_decode($response_body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format',
                'provider' => 'kimi'
            );
        }

        return array(
            'success' => true,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? array(),
            'provider' => 'kimi',
            'model' => $this->model
        );
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        $test_prompt = "Ответь одним словом: OK";
        $response = $this->generate_content($test_prompt);

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'ai-marketing-assistant'),
                'provider' => $response['provider'] ?? $this->provider,
                'model' => $response['model'] ?? $this->model,
                'response' => $response['content']
            );
        }

        return array(
            'success' => false,
            'message' => $response['error'] ?? __('Connection failed', 'ai-marketing-assistant'),
            'provider' => $this->provider,
            'model' => $this->model
        );
    }

    /**
     * Parse JSON response
     */
    public function parse_json_response($content) {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $json_str = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $json_str = $matches[1];
        } else {
            $json_str = $content;
        }

        $data = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to parse JSON response: ' . json_last_error_msg()
            );
        }

        return array(
            'success' => true,
            'data' => $data
        );
    }
}
