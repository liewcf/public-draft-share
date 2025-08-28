<?php
namespace PDS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core {
    const META_TOKEN      = '_pds_token';
    const META_EXPIRES    = '_pds_expires';
    const QUERY_VAR       = 'pds_token';
    const QUERY_VAR_POST  = 'pds_post';

    private static $instance;
    private $pds_ctx = null; // ['post_id'=>int, 'token'=>string]
    private $pds_error = null; // 'invalid' | 'expired' | null

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'send_headers', [ $this, 'maybe_no_cache_headers' ] );
        add_action( 'parse_request', [ $this, 'detect_pds_request' ] );
        add_action( 'pre_get_posts', [ $this, 'shape_main_query' ] );
        add_filter( 'posts_pre_query', [ $this, 'short_circuit_posts' ], 10, 2 );
        add_filter( 'user_has_cap', [ $this, 'grant_read_cap' ], 10, 4 );
        add_action( 'template_redirect', [ $this, 'maybe_render_public_draft' ], 0 );
        add_filter( 'redirect_canonical', [ $this, 'bypass_canonical_on_pds' ], 10, 2 );
        // If the author saves the post, purge any cached shared URL so updates show up.
        add_action( 'save_post', [ $this, 'purge_on_save' ], 10, 2 );
        // One-time rewrite upgrade (support /pds/{post}/{token} structure)
        add_action( 'admin_init', [ $this, 'maybe_upgrade_rewrites' ] );
    }

    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::QUERY_VAR_POST;
        return $vars;
    }

    public function add_rewrite_rules() {
        // Only support: /pds/{post_id}/{token}
        add_rewrite_rule(
            '^pds/(\d+)/([A-Za-z0-9\-_=]+)/?$',
            'index.php?' . self::QUERY_VAR_POST . '=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
            'top'
        );
    }

    // ===== Token & Share URL =====

    public function generate_token( int $length = 32 ) : string {
        $raw = '';
        if ( function_exists( 'random_bytes' ) ) {
            $raw = bin2hex( random_bytes( $length / 2 ) );
        } else {
            $raw = wp_generate_password( $length, false, false );
        }
        // Make URL friendly
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    public function get_share_url( int $post_id ) : ?string {
        $token = get_post_meta( $post_id, self::META_TOKEN, true );
        if ( ! $token ) {
            return null;
        }
        $expires = (int) get_post_meta( $post_id, self::META_EXPIRES, true );
        if ( $expires && time() > $expires ) {
            return null; // expired
        }
        // Append a version parameter based on last modified time to bust caches reliably.
        $ver = (int) get_post_modified_time( 'U', true, $post_id );
        $url = home_url( $this->build_share_path( $post_id, $token ) );
        if ( $ver ) {
            $url = add_query_arg( 'v', $ver, $url );
        }
        return $url;
    }

    private function build_share_path( int $post_id, string $token ) : string {
        return '/pds/' . $post_id . '/' . rawurlencode( $token );
    }

    public function set_share_link( int $post_id, int $expires_ts = 0 ) : string {
        // Purge old URL first if exists
        $old_url = $this->get_share_url( $post_id );

        $token = $this->generate_token( 32 );
        update_post_meta( $post_id, self::META_TOKEN, $token );
        update_post_meta( $post_id, self::META_EXPIRES, absint( $expires_ts ) );

        if ( $old_url ) {
            $this->purge_url_cache( $old_url );
        }
        return $token;
    }

    public function disable_share_link( int $post_id ) : void {
        $old_url = $this->get_share_url( $post_id );
        delete_post_meta( $post_id, self::META_TOKEN );
        delete_post_meta( $post_id, self::META_EXPIRES );
        if ( $old_url ) {
            $this->purge_url_cache( $old_url );
        }
    }

    // ===== Frontend handling =====

    public function maybe_render_public_draft() : void {
        // If we detected an error earlier, output a minimal error page.
        if ( $this->pds_error ) {
            $code = ( 'expired' === $this->pds_error ) ? 410 : 404;
            $msg  = ( 'expired' === $this->pds_error ) ? __( 'This link has expired.', 'public-draft-share' ) : __( 'Invalid link.', 'public-draft-share' );
            $this->render_error( $code, $msg );
            exit;
        }
        // Otherwise, do nothing and allow the theme/template loader to render the main query.
    }

    // Detect and validate PDS requests early
    public function detect_pds_request( \WP $wp ) : void {
        $qv = $wp->query_vars;
        if ( empty( $qv[ self::QUERY_VAR ] ) ) {
            return;
        }
        $token  = sanitize_text_field( (string) $qv[ self::QUERY_VAR ] );
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
        $this->pds_ctx = [ 'post_id' => $post_id, 'token' => $token ];
    }

    // Shape the main query to load the target post via the template loader
    public function shape_main_query( \WP_Query $q ) : void {
        if ( is_admin() || ! $q->is_main_query() ) {
            return;
        }
        if ( ! $this->pds_ctx ) {
            return;
        }
        $post_id = (int) $this->pds_ctx['post_id'];
        $post    = get_post( $post_id );

        // Shape variables for a single post view
        $q->set( 'p', $post_id );
        $q->set( 'post_type', 'any' );
        $q->set( 'post_status', [ 'draft', 'pending', 'future', 'private', 'publish' ] );
        $q->set( 'posts_per_page', 1 );
        $q->set( 'ignore_sticky_posts', true );

        // Clear archive/home/search contexts just in case
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

        // Singular flags
        $q->is_singular = true;
        if ( $post && 'page' === $post->post_type ) {
            $q->is_page   = true;
            $q->is_single = false;
        } else {
            $q->is_single = true;
            $q->is_page   = false;
        }
    }

    // Guarantee the post is returned even if other filters interfere
    public function short_circuit_posts( $posts, \WP_Query $q ) {
        if ( is_admin() || ! $q->is_main_query() || ! $this->pds_ctx ) {
            return $posts;
        }
        $post = get_post( $this->pds_ctx['post_id'] );
        if ( $post instanceof \WP_Post ) {
            // Ensure the query reports a single found post
            $q->found_posts   = 1;
            $q->max_num_pages = 1;
            return [ $post ];
        }
        return $posts;
    }

    // Grant read permission for this specific post to anonymous visitors
    public function grant_read_cap( $allcaps, $caps, $args, $user ) {
        if ( ! $this->pds_ctx ) {
            return $allcaps;
        }
        // $args: [0] requested cap, [1] user ID, [2] post_id
        if ( isset( $args[0], $args[2] ) && 'read_post' === $args[0] && (int) $args[2] === (int) $this->pds_ctx['post_id'] ) {
            $allcaps['read_post'] = true;
        }
        return $allcaps;
    }

    private function render_error( int $status, string $message ) : void {
        status_header( $status );
        $this->send_strong_no_cache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        echo '<!DOCTYPE html><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">';
        echo '<style>body{font:16px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:4rem;color:#2d3748} .box{max-width:640px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;background:#fff} h1{margin:0 0 .5rem;font-size:1.25rem}</style>';
        echo '<div class="box"><h1>' . esc_html( $message ) . '</h1>';
        echo '<p>' . esc_html__( 'Ask the author for a new link.', 'public-draft-share' ) . '</p></div>';
    }

    private function render_public_draft( \WP_Post $the_post ) : void {
        global $wp_query;
        // Prepare globals so filters behave
        $GLOBALS['post'] = $the_post;
        if ( $wp_query ) {
            $wp_query->is_singular     = true;
            $wp_query->is_single       = true;
            $wp_query->is_404          = false;
            $wp_query->queried_object  = $the_post;
            $wp_query->queried_object_id = $the_post->ID;
            $wp_query->posts           = [ $the_post ];
            $wp_query->post            = $the_post;
        }
        setup_postdata( $the_post );

        // Ensure blocks render using latest content
        add_filter( 'the_title', function ( $title ) use ( $the_post ) {
            return $title;
        }, 10, 1 );

        // Output template
        $template = PDS_PLUGIN_DIR . 'templates/public-draft.php';
        $this->send_strong_no_cache_headers();
        status_header( 200 );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        if ( file_exists( $template ) ) {
            $post = $the_post; // Make available to template scope.
            include $template;
        } else {
            // Fallback minimal rendering
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<meta name="robots" content="noindex,nofollow"><title>' . esc_html( get_the_title( $the_post ) ) . '</title></head><body>';
            echo '<h1>' . esc_html( get_the_title( $the_post ) ) . '</h1>';
            echo apply_filters( 'the_content', $the_post->post_content );
            echo '</body></html>';
        }
        wp_reset_postdata();
    }

    // ===== Caching helpers =====
    public function maybe_no_cache_headers() : void {
        $token = get_query_var( self::QUERY_VAR );
        if ( ! empty( $token ) ) {
            $this->send_strong_no_cache_headers();
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
    }

    public function bypass_canonical_on_pds( $redirect_url, $requested_url ) {
        if ( get_query_var( self::QUERY_VAR ) ) {
            return false;
        }
        return $redirect_url;
    }

    private function send_strong_no_cache_headers() : void {
        nocache_headers();
        // Try to defeat aggressive reverse proxies / CDNs
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
        header( 'Pragma: no-cache', true );
        header( 'Expires: 0', true );
        header( 'Surrogate-Control: no-store', true );
        header( 'X-Accel-Expires: 0', true ); // Nginx
    }

    private function purge_url_cache( string $url ) : void {
        $urls = [ $url ];
        // Also purge variant without query arguments in case cache ignores them
        $urls[] = remove_query_arg( array_keys( wp_parse_args( wp_parse_url( $url, PHP_URL_QUERY ) ?: '' ) ), $url );
        $urls = array_unique( array_filter( $urls ) );
        // WP Rocket
        if ( function_exists( 'rocket_clean_files' ) ) {
            rocket_clean_files( $urls );
        }
        // LiteSpeed Cache
        if ( function_exists( 'do_action' ) ) {
            foreach ( $urls as $u ) {
                do_action( 'litespeed_purge_url', $u );
                do_action( 'sg_cachepress_purge_url', $u );
                do_action( 'w3tc_flush_url', $u );
                do_action( 'kinsta-clear-cache-url', $u );
            }
        }
        // W3 Total Cache (direct)
        if ( function_exists( 'w3tc_flush_url' ) ) {
            foreach ( $urls as $u ) {
                w3tc_flush_url( $u );
            }
        }

        // Optional: aggressively flush Super Cache if enabled by filter
        if ( apply_filters( 'pds_aggressive_cache_flush', false ) ) {
            if ( function_exists( 'wp_cache_clear_cache' ) ) {
                // Flush current blog only on multisite-aware caches
                $blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
                @wp_cache_clear_cache( $blog_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }
    }

    public function purge_on_save( int $post_id, \WP_Post $post ) : void {
        // Skip autosave/revision
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        // Only for public post types (matches where meta box appears)
        $pt_obj = get_post_type_object( $post->post_type );
        if ( ! $pt_obj || empty( $pt_obj->public ) ) {
            return;
        }
        $url = $this->get_share_url( $post_id );
        if ( $url ) {
            $this->purge_url_cache( $url );
        }
    }

    private function find_post_by_token( string $token ) : ?\WP_Post {
        // Case-sensitive exact match using BINARY type to avoid collation surprises.
        $q = new \WP_Query( [
            'post_type'      => 'any',
            'post_status'    => [ 'draft', 'pending', 'future', 'private', 'publish' ],
            'meta_query'     => [
                [
                    'key'     => self::META_TOKEN,
                    'value'   => $token,
                    'compare' => '=',
                    'type'    => 'BINARY',
                ],
            ],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'all',
        ] );

        if ( $q->have_posts() ) {
            return $q->posts[0];
        }
        return null;
    }

    public function maybe_upgrade_rewrites() : void {
        $current = get_option( 'pds_rewrite_version', '1' );
        $target  = '3';
        if ( $current !== $target ) {
            $this->add_rewrite_rules();
            flush_rewrite_rules();
            update_option( 'pds_rewrite_version', $target );
        }
    }
}
