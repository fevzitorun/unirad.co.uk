<?php
/**
 * Plugin Name: Unirad Homepage Sections
 * Description: Ana sayfa için Blog Bantı ve Instagram Bantı shortcode'ları
 * Version: 1.0
 * Author: Unirad
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════
   1. BLOG BANTI  →  [unirad_blog_strip]
   Son 3 blog yazısını kartlar halinde gösterir
   ═══════════════════════════════════════════════ */
add_shortcode( 'unirad_blog_strip', 'unirad_blog_strip_shortcode' );

function unirad_blog_strip_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'count' => 3,
        'title' => 'Latest from Our Blog',
        'cta_text' => 'View All Articles',
        'cta_url'  => 'https://unirad.co.uk/blog/',
    ], $atts );

    $query = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => intval( $atts['count'] ),
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if ( ! $query->have_posts() ) return '';

    ob_start();
    ?>
    <section class="unirad-blog-strip">
        <div class="unirad-strip-inner">
            <div class="unirad-strip-header">
                <h2 class="unirad-strip-title"><?php echo esc_html( $atts['title'] ); ?></h2>
                <a href="<?php echo esc_url( $atts['cta_url'] ); ?>" class="unirad-strip-cta">
                    <?php echo esc_html( $atts['cta_text'] ); ?> →
                </a>
            </div>

            <div class="unirad-blog-grid">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <article class="unirad-blog-card">
                    <?php if ( has_post_thumbnail() ) : ?>
                    <a href="<?php the_permalink(); ?>" class="unirad-blog-card__img-wrap">
                        <?php the_post_thumbnail( 'medium_large', [ 'class' => 'unirad-blog-card__img', 'loading' => 'lazy' ] ); ?>
                    </a>
                    <?php endif; ?>

                    <div class="unirad-blog-card__body">
                        <?php
                        $cats = get_the_category();
                        if ( $cats ) :
                        ?>
                        <span class="unirad-blog-card__cat"><?php echo esc_html( $cats[0]->name ); ?></span>
                        <?php endif; ?>

                        <h3 class="unirad-blog-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>

                        <p class="unirad-blog-card__excerpt">
                            <?php echo wp_trim_words( get_the_excerpt(), 18, '…' ); ?>
                        </p>

                        <div class="unirad-blog-card__meta">
                            <span class="unirad-blog-card__date"><?php echo get_the_date( 'd M Y' ); ?></span>
                            <a href="<?php the_permalink(); ?>" class="unirad-blog-card__read">Read more →</a>
                        </div>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div><!-- .unirad-blog-grid -->
        </div><!-- .unirad-strip-inner -->
    </section><!-- .unirad-blog-strip -->
    <?php
    return ob_get_clean();
}


/* ═══════════════════════════════════════════════
   2. INSTAGRAM BANTI  →  [unirad_instagram_strip]
   Manuel olarak eklenen görselleri grid'de gösterir.
   Instagram Basic Display API token gerektirmez.
   Görselleri WordPress Media'ya yükleyip URL girin.
   ═══════════════════════════════════════════════ */
add_shortcode( 'unirad_instagram_strip', 'unirad_instagram_strip_shortcode' );

function unirad_instagram_strip_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title'    => 'Follow Us on Instagram',
        'handle'   => '@uniradimaging',
        'url'      => 'https://www.instagram.com/uniradimaging/',
        'count'    => 4,
    ], $atts );

    // Admin panelinden ayarlanan görseller
    $images = get_option( 'unirad_instagram_images', [] );

    ob_start();
    ?>
    <section class="unirad-instagram-strip">
        <div class="unirad-strip-inner">
            <div class="unirad-strip-header">
                <h2 class="unirad-strip-title">
                    <?php echo esc_html( $atts['title'] ); ?>
                    <a href="<?php echo esc_url( $atts['url'] ); ?>" target="_blank" rel="noopener" class="unirad-ig-handle">
                        <?php echo esc_html( $atts['handle'] ); ?>
                    </a>
                </h2>
                <a href="<?php echo esc_url( $atts['url'] ); ?>" target="_blank" rel="noopener" class="unirad-strip-cta">
                    View on Instagram →
                </a>
            </div>

            <?php if ( ! empty( $images ) ) : ?>
            <div class="unirad-ig-grid">
                <?php
                $shown = 0;
                foreach ( $images as $img ) :
                    if ( $shown >= intval( $atts['count'] ) ) break;
                    $img_url    = esc_url( $img['url'] ?? '' );
                    $ig_profile = 'https://www.instagram.com/uniradimaging/';
                    $link_raw   = trim( $img['link'] ?? '' );
                    $link_url   = ( ! empty( $link_raw ) && strpos( $link_raw, 'instagram.com' ) !== false )
                                  ? esc_url( $link_raw )
                                  : $ig_profile;
                    $alt        = esc_attr( $img['alt'] ?? 'Unirad Instagram' );
                    if ( ! $img_url ) continue;
                ?>
                <a href="<?php echo $link_url; ?>" target="_blank" rel="noopener" class="unirad-ig-item">
                    <img src="<?php echo $img_url; ?>" alt="<?php echo $alt; ?>" loading="lazy" />
                    <div class="unirad-ig-overlay">
                        <svg viewBox="0 0 24 24" fill="white" width="28" height="28"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </div>
                </a>
                <?php $shown++; endforeach; ?>
            </div><!-- .unirad-ig-grid -->

            <?php else : ?>
            <!-- Görsel eklenmemişse placeholder grid -->
            <div class="unirad-ig-grid unirad-ig-placeholder">
                <?php for ( $i = 0; $i < 4; $i++ ) : ?>
                <a href="<?php echo esc_url( $atts['url'] ); ?>" target="_blank" rel="noopener" class="unirad-ig-item unirad-ig-item--empty">
                    <svg viewBox="0 0 24 24" fill="#00C9A7" width="32" height="32"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    <span>@uniradimaging</span>
                </a>
                <?php endfor; ?>
            </div>
            <p class="unirad-ig-notice">
                ⚙️ <strong>Admin:</strong> Ayarlar → Unirad Instagram'dan görsel ekleyin.
            </p>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}


