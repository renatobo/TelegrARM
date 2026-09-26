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

    public function test_named_entities_are_double_encoded_for_telegram(): void {
        $this->assertSame('a &amp;nbsp; b &amp;copy;', TelegrARM_Message_Formatter::escape('a &nbsp; b &copy;'));
        $this->assertSame('O&#039;Brien &quot;Bob&quot;', TelegrARM_Message_Formatter::escape('O\'Brien "Bob"'));
    }

    public function test_invalid_utf8_is_substituted_not_emptied(): void {
        $this->assertNotSame('', TelegrARM_Message_Formatter::escape("abc\xff"));
    }

    public function test_flatten_joins_serialized_and_plain_lists(): void {
        $this->assertSame('Rides, Events', TelegrARM_Message_Formatter::flatten(serialize(array('Rides', 'Events'))));
        $this->assertSame('Rides, Events', TelegrARM_Message_Formatter::flatten(array('Rides', '', 'Events')));
        $this->assertSame('42', TelegrARM_Message_Formatter::flatten(42));
    }

    public function test_flatten_never_instantiates_serialized_objects(): void {
        $this->assertSame('', TelegrARM_Message_Formatter::flatten(serialize(new ArrayObject(array('x')))));
        $this->assertSame('', TelegrARM_Message_Formatter::flatten(new stdClass()));
    }

    public function test_checkbox_field_renders_as_a_list(): void {
        $line = TelegrARM_Message_Formatter::profile_line('interests', array('Rides', 'Events'), array('interests' => 'Interests'));

        $this->assertSame("Interests: Rides, Events\n", $line);
    }

    public function test_avatar_link_requires_a_host(): void {
        $map = array('avatar' => 'Avatar');

        $this->assertSame(
            "Avatar: <a href=\"https://example.com/a.jpg\">https://example.com/a.jpg</a>\n",
            TelegrARM_Message_Formatter::profile_line('avatar', 'example.com/a.jpg', $map)
        );
        $this->assertSame("Avatar: javascript:alert(1)\n", TelegrARM_Message_Formatter::profile_line('avatar', 'javascript:alert(1)', $map));
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

    public function test_lines_follow_the_mapping_order_not_the_data_order(): void {
        $message = TelegrARM_Message_Formatter::profile_message(
            'Profile',
            array('last_name' => 'Hopper', 'interests' => 'Track', 'first_name' => 'Grace'),
            array('first_name' => 'First Name', 'last_name' => 'Last Name', 'interests' => 'Interests')
        );

        $this->assertSame("<b>Profile</b>\nFirst Name: Grace\nLast Name: Hopper\nInterests: Track\n", $message);
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
