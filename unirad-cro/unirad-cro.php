<?php
/**
 * Plugin Name: Unirad CRO
 * Description: Conversion optimizasyonu — exit-intent popup (booking terk edenlere) + FAQ post type ve FAQPage schema.
 * Version:     1.0.0
 * Author:      Unirad
 */

defined( 'ABSPATH' ) || exit;

class Unirad_CRO {

    public static function init() {
        add_action( 'init',      array( __CLASS__, 'register_faq_cpt' ) );
        add_action( 'wp_footer', array( __CLASS__, 'exit_intent_popup' ) );
        add_action( 'wp_head',   array( __CLASS__, 'faq_schema' ) );
        add_shortcode( 'unirad_faqs', array( __CLASS__, 'faq_shortcode' ) );
    }

    /* ── Exit Intent Popup ───────────────────────────────────────────────── */

    public static function exit_intent_popup() {
        // Sadece booking ile ilgili sayfalarda: checkout, sepet, fiyat, ana sayfa
        $show = is_front_page()
            || ( function_exists( 'is_checkout' ) && is_checkout() )
            || ( function_exists( 'is_cart' ) && is_cart() )
            || is_page( array( 'our-pricing', 'services', 'book' ) );
        if ( ! $show || is_admin() ) return;
        ?>
        <div id="uxi-overlay" style="display:none;position:fixed;inset:0;background:rgba(13,31,60,.55);z-index:99998"></div>
        <div id="uxi-pop" role="dialog" aria-modal="true" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:16px;padding:32px;max-width:420px;width:92%;z-index:99999;box-shadow:0 25px 60px rgba(0,0,0,.3);font-family:inherit;text-align:center">
            <button id="uxi-close" aria-label="Close" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:#9aadbe">&times;</button>
            <div style="font-size:34px;margin-bottom:8px">🩻</div>
            <h3 style="margin:0 0 10px;font-size:21px;color:#0d1f3c">Wait — same-week appointments available</h3>
            <p style="margin:0 0 18px;color:#3a4d68;font-size:14px;line-height:1.5">Private MRI from <strong>&pound;290</strong> with consultant radiologist report. No GP referral needed, no waiting lists.</p>
            <a href="/book/" style="display:inline-block;background:#009B8D;color:#fff;padding:13px 30px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px">Book My Scan &rarr;</a>
            <p style="margin:14px 0 0;font-size:12px;color:#9aadbe">Or call us: <a href="tel:+441418469116" style="color:#009B8D;font-weight:600">0141 846 9116</a></p>
        </div>
        <script>
        (function(){
            if (sessionStorage.getItem('uxiShown')) return;
            var armed = false;
            setTimeout(function(){ armed = true; }, 8000); // ilk 8 saniye tetiklenmez
            function show(){
                if (!armed || sessionStorage.getItem('uxiShown')) return;
                sessionStorage.setItem('uxiShown','1');
                document.getElementById('uxi-overlay').style.display='block';
                document.getElementById('uxi-pop').style.display='block';
            }
            function hide(){
                document.getElementById('uxi-overlay').style.display='none';
                document.getElementById('uxi-pop').style.display='none';
            }
            // Desktop: imleç pencerenin üstünden çıkınca
            document.addEventListener('mouseout', function(e){
                if (!e.relatedTarget && e.clientY <= 0) show();
            });
            // Mobil: hızlı yukarı scroll (geri dönme niyeti)
            var lastY = 0, lastT = 0;
            window.addEventListener('scroll', function(){
                var y = window.scrollY, t = Date.now();
                if (lastY - y > 350 && t - lastT < 400 && y > 200) show();
                lastY = y; lastT = t;
            }, {passive:true});
            document.getElementById('uxi-close').onclick = hide;
            document.getElementById('uxi-overlay').onclick = hide;
        })();
        </script>
        <?php
    }

    /* ── FAQ Custom Post Type ────────────────────────────────────────────── */

    public static function register_faq_cpt() {
        register_post_type( 'unirad_faq', array(
            'labels' => array(
                'name'          => 'FAQs',
                'singular_name' => 'FAQ',
                'add_new_item'  => 'Yeni FAQ Ekle',
            ),
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-editor-help',
            'supports'     => array( 'title', 'editor', 'page-attributes' ),
            'has_archive'  => false,
        ) );
    }

    private static function get_faqs() {
        return get_posts( array(
            'post_type'      => 'unirad_faq',
            'posts_per_page' => 50,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ) );
    }

    /** [unirad_faqs] shortcode — accordion listesi */
    public static function faq_shortcode() {
        $faqs = self::get_faqs();
        if ( ! $faqs ) return '';
        $out = '<div class="unirad-faqs" style="max-width:760px;margin:0 auto">';
        foreach ( $faqs as $f ) {
            $out .= '<details style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;margin-bottom:10px;background:#fff">'
                . '<summary style="font-weight:700;cursor:pointer;color:#0d1f3c">' . esc_html( $f->post_title ) . '</summary>'
                . '<div style="margin-top:10px;color:#3a4d68;line-height:1.6">' . wp_kses_post( wpautop( $f->post_content ) ) . '</div>'
                . '</details>';
        }
        return $out . '</div>';
    }

    /** FAQPage schema — shortcode kullanılan sayfalarda */
    public static function faq_schema() {
        if ( ! is_singular() ) return;
        global $post;
        if ( ! $post || ! has_shortcode( (string) $post->post_content, 'unirad_faqs' ) ) return;

        $faqs = self::get_faqs();
        if ( ! $faqs ) return;

        $items = array();
        foreach ( $faqs as $f ) {
            $items[] = array(
                '@type'          => 'Question',
                'name'           => $f->post_title,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $f->post_content ),
                ),
            );
        }
        echo '<script type="application/ld+json">' . wp_json_encode( array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ), JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
}

Unirad_CRO::init();
