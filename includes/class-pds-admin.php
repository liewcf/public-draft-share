<?php
namespace PDS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    private static $instance;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        // Legacy non-AJAX fallbacks (kept just in case)
        add_action( 'admin_post_pds_generate', [ $this, 'handle_generate' ] );
        add_action( 'admin_post_pds_disable', [ $this, 'handle_disable' ] );

        // AJAX handlers
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_pds_generate', [ $this, 'ajax_generate' ] );
        add_action( 'wp_ajax_pds_disable', [ $this, 'ajax_disable' ] );
    }

    public function enqueue( $hook ) : void {
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        // Styles
        wp_register_style(
            'pds-admin-css',
            PDS_PLUGIN_URL . 'assets/admin.css',
            [],
            PDS_VERSION
        );
        wp_enqueue_style( 'pds-admin-css' );

        wp_register_script(
            'pds-admin',
            PDS_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            PDS_VERSION,
            true
        );
        wp_localize_script( 'pds-admin', 'PDS', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pds_ajax' ),
            'i18n'    => [
                'creating'   => __( 'Creating…', 'public-draft-share' ),
                'regenerating'=> __( 'Regenerating…', 'public-draft-share' ),
                'disabled'   => __( 'Link disabled.', 'public-draft-share' ),
                'error'      => __( 'Something went wrong. Please try again.', 'public-draft-share' ),
                'copied'     => __( 'Copied!', 'public-draft-share' ),
            ],
        ] );
        wp_enqueue_script( 'pds-admin' );
    }

    public function add_meta_box() : void {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        unset( $post_types['attachment'] );
        foreach ( $post_types as $pt ) {
            add_meta_box( 'pds_meta', __( 'Public Draft Share', 'public-draft-share' ), [ $this, 'render_meta_box' ], $pt, 'side', 'high' );
        }
    }

    public function render_meta_box( \WP_Post $post ) : void {
        // Only relevant for non-published content
        if ( 'publish' === $post->post_status ) {
            echo '<p>' . esc_html__( 'This content is already published.', 'public-draft-share' ) . '</p>';
            return;
        }

        $core    = Core::instance();
        $token   = get_post_meta( $post->ID, Core::META_TOKEN, true );
        $expires = (int) get_post_meta( $post->ID, Core::META_EXPIRES, true );
        $link    = $core->get_share_url( $post->ID );

        wp_nonce_field( 'pds_meta_action', 'pds_nonce' );

        echo '<div class="pds-box">';
        if ( $token && $link ) {
            echo '<p class="pds-head"><strong>' . esc_html__( 'Shareable link', 'public-draft-share' ) . '</strong></p>';
            echo '<p class="pds-link-wrap"><input type="text" class="widefat pds-link" readonly value="' . esc_attr( $link ) . '" /></p>';
            if ( $expires ) {
                echo '<p class="pds-expires">' . esc_html__( 'Expires', 'public-draft-share' ) . ': ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires ) ) . '</p>';
            } else {
                echo '<p class="pds-expires">' . esc_html__( 'Expires', 'public-draft-share' ) . ': ' . esc_html__( 'Never', 'public-draft-share' ) . '</p>';
            }

            echo '<div class="pds-actions">';
            echo '<button type="button" class="button button-link pds-btn pds-copy" data-post="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Copy', 'public-draft-share' ) . '</button> ';
            echo '<button type="button" class="button button-link-delete pds-btn pds-disable" data-post="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Disable', 'public-draft-share' ) . '</button>';
            echo '</div>';
        } else {
            echo '<p class="pds-desc">' . esc_html__( 'Generate a secure link so anyone can view this draft without logging in.', 'public-draft-share' ) . '</p>';
            echo '<p class="pds-create-wrap"><label for="pds-expiry-' . esc_attr( $post->ID ) . '">' . esc_html__( 'Expires in', 'public-draft-share' ) . ' </label>';
            echo '<select id="pds-expiry-' . esc_attr( $post->ID ) . '" class="pds-expiry">';
            foreach ( [ 1, 3, 7, 14, 30, 0 ] as $d ) {
                $label = $d ? sprintf( _n( '%d day', '%d days', $d, 'public-draft-share' ), $d ) : __( 'Never', 'public-draft-share' );
                $selected = ( 7 === $d ) ? ' selected' : '';
                echo '<option value="' . esc_attr( $d ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></p>';
            echo '<button type="button" class="button button-primary pds-btn pds-create" data-post="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Create Link', 'public-draft-share' ) . '</button>';
        }
        echo '</div>'; // .pds-box
    }

    public function handle_generate() : void {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'public-draft-share' ) );
        }
        check_admin_referer( 'pds_generate_' . $post_id );

        $days  = isset( $_POST['expiry_days'] ) ? intval( $_POST['expiry_days'] ) : 7;
        $exp   = $days > 0 ? ( time() + DAY_IN_SECONDS * $days ) : 0;

        Core::instance()->set_share_link( $post_id, $exp );

        wp_safe_redirect( get_edit_post_link( $post_id, 'redirect' ) . '&pds=enabled' );
        exit;
    }

    public function handle_disable() : void {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Permission denied.', 'public-draft-share' ) );
        }
        check_admin_referer( 'pds_disable_' . $post_id );

        Core::instance()->disable_share_link( $post_id );

        wp_safe_redirect( get_edit_post_link( $post_id, 'redirect' ) . '&pds=disabled' );
        exit;
    }

    // ===== AJAX =====
    public function ajax_generate() : void {
        check_ajax_referer( 'pds_ajax', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $days    = isset( $_POST['expiry_days'] ) ? intval( $_POST['expiry_days'] ) : 7;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'public-draft-share' ) ], 403 );
        }

        $exp = $days > 0 ? ( time() + DAY_IN_SECONDS * $days ) : 0;

        Core::instance()->set_share_link( $post_id, $exp );

        $link    = Core::instance()->get_share_url( $post_id );
        $expires = (int) get_post_meta( $post_id, Core::META_EXPIRES, true );
        $expires_h = $expires ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires ) : __( 'Never', 'public-draft-share' );

        wp_send_json_success( [
            'link'       => $link,
            'expires'    => $expires,
            'expires_h'  => $expires_h,
        ] );
    }

    public function ajax_disable() : void {
        check_ajax_referer( 'pds_ajax', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'public-draft-share' ) ], 403 );
        }

        Core::instance()->disable_share_link( $post_id );

        wp_send_json_success( [ 'disabled' => true ] );
    }
}
