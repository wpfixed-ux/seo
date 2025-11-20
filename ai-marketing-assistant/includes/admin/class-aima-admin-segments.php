<?php
/**
 * Segments admin page
 *
 * @package AIMarketingAssistant
 */

class AIMA_Admin_Segments {

    /**
     * Render segments page
     */
    public function render_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $segment_id = isset($_GET['segment_id']) ? intval($_GET['segment_id']) : 0;

        switch ($action) {
            case 'edit':
                $this->render_edit_segment($segment_id);
                break;
            case 'create':
                $this->render_create_segment();
                break;
            default:
                $this->render_segments_list();
                break;
        }
    }

    /**
     * Render segments list
     */
    private function render_segments_list() {
        if (isset($_POST['create_segment'])) {
            $this->save_segment();
        }

        $segments = AIMA_Database::get_segments();
        include AIMA_ADMIN_DIR . 'views/segments-list.php';
    }

    /**
     * Render create segment form
     */
    private function render_create_segment() {
        include AIMA_ADMIN_DIR . 'views/segment-edit.php';
    }

    /**
     * Render edit segment form
     */
    private function render_edit_segment($segment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aima_segments';
        $segment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $segment_id
        ));

        if (!$segment) {
            wp_die(__('Segment not found', 'ai-marketing-assistant'));
        }

        include AIMA_ADMIN_DIR . 'views/segment-edit.php';
    }

    /**
     * Save segment
     */
    private function save_segment() {
        check_admin_referer('aima_save_segment');

        $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;

        $data = array(
            'name' => sanitize_text_field($_POST['segment_name']),
            'description' => sanitize_textarea_field($_POST['segment_description']),
            'conditions' => wp_json_encode($_POST['segment_conditions']),
            'status' => sanitize_text_field($_POST['segment_status'])
        );

        $result = AIMA_Database::upsert_segment($data, $segment_id);

        if ($result) {
            // Update segment members
            $segmentation = new AIMA_Segmentation_Engine();
            $segmentation->update_segment($segment_id ?: $result);

            add_settings_error(
                'aima_messages',
                'aima_message',
                __('Segment saved successfully', 'ai-marketing-assistant'),
                'updated'
            );
        }
    }

    /**
     * Get predefined segment templates
     */
    public function get_segment_templates() {
        return array(
            'high_value' => array(
                'name' => __('High Value Customers', 'ai-marketing-assistant'),
                'description' => __('Customers with total spent > 10000', 'ai-marketing-assistant'),
                'conditions' => array(
                    array('field' => 'total_spent', 'operator' => '>', 'value' => 10000)
                )
            ),
            'repeat_buyers' => array(
                'name' => __('Repeat Buyers', 'ai-marketing-assistant'),
                'description' => __('Customers with more than 3 orders', 'ai-marketing-assistant'),
                'conditions' => array(
                    array('field' => 'total_orders', 'operator' => '>', 'value' => 3)
                )
            ),
            'inactive_30days' => array(
                'name' => __('Inactive 30+ Days', 'ai-marketing-assistant'),
                'description' => __('Customers who haven\'t ordered in 30 days', 'ai-marketing-assistant'),
                'conditions' => array(
                    array('field' => 'last_order_date', 'operator' => '<', 'value' => '-30 days')
                )
            ),
            'new_customers' => array(
                'name' => __('New Customers', 'ai-marketing-assistant'),
                'description' => __('Customers registered in last 7 days', 'ai-marketing-assistant'),
                'conditions' => array(
                    array('field' => 'created_at', 'operator' => '>', 'value' => '-7 days')
                )
            )
        );
    }
}
