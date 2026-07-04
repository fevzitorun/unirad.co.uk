<?php
/**
 * Plugin Name: Unirad Automation
 * Description: Otomasyon merkezi — scan sonrası follow-up emailleri, ödeme hatası akışı, dispute uyarısı, haftalık site audit, aylık gelir raporu, Brevo senkronizasyonu.
 * Version:     1.0.0
 * Author:      Unirad
 *
 * Akışlar (unirad-claude-code-master-prompt.md Katman 2):
 *   AKIŞ 3 — Scan sonrası follow-up: +1g rapor bildirimi, +3g review talebi,
 *            +30g sağlık hatırlatması, +365g yıllık tarama önerisi
 *   AKIŞ 4 — Ödeme başarısız: hasta retry linki + admin bildirimi
 *   AKIŞ 5 — Dispute uyarısı: webhook + 48 saat cevap hatırlatması
 *   Haftalık audit: SSL süresi, güncellemeler, sipariş akışı, DB boyutu
 *   Aylık gelir özeti: hizmet bazlı döküm, direktöre email
 *
 * Not: Booking onayı (AKIŞ 1) ve callback (AKIŞ 2) unirad-quick-booking-v2
 * içinde zaten mevcut — burada tekrarlanmaz.
 */

defined( 'ABSPATH' ) || exit;

date_default_timezone_set( 'Europe/London' );

if ( ! defined( 'UA_BREVO_API_KEY' ) ) define( 'UA_BREVO_API_KEY', ( get_option( 'unirad_dash_settings', [] )['brevo_key'] ?? '' ) );
if ( ! defined( 'UA_NOTIFY_EMAIL' ) )  define( 'UA_NOTIFY_EMAIL', 'booking@unirad.co.uk' );
if ( ! defined( 'UA_DIRECTOR_EMAIL' ) ) define( 'UA_DIRECTOR_EMAIL', 'booking@unirad.co.uk' ); // Ayla Hanım'ın adresi ile değiştirilebilir
if ( ! defined( 'UA_REVIEW_URL' ) )    define( 'UA_REVIEW_URL', 'https://g.page/r/unirad/review' ); // Google review linki — GBP'den gerçek link ile güncelle

class Unirad_Automation {

    const LOG_TABLE = 'unirad_auto_log';