/* ═══════════════════════════════════════════════
   3. ADMİN SAYFASI — Instagram Görselleri Yönetimi
   Ayarlar → Unirad Instagram
   ═══════════════════════════════════════════════ */
add_action( 'admin_menu', 'unirad_admin_menu' );
function unirad_admin_menu() {
    add_options_page(
        'Unirad Instagram',
        'Unirad Instagram',
        'manage_options',
        'unirad-instagram',
        'unirad_instagram_admin_page'
    );
}

function unirad_instagram_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['unirad_ig_save'] ) && check_admin_referer( 'unirad_ig_nonce' ) ) {
        $raw    = $_POST['unirad_ig_images'] ?? [];
        $clean  = [];
        foreach ( $raw as $item ) {
            $url  = esc_url_raw( $item['url'] ?? '' );
            $link = esc_url_raw( $item['link'] ?? '' );
            $alt  = sanitize_text_field( $item['alt'] ?? '' );
            if ( $url ) $clean[] = compact( 'url', 'link', 'alt' );
        }
        update_option( 'unirad_instagram_images', $clean );
        echo '<div class="notice notice-success"><p>✅ Kaydedildi!</p></div>';
    }

    $images = get_option( 'unirad_instagram_images', [] );
    // En az 6 boş satır göster
    while ( count( $images ) < 6 ) $images[] = [ 'url' => '', 'link' => '', 'alt' => '' ];
    ?>
    <div class="wrap">
        <h1>Unirad Instagram Görselleri</h1>
        <p>Her satıra bir Instagram görseli ekleyin. Görsel URL'sini WordPress Media Library'den alabilirsiniz.</p>

        <form method="post">
            <?php wp_nonce_field( 'unirad_ig_nonce' ); ?>
            <table class="widefat" style="margin-top:16px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Görsel URL (WordPress Media'dan)</th>
                        <th>Instagram Post Linki</th>
                        <th>Alt metin</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $images as $i => $img ) : ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><input type="url" name="unirad_ig_images[<?php echo $i; ?>][url]" value="<?php echo esc_attr( $img['url'] ); ?>" style="width:100%" placeholder="https://..." /></td>
                    <td><input type="url" name="unirad_ig_images[<?php echo $i; ?>][link]" value="<?php echo esc_attr( $img['link'] ); ?>" style="width:100%" placeholder="https://www.instagram.com/p/..." /></td>
                    <td><input type="text" name="unirad_ig_images[<?php echo $i; ?>][alt]" value="<?php echo esc_attr( $img['alt'] ); ?>" style="width:100%" placeholder="Kısa açıklama" /></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><input type="submit" name="unirad_ig_save" class="button button-primary" value="Kaydet" /></p>
        </form>

        <hr>
        <h2>Kullanım</h2>
        <p>Ana sayfanıza (Elementor, Gutenberg veya herhangi bir sayfa) şu shortcode'ları ekleyin:</p>
        <code style="display:block;padding:12px;background:#f0f0f0;margin:8px 0">[unirad_blog_strip]</code>
        <code style="display:block;padding:12px;background:#f0f0f0;margin:8px 0">[unirad_instagram_strip]</code>
        <p>İsteğe bağlı parametreler:</p>
        <code style="display:block;padding:12px;background:#f0f0f0;margin:8px 0">[unirad_blog_strip count="4" title="Our Blog" cta_text="All Posts" cta_url="https://unirad.co.uk/blog/"]</code>
        <code style="display:block;padding:12px;background:#f0f0f0;margin:8px 0">[unirad_instagram_strip count="6" title="Follow Us" handle="@uniradimaging"]</code>
    </div>
    <?php
}


