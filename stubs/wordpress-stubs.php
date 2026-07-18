<?php
/**
 * Minimal WordPress stubs for Psalm.
 *
 * @package TelegrARM
 */

class WP_Error {
    private string|int $code;
    private string $message;

    public function __construct(string $code = '', string $message = '', $data = '') {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_message(string $code = ''): string {
        return $this->message;
    }

    public function get_error_code(): string|int {
        return $this->code;
    }
}

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

function apply_filters(string $hook_name, $value, ...$args) {
    return $value;
}

function register_activation_hook(string $file, $callback): void {}

function is_admin(): bool {
    return true;
}

/**
 * @param mixed $default_value
 * @return mixed
 */
function get_option(string $option, $default_value = false) {
    if (isset($GLOBALS['telegrarm_test_options']) && is_array($GLOBALS['telegrarm_test_options']) && array_key_exists($option, $GLOBALS['telegrarm_test_options'])) {
        return $GLOBALS['telegrarm_test_options'][$option];
    }

    return $default_value;
}

function add_option(string $option, $value = '', string $deprecated = '', bool $autoload = true): bool {
    return true;
}

function update_option(string $option, $value, ?bool $autoload = null): bool {
    return true;
}

function wp_set_option_autoload_values(array $options): array {
    return array();
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
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function sanitize_textarea_field(string $str): string {
    return $str;
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function wp_remote_post(string $url, array $args = array()) {
    $GLOBALS['telegrarm_test_remote_requests'][] = array('url' => $url, 'args' => $args);

    return isset($GLOBALS['telegrarm_test_remote_response']) ? $GLOBALS['telegrarm_test_remote_response'] : array();
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

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function current_user_can(string $capability): bool {
    return true;
}

/**
 * @return mixed
 */
function get_transient(string $transient) {
    return isset($GLOBALS['telegrarm_test_transients'][$transient]) ? $GLOBALS['telegrarm_test_transients'][$transient] : false;
}

/**
 * @param mixed $value
 */
function set_transient(string $transient, $value, int $expiration): bool {
    $GLOBALS['telegrarm_test_transients'][$transient] = $value;
    return true;
}

function wp_schedule_single_event(int $timestamp, string $hook, array $args = array(), bool $wp_error = false): bool|WP_Error {
    $GLOBALS['telegrarm_test_scheduled_events'][] = array('timestamp' => $timestamp, 'hook' => $hook, 'args' => $args);
    return true;
}

function wp_clear_scheduled_hook(string $hook, array $args = array(), bool $wp_error = false): int|false|WP_Error {
    return 0;
}

function plugin_basename(string $file): string {
    return $file;
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return $path;
}

function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all'): void {}

function wp_enqueue_script(string $handle, string $src = '', array $deps = array(), $ver = false, bool $in_footer = false): void {}

function wp_localize_script(string $handle, string $object_name, array $l10n): bool {
    return true;
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
    return json_encode($value, $flags, $depth);
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_response_code($response): int|string {
    return is_array($response) && isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
}

/**
 * @param array<string, mixed>|WP_Error $response
 */
function wp_remote_retrieve_body($response): string {
    return is_array($response) && isset($response['body']) && is_string($response['body']) ? $response['body'] : '';
}

function wp_http_validate_url(string $url): string|false {
    return $url;
}

function wp_create_nonce(string|int $action = -1): string {
    return '';
}

/**
 * @param string|false $query_arg
 * @return int|string|false
 */
function check_ajax_referer(string|int $action = -1, string|false $query_arg = false, bool $stop = true) {
    return 1;
}

/**
 * @param mixed $value
 */
function wp_send_json_success($value = null, ?int $status_code = null, int $flags = 0): void {}

/**
 * @param mixed $value
 */
function wp_send_json_error($value = null, ?int $status_code = null, int $flags = 0): void {}

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
    return isset($GLOBALS['telegrarm_test_user_meta'][$user_id]) ? $GLOBALS['telegrarm_test_user_meta'][$user_id] : array();
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