    public static function init() {
        register_activation_hook( __FILE__,   array( __CLASS__, 'activate' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

        // WP çekirdeğinde 'monthly' aralığı yok
        add_filter( 'cron_schedules', function ( $s ) {
            $s['monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Once Monthly' );
            return $s;
        } );

        // Cron tetikleyicileri
        add_action( 'unirad_auto_daily',   array( __CLASS__, 'run_daily_followups' ) );
        add_action( 'unirad_auto_weekly',  array( __CLASS__, 'run_weekly_audit' ) );
        add_action( 'unirad_auto_monthly', array( __CLASS__, 'run_monthly_revenue' ) );

        // AKIŞ 4 — Ödeme başarısız
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'payment_failed_flow' ), 10, 1 );

        // AKIŞ 5 — Dispute (WooPayments hook + REST webhook yedeği)
        add_action( 'woocommerce_payments_dispute_created', array( __CLASS__, 'dispute_flow' ), 10, 1 );
        add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_routes' ) );
        add_action( 'unirad_auto_dispute_reminder', array( __CLASS__, 'dispute_reminder' ), 10, 2 );

        // Brevo senkronizasyon — sipariş tamamlanınca contact güncelle
        add_action( 'woocommerce_order_status_completed',  array( __CLASS__, 'sync_brevo_contact' ), 30, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'sync_brevo_contact' ), 30, 1 );
    }

    /* ── Kurulum ─────────────────────────────────────────────────────────── */

    public static function activate() {
        global $wpdb;
        $t = $wpdb->prefix . self::LOG_TABLE;
        $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$t}` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            flow VARCHAR(40) NOT NULL,
            ref_id BIGINT UNSIGNED NOT NULL,
            sent_at DATETIME NOT NULL,
            UNIQUE KEY flow_ref (flow, ref_id)
        ) " . $wpdb->get_charset_collate() );

        if ( ! wp_next_scheduled( 'unirad_auto_daily' ) )   wp_schedule_event( strtotime( 'tomorrow 08:00' ), 'daily', 'unirad_auto_daily' );
        if ( ! wp_next_scheduled( 'unirad_auto_weekly' ) )  wp_schedule_event( strtotime( 'next monday 07:00' ), 'weekly', 'unirad_auto_weekly' );
        if ( ! wp_next_scheduled( 'unirad_auto_monthly' ) ) wp_schedule_event( strtotime( 'first day of next month 07:30' ), 'monthly', 'unirad_auto_monthly' );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'unirad_auto_daily' );
        wp_clear_scheduled_hook( 'unirad_auto_weekly' );
        wp_clear_scheduled_hook( 'unirad_auto_monthly' );
    }

    /* ── Yardımcılar ─────────────────────────────────────────────────────── */

    private static function brevo_send( $to_email, $to_name, $subject, $html_body ) {
        if ( ! UA_BREVO_API_KEY || ! is_email( $to_email ) ) return false;
        $res = wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
            'timeout' => 15,
            'headers' => array(
                'api-key'      => UA_BREVO_API_KEY,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'sender'      => array( 'name' => 'Unirad Imaging', 'email' => 'info@unirad.co.uk' ),
                'to'          => array( array( 'email' => $to_email, 'name' => $to_name ?: $to_email ) ),
                'subject'     => $subject,
                'htmlContent' => $html_body,
            ) ),
        ) );
        return ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) < 300;
    }

    /** Aynı akış + referans için ikinci email atılmasını engeller. */
    private static function already_sent( $flow, $ref_id ) {
        global $wpdb;
        $t = $wpdb->prefix . self::LOG_TABLE;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$t}` WHERE flow = %s AND ref_id = %d LIMIT 1", $flow, $ref_id
        ) );
    }

    private static function mark_sent( $flow, $ref_id ) {
        global $wpdb;
        $t = $wpdb->prefix . self::LOG_TABLE;
        $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO `{$t}` (flow, ref_id, sent_at) VALUES (%s, %d, %s)",
            $flow, $ref_id, current_time( 'mysql' )
        ) );
    }

    private static function wrap( $inner ) {
        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#0d1f3c">'
            . '<div style="background:#003057;padding:18px 24px;border-radius:8px 8px 0 0"><span style="color:#fff;font-size:20px;font-weight:800">unirad</span></div>'
            . '<div style="padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px">' . $inner . '</div>'
            . '<p style="font-size:11px;color:#9aadbe;text-align:center;margin-top:12px">Unirad Imaging · 22 Loanbank Quadrant, Glasgow G51 3HZ · 0141 846 9116</p>'
            . '</div>';
    }

    /* ── AKIŞ 3: Scan sonrası follow-up ──────────────────────────────────── */

    public static function run_daily_followups() {
        global $wpdb;
        $bt = $wpdb->prefix . 'bookly_appointments';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" ) ) return;

        // Geçmiş randevular + müşteri bilgisi (son 400 gün — yıllık hatırlatma penceresi)
        $rows = $wpdb->get_results(
            "SELECT a.id, a.start_date, a.custom_service_name,
                    c.full_name, c.first_name, c.last_name, c.email
             FROM `{$bt}` a
             JOIN {$wpdb->prefix}bookly_customer_appointments ca ON ca.appointment_id = a.id
             JOIN {$wpdb->prefix}bookly_customers c ON c.id = ca.customer_id
             WHERE a.start_date BETWEEN DATE_SUB(NOW(), INTERVAL 400 DAY) AND NOW()
               AND c.email != ''", ARRAY_A
        );

        foreach ( $rows as $r ) {
            $days = (int) floor( ( time() - strtotime( $r['start_date'] ) ) / 86400 );
            $name = trim( $r['full_name'] ?: ( $r['first_name'] . ' ' . $r['last_name'] ) ) ?: 'Patient';
            $first = explode( ' ', $name )[0];
            $scan = $r['custom_service_name'] ?: 'MRI scan';
            $id   = (int) $r['id'];

            if ( $days >= 1 && $days < 3 && ! self::already_sent( 'fu_report', $id ) ) {
                self::brevo_send( $r['email'], $name, 'Your Unirad Report Is On Its Way',
                    self::wrap( "<p>Dear {$first},</p><p>Thank you for attending your <strong>" . esc_html( $scan ) . "</strong> at Unirad Imaging. Your images are being reviewed by our consultant radiologist and your report will be delivered within 5 working days.</p><p>If you have any questions in the meantime, simply reply to this email or call us on <strong>0141 846 9116</strong>.</p><p>Best wishes,<br>The Unirad Team</p>" ) );
                self::mark_sent( 'fu_report', $id );
            }

            if ( $days >= 3 && $days < 7 && ! self::already_sent( 'fu_review', $id ) ) {
                self::brevo_send( $r['email'], $name, 'How Was Your Experience at Unirad?',
                    self::wrap( "<p>Dear {$first},</p><p>We hope your visit to Unirad Imaging went smoothly. Your feedback helps other patients find fast, affordable private imaging in Glasgow.</p><p style='text-align:center;margin:20px 0'><a href='" . esc_url( UA_REVIEW_URL ) . "' style='background:#009B8D;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold'>Leave a Google Review</a></p><p>It takes less than a minute and means a great deal to our small team.</p><p>Thank you,<br>The Unirad Team</p>" ) );
                self::mark_sent( 'fu_review', $id );
            }

            if ( $days >= 30 && $days < 37 && ! self::already_sent( 'fu_health', $id ) ) {
                self::brevo_send( $r['email'], $name, 'A Quick Check-In From Unirad',
                    self::wrap( "<p>Dear {$first},</p><p>It's been a month since your <strong>" . esc_html( $scan ) . "</strong>. We hope you're doing well and that your report was helpful for your next steps.</p><p>If your GP or consultant has recommended follow-up imaging, we offer same-week appointments — no waiting lists.</p><p><a href='https://unirad.co.uk/book/' style='color:#009B8D;font-weight:bold'>Book a follow-up scan &rarr;</a></p><p>Best wishes,<br>The Unirad Team</p>" ) );
                self::mark_sent( 'fu_health', $id );
            }

            if ( $days >= 365 && $days < 372 && ! self::already_sent( 'fu_annual', $id ) ) {
                self::brevo_send( $r['email'], $name, 'It Has Been a Year — Annual Health Check Reminder',
                    self::wrap( "<p>Dear {$first},</p><p>A year has passed since your scan at Unirad Imaging. Many of our patients choose an annual check-up for peace of mind — our Full Body MRI packages start from <strong>&pound;640</strong>.</p><p><a href='https://unirad.co.uk/services/our-pricing/' style='color:#009B8D;font-weight:bold'>View packages &rarr;</a></p><p>Best wishes,<br>The Unirad Team</p>" ) );
                self::mark_sent( 'fu_annual', $id );
            }
        }
    }

    /* ── AKIŞ 4: Ödeme başarısız ─────────────────────────────────────────── */

    public static function payment_failed_flow( $order_id ) {
        if ( self::already_sent( 'pay_failed', $order_id ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email = $order->get_billing_email();
        $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $first = $order->get_billing_first_name() ?: 'there';
        $retry = $order->get_checkout_payment_url();

        self::brevo_send( $email, $name, 'Payment Issue — Your Unirad Booking Is Not Confirmed Yet',
            self::wrap( "<p>Dear {$first},</p><p>Unfortunately your payment for order <strong>#{$order_id}</strong> did not go through, so your appointment is <strong>not yet confirmed</strong>.</p><p style='text-align:center;margin:20px 0'><a href='" . esc_url( $retry ) . "' style='background:#009B8D;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold'>Retry Payment</a></p><p>Your selected time slot is held for a limited time. If you need help, call us on <strong>0141 846 9116</strong>.</p><p>The Unirad Team</p>" ) );

        self::brevo_send( UA_NOTIFY_EMAIL, 'Unirad Team', '[Payment Failed] Order #' . $order_id . ' - ' . $name,
            self::wrap( "<p><strong>Ödeme başarısız.</strong></p><p>Order: #{$order_id}<br>Hasta: " . esc_html( $name ) . "<br>Email: " . esc_html( $email ) . "<br>Telefon: " . esc_html( $order->get_billing_phone() ) . "<br>Tutar: &pound;" . $order->get_total() . "</p><p>Hasta retry linki aldı. Slot çakışmasını önlemek için takip edin.</p>" ) );

        self::mark_sent( 'pay_failed', $order_id );
    }

    /* ── AKIŞ 5: Dispute ─────────────────────────────────────────────────── */

    public static function register_webhook_routes() {
        register_rest_route( 'unirad/v1', '/dispute', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'dispute_webhook' ),
            'permission_callback' => function ( $req ) {
                $secret = get_option( 'unirad_auto_webhook_secret', '' );
                return $secret && hash_equals( $secret, (string) $req->get_header( 'X-Unirad-Secret' ) );
            },
        ) );
    }

    public static function dispute_webhook( WP_REST_Request $req ) {
        $order_id = absint( $req->get_param( 'order_id' ) );
        self::dispute_flow( array( 'order_id' => $order_id, 'reason' => sanitize_text_field( $req->get_param( 'reason' ) ?? '' ) ) );
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    public static function dispute_flow( $dispute ) {
        $order_id = is_array( $dispute ) ? ( $dispute['order_id'] ?? 0 ) : 0;
        if ( is_object( $dispute ) && method_exists( $dispute, 'get_order_id' ) ) $order_id = $dispute->get_order_id();
        $reason = is_array( $dispute ) ? ( $dispute['reason'] ?? '' ) : '';

        if ( $order_id && self::already_sent( 'dispute', $order_id ) ) return;

        $detail = $order_id ? 'Order #' . $order_id : 'Order bilinmiyor';
        self::brevo_send( UA_NOTIFY_EMAIL, 'Unirad Team', '🔴 [DISPUTE] Acil — ' . $detail,
            self::wrap( "<p style='color:#b91c1c'><strong>Ödeme itirazı (dispute) açıldı!</strong></p><p>{$detail}" . ( $reason ? '<br>Sebep: ' . esc_html( $reason ) : '' ) . "</p><p><strong>48 saat içinde</strong> WooPayments panelinden kanıt yüklenmiş olmalı: randevu onayı, hasta formu, hizmet kaydı.</p>" ) );

        if ( $order_id ) {
            self::mark_sent( 'dispute', $order_id );
            // 24 saat sonra cevap hatırlatması
            wp_schedule_single_event( time() + DAY_IN_SECONDS, 'unirad_auto_dispute_reminder', array( $order_id, $detail ) );
        }
    }

    public static function dispute_reminder( $order_id, $detail ) {
        self::brevo_send( UA_NOTIFY_EMAIL, 'Unirad Team', '⏰ [DISPUTE Hatırlatma] 24 saat kaldı — ' . $detail,
            self::wrap( "<p><strong>Dispute cevap süresi dolmak üzere.</strong></p><p>{$detail} için kanıt yüklendi mi? Son 24 saat.</p>" ) );
    }

    /* ── Haftalık audit ──────────────────────────────────────────────────── */

    public static function run_weekly_audit() {
        global $wpdb;
        $lines = array();

        // SSL süresi
        $ssl = @stream_socket_client( 'ssl://unirad.co.uk:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true ) ) ) );
        if ( $ssl ) {
            $cert = openssl_x509_parse( stream_context_get_params( $ssl )['options']['ssl']['peer_certificate'] );
            $days_left = (int) floor( ( $cert['validTo_time_t'] - time() ) / 86400 );
            $lines[] = ( $days_left < 30 ? '🔴' : '🟢' ) . " SSL sertifikası: {$days_left} gün kaldı";
        } else {
            $lines[] = '🔴 SSL kontrolü başarısız: ' . $errstr;
        }

        // Güncelleme bekleyen plugin sayısı
        $updates = get_site_transient( 'update_plugins' );
        $n_upd   = ! empty( $updates->response ) ? count( $updates->response ) : 0;
        $lines[] = ( $n_upd > 5 ? '🟡' : '🟢' ) . " Güncelleme bekleyen plugin: {$n_upd}";

        // Son 7 gün sipariş akışı
        if ( function_exists( 'wc_get_orders' ) ) {
            $orders = wc_get_orders( array( 'limit' => -1, 'date_created' => '>' . ( time() - 7 * DAY_IN_SECONDS ), 'status' => array( 'wc-processing', 'wc-completed' ), 'return' => 'ids' ) );
            $n = count( $orders );
            $lines[] = ( $n === 0 ? '🔴' : '🟢' ) . " Son 7 gün başarılı sipariş: {$n}" . ( $n === 0 ? ' — BOOKING AKIŞI KONTROL EDİLMELİ' : '' );
        }

        // DB boyutu
        $size_mb = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) FROM information_schema.tables WHERE table_schema=%s", DB_NAME
        ) );
        $lines[] = ( $size_mb > 2000 ? '🟡' : '🟢' ) . " Veritabanı boyutu: {$size_mb} MB";

        // Bekleyen ödeme siparişleri
        if ( function_exists( 'wc_get_orders' ) ) {
            $pending = count( wc_get_orders( array( 'limit' => -1, 'status' => array( 'wc-pending', 'wc-on-hold' ), 'return' => 'ids' ) ) );
            $lines[] = ( $pending > 3 ? '🟡' : '🟢' ) . " Ödeme bekleyen sipariş: {$pending}";
        }

        self::brevo_send( UA_NOTIFY_EMAIL, 'Unirad Team', '[Haftalık Audit] unirad.co.uk — ' . date_i18n( 'j M Y' ),
            self::wrap( '<h3 style="margin-top:0">Haftalık Site Sağlık Raporu</h3><p>' . implode( '<br>', $lines ) . '</p>' ) );
    }

    /* ── Aylık gelir özeti ───────────────────────────────────────────────── */

    public static function run_monthly_revenue() {
        if ( ! function_exists( 'wc_get_orders' ) ) return;

        $start = strtotime( 'first day of last month 00:00' );
        $end   = strtotime( 'last day of last month 23:59:59' );
        $orders = wc_get_orders( array(
            'limit'        => -1,
            'status'       => array( 'wc-processing', 'wc-completed' ),
            'date_created' => $start . '...' . $end,
        ) );

        $total = 0; $by_service = array(); $n = 0;
        foreach ( $orders as $order ) {
            $total += (float) $order->get_total();
            $n++;
            foreach ( $order->get_items() as $item ) {
                $label = $item->get_meta( 'Scan Details' ) ?: $item->get_name();
                // Çoklu bölge etiketlerini tek kalemde topla
                $key = ( strpos( $label, ',' ) !== false ) ? 'Multi-region (' . ( substr_count( $label, ',' ) + 1 ) . ' parts)' : $label;
                if ( ! isset( $by_service[ $key ] ) ) $by_service[ $key ] = array( 'n' => 0, 'sum' => 0 );
                $by_service[ $key ]['n']++;
                $by_service[ $key ]['sum'] += (float) $order->get_total();
            }
        }
        arsort( $by_service );

        $rows = '';
        foreach ( $by_service as $svc => $d ) {
            $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #eee">' . esc_html( $svc ) . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:center">' . $d['n'] . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:right">&pound;' . number_format( $d['sum'] ) . '</td></tr>';
        }

        $month_name = date_i18n( 'F Y', $start );
        self::brevo_send( UA_DIRECTOR_EMAIL, 'Unirad Director', '[Aylık Gelir Raporu] ' . $month_name,
            self::wrap( '<h3 style="margin-top:0">' . $month_name . ' Gelir Özeti</h3>'
                . '<p style="font-size:22px;font-weight:800;color:#009B8D">&pound;' . number_format( $total ) . '</p>'
                . '<p>' . $n . ' başarılı sipariş · Ortalama sipariş: &pound;' . ( $n ? number_format( $total / $n ) : 0 ) . '</p>'
                . '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr><th style="text-align:left;padding:6px;border-bottom:2px solid #003057">Hizmet</th><th style="padding:6px;border-bottom:2px solid #003057">Adet</th><th style="text-align:right;padding:6px;border-bottom:2px solid #003057">Gelir</th></tr>' . $rows . '</table>' ) );
    }

    /* ── Brevo contact senkronizasyonu ───────────────────────────────────── */

    public static function sync_brevo_contact( $order_id ) {
        if ( ! UA_BREVO_API_KEY || self::already_sent( 'brevo_sync', $order_id ) ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        $email = $order->get_billing_email();
        if ( ! is_email( $email ) ) return;

        $scan = '';
        foreach ( $order->get_items() as $item ) {
            $scan = $item->get_meta( 'Scan Details' ) ?: $item->get_name();
            break;
        }

        wp_remote_post( 'https://api.brevo.com/v3/contacts', array(
            'timeout' => 15,
            'headers' => array( 'api-key' => UA_BREVO_API_KEY, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'email'         => $email,
                'updateEnabled' => true,
                'attributes'    => array(
                    'FIRSTNAME'      => $order->get_billing_first_name(),
                    'LASTNAME'       => $order->get_billing_last_name(),
                    'SMS'            => $order->get_billing_phone(),
                    'LAST_SCAN'      => $scan,
                    'LAST_ORDER'     => (string) $order_id,
                    'LAST_ORDER_GBP' => (float) $order->get_total(),
                    'PATIENT'        => true,
                ),
            ) ),
        ) );
        self::mark_sent( 'brevo_sync', $order_id );
    }
}

Unirad_Automation::init();

/* ── Ayarlar: webhook secret üretimi ─────────────────────────────────────── */
add_action( 'admin_init', function () {
    if ( ! get_option( 'unirad_auto_webhook_secret' ) ) {
        update_option( 'unirad_auto_webhook_secret', wp_generate_password( 40, false ) );
    }
} );
