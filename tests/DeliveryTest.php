<?php

use PHPUnit\Framework\TestCase;

final class DeliveryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['telegrarm_test_options'] = array(
            'telegram_bot_api_token'      => '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
            'telegram_channel_id_updates' => '-1001234567890',
            'telegrarm_arm_mapping'       => array('first_name' => 'First Name'),
        );
        $GLOBALS['telegrarm_test_transients'] = array();
        $GLOBALS['telegrarm_test_scheduled_events'] = array();
        $GLOBALS['telegrarm_test_remote_requests'] = array();
        $GLOBALS['telegrarm_test_schedule_refused'] = false;
        $GLOBALS['telegrarm_test_cleared_hooks'] = array();
        $GLOBALS['telegrarm_test_actions'] = array();
        $GLOBALS['telegrarm_test_user_meta'] = array();
        $GLOBALS['telegrarm_test_users'] = array();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function queued_ticket_and_payload(): array {
        $ticket = $GLOBALS['telegrarm_test_scheduled_events'][0]['args'][0];
        $record = $GLOBALS['telegrarm_test_options'][$ticket];

        return array($ticket, $record['payload']);
    }

    public function test_profile_hook_queues_without_blocking_on_http(): void {
        telegrarm_profile_update(42, array('first_name' => 'Renato', 'secret' => 'not sent'));

        $this->assertCount(1, $GLOBALS['telegrarm_test_scheduled_events']);
        $this->assertSame(TelegrARM_Delivery_Queue::HOOK, $GLOBALS['telegrarm_test_scheduled_events'][0]['hook']);
        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);

        list($ticket, $payload) = $this->queued_ticket_and_payload();
        $this->assertIsString($ticket);
        $this->assertStringStartsWith(TelegrARM_Delivery_Queue::TICKET_PREFIX, $ticket);

        $this->assertStringContainsString('First Name: Renato', $payload['body']['text']);
        $this->assertStringNotContainsString('not sent', $payload['body']['text']);
        $this->assertArrayNotHasKey('chat_id', $payload['body']);
    }

    public function test_cron_arguments_never_carry_member_data(): void {
        telegrarm_profile_update(42, array('first_name' => 'Renato'));

        $args = $GLOBALS['telegrarm_test_scheduled_events'][0]['args'];

        $this->assertStringNotContainsString('Renato', wp_json_encode($args));
    }

    public function test_pacing_defers_a_second_delivery_to_the_next_slot(): void {
        $channel_id = '-1001234567890';
        $last_send  = time();

        $GLOBALS['telegrarm_test_transients']['telegrarm_rate_' . md5($channel_id)] = $last_send;

        TelegrARM_Delivery_Queue::process(
            array(
                'method'  => 'sendMessage',
                'target'  => 'profile',
                'body'    => array('text' => 'paced', 'parse_mode' => 'HTML'),
                'attempt' => 0,
            )
        );

        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);
        $this->assertCount(1, $GLOBALS['telegrarm_test_scheduled_events']);
        $this->assertSame(
            $last_send + TelegrARM_Delivery_Queue::MIN_SEND_INTERVAL,
            $GLOBALS['telegrarm_test_scheduled_events'][0]['timestamp']
        );
    }

    public function test_telegram_429_response_exposes_bounded_retry_delay(): void {
        $response = array(
            'response' => array('code' => 429),
            'body' => wp_json_encode(
                array(
                    'ok' => false,
                    'error_code' => 429,
                    'description' => 'Too Many Requests',
                    'parameters' => array('retry_after' => 999),
                )
            ),
        );

        $details = TelegrARM_Telegram_Client::response_details($response);

        $this->assertSame(429, $details['status_code']);
        $this->assertFalse($details['ok']);
        $this->assertSame(300, $details['retry_after']);
    }

    public function test_payload_survives_an_object_cache_flush(): void {
        telegrarm_profile_update(42, array('first_name' => 'Renato'));

        list($ticket) = $this->queued_ticket_and_payload();

        // Emulate a persistent object cache being flushed: transients vanish,
        // options do not.
        $GLOBALS['telegrarm_test_transients'] = array();

        TelegrARM_Delivery_Queue::process($ticket);

        $this->assertCount(1, $GLOBALS['telegrarm_test_remote_requests']);
        $this->assertArrayNotHasKey($ticket, $GLOBALS['telegrarm_test_options']);
    }

    public function test_expired_payload_is_dropped_and_not_sent(): void {
        telegrarm_profile_update(42, array('first_name' => 'Renato'));

        list($ticket) = $this->queued_ticket_and_payload();
        $GLOBALS['telegrarm_test_options'][$ticket]['expires'] = time() - 1;

        TelegrARM_Delivery_Queue::process($ticket);

        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);
        $this->assertArrayNotHasKey($ticket, $GLOBALS['telegrarm_test_options']);
    }

    public function test_refused_reschedule_keeps_the_payload_for_the_live_event(): void {
        $channel_id = '-1001234567890';
        $GLOBALS['telegrarm_test_transients']['telegrarm_rate_' . md5($channel_id)] = time();

        telegrarm_profile_update(42, array('first_name' => 'Renato'));

        list($ticket) = $this->queued_ticket_and_payload();

        // WordPress refuses the deferral as a duplicate of the live event.
        $GLOBALS['telegrarm_test_schedule_refused'] = true;

        TelegrARM_Delivery_Queue::process($ticket);

        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);
        $this->assertArrayHasKey($ticket, $GLOBALS['telegrarm_test_options']);
    }

    public function test_garbage_collection_reaps_only_expired_payloads(): void {
        $GLOBALS['wpdb'] = new TelegrARM_Test_wpdb();

        telegrarm_profile_update(42, array('first_name' => 'Renato'));
        $live = $GLOBALS['telegrarm_test_scheduled_events'][0]['args'][0];

        $stale = TelegrARM_Delivery_Queue::TICKET_PREFIX . 'stale';
        $GLOBALS['telegrarm_test_options'][$stale] = array(
            'payload' => array('method' => 'sendMessage', 'target' => 'profile', 'body' => array('text' => 'old')),
            'expires' => time() - 1,
        );

        TelegrARM_Delivery_Queue::collect_garbage();

        $this->assertArrayHasKey($live, $GLOBALS['telegrarm_test_options']);
        $this->assertArrayNotHasKey($stale, $GLOBALS['telegrarm_test_options']);
    }

    public function test_legacy_transient_payloads_still_deliver(): void {
        $ticket = TelegrARM_Delivery_Queue::TICKET_PREFIX . 'legacy';

        $GLOBALS['telegrarm_test_transients'][$ticket] = array(
            'method'  => 'sendMessage',
            'target'  => 'profile',
            'body'    => array('text' => 'legacy', 'parse_mode' => 'HTML'),
            'attempt' => 0,
        );

        TelegrARM_Delivery_Queue::process($ticket);

        $this->assertCount(1, $GLOBALS['telegrarm_test_remote_requests']);
    }

    public function test_failed_scheduling_releases_the_dedupe_marker(): void {
        $GLOBALS['telegrarm_test_schedule_refused'] = true;

        $body = array('text' => 'hello', 'parse_mode' => 'HTML');

        $this->assertFalse(TelegrARM_Delivery_Queue::enqueue('sendMessage', 'profile', $body));
        $this->assertSame(array(), $GLOBALS['telegrarm_test_transients']);

        $GLOBALS['telegrarm_test_schedule_refused'] = false;

        $this->assertTrue(TelegrARM_Delivery_Queue::enqueue('sendMessage', 'profile', $body));
        $this->assertCount(1, $GLOBALS['telegrarm_test_scheduled_events']);
    }

    public function test_register_does_not_schedule_on_every_request(): void {
        TelegrARM_Delivery_Queue::register();

        $this->assertCount(0, $GLOBALS['telegrarm_test_scheduled_events']);
    }

    public function test_activation_schedules_cleanup_even_at_the_same_version(): void {
        $GLOBALS['telegrarm_test_options']['telegrarm_version'] = BONO_TELEGRARM_VERSION;

        TelegrARM_Upgrader::activate();

        $this->assertNotFalse(wp_next_scheduled(TelegrARM_Delivery_Queue::GC_HOOK));
    }

    public function test_deactivation_clears_events_and_purges_payloads(): void {
        $GLOBALS['wpdb'] = new TelegrARM_Test_wpdb();

        telegrarm_profile_update(42, array('first_name' => 'Renato'));
        list($ticket) = $this->queued_ticket_and_payload();

        TelegrARM_Delivery_Queue::deactivate();

        $this->assertContains(TelegrARM_Delivery_Queue::GC_HOOK, $GLOBALS['telegrarm_test_cleared_hooks']);
        $this->assertContains(TelegrARM_Delivery_Queue::HOOK, $GLOBALS['telegrarm_test_cleared_hooks']);
        $this->assertArrayNotHasKey($ticket, $GLOBALS['telegrarm_test_options']);
    }

    public function test_successful_delivery_fires_the_sent_action(): void {
        $GLOBALS['telegrarm_test_remote_response'] = array(
            'response' => array('code' => 200),
            'body'     => wp_json_encode(array('ok' => true)),
        );

        TelegrARM_Delivery_Queue::process(
            array(
                'method'  => 'sendMessage',
                'target'  => 'profile',
                'body'    => array('text' => 'sent', 'parse_mode' => 'HTML'),
                'attempt' => 0,
            )
        );

        unset($GLOBALS['telegrarm_test_remote_response']);

        $this->assertSame('telegrarm_delivery_sent', $GLOBALS['telegrarm_test_actions'][0]['hook']);
    }

    public function test_contact_card_falls_back_to_login_when_first_name_is_empty(): void {
        $GLOBALS['telegrarm_test_options']['telegram_channel_id_newuser'] = '-1001234567890';
        $GLOBALS['telegrarm_test_options']['telegram_send_contact_during_registration'] = true;
        $GLOBALS['telegrarm_test_user_meta'][7] = array(
            'text_t0cls' => array('5551234567'),
            'first_name' => array(''),
        );

        telegrarm_after_new_user_notification((object) array('ID' => 7, 'user_login' => 'rider7'));

        $contact_ticket = $GLOBALS['telegrarm_test_scheduled_events'][1]['args'][0];
        $body           = $GLOBALS['telegrarm_test_options'][$contact_ticket]['payload']['body'];

        $this->assertSame('rider7', $body['first_name']);
        $this->assertSame('+15551234567', $body['phone_number']);
    }

    public function test_new_user_message_includes_users_table_fields_but_never_the_password(): void {
        $GLOBALS['telegrarm_test_options']['telegram_channel_id_newuser'] = '-1001234567890';
        $GLOBALS['telegrarm_test_options']['telegrarm_arm_mapping'] = array(
            'user_login' => 'Login',
            'user_email' => 'Email',
            'first_name' => 'First Name',
        );
        $GLOBALS['telegrarm_test_user_meta'][8] = array('first_name' => array('Ada'));
        $GLOBALS['telegrarm_test_users'][8] = array(
            'user_login'          => 'ada8',
            'user_email'          => 'ada@example.com',
            'user_pass'           => '$P$hash',
            'user_activation_key' => 'activation',
        );

        telegrarm_after_new_user_notification((object) array('ID' => 8, 'user_login' => 'ada8'));

        $meta = telegrarm_get_registration_meta(8);
        $this->assertArrayNotHasKey('user_pass', $meta);
        $this->assertArrayNotHasKey('user_activation_key', $meta);

        list(, $payload) = $this->queued_ticket_and_payload();
        $this->assertStringContainsString("Login: ada8\n", $payload['body']['text']);
        $this->assertStringContainsString("Email: ada@example.com\n", $payload['body']['text']);
        $this->assertStringContainsString("First Name: Ada\n", $payload['body']['text']);
    }

    public function test_member_profile_save_is_notified_through_the_activity_pair(): void {
        telegrarm_capture_profile_submission(42, array('first_name' => 'Renato', 'user_pass' => 'plain'), 0);
        telegrarm_notify_on_profile_activity(array('user_id' => 42, 'type' => 'update_profile'));

        $this->assertCount(1, $GLOBALS['telegrarm_test_scheduled_events']);

        list(, $payload) = $this->queued_ticket_and_payload();
        $this->assertStringContainsString('First Name: Renato', $payload['body']['text']);
        $this->assertStringNotContainsString('plain', $payload['body']['text']);
    }

    public function test_admin_edits_and_registrations_are_not_reported_as_profile_updates(): void {
        // Administrator save: flag 1, even if an activity follows.
        telegrarm_capture_profile_submission(42, array('first_name' => 'Admin edit'), 1);
        telegrarm_notify_on_profile_activity(array('user_id' => 42, 'type' => 'update_profile'));

        // Registration: meta is saved but a different activity is recorded.
        telegrarm_capture_profile_submission(43, array('first_name' => 'New member'), 0);
        telegrarm_notify_on_profile_activity(array('user_id' => 43, 'type' => 'new_subscription'));

        // An activity for another member must not release this member's data.
        telegrarm_notify_on_profile_activity(array('user_id' => 44, 'type' => 'update_profile'));

        $this->assertCount(0, $GLOBALS['telegrarm_test_scheduled_events']);
    }

    public function test_client_rejects_malformed_token_before_http(): void {
        $client = new TelegrARM_Telegram_Client('not-a-token');
        $result = $client->send('sendMessage', array('chat_id' => '1', 'text' => 'test'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('telegrarm_invalid_bot_token', $result->get_error_code());
        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);
    }
}
