<?php
/**
 * Minimal WordPress stubs for Psalm.
 *
 * @package TelegrARM
 */

class WP_Error {}

/**
 * @param callable|string $callback
 */
function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
    return true;
}

/**
 * @param callable|string $callback
 */
function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): bool {
    return true;
}

/**
 * @param mixed $default_value
 * @return mixed
 */
function get_option(string $option, $default_value = false) {
    return $default_value;
}

function delete_option(string $option): bool {
    return true;
}

/**
 * @param array<string, mixed> $args
 */
function register_setting(string $option_group, string $option_name, array $args = array()): void {}

function settings_fields(string $option_group): void {}

function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void {}

function esc_html(string $text): string {
    return $text;
}

function esc_attr(string $text): string {
    return $text;
}

function esc_url(string $url): string {
    return $url;
}

function sanitize_text_field(string $str): string {
    return $str;
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_post(string $url, array $args = array()) {
    return array();
}

/**
 * @param mixed $thing
 * @psalm-assert-if-true WP_Error $thing
 * @psalm-assert-if-false array<string, mixed> $thing
 */
function is_wp_error($thing): bool {
    return $thing instanceof WP_Error;
}

function admin_url(string $path = '', string $scheme = 'admin'): string {
    return $path;
}

function current_user_can(string $capability): bool {
    return true;
}

function plugin_basename(string $file): string {
    return $file;
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return $path;
}

function load_plugin_textdomain(string $domain, bool $deprecated = false, string $plugin_rel_path = ''): bool {
    return true;
}

function __(string $text, string $domain = 'default'): string {
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string {
    return $text;
}

function esc_html_e(string $text, string $domain = 'default'): void {}

function esc_attr__(string $text, string $domain = 'default'): string {
    return $text;
}

function submit_button(?string $text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true): void {}

/**
 * @param mixed $checked
 * @param mixed $current
 */
function checked($checked, $current = true, bool $display = true): string {
    return $checked === $current ? 'checked="checked"' : '';
}

function esc_textarea(string $text): string {
    return $text;
}

/**
 * @param mixed $value
 */
function wp_json_encode($value, int $flags = 0, int $depth = 512): string|false {
    return '';
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_response_code($response): int|string {
    return 200;
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_body($response): string {
    return '';
}

function wp_http_validate_url(string $url): string|false {
    return $url;
}

/**
 * @param mixed $data
 * @return mixed
 */
function maybe_unserialize($data) {
    return $data;
}

/**
 * @return array<string, mixed>
 */
function get_user_meta(int $user_id, string $key = '', bool $single = false): array {
    return array();
}

/**
 * @param string|array<string, mixed> $value
 * @return string|array<string, mixed>
 */
function wp_unslash($value) {
    return $value;
}

function settings_errors(string $setting = '', bool $sanitize = false, bool $hide_on_update = false): void {}

function add_options_page(
    string $page_title,
    string $menu_title,
    string $capability,
    string $menu_slug,
    callable|string $callback = ''
): string|false {
    return $menu_slug;
}
