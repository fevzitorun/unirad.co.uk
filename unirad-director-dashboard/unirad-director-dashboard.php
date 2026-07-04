<?php
/**
 * Plugin Name: Unirad Director Dashboard
 * Description: Direktör paneli — bugünkü randevular ve gelir, haftalık takvim, boş slotlar, bekleyen ödemeler ve uyarılar tek ekranda.
 * Version:     1.0.0
 * Author:      Unirad
 */

defined( 'ABSPATH' ) || exit;

date_default_timezone_set( 'Europe/London' );

class Unirad_Director_Dashboard {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }

    public static function menu() {
        add_menu_page(
            'Unirad Dashboard',
            '📊 Unirad Dashboard',
            'manage_options',
            'unirad-director',
            array( __CLASS__, 'render' ),
            'dashicons-chart-area',
            2
        );
    }

    /* ── Veri toplama ────────────────────────────────────────────────────── */

    private static function todays_appointments() {
        global $wpdb;
        $bt = $wpdb->prefix . 'bookly_appointments';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" ) ) return array();
        return $wpdb->get_results(
            "SELECT a.start_date, a.end_date, a.custom_service_name, a.custom_service_price,
                    c.full_name, c.first_name, c.last_name, c.phone
             FROM `{$bt}` a
             LEFT JOIN {$wpdb->prefix}bookly_customer_appointments ca ON ca.appointment_id = a.id
             LEFT JOIN {$wpdb->prefix}bookly_customers c ON c.id = ca.customer_id
             WHERE DATE(a.start_date) = CURDATE()
             ORDER BY a.start_date", ARRAY_A
        );
    }

    private static function week_appointments() {
        global $wpdb;
        $bt = $wpdb->prefix . 'bookly_appointments';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" ) ) return array();
        return $wpdb->get_results(
            "SELECT DATE(a.start_date) d, COUNT(*) n, SUM(a.custom_service_price) rev
             FROM `{$bt}` a
             WHERE a.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(a.start_date) ORDER BY d", ARRAY_A
        );
    }

    private static function revenue_today() {
        if ( ! function_exists( 'wc_get_orders' ) ) return array( 0, 0 );
        $orders = wc_get_orders( array(
            'limit'        => -1,
            'status'       => array( 'wc-processing', 'wc-completed' ),
            'date_created' => '>' . strtotime( 'today 00:00' ),
        ) );
        $sum = 0;
        foreach ( $orders as $o ) $sum += (float) $o->get_total();
        return array( count( $orders ), $sum );
    }

    private static function pending_orders() {
        if ( ! function_exists( 'wc_get_orders' ) ) return array();
        return wc_get_orders( array(
            'limit'  => 20,
            'status' => array( 'wc-pending', 'wc-on-hold', 'wc-failed' ),
            'date_created' => '>' . strtotime( '-14 days' ),
        ) );
    }

    private static function month_revenue() {
        if ( ! function_exists( 'wc_get_orders' ) ) return array( 0, 0 );
        $orders = wc_get_orders( array(
            'limit'        => -1,
            'status'       => array( 'wc-processing', 'wc-completed' ),
            'date_created' => '>' . strtotime( 'first day of this month 00:00' ),
        ) );
        $sum = 0;
        foreach ( $orders as $o ) $sum += (float) $o->get_total();
        return array( count( $orders ), $sum );
    }

    /* ── Render ──────────────────────────────────────────────────────────── */

    public static function render() {
        $today                = self::todays_appointments();
        list( $n_o, $rev_o )  = self::revenue_today();
        list( $n_m, $rev_m )  = self::month_revenue();
        $pending              = self::pending_orders();
        $week                 = self::week_appointments();
        ?>
        <div class="wrap" style="max-width:1100px">
            <h1 style="font-weight:800">📊 Unirad Dashboard <span style="font-size:13px;color:#787c82;font-weight:400"><?php echo esc_html( date_i18n( 'l, j F Y' ) ); ?></span></h1>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0">
                <?php
                self::card( 'Bugünkü Randevu', count( $today ), '#003057' );
                self::card( 'Bugünkü Gelir', '£' . number_format( $rev_o ), '#009B8D' );
                self::card( 'Bu Ay Gelir', '£' . number_format( $rev_m ) . " <span style='font-size:12px'>({$n_m} sipariş)</span>", '#009B8D' );
                self::card( 'Bekleyen Ödeme', count( $pending ), count( $pending ) > 3 ? '#b91c1c' : '#787c82' );
                ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

                <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px">
                    <h2 style="margin-top:0">📅 Bugünkü Randevular</h2>
                    <?php if ( ! $today ) : ?>
                        <p style="color:#787c82">Bugün randevu yok.</p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th>Saat</th><th>Hasta</th><th>Tarama</th><th>£</th></tr></thead>
                            <tbody>
                            <?php foreach ( $today as $a ) :
                                $name = trim( $a['full_name'] ?: ( $a['first_name'] . ' ' . $a['last_name'] ) );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( substr( $a['start_date'], 11, 5 ) . '–' . substr( $a['end_date'], 11, 5 ) ); ?></td>
                                    <td><?php echo esc_html( $name ?: '—' ); ?><br><small><?php echo esc_html( $a['phone'] ); ?></small></td>
                                    <td><?php echo esc_html( $a['custom_service_name'] ?: 'MRI' ); ?></td>
                                    <td><?php echo $a['custom_service_price'] ? '£' . number_format( (float) $a['custom_service_price'] ) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px">
                    <h2 style="margin-top:0">🗓️ Önümüzdeki 7 Gün</h2>
                    <?php if ( ! $week ) : ?>
                        <p style="color:#787c82">Önümüzdeki hafta randevu yok — <strong>takvim boş, pazarlama aksiyonu gerekebilir.</strong></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th>Gün</th><th>Randevu</th><th>Beklenen Gelir</th></tr></thead>
                            <tbody>
                            <?php foreach ( $week as $w ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( 'D, j M', strtotime( $w['d'] ) ) ); ?></td>
                                    <td><?php echo (int) $w['n']; ?></td>
                                    <td>£<?php echo number_format( (float) $w['rev'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;grid-column:1/-1">
                    <h2 style="margin-top:0">🔔 Bekleyen / Başarısız Ödemeler (son 14 gün)</h2>
                    <?php if ( ! $pending ) : ?>
                        <p style="color:#00a32a">✓ Bekleyen ödeme yok.</p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th>Order</th><th>Hasta</th><th>Durum</th><th>Tutar</th><th>Tarih</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ( $pending as $o ) : ?>
                                <tr>
                                    <td>#<?php echo $o->get_id(); ?></td>
                                    <td><?php echo esc_html( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ); ?></td>
                                    <td><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></td>
                                    <td>£<?php echo number_format( (float) $o->get_total() ); ?></td>
                                    <td><?php echo esc_html( $o->get_date_created()->date_i18n( 'j M H:i' ) ); ?></td>
                                    <td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>" class="button button-small">Aç</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
    }

    private static function card( $label, $value, $color ) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;text-align:center">'
            . '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#787c82">' . esc_html( $label ) . '</div>'
            . '<div style="font-size:26px;font-weight:800;color:' . esc_attr( $color ) . ';margin-top:6px">' . wp_kses_post( $value ) . '</div>'
            . '</div>';
    }
}

Unirad_Director_Dashboard::init();
