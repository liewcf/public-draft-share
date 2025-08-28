<?php
namespace PDS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core {
    const META_TOKEN   = '_pds_token';
    const META_EXPIRES = '_pds_expires';
    const QUERY_VAR    = 'pds_token';

    private static $instance;

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
        add_action( 'template_redirect', [ $this, 'maybe_render_public_draft' ], 0 );
    }

    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^pds/([A-Za-z0-9\-_=]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
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
        return home_url( '/pds/' . rawurlencode( $token ) );
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
        $token = get_query_var( self::QUERY_VAR );
        if ( empty( $token ) ) {
            return;
        }

        $post = $this->find_post_by_token( sanitize_text_field( (string) $token ) );

        if ( ! $post ) {
            $this->render_error( 404, __( 'Invalid link.', 'public-draft-share' ) );
            exit;
        }

        $expires = (int) get_post_meta( $post->ID, self::META_EXPIRES, true );
        if ( $expires && time() > $expires ) {
            $this->render_error( 410, __( 'This link has expired.', 'public-draft-share' ) );
            exit;
        }

        $this->render_public_draft( $post );
        exit;
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
        }
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
        // WP Rocket
        if ( function_exists( 'rocket_clean_files' ) ) {
            rocket_clean_files( [ $url ] );
        }
        // LiteSpeed Cache
        if ( function_exists( 'do_action' ) ) {
            do_action( 'litespeed_purge_url', $url );
            do_action( 'sg_cachepress_purge_url', $url );
            do_action( 'w3tc_flush_url', $url );
            do_action( 'kinsta-clear-cache-url', $url );
        }
        // W3 Total Cache (direct)
        if ( function_exists( 'w3tc_flush_url' ) ) {
            w3tc_flush_url( $url );
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

    private function find_post_by_token( string $token ) : ?\WP_Post {
        $q = new \WP_Query( [
            'post_type'      => 'any',
            'post_status'    => [ 'draft', 'pending', 'future', 'private', 'publish' ],
            'meta_key'       => self::META_TOKEN,
            'meta_value'     => $token,
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'all',
        ] );

        if ( $q->have_posts() ) {
            return $q->posts[0];
        }
        return null;
    }
}
