<?php
/**
 * Email campaign module
 *
 * @package AIMarketingAssistant
 */

class AIMA_Email_Campaign {

    /**
     * Send campaign
     */
    public function send_campaign($offer_id) {
        $offer = AIMA_Database::get_offer($offer_id);

        if (!$offer || $offer->status === 'sent') {
            return array(
                'success' => false,
                'error' => __('Offer not found or already sent', 'ai-marketing-assistant')
            );
        }

        // Get segment customers
        $customers = AIMA_Database::get_segment_customers($offer->segment_id);

        if (empty($customers)) {
            return array(
                'success' => false,
                'error' => __('No customers in segment', 'ai-marketing-assistant')
            );
        }

        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        // Prepare recipients
        foreach ($customers as $customer) {
            $wpdb->insert(
                $recipients_table,
                array(
                    'offer_id' => $offer_id,
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                    'status' => 'pending'
                ),
                array('%d', '%d', '%s', '%s')
            );
        }

        // Send emails in batches
        $sent_count = $this->process_email_queue($offer_id);

        // Update offer status
        AIMA_Database::upsert_offer(
            array(
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
                'sent_count' => $sent_count
            ),
            $offer_id
        );

        return array(
            'success' => true,
            'sent_count' => $sent_count
        );
    }

    /**
     * Process email queue
     */
    public function process_email_queue($offer_id, $batch_size = 50) {
        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        // Get pending recipients
        $recipients = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $recipients_table
            WHERE offer_id = %d AND status = 'pending'
            LIMIT %d",
            $offer_id,
            $batch_size
        ));

        if (empty($recipients)) {
            return 0;
        }

        $offer = AIMA_Database::get_offer($offer_id);
        $offer_generator = new AIMA_Offer_Generator();

        // Prepare offer data
        $offer_data = array(
            'headline' => $offer->name,
            'subheadline' => $offer->subject,
            'body' => $offer->content,
            'cta_text' => __('Купить сейчас', 'ai-marketing-assistant'),
            'urgency' => '',
            'personalization_note' => '',
            'banner_concept' => '',
            'coupon_code' => $offer->coupon_code,
            'discount_value' => $offer->discount_value,
            'discount_type' => $offer->discount_type,
            'products' => json_decode($offer->products, true)
        );

        $sent_count = 0;
        $from_name = get_option('aima_email_from_name', get_bloginfo('name'));
        $from_email = get_option('aima_email_from_email', get_bloginfo('admin_email'));

        foreach ($recipients as $recipient) {
            // Get customer data for personalization
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aima_customers WHERE id = %d",
                $recipient->customer_id
            ));

            $customer_data = array(
                'first_name' => $customer->first_name ?? '',
                'email' => $recipient->email
            );

            // Generate email content
            $email_content = $offer_generator->generate_email_content($offer_data, $customer_data);

            // Add tracking pixel
            $tracking_url = add_query_arg(
                array(
                    'aima_track' => 'open',
                    'recipient_id' => $recipient->id
                ),
                home_url('/')
            );
            $email_content .= '<img src="' . esc_url($tracking_url) . '" width="1" height="1" />';

            // Replace unsubscribe URL
            $unsubscribe_url = add_query_arg(
                array(
                    'aima_action' => 'unsubscribe',
                    'customer_id' => $recipient->customer_id,
                    'token' => wp_hash($recipient->customer_id)
                ),
                home_url('/')
            );
            $email_content = str_replace('{unsubscribe_url}', $unsubscribe_url, $email_content);

            // Send email
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>'
            );

            $sent = wp_mail(
                $recipient->email,
                $offer->subject,
                $email_content,
                $headers
            );

            // Update recipient status
            if ($sent) {
                $wpdb->update(
                    $recipients_table,
                    array(
                        'status' => 'sent',
                        'sent_at' => current_time('mysql')
                    ),
                    array('id' => $recipient->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $sent_count++;
            } else {
                $wpdb->update(
                    $recipients_table,
                    array('status' => 'failed'),
                    array('id' => $recipient->id),
                    array('%s'),
                    array('%d')
                );
            }

            // Small delay to avoid overwhelming mail server
            usleep(100000); // 0.1 second
        }

        return $sent_count;
    }

    /**
     * Process scheduled campaigns
     */
    public function process_scheduled_campaigns() {
        $offers = AIMA_Database::get_offers('scheduled');

        foreach ($offers as $offer) {
            if (empty($offer->scheduled_at)) {
                continue;
            }

            $scheduled_time = strtotime($offer->scheduled_at);
            $current_time = current_time('timestamp');

            // Send if scheduled time has passed
            if ($scheduled_time <= $current_time) {
                $this->send_campaign($offer->id);
            }
        }
    }

    /**
     * Track email open
     */
    public function track_open($recipient_id) {
        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        $wpdb->update(
            $recipients_table,
            array(
                'status' => 'opened',
                'opened_at' => current_time('mysql')
            ),
            array('id' => $recipient_id),
            array('%s', '%s'),
            array('%d')
        );

        // Update offer opened count
        $recipient = $wpdb->get_row($wpdb->prepare(
            "SELECT offer_id FROM $recipients_table WHERE id = %d",
            $recipient_id
        ));

        if ($recipient) {
            $offers_table = $wpdb->prefix . 'aima_offers';
            $wpdb->query($wpdb->prepare(
                "UPDATE $offers_table SET opened_count = opened_count + 1 WHERE id = %d",
                $recipient->offer_id
            ));
        }
    }

    /**
     * Track email click
     */
    public function track_click($recipient_id) {
        global $wpdb;
        $recipients_table = $wpdb->prefix . 'aima_campaign_recipients';

        $wpdb->update(
            $recipients_table,
            array(
                'status' => 'clicked',
                'clicked_at' => current_time('mysql')
            ),
            array('id' => $recipient_id),
            array('%s', '%s'),
            array('%d')
        );

        // Update offer clicked count
        $recipient = $wpdb->get_row($wpdb->prepare(
            "SELECT offer_id FROM $recipients_table WHERE id = %d",
            $recipient_id
        ));

        if ($recipient) {
            $offers_table = $wpdb->prefix . 'aima_offers';
            $wpdb->query($wpdb->prepare(
                "UPDATE $offers_table SET clicked_count = clicked_count + 1 WHERE id = %d",
                $recipient->offer_id
            ));
        }
    }
}
