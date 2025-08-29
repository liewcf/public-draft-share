<?php
/**
 * Core logic for Public Draft Share: routing, validation, headers, and cache purge.
 *
 * @package PublicDraftShare
 */

namespace PDS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core controller.
 */
class Core {
	const META_TOKEN     = '_pds_token';
	const META_EXPIRES   = '_pds_expires';
	const QUERY_VAR      = 'pds_token';
	const QUERY_VAR_POST = 'pds_post';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Current request context when a valid token is present.
	 *
	 * @var array{post_id:int,token:string}|null
	 */
	private $pds_ctx = null;

	/**
	 * Error state for the current request: 'invalid', 'expired', or null.
	 *
	 * @var string|null
	 */
	private $pds_error = null;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'send_headers', array( $this, 'maybe_no_cache_headers' ) );
		add_action( 'parse_request', array( $this, 'detect_pds_request' ) );
		add_action( 'pre_get_posts', array( $this, 'shape_main_query' ) );
		add_filter( 'posts_pre_query', array( $this, 'short_circuit_posts' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'grant_read_cap' ), 10, 4 );
		add_action( 'template_redirect', array( $this, 'maybe_render_public_draft' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'bypass_canonical_on_pds' ), 10, 2 );
		// If the author saves the post, purge any cached shared URL so updates show up.
		add_action( 'save_post', array( $this, 'purge_on_save' ), 10, 2 );
		// Auto-expire link on first publish.
		add_action( 'transition_post_status', array( $this, 'auto_disable_on_publish' ), 10, 3 );
		// One-time rewrite upgrade (support /pds/{post}/{token} structure).
		add_action( 'admin_init', array( $this, 'maybe_upgrade_rewrites' ) );
	}

	/**
	 * Register custom query vars used by the share route.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::QUERY_VAR_POST;
		return $vars;
	}

	/**
	 * Add pretty permalink rule for /pds/{post_id}/{token}.
	 */
	public function add_rewrite_rules() {
		// Only support: /pds/{post_id}/{token}.
		add_rewrite_rule(
			'^pds/(\d+)/([A-Za-z0-9\-_=]+)/?$',
			'index.php?' . self::QUERY_VAR_POST . '=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
			'top'
		);
	}

	// ===== Token & Share URL =====.

	/**
	 * Generate a URL-safe token for sharing.
	 *
	 * @param int $length Token length (base string before base64url).
	 * @return string Token.
	 */
	public function generate_token( int $length = 32 ): string {
		$raw = '';
		if ( function_exists( 'random_bytes' ) ) {
			$raw = bin2hex( random_bytes( $length / 2 ) );
		} else {
			$raw = wp_generate_password( $length, false, false );
		}
		// Make URL friendly.
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	/**
	 * Get the current share URL if token exists and not expired.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null URL or null.
	 */
	public function get_share_url( int $post_id ): ?string {
		$token = get_post_meta( $post_id, self::META_TOKEN, true );
		if ( ! $token ) {
			return null;
		}
		$expires = (int) get_post_meta( $post_id, self::META_EXPIRES, true );
		if ( $expires && time() > $expires ) {
			return null; // Expired.
		}
		// Append a version parameter based on last modified time to bust caches reliably.
		$ver = (int) get_post_modified_time( 'U', true, $post_id );
		$url = home_url( $this->build_share_path( $post_id, $token ) );
		if ( $ver ) {
			$url = add_query_arg( 'v', $ver, $url );
		}
		return $url;
	}

	/**
	 * Build relative path used for the share URL.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $token   Token.
	 * @return string Path.
	 */
	private function build_share_path( int $post_id, string $token ): string {
		return '/pds/' . $post_id . '/' . rawurlencode( $token );
	}

	/**
	 * Create (or replace) a share link and set expiry.
	 *
	 * @param int $post_id    Post ID.
	 * @param int $expires_ts Expiry timestamp (0 for never).
	 * @return string Token.
	 */
	public function set_share_link( int $post_id, int $expires_ts = 0 ): string {
		// Purge old URL first if exists.
		$old_url = $this->get_share_url( $post_id );

		$token = $this->generate_token( 32 );
		update_post_meta( $post_id, self::META_TOKEN, $token );
		update_post_meta( $post_id, self::META_EXPIRES, absint( $expires_ts ) );

		if ( $old_url ) {
			$this->purge_url_cache( $old_url );
		}
		return $token;
	}

	/**
	 * Disable a share link and purge caches.
	 *
	 * @param int $post_id Post ID.
	 */
	public function disable_share_link( int $post_id ): void {
		$old_url = $this->get_share_url( $post_id );
		delete_post_meta( $post_id, self::META_TOKEN );
		delete_post_meta( $post_id, self::META_EXPIRES );
		if ( $old_url ) {
			$this->purge_url_cache( $old_url );
		}
	}

	// ===== Frontend handling =====.

	/**
	 * On template_redirect, render an error page for invalid/expired links.
	 */
	public function maybe_render_public_draft(): void {
		// If we detected an error earlier, output a minimal error page.
		if ( $this->pds_error ) {
			// Normalize to a single 404 to avoid disclosing token state.
			$code = 404;
			$msg  = __( 'Invalid or expired link.', 'public-draft-share' );
			$this->render_error( $code, $msg );
			exit;
		}
		// Otherwise, do nothing and allow the theme/template loader to render the main query.
	}

	// Detect and validate PDS requests early.
	/**
	 * Parse request to validate token/post and capture context.
	 *
	 * @param \WP $wp WP request.
	 */
	public function detect_pds_request( \WP $wp ): void {
		$qv = $wp->query_vars;
		if ( empty( $qv[ self::QUERY_VAR ] ) ) {
			return;
		}
		$token_raw = (string) $qv[ self::QUERY_VAR ];
		// Constrain to base64url charset we generate: A‑Z, a‑z, 0‑9, '-', '_', '='.
		$token   = preg_replace( '/[^A-Za-z0-9\-_=]/', '', $token_raw );
		$post_id = isset( $qv[ self::QUERY_VAR_POST ] ) ? absint( $qv[ self::QUERY_VAR_POST ] ) : 0;
		if ( ! $post_id ) {
			$this->pds_error = 'invalid';
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			$this->pds_error = 'invalid';
			return;
		}
		$stored = (string) get_post_meta( $post_id, self::META_TOKEN, true );
		if ( empty( $stored ) || ! hash_equals( (string) $stored, $token ) ) {
			$this->pds_error = 'invalid';
			return;
		}
		$expires = (int) get_post_meta( $post_id, self::META_EXPIRES, true );
		if ( $expires && time() > $expires ) {
			$this->pds_error = 'expired';
			return;
		}
		$this->pds_ctx = array(
			'post_id' => $post_id,
			'token'   => $token,
		);
	}

	// Shape the main query to load the target post via the template loader.
	/**
	 * Force main query to a single post view for the target post.
	 *
	 * @param \WP_Query $q Main query.
	 */
	public function shape_main_query( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ! $this->pds_ctx ) {
			return;
		}
		$post_id = (int) $this->pds_ctx['post_id'];
		$post    = get_post( $post_id );

		// Shape variables for a single post view.
		$q->set( 'p', $post_id );
		$q->set( 'post_type', 'any' );
		$q->set( 'post_status', array( 'draft', 'pending', 'future', 'private', 'publish' ) );
		$q->set( 'posts_per_page', 1 );
		$q->set( 'ignore_sticky_posts', true );

		// Clear archive/home/search contexts just in case.
		$q->is_home              = false;
		$q->is_front_page        = false;
		$q->is_archive           = false;
		$q->is_post_type_archive = false;
		$q->is_category          = false;
		$q->is_tag               = false;
		$q->is_tax               = false;
		$q->is_author            = false;
		$q->is_date              = false;
		$q->is_search            = false;
		$q->is_feed              = false;
		$q->is_paged             = false;
		$q->is_404               = false;

		// Singular flags.
		$q->is_singular = true;
		if ( $post && 'page' === $post->post_type ) {
			$q->is_page   = true;
			$q->is_single = false;
		} else {
			$q->is_single = true;
			$q->is_page   = false;
		}
	}

	// Guarantee the post is returned even if other filters interfere.
	/**
	 * Short-circuit posts to ensure the target post is returned.
	 *
	 * @param mixed     $posts Posts or null.
	 * @param \WP_Query $q     Query.
	 * @return mixed Posts array or original value.
	 */
	public function short_circuit_posts( $posts, \WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $this->pds_ctx ) {
			return $posts;
		}
		$post = get_post( $this->pds_ctx['post_id'] );
		if ( $post instanceof \WP_Post ) {
			// Ensure the query reports a single found post.
			$q->found_posts   = 1;
			$q->max_num_pages = 1;
			return array( $post );
		}
		return $posts;
	}

	// Grant read permission for this specific post to anonymous visitors.
	/**
	 * Temporarily grant read_post for the target post.
	 *
	 * @param array $allcaps All caps.
	 * @param array $caps    Required caps.
	 * @param array $args    Args: [0] cap, [1] user, [2] post_id.
	 * @param array $_user   User (unused).
	 * @return array Filtered caps.
	 */
	public function grant_read_cap( $allcaps, $caps, $args, $_user ) {
		// Avoid unused parameter warning.
		unset( $_user );
		if ( ! $this->pds_ctx ) {
			return $allcaps;
		}
		// $args: [0] requested cap, [1] user ID, [2] post_id.
		if ( isset( $args[0], $args[2] ) && 'read_post' === $args[0] && (int) $args[2] === (int) $this->pds_ctx['post_id'] ) {
			$allcaps['read_post'] = true;
		}
		return $allcaps;
	}

	/**
	 * Output a minimal HTML error page with noindex headers.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Message.
	 */
	private function render_error( int $status, string $message ): void {
		status_header( $status );
		$this->send_strong_no_cache_headers();
		$this->send_security_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo '<!DOCTYPE html><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">';
		echo '<style>body{font:16px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:4rem;color:#2d3748} .box{max-width:640px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff} h1{margin:0 0 .5rem;font-size:1.25rem}</style>';
		echo '<div class="box"><h1>' . esc_html( $message ) . '</h1>';
		echo '<p>' . esc_html__( 'Ask the author for a new link.', 'public-draft-share' ) . '</p></div>';
	}

	// Rendering is handled by the active theme. This plugin only controls.
	// Access and emits error pages for invalid/expired tokens.

	// ===== Caching helpers =====
	/**
	 * Send strong no-cache headers on valid PDS views.
	 */
	public function maybe_no_cache_headers(): void {
		$token = get_query_var( self::QUERY_VAR );
		if ( ! empty( $token ) ) {
			$this->send_strong_no_cache_headers();
			$this->send_security_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/**
	 * Disable canonical redirects on PDS requests to preserve tokens.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @param string       $_requested_url Requested URL (unused).
	 * @return string|false Redirect URL or false.
	 */
	public function bypass_canonical_on_pds( $redirect_url, $_requested_url ) {
		// Avoid unused parameter warning.
		unset( $_requested_url );
		if ( get_query_var( self::QUERY_VAR ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Emit a set of headers intended to defeat caches.
	 */
	private function send_strong_no_cache_headers(): void {
		nocache_headers();
		// Try to defeat aggressive reverse proxies / CDNs.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: 0', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'X-Accel-Expires: 0', true ); // Nginx.
	}

	/**
	 * Emit security-related headers for PDS views.
	 */
	private function send_security_headers(): void {
		// Prevent token leakage and clickjacking on PDS requests.
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Frame-Options: DENY', true );
		// Minimal baseline CSP: protect against framing. May be overridden below if strict mode enabled.
		header( "Content-Security-Policy: frame-ancestors 'none'", true );
		header( 'X-Content-Type-Options: nosniff', true );

		// Optional strict CSP: blocks scripts on public draft views to reduce XSS risk from content.
		if ( apply_filters( 'pds_strict_csp', false ) ) {
			header( "Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; script-src 'none'; base-uri 'self'", true );
		}
	}

	/**
	 * Purge page cache for the given URL across popular plugins/services.
	 *
	 * @param string $url URL to purge.
	 */
	private function purge_url_cache( string $url ): void {
		$urls = array( $url );
		// Also purge variant without query arguments in case cache ignores them.
		$urls[] = remove_query_arg( array_keys( wp_parse_args( wp_parse_url( $url, PHP_URL_QUERY ) ?: '' ) ), $url );
		$urls   = array_unique( array_filter( $urls ) );
		// WP Rocket.
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
		}
		// LiteSpeed Cache.
		if ( function_exists( 'do_action' ) ) {
			foreach ( $urls as $u ) {
				do_action( 'litespeed_purge_url', $u );
				do_action( 'sg_cachepress_purge_url', $u );
					do_action( 'w3tc_flush_url', $u );
					// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- 3rd-party hook name uses dashes.
					do_action( 'kinsta-clear-cache-url', $u );
			}
		}
		// W3 Total Cache (direct).
		if ( function_exists( 'w3tc_flush_url' ) ) {
			foreach ( $urls as $u ) {
				w3tc_flush_url( $u );
			}
		}

		// Optional: aggressively flush Super Cache if enabled by filter.
		if ( apply_filters( 'pds_aggressive_cache_flush', false ) ) {
			if ( function_exists( 'wp_cache_clear_cache' ) ) {
				// Flush current blog only on multisite-aware caches.
				$blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
				@wp_cache_clear_cache( $blog_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * On save, purge the shared URL to ensure fresh content.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function purge_on_save( int $post_id, \WP_Post $post ): void {
		// Skip autosave/revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		// Only for public post types (matches where meta box appears).
		$pt_obj = get_post_type_object( $post->post_type );
		if ( ! $pt_obj || empty( $pt_obj->public ) ) {
			return;
		}
		$url = $this->get_share_url( $post_id );
		if ( $url ) {
			$this->purge_url_cache( $url );
		}
	}

	// Disable and purge on publish to avoid lingering public access after going live.
	/**
	 * Auto-disable and purge on first publish.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function auto_disable_on_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return; // Only on first transition to publish.
		}
		// Allow site owners to opt-out.
		$enabled = apply_filters( 'pds_auto_expire_on_publish', true, $post );
		if ( ! $enabled ) {
			return;
		}
		// Only for public post types we support.
		$pt = get_post_type_object( $post->post_type );
		if ( ! $pt || empty( $pt->public ) ) {
			return;
		}
		$token = get_post_meta( $post->ID, self::META_TOKEN, true );
		if ( $token ) {
			$this->disable_share_link( $post->ID );
		}
	}

	/**
	 * Find a post by token using a meta query (unused helper).
	 *
	 * @param string $token Token.
	 * @return \WP_Post|null Post or null.
	 */
	private function find_post_by_token( string $token ): ?\WP_Post {
		// Case-sensitive exact match using BINARY type to avoid collation surprises.
		$q = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'draft', 'pending', 'future', 'private', 'publish' ),
				'meta_query'     => array(
					array(
						'key'     => self::META_TOKEN,
						'value'   => $token,
						'compare' => '=',
						'type'    => 'BINARY',
					),
				),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'all',
			)
		);

		if ( $q->have_posts() ) {
			return $q->posts[0];
		}
		return null;
	}

	/**
	 * One-time rewrite rules upgrade with version flag.
	 */
	public function maybe_upgrade_rewrites(): void {
		$current    = get_option( 'pds_rewrite_version', '1' );
		$target     = '3';
		$need_flush = ( $current !== $target );

		// Self-heal: if custom PDS rule is missing from rewrite array, flush once.
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) ) {
			$has_pds = false;
			foreach ( array_keys( $rules ) as $rule ) {
				if ( 0 === strpos( (string) $rule, '^pds/' ) ) {
					$has_pds = true;
					break;
				}
			}
			if ( ! $has_pds ) {
				$need_flush = true;
			}
		}

		if ( $need_flush ) {
			$this->add_rewrite_rules();
			flush_rewrite_rules();
			update_option( 'pds_rewrite_version', $target );
		}
	}
}
