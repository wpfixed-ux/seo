<?php
/**
 * Wizard Handler for multi-step conversations
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/chatbot
 */

class SAP_Wizard_Handler {

    /**
     * Session data
     *
     * @var array
     */
    private $session;

    /**
     * Constructor
     *
     * @param array $session Current session
     */
    public function __construct($session) {
        $this->session = $session;
    }

    /**
     * Process wizard step
     *
     * @param string $wizard_type Type of wizard
     * @param array $entities Input entities
     * @return array Response
     */
    public function process($wizard_type, $entities) {
        $context = $this->session['context'];
        $step = $context['wizard_step'] ?? 'start';
        $data = $context['wizard_data'] ?? array();
        $input = $entities['input'] ?? '';

        switch ($wizard_type) {
            case 'register_producer':
                return $this->process_register_producer($step, $data, $input);

            case 'add_product':
                return $this->process_add_product($step, $data, $input);

            case 'update_price_wizard':
                return $this->process_update_price($step, $data, $input);

            default:
                return $this->cancel_wizard("Неизвестный мастер.");
        }
    }

    /**
     * Process producer registration wizard
     */
    private function process_register_producer($step, $data, $input) {
        switch ($step) {
            case 'name':
                if (empty($input) || mb_strlen($input) < 2) {
                    return array(
                        'text' => "Пожалуйста, введите название (минимум 2 символа):",
                        'continue_wizard' => true,
                    );
                }

                $data['name'] = sanitize_text_field($input);
                return $this->next_step('register_producer', 'description', $data,
                    "Отлично! Теперь кратко опишите ваше производство (что производите, особенности):"
                );

            case 'description':
                $data['description'] = sanitize_textarea_field($input);
                return $this->next_step('register_producer', 'city', $data,
                    "Шаг 3 из 5: В каком городе вы находитесь?"
                );

            case 'city':
                if (empty($input)) {
                    return array(
                        'text' => "Пожалуйста, укажите город:",
                        'continue_wizard' => true,
                    );
                }

                $data['city'] = sanitize_text_field($input);
                return $this->next_step('register_producer', 'phone', $data,
                    "Шаг 4 из 5: Укажите контактный телефон (формат: +380XXXXXXXXX):"
                );

            case 'phone':
                // Validate phone
                $phone = preg_replace('/[^\d+]/', '', $input);
                if (!preg_match('/^\+?380\d{9}$/', $phone)) {
                    return array(
                        'text' => "Неверный формат телефона. Введите в формате +380XXXXXXXXX:",
                        'continue_wizard' => true,
                    );
                }

                $data['contact_phone'] = $phone;
                return $this->next_step('register_producer', 'telegram', $data,
                    "Последний шаг! Укажите ваш Telegram (без @) или напишите 'пропустить':"
                );

            case 'telegram':
                if (mb_strtolower($input) !== 'пропустить' && mb_strtolower($input) !== 'skip') {
                    $telegram = ltrim($input, '@');
                    $data['contact_telegram'] = sanitize_text_field($telegram);
                }

                // Complete registration
                return $this->complete_producer_registration($data);
        }

        return $this->cancel_wizard("Ошибка в процессе регистрации.");
    }

