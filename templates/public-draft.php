<?php
/** @var WP_Post $post */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// $post is set by Core::render_public_draft
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php echo esc_html( sprintf( __( 'Preview: %s', 'public-draft-share' ), get_the_title( $post ) ) ); ?></title>
    <?php wp_head(); ?>
    <style>
        .pds-banner{position:sticky;top:0;z-index:999;background:#111827;color:#f9fafb;padding:.5rem 1rem;font:14px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
        .pds-banner a{color:#93c5fd;text-decoration:underline}
        .pds-container{max-width:800px;margin:2rem auto;padding:0 1rem}
        .pds-title{font-size:2rem;margin:.5rem 0}
        .pds-meta{color:#6b7280;font-size:.9rem;margin-bottom:1rem}
        .pds-content{font-size:1.05rem;line-height:1.7}
    </style>
    </head>
<body <?php body_class( 'pds-public-draft' ); ?>>
    <div class="pds-banner">
        <?php esc_html_e( 'Public Draft Share — not published. Do not share widely.', 'public-draft-share' ); ?>
    </div>
    <div class="pds-container">
        <article id="post-<?php echo esc_attr( $post->ID ); ?>">
            <h1 class="pds-title"><?php echo esc_html( get_the_title( $post ) ); ?></h1>
            <div class="pds-meta">
                <?php
                $status_obj = get_post_status_object( $post->post_status );
                if ( $status_obj ) {
                    printf( esc_html__( 'Status: %s', 'public-draft-share' ), esc_html( $status_obj->label ) );
                }
                ?>
            </div>
            <div class="pds-content">
                <?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </article>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

