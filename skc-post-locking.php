<?php
/*
Plugin Name: Post Locking for Frontend
Plugin URI: https://github.com/sc0ttkclark/wp-post-locking
Description: Drop-in Post Locking for the frontend of WordPress
Version: 0.1
Author: Scott Kingsley Clark
Author URI: https://www.scottkclark.com/
Text Domain: skc-post-locking
GitHub Plugin URI: https://github.com/sc0ttkclark/wp-post-locking

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Add display name / role for post locking dialogs.
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 *
 * @return array The Heartbeat response.
 */
function skc_post_lock_add_display_name_role( $response, $data, $screen_id ) {
	// Handle post locking on singular.
	if ( ! empty( $response['wp-refresh-post-lock']['lock_error'] ) ) {
		$response_item = $response['wp-refresh-post-lock']['lock_error'];

		$post_id = 0;

		if ( ! empty( $data['wp-refresh-post-lock']['post_id'] ) ) {
			$post_id = absint( $data['wp-refresh-post-lock']['post_id'] );
		}

		$response['wp-refresh-post-lock']['lock_error'] = skc_post_lock_add_display_name_role_to_response( $response_item, $post_id );
	}

	// Handle post locking notices on list.
	if ( ! empty( $response['wp-check-locked-posts'] ) && is_array( $response['wp-check-locked-posts'] ) ) {
		foreach ( $response['wp-check-locked-posts'] as $key => $response_item ) {
			$post_id = absint( substr( $key, 5 ) );

			$response['wp-check-locked-posts'][ $key ] = skc_post_lock_add_display_name_role_to_response( $response_item, $post_id );
		}
	}

	return $response;

}

add_filter( 'heartbeat_received', 'skc_post_lock_add_display_name_role', 12, 3 );

/**
 * Add display name and role to responses.
 *
 * @param array $response_item Response item.
 * @param int   $post_id       Post ID.
 *
 * @return array Response item with display_name and role set.
 */
function skc_post_lock_add_display_name_role_to_response( $response_item, $post_id ) {

	// Set empty defaults.
	$response_item['display_name'] = '';
	$response_item['role']         = '';

	if ( ! $post_id ) {
		return $response_item;
	}

	$user_id = wp_check_post_lock( $post_id );

	if ( ! $user_id ) {
		return $response_item;
	}

	$display_name_role = skc_post_lock_get_display_name_role( $user_id );

	if ( ! $display_name_role ) {
		return $response_item;
	}

	$role = '';

	if ( ! empty( $display_name_role['role'] ) ) {
		$role = sprintf( '(%s)', $display_name_role['role'] );
	}

	$response_item['display_name'] = esc_html( $display_name_role['display_name'] );
	$response_item['role']         = esc_html( $role );

	return $response_item;

}

/**
 * Get Display Name / Role text from user.
 *
 * @param int $user_id User ID.
 *
 * @return array|false Display name and role of user, false if user not found.
 */
function skc_post_lock_get_display_name_role( $user_id ) {

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return false;
	}

	$roles = array();

	// Loop through the roles and convert them to labels.
	foreach ( $user->roles as $role ) {
		$roles[] = ucwords( str_replace( array( '-', '_' ), ' ', $role ) );
	}

	// Combine multiple roles into one string.
	$role_text = implode( ', ', $roles );

	$display_name_role = array(
		'display_name' => $user->display_name,
		'role'         => $role_text,
	);

	/**
	 * Filter the display name and role of user who has locked the post.
	 *
	 * @param array $display_name_role Display name and role of user.
	 * @param int   $user_id           User ID.
	 *
	 * @since 1.0.0
	 */
	$display_name_role = apply_filters( 'skc_post_locking_display_name_role', $display_name_role, $user_id );

	return $display_name_role;

}

/**
 * Get notice text if a post is locked.
 *
 * @param int $post_id Post ID.
 *
 * @return array|false Display Name / Role if post is locked, false if it is not locked.
 */
