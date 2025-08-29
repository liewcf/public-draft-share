<?php
/**
 * Admin UI and AJAX for Public Draft Share.
 *
 * @package PublicDraftShare
 */

namespace PDS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller for meta box, assets, and AJAX endpoints.
 */
/**
 * Admin controller for meta box, assets, and AJAX endpoints.
 */
class Admin {
	/**
	 * Singleton instance.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register admin hooks.
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// AJAX handlers.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_pds_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_pds_disable', array( $this, 'ajax_disable' ) );
	}

	/**
	 * Enqueue admin assets on editor screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		// Styles.
		wp_register_style(
			'pds-admin-css',
			PDS_PLUGIN_URL . 'assets/admin.css',
			array(),
			PDS_VERSION
		);
		wp_enqueue_style( 'pds-admin-css' );

		wp_register_script(
			'pds-admin',
			PDS_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			PDS_VERSION,
			true
		);
		wp_localize_script(
			'pds-admin',
			'PDS',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pds_ajax' ),
				'i18n'    => array(
					'creating' => __( 'Creating…', 'public-draft-share' ),
					'disabled' => __( 'Link disabled.', 'public-draft-share' ),
					'error'    => __( 'Something went wrong. Please try again.', 'public-draft-share' ),
					'copied'   => __( 'Copied!', 'public-draft-share' ),
				),
			)
		);
		wp_enqueue_script( 'pds-admin' );
	}

	/**
	 * Register the sidebar meta box for public post types.
	 */
	public function add_meta_box(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		foreach ( $post_types as $pt ) {
			add_meta_box( 'pds_meta', __( 'Public Draft Share', 'public-draft-share' ), array( $this, 'render_meta_box' ), $pt, 'side', 'high' );
		}
	}

	/**
	 * Render the sidebar meta box in the post editor.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		// Only relevant for non-published content.
		if ( 'publish' === $post->post_status ) {
			echo '<p>' . esc_html__( 'This content is already published.', 'public-draft-share' ) . '</p>';
			return;
		}

		$core    = Core::instance();
		$token   = get_post_meta( $post->ID, Core::META_TOKEN, true );
		$expires = (int) get_post_meta( $post->ID, Core::META_EXPIRES, true );
		$link    = $core->get_share_url( $post->ID );

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
			foreach ( array( 1, 3, 7, 14, 30, 0 ) as $d ) {
				/* translators: %d: number of days until the shared link expires. */
				$label = $d ? sprintf( _n( '%d day', '%d days', $d, 'public-draft-share' ), $d ) : __( 'Never', 'public-draft-share' );
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( (string) $d ),
					selected( 7 === $d, true, false ),
					esc_html( $label )
				);
			}
			echo '</select></p>';
			echo '<button type="button" class="button button-primary pds-btn pds-create" data-post="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Create Link', 'public-draft-share' ) . '</button>';
		}
		echo '</div>'; // .pds-box
	}

	// ===== AJAX =====
	/**
	 * AJAX: Create or regenerate a public draft link.
	 */
	public function ajax_generate(): void {
		check_ajax_referer( 'pds_ajax', 'nonce' );

		$post_id      = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$days         = isset( $_POST['expiry_days'] ) ? intval( $_POST['expiry_days'] ) : 7;
		$allowed_days = array( 1, 3, 7, 14, 30, 0 );
		if ( ! in_array( $days, $allowed_days, true ) ) {
			$days = 7;
		}

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'public-draft-share' ) ), 403 );
		}

		// Do not allow creating/regenerating links for already published content.
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'This content is already published.', 'public-draft-share' ) ), 400 );
		}

		$exp = $days > 0 ? ( time() + DAY_IN_SECONDS * $days ) : 0;

		Core::instance()->set_share_link( $post_id, $exp );

		$link      = Core::instance()->get_share_url( $post_id );
		$expires   = (int) get_post_meta( $post_id, Core::META_EXPIRES, true );
		$expires_h = $expires ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires ) : __( 'Never', 'public-draft-share' );

		wp_send_json_success(
			array(
				'link'      => $link,
				'expires'   => $expires,
				'expires_h' => $expires_h,
			)
		);
	}

	/**
	 * AJAX: Disable a public draft link for a post.
	 */
	public function ajax_disable(): void {
		check_ajax_referer( 'pds_ajax', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'public-draft-share' ) ), 403 );
		}

		Core::instance()->disable_share_link( $post_id );

		wp_send_json_success( array( 'disabled' => true ) );
	}
}