    /**
     * Complete producer registration
     */
    private function complete_producer_registration($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_producers';

        $now = current_time('mysql');
        $user_id = get_current_user_id();

        // Generate verification code
        $verification_code = wp_generate_password(6, false, false);

        $result = $wpdb->insert($table, array(
            'user_id' => $user_id ?: null,
            'telegram_id' => $this->session['telegram_chat_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'contact_phone' => $data['contact_phone'],
            'contact_telegram' => $data['contact_telegram'] ?? null,
            'city' => $data['city'],
            'status' => 'pending',
            'verification_code' => $verification_code,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if (!$result) {
            return $this->cancel_wizard("Ошибка при сохранении. Попробуйте позже.");
        }

        $producer_id = $wpdb->insert_id;

        // Check if auto-approval is enabled
        $settings = get_option('sap_chatbot_settings', array());
        $needs_approval = $settings['producer_approval_required'] ?? true;

        if ($needs_approval) {
            $text = "Регистрация завершена!\n\n";
            $text .= "**{$data['name']}**\n";
            $text .= "📍 {$data['city']}\n";
            $text .= "📞 {$data['contact_phone']}\n\n";
            $text .= "Ваша заявка отправлена на модерацию. После одобрения вы сможете добавлять товары.\n\n";
            $text .= "Код подтверждения: **{$verification_code}**";
        } else {
            // Auto-approve
            $wpdb->update($table, array(
                'status' => 'active',
                'verified_at' => $now,
            ), array('id' => $producer_id));

            $text = "Поздравляем! Вы зарегистрированы как производитель!\n\n";
            $text .= "**{$data['name']}**\n";
            $text .= "📍 {$data['city']}\n";
            $text .= "📞 {$data['contact_phone']}\n\n";
            $text .= "Теперь вы можете добавлять товары в каталог.";
        }

        return array(
            'text' => $text,
            'clear_wizard' => true,
            'actions' => $needs_approval ? array() : array(
                array('type' => 'button', 'text' => 'Добавить товар', 'action' => 'add_product'),
                array('type' => 'button', 'text' => 'Импорт из Excel', 'action' => 'import_excel'),
            ),
        );
    }

    /**
     * Process add product wizard
     */
    private function process_add_product($step, $data, $input) {
        switch ($step) {
            case 'name':
                if (empty($input) || mb_strlen($input) < 2) {
                    return array(
                        'text' => "Введите название товара (минимум 2 символа):",
                        'continue_wizard' => true,
                    );
                }

                $data['name'] = sanitize_text_field($input);
                return $this->next_step('add_product', 'category', $data,
                    $this->get_category_selection_text()
                );

            case 'category':
                // Try to match category
                $category_id = $this->match_category($input);
                if (!$category_id) {
                    return array(
                        'text' => "Категория не найдена. " . $this->get_category_selection_text(),
                        'continue_wizard' => true,
                    );
                }

                $data['category_id'] = $category_id;
                return $this->next_step('add_product', 'description', $data,
                    "Добавьте описание товара (или напишите 'пропустить'):"
                );

            case 'description':
                if (mb_strtolower($input) !== 'пропустить' && mb_strtolower($input) !== 'skip') {
                    $data['description'] = sanitize_textarea_field($input);
                }

                return $this->next_step('add_product', 'price', $data,
                    "Укажите цену в гривнах (только число, например: 150):"
                );

            case 'price':
                $price = floatval(preg_replace('/[^\d.,]/', '', str_replace(',', '.', $input)));
                if ($price <= 0) {
                    return array(
                        'text' => "Введите корректную цену (число больше 0):",
                        'continue_wizard' => true,
                    );
                }

                $data['price'] = $price;
                return $this->next_step('add_product', 'unit', $data,
                    "Единица измерения цены:\n1. шт\n2. кг\n3. л\n4. 100г\n\nВведите номер или название:"
                );

            case 'unit':
                $units = array(
                    '1' => 'шт', 'шт' => 'шт', 'штука' => 'шт',
                    '2' => 'кг', 'кг' => 'кг', 'килограмм' => 'кг',
                    '3' => 'л', 'л' => 'л', 'литр' => 'л',
                    '4' => '100г', '100г' => '100г', '100 г' => '100г',
                );

                $unit = $units[mb_strtolower(trim($input))] ?? 'шт';
                $data['price_unit'] = $unit;

                // Complete product addition
                return $this->complete_add_product($data);
        }

        return $this->cancel_wizard("Ошибка при добавлении товара.");
    }

    /**
     * Complete adding product
     */
    private function complete_add_product($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_catalog_products';

        $now = current_time('mysql');

        $result = $wpdb->insert($table, array(
            'producer_id' => $this->session['producer_id'],
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'price' => $data['price'],
            'price_unit' => $data['price_unit'],
            'in_stock' => true,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if (!$result) {
            return $this->cancel_wizard("Ошибка при сохранении товара.");
        }

        // Update category count
        $this->update_category_count($data['category_id']);

        $price_str = number_format($data['price'], 0, '.', ' ') . ' грн/' . $data['price_unit'];

        $text = "Товар добавлен!\n\n";
        $text .= "**{$data['name']}**\n";
        $text .= "Цена: {$price_str}\n";
        $text .= "Статус: ✅ В наличии\n\n";
        $text .= "Добавить ещё товар?";

        return array(
            'text' => $text,
            'clear_wizard' => true,
            'actions' => array(
                array('type' => 'button', 'text' => 'Добавить ещё', 'action' => 'add_product'),
                array('type' => 'button', 'text' => 'Мои товары', 'action' => 'my_products'),
            ),
        );
    }

    /**
     * Process update price wizard
     */
    private function process_update_price($step, $data, $input) {
        switch ($step) {
            case 'select_product':
                // Find product by name or ID
                $product = $this->find_producer_product($input);
                if (!$product) {
                    return array(
                        'text' => "Товар не найден. Введите название товара из списка:",
                        'continue_wizard' => true,
                    );
                }

                $data['product_id'] = $product['id'];
                $data['product_name'] = $product['name'];

                $current_price = $product['price']
                    ? number_format($product['price'], 0) . ' грн/' . $product['price_unit']
                    : 'не указана';

                return $this->next_step('update_price_wizard', 'new_price', $data,
                    "Товар: **{$product['name']}**\nТекущая цена: {$current_price}\n\nВведите новую цену:"
                );

            case 'new_price':
                $price = floatval(preg_replace('/[^\d.,]/', '', str_replace(',', '.', $input)));
                if ($price <= 0) {
                    return array(
                        'text' => "Введите корректную цену:",
                        'continue_wizard' => true,
                    );
                }

                // Update price
                global $wpdb;
                $table = $wpdb->prefix . 'sap_catalog_products';

                $wpdb->update($table, array(
                    'price' => $price,
                    'updated_at' => current_time('mysql'),
                ), array('id' => $data['product_id']));

                $text = "Цена обновлена!\n\n";
                $text .= "**{$data['product_name']}**\n";
                $text .= "Новая цена: " . number_format($price, 0) . " грн";

                return array(
                    'text' => $text,
                    'clear_wizard' => true,
                    'actions' => array(
                        array('type' => 'button', 'text' => 'Обновить другой', 'action' => 'update_price'),
                        array('type' => 'button', 'text' => 'Мои товары', 'action' => 'my_products'),
                    ),
                );
        }

        return $this->cancel_wizard("Ошибка при обновлении цены.");
    }

    /**
     * Move to next wizard step
     */
    private function next_step($wizard, $step, $data, $message) {
        return array(
            'text' => $message,
            'update_context' => array(
                'wizard' => $wizard,
                'wizard_step' => $step,
                'wizard_data' => $data,
            ),
            'continue_wizard' => true,
        );
    }

    /**
     * Cancel wizard
     */
    private function cancel_wizard($message = "Операция отменена.") {
        return array(
            'text' => $message,
            'clear_wizard' => true,
        );
    }

    /**
     * Get category selection text
     */
    private function get_category_selection_text() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        $categories = $wpdb->get_results(
            "SELECT name, slug FROM $table WHERE parent_id = 0 AND status = 'active' ORDER BY sort_order LIMIT 10",
            ARRAY_A
        );

        $text = "Выберите категорию:\n\n";
        foreach ($categories as $i => $cat) {
            $text .= ($i + 1) . ". {$cat['name']}\n";
        }
        $text .= "\nВведите номер или название категории:";

        return $text;
    }

    /**
     * Match category by input
     */
    private function match_category($input) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_product_categories';

        // Try number first
        if (is_numeric($input)) {
            $categories = $wpdb->get_results(
                "SELECT id FROM $table WHERE parent_id = 0 AND status = 'active' ORDER BY sort_order",
                ARRAY_A
            );
            $index = intval($input) - 1;
            if (isset($categories[$index])) {
                return $categories[$index]['id'];
            }
        }

        // Try by name or slug
        $input_lower = mb_strtolower($input);

        $category = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE status = 'active' AND (LOWER(name) LIKE %s OR slug = %s)",
            '%' . $wpdb->esc_like($input_lower) . '%',
            $input_lower
        ));

        return $category ?: null;
    }

    /**
     * Find producer's product
     */
    private function find_producer_product($input) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_catalog_products';

        // Try by ID
        if (is_numeric($input)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND producer_id = %d AND status != 'deleted'",
                intval($input),
                $this->session['producer_id']
            ), ARRAY_A);
        }

        // Try by name
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE producer_id = %d AND status != 'deleted' AND name LIKE %s",
            $this->session['producer_id'],
            '%' . $wpdb->esc_like($input) . '%'
        ), ARRAY_A);
    }

    /**
     * Update category product count
     */
    private function update_category_count($category_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'sap_catalog_products';
        $categories_table = $wpdb->prefix . 'sap_product_categories';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $products_table WHERE category_id = %d AND status = 'active'",
            $category_id
        ));

        $wpdb->update($categories_table, array('products_count' => $count), array('id' => $category_id));
    }
}
