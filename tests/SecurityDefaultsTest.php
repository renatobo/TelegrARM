<?php

use PHPUnit\Framework\TestCase;

final class SecurityDefaultsTest extends TestCase {
    /**
     * @dataProvider sensitiveMetaKeyProvider
     */
    public function test_sensitive_meta_keys_are_never_discoverable(string $key): void {
        $this->assertFalse(telegrarm_should_include_discovered_metakey($key));
    }

    public function sensitiveMetaKeyProvider(): array {
        return array(
            'password'     => array('password'),
            'api token'    => array('custom_api_token'),
            'secret'       => array('membership_secret'),
            'private key'  => array('private_key'),
            'recovery key' => array('recovery_code'),
        );
    }

    public function test_valid_public_profile_key_remains_discoverable(): void {
        $this->assertTrue(telegrarm_should_include_discovered_metakey('first_name'));
    }

    public function test_bot_token_validation_never_falls_back_to_the_stored_token(): void {
        $GLOBALS['telegrarm_test_options'] = array(
            'telegram_bot_api_token' => '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
        );

        $this->assertSame('', telegrarm_validate_bot_token('not-a-token'));
        $this->assertSame('', telegrarm_validate_bot_token(''));
        $this->assertSame(
            '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
            telegrarm_validate_bot_token(' 123456789:abcdefghijklmnopqrstuvwxyzABCDE ')
        );
    }

    public function test_settings_sanitizer_retains_the_stored_token_on_invalid_input(): void {
        $GLOBALS['telegrarm_test_options'] = array(
            'telegram_bot_api_token' => '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
        );

        $this->assertSame(
            '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
            telegrarm_sanitize_bot_token('not-a-token')
        );
        $this->assertSame(
            '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
            telegrarm_sanitize_bot_token('')
        );
    }

    public function test_clear_request_is_ignored_without_a_verified_settings_nonce(): void {
        $GLOBALS['telegrarm_test_options'] = array(
            'telegram_bot_api_token' => '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
        );
        $GLOBALS['telegrarm_test_valid_nonces'] = array();

        $_POST = array(
            'option_page'                => 'telegrarm_settings_group',
            '_wpnonce'                   => 'forged',
            'telegrarm_clear_bot_token'  => '1',
        );

        $this->assertFalse(telegrarm_is_settings_save_request());
        $this->assertSame(
            '123456789:abcdefghijklmnopqrstuvwxyzABCDE',
            telegrarm_sanitize_bot_token('')
        );

        $GLOBALS['telegrarm_test_valid_nonces'] = array('valid' => true);
        $_POST['_wpnonce'] = 'valid';

        $this->assertTrue(telegrarm_is_settings_save_request());
        $this->assertSame('', telegrarm_sanitize_bot_token(''));

        $_POST = array();
    }

    public function test_channel_ids_are_strictly_validated(): void {
        $this->assertSame('-1001234567890', telegrarm_sanitize_channel_id('-1001234567890'));
        $this->assertSame('@valid_channel', telegrarm_sanitize_channel_id('@valid_channel'));
        $this->assertSame('', telegrarm_sanitize_channel_id('https://attacker.example'));
    }
}
