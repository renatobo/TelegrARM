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

    public function test_login_form_remember_me_checkbox_is_not_discoverable(): void {
        $this->assertFalse(telegrarm_should_include_discovered_metakey('rememberme', array('type' => 'rememberme')));
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

    /**
     * @dataProvider credentialKeyProvider
     */
    public function test_credential_keys_are_flagged_sensitive(string $key): void {
        $this->assertTrue(TelegrARM_Message_Formatter::is_sensitive_key($key));
    }

    public function credentialKeyProvider(): array {
        return array(
            'user_pass'           => array('user_pass'),
            'uppercase'           => array('USER_PASS'),
            'confirm password'    => array('confirm_password'),
            'session tokens'      => array('session_tokens'),
            'activation key'      => array('user_activation_key'),
            'capabilities'        => array('wp_capabilities'),
            'multisite caps'      => array('wp_2_capabilities'),
            'multisite level'     => array('wp_2_user_level'),
            'api key'             => array('stripe_api_key'),
        );
    }

    /**
     * @dataProvider publicKeyProvider
     */
    public function test_public_profile_keys_are_not_flagged(string $key): void {
        $this->assertFalse(TelegrARM_Message_Formatter::is_sensitive_key($key));
    }

    public function publicKeyProvider(): array {
        return array(
            'first_name' => array('first_name'),
            'user_email' => array('user_email'),
            'author bio' => array('author_bio'),
            'phone'      => array('text_t0cls'),
        );
    }

    public function test_mapping_sanitizer_drops_credential_keys(): void {
        $sanitized = telegrarm_arm_mapping_sanitize(
            '{"first_name":"First Name","user_pass":"Password","wp_capabilities":"Roles"}'
        );

        $this->assertSame(array('first_name' => 'First Name'), $sanitized);
    }

    public function test_mapped_credential_key_is_never_rendered(): void {
        $line = TelegrARM_Message_Formatter::profile_line('user_pass', 'hunter2', array('user_pass' => 'Password'));

        $this->assertSame('', $line);
    }

    public function test_successful_test_message_feedback_omits_the_fallback_description(): void {
        $ok = array('response' => array('code' => 200), 'body' => '{"ok":true,"result":{}}');
        $bad = array('response' => array('code' => 400), 'body' => '{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}');

        $this->assertStringNotContainsString('Telegram description', telegrarm_build_test_message_feedback('new-user', '-100123', $ok, true));
        $this->assertStringContainsString('Telegram description: Bad Request: chat not found', telegrarm_build_test_message_feedback('new-user', '-100123', $bad, false));
    }

    public function test_channel_ids_are_strictly_validated(): void {
        $this->assertSame('-1001234567890', telegrarm_sanitize_channel_id('-1001234567890'));
        $this->assertSame('@valid_channel', telegrarm_sanitize_channel_id('@valid_channel'));
        $this->assertSame('', telegrarm_sanitize_channel_id('https://attacker.example'));
    }
}
