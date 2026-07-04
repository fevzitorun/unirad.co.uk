<?php
/**
 * Plugin Name: Unirad SEO Boost
 * Description: MedicalBusiness + MedicalProcedure schema markup, 404 → 301 redirect haritası, Microsoft Clarity entegrasyonu.
 * Version:     1.0.0
 * Author:      Unirad
 */

defined( 'ABSPATH' ) || exit;

class Unirad_SEO_Boost {

    /** Search Console 404 listesi → 301 hedefleri (2026-07-02 taraması) */
    private static $redirects = array(
        '/product/chest-thorax-mri-scan/'                    => '/services/',
        '/product/gp-consultation-post-mri-health-review/'   => '/services/',
        '/product/gp-consultation-with-mri-scan-booking/'    => '/services/',
        '/quick-mri-glasgow-v2/'                             => '/',
        '/yedek_home/'                                       => '/',
        '/home-2/'                                           => '/',
        '/home3/'                                            => '/',
        '/events-grid-style/'                                => '/',
        '/events/locations/'                                 => '/',
        '/appointment-test/'                                 => '/book/',
        '/appointment/gp-details/'                           => '/book/',
        '/appointment/quick-mri-glasgow/'                    => '/book/',
    );

    public static function init() {
        add_action( 'template_redirect', array( __CLASS__, 'handle_redirects' ), 1 );
        add_action( 'wp_head', array( __CLASS__, 'inject_schema' ), 5 );
        add_action( 'wp_head', array( __CLASS__, 'inject_clarity' ), 20 );
    }

    /* ── 301 Redirects ───────────────────────────────────────────────────── */

    public static function handle_redirects() {
        if ( ! is_404() ) return;
        $path = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );
        if ( isset( self::$redirects[ $path ] ) ) {
            wp_redirect( home_url( self::$redirects[ $path ] ), 301 );
            exit;
        }
    }

    /* ── Schema Markup ───────────────────────────────────────────────────── */

    public static function inject_schema() {
        // Site geneli: MedicalBusiness (sadece ana sayfada tam blok)
        if ( is_front_page() ) {
            $biz = array(
                '@context'          => 'https://schema.org',
                '@type'             => 'MedicalBusiness',
                '@id'               => 'https://unirad.co.uk/#organization',
                'name'              => 'Unirad Imaging',
                'url'               => 'https://unirad.co.uk',
                'logo'              => 'https://unirad.co.uk/wp-content/uploads/unirad-logo.png',
                'image'             => 'https://unirad.co.uk/wp-content/uploads/unirad-clinic.jpg',
                'telephone'         => '+441418469116',
                'email'             => 'info@unirad.co.uk',
                'priceRange'        => '££',
                'medicalSpecialty'  => 'Radiology',
                'address'           => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '22 Loanbank Quadrant',
                    'addressLocality' => 'Glasgow',
                    'postalCode'      => 'G51 3HZ',
                    'addressCountry'  => 'GB',
                ),
                'geo' => array(
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => 55.8642,
                    'longitude' => -4.3300,
                ),
                'areaServed'   => array( 'Glasgow', 'Scotland' ),
                'openingHoursSpecification' => array(
                    array(
                        '@type'     => 'OpeningHoursSpecification',
                        'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
                        'opens'     => '09:30',
                        'closes'    => '20:00',
                    ),
                ),
            );
            echo '<script type="application/ld+json">' . wp_json_encode( $biz, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }

        // Ürün sayfaları: MedicalProcedure
        if ( function_exists( 'is_product' ) && is_product() ) {
            global $post;
            $product = wc_get_product( $post->ID );
            if ( ! $product ) return;

            $name = $product->get_name();
            $proc = array(
                '@context'      => 'https://schema.org',
                '@type'         => 'MedicalProcedure',
                'name'          => $name,
                'procedureType' => 'https://schema.org/NoninvasiveProcedure',
                'howPerformed'  => 'MRI scan performed on a modern wide-bore scanner at Unirad Imaging, Glasgow. Consultant radiologist report within 5 working days.',
                'preparation'   => 'No special preparation required for most scans. Remove all metallic items before scanning.',
                'followup'      => 'Report delivered within 5 working days; optional GP consultation available.',
                'provider'      => array( '@id' => 'https://unirad.co.uk/#organization' ),
                'offers'        => array(
                    '@type'         => 'Offer',
                    'price'         => $product->get_price(),
                    'priceCurrency' => 'GBP',
                    'availability'  => 'https://schema.org/InStock',
                    'url'           => get_permalink( $post->ID ),
                ),
            );
            echo '<script type="application/ld+json">' . wp_json_encode( $proc, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }
    }

    /* ── Microsoft Clarity ───────────────────────────────────────────────── */

    public static function inject_clarity() {
        $clarity_id = get_option( 'unirad_clarity_id', '' );
        if ( ! $clarity_id || is_admin() ) return;
        ?>
        <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "<?php echo esc_js( $clarity_id ); ?>");
        </script>
        <?php
    }
}

Unirad_SEO_Boost::init();

/* ── Ayar sayfası: Clarity ID ────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_options_page( 'SEO Boost', 'SEO Boost', 'manage_options', 'unirad-seo-boost', function () {
        if ( isset( $_POST['usb_save'] ) && check_admin_referer( 'usb_settings' ) ) {
            update_option( 'unirad_clarity_id', sanitize_text_field( $_POST['usb_clarity'] ?? '' ) );
            echo '<div class="notice notice-success"><p>Kaydedildi.</p></div>';
        }
        $cid = get_option( 'unirad_clarity_id', '' );
        ?>
        <div class="wrap">
            <h1>Unirad SEO Boost</h1>
            <form method="post">
                <?php wp_nonce_field( 'usb_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="usb_clarity">Microsoft Clarity Project ID</label></th>
                        <td>
                            <input type="text" id="usb_clarity" name="usb_clarity" value="<?php echo esc_attr( $cid ); ?>" class="regular-text" placeholder="örn: abcd1234ef" />
                            <p class="description">clarity.microsoft.com → proje oluştur → Settings → Project ID. Boşsa Clarity yüklenmez.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button name="usb_save" class="button-primary">Kaydet</button></p>
            </form>
            <h2>Aktif 301 Redirect'ler (12)</h2>
            <p>Search Console 404 hataları bu plugin ile otomatik yönlendiriliyor — ayrıca Redirection plugin'e gerek yok.</p>
            <h2>Schema Markup</h2>
            <p>✓ Ana sayfa: MedicalBusiness &nbsp;·&nbsp; ✓ Ürün sayfaları: MedicalProcedure + Offer</p>
        </div>
        <?php
    } );
} );
