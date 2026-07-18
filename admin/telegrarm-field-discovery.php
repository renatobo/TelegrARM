<?php
/**
 * Discover ARMember profile fields for the settings mapping builder.
 *
 * @package TelegrARM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Humanize a meta key into a label suggestion.
 *
 * @param string $key Meta key.
 * @return string
 */
function telegrarm_humanize_metakey_label( $key ) {
	$label = trim( (string) $key );

	if ( '' === $label ) {
		return '';
	}

	$label = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $label );
	$label = str_replace( array( '_', '-' ), ' ', $label );
	$label = preg_replace( '/\s+/', ' ', $label );

	return ucwords( trim( (string) $label ) );
}

/**
 * Determine whether a discovered ARMember field is safe and useful to expose in
 * the mapping builder.
 *
 * @param string               $meta_key      Candidate meta key.
 * @param array<string, mixed> $field_options Optional ARMember field options.
 * @return bool
 */
function telegrarm_should_include_discovered_metakey( $meta_key, array $field_options = array() ) {
	$meta_key = trim( (string) $meta_key );

	if ( '' === $meta_key ) {
		return false;
	}

	$excluded_meta_keys = array(
		'user_pass',
		'password',
		'confirm_password',
		'confirm_user_pass',
		'repeat_password',
		'repeat_user_pass',
		'session_tokens',
		'wp_capabilities',
		'wp_user_level',
	);

	if ( in_array( $meta_key, $excluded_meta_keys, true ) ) {
		return false;
	}

	if ( preg_match( '/(?:pass(?:word)?|secret|token|auth|credential|recovery|private[_-]?key|api[_-]?key)/i', $meta_key ) ) {
		return false;
	}

	if ( 0 === strpos( $meta_key, '_' ) ) {
		return false;
	}

	if ( 0 === strpos( $meta_key, 'arm_' ) && ! in_array( $meta_key, array( 'arm_social_field_instagram' ), true ) ) {
		return false;
	}

	if ( ! empty( $field_options ) ) {
		$field_type = isset( $field_options['type'] ) && is_scalar( $field_options['type'] ) ? trim( (string) $field_options['type'] ) : '';

		if ( in_array( $field_type, array( 'hidden', 'html', 'password', 'section', 'social_fields', 'submit' ), true ) ) {
			return false;
		}

		if ( ! empty( $field_options['is_hidden'] ) || ! empty( $field_options['hidden'] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Return common ARMember and WordPress profile meta keys that are useful in TelegrARM.
 *
 * @return array<int, string>
 */
function telegrarm_get_common_armember_metakeys() {
	return array(
		'first_name',
		'last_name',
		'user_email',
		'user_login',
		'display_name',
		'nickname',
		'user_url',
		'description',
		'arm_social_field_instagram',
		'avatar',
	);
}

/**
 * Get the active ARMember runtime object if the plugin is loaded.
 *
 * @return object|null
 */
function telegrarm_get_armember_runtime_object() {
	if ( isset( $GLOBALS['ARMemberLite'] ) && is_object( $GLOBALS['ARMemberLite'] ) ) {
		return $GLOBALS['ARMemberLite'];
	}

	if ( isset( $GLOBALS['ARMember'] ) && is_object( $GLOBALS['ARMember'] ) ) {
		return $GLOBALS['ARMember'];
	}

	return null;
}

/**
 * Resolve an ARMember table name, falling back to the current WordPress prefix.
 *
 * @param string $property      Runtime property name.
 * @param string $fallback_name Table suffix without the WordPress prefix.
 * @return string
 */
function telegrarm_get_armember_table_name( $property, $fallback_name ) {
	$runtime = telegrarm_get_armember_runtime_object();

	if ( is_object( $runtime ) && isset( $runtime->{$property} ) && is_string( $runtime->{$property} ) && '' !== $runtime->{$property} ) {
		return $runtime->{$property};
	}

	global $wpdb;

	return $wpdb->prefix . $fallback_name;
}

/**
 * Normalize a discovered field entry into a consistent shape.
 *
 * @param string $key    Meta key.
 * @param string $label  Suggested label.
 * @param string $source Discovery source.
 * @return array{key:string,label:string,source:string}|null
 */
function telegrarm_build_discovered_metakey_item( $key, $label, $source ) {
	$key    = is_scalar( $key ) ? trim( (string) $key ) : '';
	$label  = is_scalar( $label ) ? trim( (string) $label ) : '';
	$source = is_scalar( $source ) ? trim( (string) $source ) : 'discovered';

	if ( '' === $key ) {
		return null;
	}

	if ( '' === $label ) {
		$label = telegrarm_humanize_metakey_label( $key );
	}

	return array(
		'key'    => $key,
		'label'  => $label,
		'source' => $source,
	);
}

/**
 * Merge a discovered field item into the result set, preserving the more
 * authoritative source when the same key appears multiple times.
 *
 * @param array<string, array{key:string,label:string,source:string}> $items Collected items keyed by meta key.
 * @param array{key:string,label:string,source:string}                $item  Item to merge.
 * @return void
 */
function telegrarm_merge_discovered_metakey_item( array &$items, array $item ) {
	if ( ! isset( $item['key'] ) || '' === $item['key'] ) {
		return;
	}

	$source_priority = array(
		'preset'     => 1,
		'form_field' => 2,
		'common'     => 3,
		'usermeta'   => 4,
		'fallback'   => 5,
		'discovered' => 6,
	);

	$key = $item['key'];

	if ( ! isset( $items[ $key ] ) ) {
		$items[ $key ] = $item;

		return;
	}

	$existing_source = isset( $source_priority[ $items[ $key ]['source'] ] ) ? $source_priority[ $items[ $key ]['source'] ] : 99;
	$new_source      = isset( $source_priority[ $item['source'] ] ) ? $source_priority[ $item['source'] ] : 99;

	if ( $new_source < $existing_source ) {
		$items[ $key ] = $item;

		return;
	}

	if ( '' === $items[ $key ]['label'] && '' !== $item['label'] ) {
		$items[ $key ]['label'] = $item['label'];
	}
}

/**
 * Extract ARMember preset field definitions from the arm_preset_form_fields option.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_preset_field_items() {
	$preset_form_fields = maybe_unserialize( get_option( 'arm_preset_form_fields', '' ) );

	if ( ! is_array( $preset_form_fields ) || empty( $preset_form_fields ) ) {
		return array();
	}

	$items = array();

	foreach ( $preset_form_fields as $group_name => $group_fields ) {
		if ( ! is_array( $group_fields ) ) {
			continue;
		}

		foreach ( $group_fields as $field_key => $field_value ) {
			if ( ! is_array( $field_value ) ) {
				continue;
			}

			$meta_key = isset( $field_value['meta_key'] ) && is_scalar( $field_value['meta_key'] ) ? trim( (string) $field_value['meta_key'] ) : '';
			$label    = isset( $field_value['label'] ) && is_scalar( $field_value['label'] ) ? trim( (string) $field_value['label'] ) : '';

			if ( '' === $meta_key && is_scalar( $field_key ) ) {
				$meta_key = trim( (string) $field_key );
			}

			if ( '' === $meta_key ) {
				continue;
			}

			if ( ! telegrarm_should_include_discovered_metakey( $meta_key, $field_value ) ) {
				continue;
			}

			$source = 'default' === $group_name ? 'preset' : 'preset';
			$item   = telegrarm_build_discovered_metakey_item( $meta_key, $label, $source );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}
	}

	return $items;
}

/**
 * Extract ARMember field definitions from the form field table.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_form_field_items() {
	global $wpdb;

	$table_name = telegrarm_get_armember_table_name( 'tbl_arm_form_field', 'arm_form_field' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Registry discovery is cached by telegrarm_get_discovered_armember_metakeys().
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );

	if ( $table_name !== $table_exists ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The table name comes from the trusted ARMember runtime and is verified above.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT arm_form_field_slug, arm_form_field_option, arm_form_field_status
			 FROM %i
			 WHERE arm_form_field_slug <> ''
			   AND arm_form_field_status != 2
			 ORDER BY arm_form_field_id ASC",
			$table_name
		),
		ARRAY_A
	);

	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return array();
	}

	$items = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$field_options = isset( $row['arm_form_field_option'] ) ? maybe_unserialize( $row['arm_form_field_option'] ) : array();
		$field_options = is_array( $field_options ) ? $field_options : array();

		$meta_key = isset( $field_options['meta_key'] ) && is_scalar( $field_options['meta_key'] ) ? trim( (string) $field_options['meta_key'] ) : '';
		$label    = isset( $field_options['label'] ) && is_scalar( $field_options['label'] ) ? trim( (string) $field_options['label'] ) : '';

		if ( '' === $meta_key && isset( $row['arm_form_field_slug'] ) && is_scalar( $row['arm_form_field_slug'] ) ) {
			$meta_key = trim( (string) $row['arm_form_field_slug'] );
		}

		if ( '' === $meta_key ) {
			continue;
		}

		if ( ! telegrarm_should_include_discovered_metakey( $meta_key, $field_options ) ) {
			continue;
		}

		$source = ( ! empty( $field_options['_builtin'] ) || ! empty( $field_options['default_field'] ) ) ? 'common' : 'form_field';
		$item   = telegrarm_build_discovered_metakey_item( $meta_key, $label, $source );

		if ( null !== $item ) {
			$items[] = $item;
		}
	}

	return $items;
}

/**
 * Collect likely ARMember user meta keys by scanning the WordPress usermeta table.
 *
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_armember_usermeta_items() {
	global $wpdb;

	$technical_user_meta_keys = array(
		'admin_color',
		'comment_shortcuts',
		'dismissed_wp_pointers',
		'locale',
		'manages',
		'rich_editing',
		'screen_layout_dashboard',
		'screen_layout_profile',
		'session_tokens',
		'show_admin_bar_front',
		'syntax_highlighting',
		'user_level',
		'user-settings',
		'user-settings-time',
		'wp_capabilities',
		'wp_user_level',
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback discovery is cached for ten minutes.
	$user_meta_keys = $wpdb->get_col(
		"SELECT DISTINCT meta_key
         FROM {$wpdb->usermeta}
         WHERE meta_key <> ''
           AND meta_key NOT LIKE '\\_%'
         ORDER BY meta_key ASC"
	);

	if ( ! is_array( $user_meta_keys ) || empty( $user_meta_keys ) ) {
		return array();
	}

	$items = array();

	foreach ( $user_meta_keys as $meta_key ) {
		if ( ! is_scalar( $meta_key ) ) {
			continue;
		}

		$meta_key = trim( (string) $meta_key );

		if ( '' === $meta_key || in_array( $meta_key, $technical_user_meta_keys, true ) ) {
			continue;
		}

		if ( ! telegrarm_should_include_discovered_metakey( $meta_key ) ) {
			continue;
		}

		$item = telegrarm_build_discovered_metakey_item( $meta_key, telegrarm_humanize_metakey_label( $meta_key ), 'usermeta' );

		if ( null !== $item ) {
			$items[] = $item;
		}
	}

	return $items;
}

/**
 * Discover likely ARMember meta keys from the current site.
 *
 * Discovery order:
 * 1. ARMember preset fields from `arm_preset_form_fields`
 * 2. ARMember form field registry from `tbl_arm_form_field`
 * 3. Fallback scan of public user meta keys
 *
 * @param bool $force_refresh Whether to bypass the transient cache.
 * @return array<int, array{key:string,label:string,source:string}>
 */
function telegrarm_get_discovered_armember_metakeys( $force_refresh = false ) {
	$cache_key = 'telegrarm_armember_metakeys';

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$items = array();

	$registry_items = array();

	foreach ( telegrarm_get_armember_preset_field_items() as $item ) {
		telegrarm_merge_discovered_metakey_item( $registry_items, $item );
	}

	foreach ( telegrarm_get_armember_form_field_items() as $item ) {
		telegrarm_merge_discovered_metakey_item( $registry_items, $item );
	}

	$items = $registry_items;

	foreach ( telegrarm_get_common_armember_metakeys() as $key ) {
		$item = telegrarm_build_discovered_metakey_item( $key, telegrarm_humanize_metakey_label( $key ), 'common' );

		if ( null !== $item ) {
			telegrarm_merge_discovered_metakey_item( $items, $item );
		}
	}

	if ( empty( $registry_items ) ) {
		foreach ( telegrarm_get_armember_usermeta_items() as $item ) {
			telegrarm_merge_discovered_metakey_item( $items, $item );
		}
	}

	$items = array_values( $items );

	usort(
		$items,
		static function ( $left, $right ) {
			return strcasecmp( $left['key'], $right['key'] );
		}
	);

	set_transient( $cache_key, $items, 10 * MINUTE_IN_SECONDS );

	return $items;
}

/**
 * AJAX endpoint that returns discovered ARMember meta keys.
 *
 * @return void
 */
function telegrarm_ajax_discover_arm_metakeys() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to discover ARMember fields.', 'telegrarm' ),
			),
			403
		);
	}

	check_ajax_referer( 'telegrarm_discover_arm_metakeys' );

	$force_refresh = isset( $_POST['refresh'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['refresh'] ) );
	$saved_mapping = telegrarm_get_arm_mapping();
	$items         = array();

	foreach ( telegrarm_get_discovered_armember_metakeys( $force_refresh ) as $item ) {
		if ( ! isset( $item['key'] ) || '' === $item['key'] ) {
			continue;
		}

		$is_selected = isset( $saved_mapping[ $item['key'] ] );

		$items[] = array(
			'key'        => $item['key'],
			'label'      => isset( $saved_mapping[ $item['key'] ] ) && is_scalar( $saved_mapping[ $item['key'] ] )
				? (string) $saved_mapping[ $item['key'] ]
				: $item['label'],
			'source'     => $item['source'],
			'isSelected' => $is_selected,
		);
	}

	wp_send_json_success(
		array(
			'count' => count( $items ),
			'items' => $items,
		)
	);
}
