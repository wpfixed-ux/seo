<?php
/**
 * Telegram Bot API integration
 *
 * @package AIMarketingAssistant
 */

class AIMA_Telegram_Bot {

    private $bot_token;
    private $api_url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->bot_token = get_option('aima_telegram_bot_token');
        $this->api_url = 'https://api.telegram.org/bot' . $this->bot_token . '/';
    }

    /**
     * Send message to user
     */
    public function send_message($chat_id, $message, $parse_mode = 'HTML', $reply_markup = null) {
        if (empty($this->bot_token)) {
            return array(
                'success' => false,
                'error' => __('Telegram bot token not configured', 'ai-marketing-assistant')
            );
        }

        $data = array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
        );

        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }

        return $this->call_api('sendMessage', $data);
    }

    /**
     * Send photo with caption
     */
    public function send_photo($chat_id, $photo_url, $caption = '', $reply_markup = null) {
        $data = array(
            'chat_id' => $chat_id,
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        );

        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }

        return $this->call_api('sendPhoto', $data);
    }

    /**
     * Send campaign to subscribers
     */
    public function send_campaign($offer_id) {
        $offer = AIMA_Database::get_offer($offer_id);

        if (!$offer) {
            return array(
                'success' => false,
                'error' => __('Offer not found', 'ai-marketing-assistant')
            );
        }

        // Get telegram subscribers who are also customers in the segment
        $subscribers = $this->get_segment_subscribers($offer->segment_id);

        if (empty($subscribers)) {
            return array(
                'success' => false,
                'error' => __('No Telegram subscribers in this segment', 'ai-marketing-assistant')
            );
        }

        $sent_count = 0;
        $offer_data = json_decode($offer->products, true);

        foreach ($subscribers as $subscriber) {
            $message = $this->format_offer_message($offer, $offer_data);

            // Create inline keyboard with product links
            $keyboard = array();
            if (!empty($offer_data)) {
                foreach (array_slice($offer_data, 0, 3) as $product) {
                    if (isset($product['permalink'])) {
                        $keyboard[] = array(
                            array(
                                'text' => '🛒 ' . $product['name'],
                                'url' => $product['permalink']
                            )
                        );
                    }
                }
            }

            // Add coupon button
            if (!empty($offer->coupon_code)) {
                $shop_url = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/shop');
                $keyboard[] = array(
                    array(
                        'text' => '🎁 Использовать промокод: ' . $offer->coupon_code,
                        'url' => $shop_url
                    )
                );
            }

            $reply_markup = array('inline_keyboard' => $keyboard);

            // Send with banner if available
            if (!empty($offer->banner_url)) {
                $result = $this->send_photo(
                    $subscriber->telegram_user_id,
                    $offer->banner_url,
                    $message,
                    $reply_markup
                );
            } else {
                $result = $this->send_message(
                    $subscriber->telegram_user_id,
                    $message,
                    'HTML',
                    $reply_markup
                );
            }

            if ($result['success']) {
                $sent_count++;
            }

            // Delay to avoid rate limiting
            usleep(50000); // 0.05 second between messages
        }

        return array(
            'success' => true,
            'sent_count' => $sent_count
        );
    }

    /**
     * Format offer message for Telegram
     */
    private function format_offer_message($offer, $products_data) {
        $message = "<b>🎉 " . esc_html($offer->name) . "</b>\n\n";

        if (!empty($offer->subject)) {
            $message .= esc_html($offer->subject) . "\n\n";
        }

        if (!empty($offer->content)) {
            $content = wp_strip_all_tags($offer->content);
            $message .= $content . "\n\n";
        }

        // Add products
        if (!empty($products_data)) {
            $message .= "<b>📦 Товары:</b>\n";
            foreach (array_slice($products_data, 0, 5) as $product) {
                $message .= "• " . esc_html($product['name']);
                if (!empty($product['price'])) {
                    $message .= " - " . number_format($product['price'], 0, ',', ' ') . " ₽";
                }
                $message .= "\n";
            }
            $message .= "\n";
        }

        // Add discount info
        if (!empty($offer->coupon_code)) {
            $message .= "<b>🎁 Промокод:</b> <code>" . esc_html($offer->coupon_code) . "</code>\n";
            $message .= "<b>💰 Скидка:</b> " . esc_html($offer->discount_value) . "%\n\n";
        }

        $message .= "⏰ <i>Предложение ограничено!</i>";

        return $message;
    }

    /**
     * Get segment subscribers
     */
    private function get_segment_subscribers($segment_id) {
        global $wpdb;
        $telegram_table = $wpdb->prefix . 'aima_telegram_subscribers';
        $members_table = $wpdb->prefix . 'aima_segment_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*
            FROM $telegram_table t
            INNER JOIN $members_table m ON t.customer_id = m.customer_id
            WHERE m.segment_id = %d
            AND t.is_active = 1",
            $segment_id
        ));
    }

    /**
     * Subscribe user
     */
    public function subscribe_user($telegram_user_id, $customer_id = null, $user_data = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_telegram_subscribers';

        // Check if already subscribed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE telegram_user_id = %s",
            $telegram_user_id
        ));

        if ($existing) {
            // Reactivate if unsubscribed
            $wpdb->update(
                $table,
                array(
                    'is_active' => 1,
                    'unsubscribed_at' => null
                ),
                array('telegram_user_id' => $telegram_user_id),
                array('%d', '%s'),
                array('%s')
            );

            return $existing->id;
        }

        // Insert new subscriber
        $data = array(
            'telegram_user_id' => $telegram_user_id,
            'customer_id' => $customer_id,
            'telegram_username' => $user_data['username'] ?? null,
            'first_name' => $user_data['first_name'] ?? null,
            'last_name' => $user_data['last_name'] ?? null,
            'phone' => $user_data['phone'] ?? null,
            'is_active' => 1
        );

        $wpdb->insert($table, $data);

        return $wpdb->insert_id;
    }

    /**
     * Unsubscribe user
     */
    public function unsubscribe_user($telegram_user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_telegram_subscribers';

        return $wpdb->update(
            $table,
            array(
                'is_active' => 0,
                'unsubscribed_at' => current_time('mysql')
            ),
            array('telegram_user_id' => $telegram_user_id),
            array('%d', '%s'),
            array('%s')
        );
    }

    /**
     * Handle webhook
     */
    public function handle_webhook($update) {
        if (empty($update)) {
            return false;
        }

        // Handle different update types
        if (isset($update['message'])) {
            $this->handle_message($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handle_callback_query($update['callback_query']);
        }

        return true;
    }

    /**
     * Handle incoming message
     */
    private function handle_message($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $this->send_welcome_message($chat_id, $message['from']);
        } elseif ($text === '/subscribe') {
            $this->subscribe_user($chat_id, null, $message['from']);
            $this->send_message($chat_id, '✅ Вы успешно подписались на рассылку специальных предложений!');
        } elseif ($text === '/unsubscribe') {
            $this->unsubscribe_user($chat_id);
            $this->send_message($chat_id, '❌ Вы отписались от рассылки.');
        }
    }

    /**
     * Send welcome message
     */
    private function send_welcome_message($chat_id, $user_data) {
        $message = "👋 Привет, " . ($user_data['first_name'] ?? 'друг') . "!\n\n";
        $message .= "Я бот магазина " . get_bloginfo('name') . ".\n\n";
        $message .= "Подпишись на мою рассылку, чтобы получать:\n";
        $message .= "• 🎁 Эксклюзивные предложения\n";
        $message .= "• 💰 Персональные скидки\n";
        $message .= "• 📦 Информацию о новых товарах\n\n";
        $message .= "Используй команды:\n";
        $message .= "/subscribe - Подписаться на рассылку\n";
        $message .= "/unsubscribe - Отписаться";

        $this->send_message($chat_id, $message);
    }

    /**
     * Call Telegram API
     */
    private function call_api($method, $data) {
        $url = $this->api_url . $method;

        $response = wp_remote_post($url, array(
            'body' => $data,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (!isset($result['ok']) || !$result['ok']) {
            return array(
                'success' => false,
                'error' => $result['description'] ?? 'Unknown error'
            );
        }

        return array(
            'success' => true,
            'result' => $result['result'] ?? array()
        );
    }

    /**
     * Set webhook
     */
    public function set_webhook($webhook_url) {
        return $this->call_api('setWebhook', array(
            'url' => $webhook_url
        ));
    }

    /**
     * Get bot info
     */
    public function get_me() {
        return $this->call_api('getMe', array());
    }
}