/* ═══════════════════════════════════════════════
   4. CSS — Unirad marka renkleri ile tam stil
   ═══════════════════════════════════════════════ */
add_action( 'wp_head', 'unirad_sections_css' );
function unirad_sections_css() {
    ?>
    <style>
    /* ── Genel strip ── */
    .unirad-blog-strip,
    .unirad-instagram-strip {
        padding: 72px 20px;
        background: #fff;
    }
    .unirad-instagram-strip {
        background: #f8f9fb;
    }
    .unirad-strip-inner {
        max-width: 1200px;
        margin: 0 auto;
    }
    .unirad-strip-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .unirad-strip-title {
        font-size: 28px;
        font-weight: 700;
        color: #0A1628;
        margin: 0;
    }
    .unirad-strip-cta {
        font-size: 15px;
        font-weight: 600;
        color: #00C9A7;
        text-decoration: none;
        border: 2px solid #00C9A7;
        padding: 8px 20px;
        border-radius: 100px;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .unirad-strip-cta:hover {
        background: #00C9A7;
        color: #fff;
    }

    /* ── Blog grid ── */
    .unirad-blog-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
    }
    @media (max-width: 900px) {
        .unirad-blog-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 580px) {
        .unirad-blog-grid { grid-template-columns: 1fr; }
        .unirad-strip-header { flex-direction: column; align-items: flex-start; }
    }

    .unirad-blog-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(10,22,40,0.08);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .unirad-blog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 32px rgba(10,22,40,0.13);
    }
    .unirad-blog-card__img-wrap {
        display: block;
        overflow: hidden;
        aspect-ratio: 16/9;
    }
    .unirad-blog-card__img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }
    .unirad-blog-card:hover .unirad-blog-card__img {
        transform: scale(1.04);
    }
    .unirad-blog-card__body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .unirad-blog-card__cat {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #00C9A7;
        background: rgba(0,201,167,0.1);
        padding: 3px 10px;
        border-radius: 100px;
        display: inline-block;
        margin-bottom: 12px;
    }
    .unirad-blog-card__title {
        font-size: 17px;
        font-weight: 700;
        color: #0A1628;
        margin: 0 0 10px;
        line-height: 1.4;
    }
    .unirad-blog-card__title a {
        color: inherit;
        text-decoration: none;
    }
    .unirad-blog-card__title a:hover { color: #00C9A7; }
    .unirad-blog-card__excerpt {
        font-size: 14px;
        color: #5a6a7e;
        line-height: 1.6;
        flex: 1;
        margin: 0 0 16px;
    }
    .unirad-blog-card__meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid #eef0f3;
        padding-top: 14px;
    }
    .unirad-blog-card__date {
        font-size: 12px;
        color: #9aa5b4;
    }
    .unirad-blog-card__read {
        font-size: 13px;
        font-weight: 600;
        color: #00C9A7;
        text-decoration: none;
    }
    .unirad-blog-card__read:hover { text-decoration: underline; }

    /* ── Instagram grid ── */
    .unirad-ig-handle {
        font-size: 16px;
        font-weight: 500;
        color: #00C9A7;
        margin-left: 8px;
        text-decoration: none;
    }
    .unirad-ig-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
    }
    @media (max-width: 900px) {
        .unirad-ig-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
        .unirad-ig-grid { grid-template-columns: repeat(2, 1fr); }
    }
    .unirad-ig-item {
        position: relative;
        display: block;
        aspect-ratio: 4 / 5;
        overflow: hidden;
        border-radius: 10px;
        background: #0A1628;
    }
    .unirad-ig-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s, opacity 0.3s;
    }
    .unirad-ig-item:hover img {
        transform: scale(1.06);
        opacity: 0.7;
    }
    .unirad-ig-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s;
        background: rgba(10,22,40,0.35);
    }
    .unirad-ig-item:hover .unirad-ig-overlay { opacity: 1; }

    /* Placeholder (görsel eklenmemiş) */
    .unirad-ig-item--empty {
        background: #eef0f3;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }
    .unirad-ig-item--empty span {
        font-size: 11px;
        color: #9aa5b4;
    }
    .unirad-ig-notice {
        margin-top: 16px;
        padding: 12px 16px;
        background: #fff8e1;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
        font-size: 14px;
        color: #5a6a7e;
    }
    </style>
    <?php
}
