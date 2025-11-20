<?php
/**
 * AI Client for Claude API
 *
 * @package AIMarketingAssistant
 */

class AIMA_AI_Client {

    private $api_key;
    private $model;
    private $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('aima_ai_api_key');
        $this->model = get_option('aima_ai_model', 'claude-3-5-sonnet-20241022');
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
     * Call Claude API
     */
    private function call_api($system_prompt, $user_message, $max_tokens = 2000) {
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
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => "API error: {$response_code}",
                'details' => $response_body
            );
        }

        $data = json_decode($response_body, true);

        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }

        return array(
            'success' => true,
            'content' => $data['content'][0]['text'],
            'usage' => $data['usage'] ?? array()
        );
    }

    /**
     * Parse JSON response
     */
    public function parse_json_response($content) {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $json_str = $matches[1];
        } else {
            $json_str = $content;
        }

        $data = json_decode($json_str, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to parse JSON response'
            );
        }

        return array(
            'success' => true,
            'data' => $data
        );
    }
}