function skc_post_lock_get_display_name_role_from_post( $post_id ) {

	// Include necessary files.
	require_once ABSPATH . 'wp-admin/includes/post.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';

	/**
	 * Check if the post is locked.
	 *
	 * @param int|string $post_id Post ID.
	 *
	 * @return string|false User ID who is currently editing or false if the post is not locked.
	 */
	$user_id = wp_check_post_lock( $post_id );

	if ( ! $user_id ) {
		return false;
	}

	return skc_post_lock_get_display_name_role( $user_id );

}

/**
 * Init the post locking JS and include the necessary functions.
 */
function skc_post_lock_frontend_init() {

	wp_register_script( 'skc-post-locking', plugins_url( 'js/skc-post-locking.js', __FILE__ ), array( 'jquery' ), '1.0', true );
	wp_enqueue_script( 'skc-post-locking' );

	wp_enqueue_script( 'heartbeat', '', array(), false, true );

	// Include necessary files.
	require_once ABSPATH . 'wp-admin/includes/post.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/admin-filters.php';

}

/**
 * Render the post locking frontend notice.
 *
 * @param int  $post_id               Post ID.
 * @param bool $set_lock_if_logged_in Whether to set the lock as active if the user is logged in.
 *
 * @return bool Whether the post is currenty locked by another user.
 */
function skc_post_lock_frontend_notice( $post_id, $set_lock_if_logged_in = true ) {

	// Init what we need.
	skc_post_lock_frontend_init();

	// Get the display name / role for post lock (if there is one).
	$display_name_role = skc_post_lock_get_display_name_role_from_post( $post_id );

	$hidden = 'hidden';

	$is_locked = false;

	$active_post_lock = array();

	if ( false !== $display_name_role ) {
		$hidden = '';

		$is_locked = true;
	} else {
		$display_name_role = array(
			'display_name' => '',
			'role'         => '',
		);

		if ( is_user_logged_in() && $set_lock_if_logged_in ) {
			$active_post_lock = wp_set_post_lock( $post_id );
		}
	}
	?>
	<div id="skc_post_locking_dialog" class="<?php echo esc_attr( $hidden ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
		<?php skc_post_lock_notice_text( $display_name_role ); ?>
	</div>

	<input type="hidden" id="skc_post_locking_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'heartbeat-nonce' ) ); ?>" />
	<input type="hidden" id="skc_post_locking_post_id" value="<?php echo esc_attr( $post_id ); ?>" />
	<input type="hidden" id="skc_post_locking_active" value="<?php echo esc_attr( implode( ':', $active_post_lock ) ); ?>" />
	<?php

	return $is_locked;

}

/**
 * Render the post locking frontend list notice.
 *
 * @param int $post_id Post ID.
 *
 * @return bool Whether the post is currenty locked by another user.
 */
function skc_post_lock_frontend_list_notice( $post_id ) {

	// Init what we need.
	skc_post_lock_frontend_init();

	// Get the display name / role for post lock (if there is one).
	$display_name_role = skc_post_lock_get_display_name_role_from_post( $post_id );

	$hidden = ' hidden';

	$is_locked = false;

	if ( false !== $display_name_role ) {
		$hidden = '';

		$is_locked = true;
	}
	?>
	<div class="skc-post-locking-list-dialog<?php echo esc_attr( $hidden ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>">
		<?php skc_post_lock_notice_text( $display_name_role ); ?>
	</div>
	<?php

	return $is_locked;

}

/**
 * Render frontend notice text.
 *
 * @param array $display_name_role Display Name / Role information.
 */
function skc_post_lock_notice_text( $display_name_role ) {

	$role = '';

	if ( ! empty( $display_name_role['role'] ) ) {
		$role = sprintf( '(%s)', $display_name_role['role'] );
	}

	printf(
		'<span class="skc-post-locking-user-display-name">%1$s</span> <span class="skc-post-locking-user-role">%2$s</span> %3$s.',
		esc_html( $display_name_role['display_name'] ),
		esc_html( $role ),
		esc_html__( 'is editing', 'skc-post-locking' )
	);

}
