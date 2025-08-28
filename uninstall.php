<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove all Public Draft Share post meta.
if ( function_exists( 'delete_post_meta_by_key' ) ) {
    delete_post_meta_by_key( '_pds_token' );
    delete_post_meta_by_key( '_pds_expires' );
}

