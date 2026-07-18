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
    }

    public function test_profile_hook_queues_without_blocking_on_http(): void {
        telegrarm_profile_update(42, array('first_name' => 'Renato', 'secret' => 'not sent'));

        $this->assertCount(1, $GLOBALS['telegrarm_test_scheduled_events']);
        $this->assertSame(TelegrARM_Delivery_Queue::HOOK, $GLOBALS['telegrarm_test_scheduled_events'][0]['hook']);
        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);

        $payload = $GLOBALS['telegrarm_test_scheduled_events'][0]['args'][0];
        $this->assertStringContainsString('First Name: Renato', $payload['body']['text']);
        $this->assertStringNotContainsString('not sent', $payload['body']['text']);
        $this->assertArrayNotHasKey('chat_id', $payload['body']);
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

    public function test_client_rejects_malformed_token_before_http(): void {
        $client = new TelegrARM_Telegram_Client('not-a-token');
        $result = $client->send('sendMessage', array('chat_id' => '1', 'text' => 'test'));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('telegrarm_invalid_bot_token', $result->get_error_code());
        $this->assertCount(0, $GLOBALS['telegrarm_test_remote_requests']);
    }
}
