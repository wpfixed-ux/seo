<?php
/**
 * Email queue processor with throttling
 *
 * @package AIMarketingAssistant
 */

class AIMA_Email_Queue {

    /**
     * Process email queue with throttling
     */
    public function process_queue() {
        $emails_per_hour = get_option('aima_emails_per_hour', 20);

        // Calculate how many emails to send this round (every 3 minutes)
        // 60 minutes / 20 emails = 3 minutes per email, so send 1 email per run
        $batch_size = max(1, floor($emails_per_hour / 20));

        global $wpdb;
        $queue_table = $wpdb->prefix . 'aima_email_queue';

        // Get pending emails ordered by priority and scheduled time
        $pending = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $queue_table
            WHERE status = 'queued'
            AND scheduled_for <= %s
            AND attempts < max_attempts
            ORDER BY priority DESC, scheduled_for ASC
            LIMIT %d",
            current_time('mysql'),
            $batch_size
        ));

        foreach ($pending as $queue_item) {
            $this->send_queued_email($queue_item);
        }

        return count($pending);
    }

    /**
     * Send individual queued email
     */
    private function send_queued_email($queue_item) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'aima_email_queue';

        // Update status to sending
        $wpdb->update(
            $queue_table,
            array('status' => 'sending', 'attempts' => $queue_item->attempts + 1),
            array('id' => $queue_item->id)
        );

        // Get offer and customer
        $offer = AIMA_Database::get_offer($queue_item->offer_id);
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aima_customers WHERE id = %d",
            $queue_item->customer_id
        ));

        if (!$offer || !$customer) {
            $wpdb->update(
                $queue_table,
                array('status' => 'failed', 'error_message' => 'Offer or customer not found'),
                array('id' => $queue_item->id)
            );
            return false;
        }

        // Check for personalized version
        $personalized = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aima_personalized_offers
            WHERE offer_id = %d AND customer_id = %d",
            $queue_item->offer_id,
            $queue_item->customer_id
        ));

        // Prepare offer data
        $offer_data = array(
            'headline' => $personalized ? $personalized->headline : $offer->name,
            'subheadline' => $personalized ? $personalized->subheadline : $offer->subject,
            'body' => $personalized ? $personalized->body : $offer->content,
            'banner_url' => $personalized ? $personalized->banner_url : $offer->banner_url,
            'products' => $personalized ? json_decode($personalized->products, true) : json_decode($offer->products, true),
            'coupon_code' => $offer->coupon_code,
            'discount_value' => $offer->discount_value,
            'discount_type' => $offer->discount_type,
            'cta_text' => 'Купить сейчас',
            'urgency' => '',
            'personalization_note' => ''
        );

        $customer_data = array(
            'first_name' => $customer->first_name,
            'email' => $customer->email
        );

        // Generate email content
        $generator = new AIMA_Offer_Generator();
        $email_content = $generator->generate_email_content($offer_data, $customer_data);

        // Add tracking pixel
        $tracking_url = add_query_arg(
            array(
                'aima_track' => 'open',
                'queue_id' => $queue_item->id
            ),
            home_url('/')
        );
        $email_content .= '<img src="' . esc_url($tracking_url) . '" width="1" height="1" />';

        // Replace unsubscribe URL
        $unsubscribe_url = add_query_arg(
            array(
                'aima_action' => 'unsubscribe',
                'customer_id' => $customer->id,
                'token' => wp_hash($customer->id)
            ),
            home_url('/')
        );
        $email_content = str_replace('{unsubscribe_url}', $unsubscribe_url, $email_content);

        // Send email
        $from_name = get_option('aima_email_from_name', get_bloginfo('name'));
        $from_email = get_option('aima_email_from_email', get_bloginfo('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );

        $sent = wp_mail(
            $customer->email,
            $offer->subject,
            $email_content,
            $headers
        );

        // Update queue status
        if ($sent) {
            $wpdb->update(
                $queue_table,
                array(
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ),
                array('id' => $queue_item->id)
            );

            // Update campaign recipient
            $wpdb->insert(
                $wpdb->prefix . 'aima_campaign_recipients',
                array(
                    'offer_id' => $queue_item->offer_id,
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                )
            );

            return true;
        } else {
            // Check if max attempts reached
            if ($queue_item->attempts + 1 >= $queue_item->max_attempts) {
                $status = 'failed';
            } else {
                $status = 'queued'; // Will retry
            }

            $wpdb->update(
                $queue_table,
                array(
                    'status' => $status,
                    'error_message' => 'Failed to send email'
                ),
                array('id' => $queue_item->id)
            );

            return false;
        }
    }

    /**
     * Add emails to queue
     */
    public function add_to_queue($offer_id, $customer_ids, $priority = 5) {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'aima_email_queue';
        $customers_table = $wpdb->prefix . 'aima_customers';

        // Calculate scheduled time based on priority
        $scheduled_for = current_time('mysql');

        foreach ($customer_ids as $customer_id) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $customers_table WHERE id = %d",
                $customer_id
            ));

            if (!$customer) {
                continue;
            }

            // Check if not already in queue
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $queue_table
                WHERE offer_id = %d AND customer_id = %d AND status IN ('queued', 'sending')",
                $offer_id,
                $customer_id
            ));

            if ($exists) {
                continue;
            }

            $wpdb->insert(
                $queue_table,
                array(
                    'offer_id' => $offer_id,
                    'customer_id' => $customer_id,
                    'email' => $customer->email,
                    'priority' => $priority,
                    'scheduled_for' => $scheduled_for,
                    'status' => 'queued'
                ),
                array('%d', '%d', '%s', '%d', '%s', '%s')
            );
        }
    }

    /**
     * Get queue statistics
     */
    public function get_queue_stats() {
        global $wpdb;
        $queue_table = $wpdb->prefix . 'aima_email_queue';

        return array(
            'queued' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'queued'"),
            'sending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'sending'"),
            'sent' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'sent' AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'failed'")
        );
    }
}
