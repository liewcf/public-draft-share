<?php
/**
 * Uninstall routine for Public Draft Share.
 *
 * @package PublicDraftShare
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup for a single site: delete meta and options.
 *
 * @return void
 */
function pds_uninstall_site() {
	// Remove all Public Draft Share post meta.
	if ( function_exists( 'delete_post_meta_by_key' ) ) {
		delete_post_meta_by_key( '_pds_token' );
		delete_post_meta_by_key( '_pds_expires' );
	}

	// Remove rewrite version option.
	if ( function_exists( 'delete_option' ) ) {
		delete_option( 'pds_rewrite_version' );
	}
}

// Handle single site or multisite network uninstall.
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$site_ids = function_exists( 'get_sites' ) ? get_sites( array( 'fields' => 'ids' ) ) : array();
	if ( $site_ids ) {
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			pds_uninstall_site();
			restore_current_blog();
		}
	} else {
		pds_uninstall_site();
	}
} else {
	pds_uninstall_site();
}
