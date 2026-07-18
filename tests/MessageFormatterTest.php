<?php

use PHPUnit\Framework\TestCase;

final class MessageFormatterTest extends TestCase {
    public function test_unmapped_special_fields_are_excluded(): void {
        $this->assertSame('', TelegrARM_Message_Formatter::profile_line('avatar', 'example.com/avatar.jpg', array()));
        $this->assertSame('', TelegrARM_Message_Formatter::profile_line('arm_social_field_instagram', 'example', array()));
    }

    public function test_mapped_text_is_escaped_for_telegram_html(): void {
        $line = TelegrARM_Message_Formatter::profile_line('first_name', '<script>alert(1)</script>', array('first_name' => 'First & Name'));

        $this->assertSame("First &amp; Name: &lt;script&gt;alert(1)&lt;/script&gt;\n", $line);
    }

    public function test_instagram_username_is_allowlisted(): void {
        $line = TelegrARM_Message_Formatter::profile_line(
            'arm_social_field_instagram',
            'bad\" onclick=alert(1)',
            array('arm_social_field_instagram' => 'Instagram')
        );

        $this->assertSame(
            "Instagram: <a href=\"https://instagram.com/badonclickalert1\">@badonclickalert1</a>\n",
            $line
        );
    }

    public function test_messages_are_bounded_below_the_telegram_limit(): void {
        $message = TelegrARM_Message_Formatter::profile_message(
            'Profile',
            array(
                'field_1' => str_repeat('a', 1500),
                'field_2' => str_repeat('b', 1500),
                'field_3' => str_repeat('c', 1500),
                'field_4' => str_repeat('d', 1500),
                'field_5' => str_repeat('e', 1500),
            ),
            array(
                'field_1' => 'Field 1',
                'field_2' => 'Field 2',
                'field_3' => 'Field 3',
                'field_4' => 'Field 4',
                'field_5' => 'Field 5',
            )
        );

        $this->assertLessThanOrEqual(TelegrARM_Message_Formatter::SAFE_TEXT_LIMIT, mb_strlen($message));
        $this->assertStringContainsString('Additional mapped fields were omitted', $message);
    }
}
