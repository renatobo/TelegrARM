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

    public function test_channel_ids_are_strictly_validated(): void {
        $this->assertSame('-1001234567890', telegrarm_sanitize_channel_id('-1001234567890'));
        $this->assertSame('@valid_channel', telegrarm_sanitize_channel_id('@valid_channel'));
        $this->assertSame('', telegrarm_sanitize_channel_id('https://attacker.example'));
    }
}
