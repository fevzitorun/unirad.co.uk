<?php
/**
 * Plugin Name: Unirad Quick Booking V2 - Pro Edition
 * Description: 3-step booking flow with Bookly slots, Brevo email, deposit/callback, GP upload.
 * Version: 4.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Force London timezone
date_default_timezone_set( 'Europe/London' );

// ============================================================
// CONFIGURATION — edit these values as needed
// ============================================================
// Brevo key shared with Email Dashboard — set via WP Admin → 📧 Email Dashboard → ⚙️ API Keys
if ( ! defined( 'UQB_BREVO_API_KEY' ) )     define( 'UQB_BREVO_API_KEY', ( get_option( 'unirad_dash_settings', [] )['brevo_key'] ?? '' ) );
if ( ! defined( 'UQB_NOTIFY_EMAIL' ) )      define( 'UQB_NOTIFY_EMAIL',        'booking@unirad.co.uk' );
if ( ! defined( 'UQB_BOOKLY_STAFF_ID' ) )   define( 'UQB_BOOKLY_STAFF_ID',     6 );
if ( ! defined( 'UQB_BOOKLY_SERVICE_ID' ) ) define( 'UQB_BOOKLY_SERVICE_ID',   38 );

// WooCommerce product ID → Bookly service ID lookup
function uqb_get_bookly_service_id( $wc_product_id ) {
    $map = array(
        1332=>1, 1464=>2, 1465=>3, 1473=>7, 2659=>9, 2656=>11, 1481=>12, 1482=>13,
        3562=>16, 2632=>19, 2686=>22, 2640=>24, 2643=>26, 3669=>29, 2646=>32, 2663=>35,
        2665=>38, 2667=>41, 2669=>44, 3583=>47, 2673=>50, 2684=>56, 2677=>59, 2679=>62,
        3482=>63, 3483=>64, 3484=>65, 3485=>66, 3486=>67, 3490=>68, 3491=>69, 3493=>70,
        3495=>71, 3496=>72, 3497=>73, 3498=>74, 3499=>75, 3500=>76, 3559=>77, 3560=>78,
        3561=>79, 3564=>80, 3565=>81, 3566=>82, 3567=>83, 3568=>84, 3569=>85, 3668=>86,
        3571=>87, 3572=>88, 3573=>89, 3574=>90, 3575=>91, 3576=>92, 3577=>93, 3578=>94,
        3580=>95, 3581=>96, 3582=>97, 3584=>98, 3587=>100, 3588=>101, 3589=>102, 3590=>103,
        3591=>104, 3592=>105, 3593=>106, 3594=>107, 3595=>108, 3596=>109, 4818=>160,
        4575=>161, 5141=>162, 5234=>163, 5235=>164, 5236=>165, 5237=>166, 5238=>167,
        5239=>168, 5240=>169, 5241=>170, 5242=>171, 5243=>172, 5245=>174, 5244=>175,
        5248=>176, 5253=>177, 6387=>179, 6388=>179, 6424=>180, 7329=>181,
    );
    return isset( $map[ (int)$wc_product_id ] ) ? $map[ (int)$wc_product_id ] : UQB_BOOKLY_SERVICE_ID;
}
// ============================================================

if ( ! function_exists( 'uqb_row' ) ) {
    function uqb_row( $label, $value ) {
        $v = $value ? esc_html( $value ) : '-';
        return '<div style="padding:7px 0;border-bottom:1px solid #eee"><span style="display:inline-block;width:160px;font-weight:bold;color:#3a4d68;vertical-align:top">' . $label . ':</span><span style="color:#0d1f3c">' . $v . '</span></div>';
    }
}
if ( ! function_exists( 'uqb_section' ) ) {
    function uqb_section( $title ) {
        return '<div style="background:#0d1f3c;color:#ffffff;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;padding:6px 10px;margin-top:16px">' . $title . '</div>';
    }
}

// ── Scan duration calculator ──────────────────────────────────────────────────
// Rules (minutes):
//   Silver package            → 180 (3h)
//   Gold / Platinum package   → 240 (4h)
//   Bilateral (BTH) scan      →  90 (1.5h)
//   4 standard regions        → 180 (3h)
//   3 standard regions        → 120 (2h)
//   2 standard regions        →  90 (1.5h)
//   Abdomen + standard combo  →  90 (1.5h, handled by count ≥ 2)
//   Single standard / Abdomen →  60 (1h)
if ( ! function_exists( 'uqb_calc_duration' ) ) {
    function uqb_calc_duration( $scan_label ) {
        if ( stripos( $scan_label, 'Silver'   ) !== false ) return 180;
        if ( stripos( $scan_label, 'Gold'     ) !== false ) return 240;
        if ( stripos( $scan_label, 'Platinum' ) !== false ) return 240;

        // Split on comma to count distinct body-part entries
        $parts = array_filter( array_map( 'trim', explode( ',', $scan_label ) ) );
        $count = count( $parts );

        // Detect bilateral in any entry → 1.5h
        foreach ( $parts as $part ) {
            if ( stripos( $part, '(Both)'    ) !== false ||
                 stripos( $part, 'Bilateral' ) !== false ||
                 stripos( $part, ' BTH'      ) !== false ) {
                return 90;
            }
        }

        // Four distinct regions → 3h
        if ( $count >= 4 ) return 180;

        // Three distinct regions → 2h
        if ( $count >= 3 ) return 120;

        // Two distinct regions → 1.5h
        if ( $count >= 2 ) return 90;

        // Single region (including Abdomen alone) → 1h
        return 60;
    }
}

class Unirad_Quick_Booking_V2 {

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_shortcode( 'quick_booking_v2', array( __CLASS__, 'shortcode' ) );

        add_action( 'wp_ajax_unirad_qb2_slots',             array( __CLASS__, 'ajax_slots' ) );
        add_action( 'wp_ajax_nopriv_unirad_qb2_slots',      array( __CLASS__, 'ajax_slots' ) );
        add_action( 'wp_ajax_unirad_qb2_checkout',          array( __CLASS__, 'ajax_checkout' ) );
        add_action( 'wp_ajax_nopriv_unirad_qb2_checkout',   array( __CLASS__, 'ajax_checkout' ) );
        add_action( 'wp_ajax_unirad_qb2_callback',          array( __CLASS__, 'ajax_callback' ) );
        add_action( 'wp_ajax_nopriv_unirad_qb2_callback',   array( __CLASS__, 'ajax_callback' ) );

        add_filter( 'woocommerce_get_item_data',                   array( __CLASS__, 'get_item_data' ),      10, 2 );
        add_action( 'woocommerce_before_calculate_totals',         array( __CLASS__, 'filter_cart_price' ),    20, 1 );
        add_filter( 'woocommerce_cart_item_name',                   array( __CLASS__, 'filter_cart_item_name' ), 10, 3 );
        add_filter( 'woocommerce_order_item_name',                  array( __CLASS__, 'filter_order_item_name' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item',  array( __CLASS__, 'rename_order_line_item' ), 20, 4 );

        // ── Bookly calendar: show custom_service_name instead of service title ──
        add_filter( 'bookly_appointment_title',        array( __CLASS__, 'bookly_calendar_title' ), 10, 2 );
        add_action( 'admin_head',                          array( __CLASS__, 'bookly_admin_calendar_css' ) );
        add_filter( 'bookly_calendar_appointment_data',array( __CLASS__, 'bookly_calendar_appointment_data' ), 10, 2 );
        add_action( 'woocommerce_new_order_item',                   array( __CLASS__, 'rename_new_order_item' ), 99, 3 );
        add_action( 'woocommerce_order_status_processing',         array( __CLASS__, 'write_to_bookly' ),      10, 1 );
        add_action( 'woocommerce_order_status_completed',          array( __CLASS__, 'write_to_bookly' ),      10, 1 );
        add_action( 'woocommerce_order_status_on-hold',            array( __CLASS__, 'write_to_bookly' ),      10, 1 );
        add_action( 'woocommerce_payment_complete',                array( __CLASS__, 'write_to_bookly' ),      10, 1 );
        add_action( 'woocommerce_checkout_order_processed',        array( __CLASS__, 'write_to_bookly_checkout' ), 20, 3 );
        add_action( 'woocommerce_checkout_create_order',           array( __CLASS__, 'save_booking_to_order' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_payment_complete',               array( __CLASS__, 'send_patient_confirmation' ), 20, 1 );
        add_action( 'woocommerce_order_status_processing',          array( __CLASS__, 'send_patient_confirmation' ), 20, 1 );

        // ── Abandoned Booking Recovery ────────────────────────
        add_action( 'wp_ajax_unirad_qb2_save_lead',        array( __CLASS__, 'ajax_save_lead' ) );
        add_action( 'wp_ajax_nopriv_unirad_qb2_save_lead', array( __CLASS__, 'ajax_save_lead' ) );
        add_action( 'uqb_abandoned_recovery_cron',         array( __CLASS__, 'send_abandoned_recovery_emails' ) );
        if ( ! wp_next_scheduled( 'uqb_abandoned_recovery_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'uqb_abandoned_recovery_cron' );
        }
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
    }

    // ── Brevo transactional email ────────────────────────────
    private static function brevo_send( $to_email, $to_name, $subject, $html_body ) {
        $api_key = UQB_BREVO_API_KEY;

        // Wrap in full HTML document — required for proper rendering in all email clients
        $full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($subject) . '</title></head><body style="margin:0;padding:20px;background:#f4f6f9;font-family:Arial,sans-serif">' . $html_body . '</body></html>';

        if ( empty( $api_key ) ) {
            // No Brevo key set — fall back to wp_mail
            $headers = array( 'Content-Type: text/html; charset=UTF-8' );
            wp_mail( $to_email, $subject, $full_html, $headers );
            return;
        }
        $payload = array(
            'sender'      => array( 'name' => 'Unirad Imaging', 'email' => 'booking@unirad.co.uk' ),
            'to'          => array( array( 'email' => $to_email, 'name' => $to_name ) ),
            'subject'     => $subject,
            'htmlContent' => $full_html,
            'textContent' => wp_strip_all_tags( $html_body ),
        );
        wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
            'headers' => array(
                'api-key'      => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ) );
    }

    // ── Bookly available slots from DB ───────────────────────
    public static function ajax_slots() {
        check_ajax_referer( 'uqb2_nonce', 'nonce' );
        global $wpdb;

        $date      = sanitize_text_field( isset( $_POST['date'] ) ? $_POST['date'] : date('Y-m-d') );
        // Duration of the scan being booked (minutes) — sent by JS, used for forward-look conflict check
        $scan_dur  = isset( $_POST['duration'] ) ? (int) $_POST['duration'] : 60;
        $scan_dur  = max( 30, min( 240, $scan_dur ) ); // clamp to valid range
        $staff_id  = (int) UQB_BOOKLY_STAFF_ID;
        $dow_map   = array( 'sunday','monday','tuesday','wednesday','thursday','friday','saturday' );
        $dow       = $dow_map[ (int) date( 'w', strtotime( $date ) ) ];

        // Get staff working hours for this day
        $schedule  = $wpdb->get_row( $wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}bookly_staff_schedule_items
             WHERE staff_id = %d AND day_index = %s AND start_time IS NOT NULL",
            $staff_id, $dow
        ) );

        $slots = array();

        // ── Clinic schedule rules ─────────────────────────────────────────
        // Mon(1) / Wed(3) / Fri(5): 09:30 – 19:30 (half-hour slots)
        // Tue(2) / Thu(4):          09:30 – 16:30 (half-hour slots)
        // Sat(6) / Sun(7):          CLOSED
        //
        // Lead time rules:
        //   TODAY:    earliest = now + 6 hours (rounded up to next half hour)
        //   TOMORROW: earliest = 11:00
        //   AFTER:    earliest = 09:30
        // ─────────────────────────────────────────────────────────────────

        $tz       = new DateTimeZone( 'Europe/London' );
        $now      = new DateTime( 'now', $tz );
        $today    = $now->format( 'Y-m-d' );
        $tomorrow = ( new DateTime( 'tomorrow', $tz ) )->format( 'Y-m-d' );

        // UK Bank Holidays — return empty slots
        $bank_holidays = array(
            '2026-01-01', // New Year's Day
            '2026-04-03', // Good Friday
            '2026-04-06', // Easter Monday
            // '2026-05-04' — Early May Bank Holiday — OPEN
            // '2026-05-25' — Spring Bank Holiday — OPEN
            '2026-08-31', // Summer Bank Holiday
            '2026-12-25', // Christmas Day
            '2026-12-26', // Boxing Day
            '2025-12-25', '2025-12-26', '2025-01-01',
        );
        if ( in_array( $date, $bank_holidays ) ) {
            wp_send_json_success( array( 'slots' => array() ) );
            return;
        }

        // Day-of-week → last slot (minutes from midnight, 0 = closed)
        // Mon/Wed/Fri: last slot 19:30, Tue/Thu: last slot 16:30
        $dow_num = (int) date( 'N', strtotime( $date ) ); // 1=Mon … 7=Sun
        // end_mins = minute value of LAST slot + 1 (exclusive)
        // Mon/Wed/Fri: 10:00–19:00 (last slot 19:00=1140, exclusive end 1170)
        // Tue/Thu:     10:00–16:00 (last slot 16:00=960,  exclusive end 990)
        // Saturday:    10:00–15:00 (last slot 15:00=900,  exclusive end 930)
        // Sunday:      closed
        $day_end_mins = array( 1=>1170, 2=>990, 3=>1170, 4=>990, 5=>1170, 6=>930, 7=>0 );
        $end_mins = isset( $day_end_mins[$dow_num] ) ? $day_end_mins[$dow_num] : 0;

        if ( $end_mins === 0 ) {
            wp_send_json_success( array( 'slots' => array() ) );
            return;
        }

        // Start slot in minutes from midnight
        if ( $date === $today ) {
            // 3-hour lead time in London time, round up to next :30 slot
            $earliest = clone $now;
            $earliest->modify( '+3 hours' );
            $e_mins = (int)$earliest->format('H') * 60 + (int)$earliest->format('i');
            // Round up to next :30 boundary (09:30, 10:30, 11:30...)
            $remainder = $e_mins % 60;
            if ( $remainder <= 30 ) {
                $e_mins = $e_mins - $remainder + 30;
            } else {
                $e_mins = $e_mins - $remainder + 90; // next hour :30
            }
            $start_mins = max( 600, $e_mins ); // minimum 10:00
            // If earliest available slot is past the last slot of the day, today is full
            if ( $start_mins > $end_mins - 30 ) {
                wp_send_json_success( array( 'slots' => array() ) );
                return;
            }
        } elseif ( $date === $tomorrow ) {
            // If order placed before 14:00 London time → tomorrow starts 09:30 (or 10:00 for Saturday)
            // If order placed at/after 14:00 London time → tomorrow starts 11:30
            $now_hour  = (int)$now->format('H');
            $now_min   = (int)$now->format('i');
            $now_total = $now_hour * 60 + $now_min;
            $start_mins = ( $now_total < 840 ) ? 600 : 690; // before 14:00 → 10:00, after → 11:30
        } else {
            // All days start at 10:00
            $start_mins = 600;
        }

        // ── Get booked slots from Bookly ──────────────────────────────────
        $booked = array();
        // ── Check all possible Bookly table names ────────────────────────
        $possible_bt = array(
            $wpdb->prefix . 'bookly_appointments',
            $wpdb->prefix . 'ab_appointments',
            $wpdb->prefix . 'bookly_calendar',
        );
        foreach ( $possible_bt as $bt ) {
            $bt_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" );
            if ( $bt_exists ) {
                // Get start + end times to block ALL hours a multi-hour appointment spans
                $appts = $wpdb->get_results( $wpdb->prepare(
                    "SELECT start_date, end_date FROM `{$bt}`
                     WHERE DATE(start_date) = %s",
                    $date
                ) );
                if ( $appts ) {
                    foreach ( $appts as $appt ) {
                        // Bookly stores local time — read directly
                        $s_m = (int)substr($appt->start_date, 11, 2) * 60 + (int)substr($appt->start_date, 14, 2);
                        $e_m = (int)substr($appt->end_date,   11, 2) * 60 + (int)substr($appt->end_date,   14, 2);
                        // 30-min granularity: block every half-hour slot that overlaps the appointment
                        for ( $bm = 570; $bm < 1200; $bm += 30 ) {
                            if ( $bm < $e_m && ( $bm + 30 ) > $s_m ) {
                                $booked[] = sprintf( '%02d:%02d', intdiv($bm,60), $bm%60 );
                            }
                        }
                    }
                }
                break; // found the right table
            }
        }

        // ── Get booked slots from WooCommerce ────────────────────────────
        try {
            if ( function_exists( 'wc_get_orders' ) ) {
                $orders = wc_get_orders( array(
                    'limit'        => 200,
                    'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
                    'meta_key'     => '_uqb_booking_date',
                    'meta_value'   => $date,
                ) );
                foreach ( $orders as $order ) {
                    foreach ( $order->get_items() as $item ) {
                        $appt         = $item->get_meta( 'Appointment' );
                        $scan_details = $item->get_meta( 'Scan Details' );
                        if ( $appt && strpos( $appt, $date ) === 0 ) {
                            $p = explode( ' ', trim( $appt ) );
                            if ( isset( $p[1] ) ) {
                                // Block all 30-min slots covered by this appointment's duration
                                list( $bh, $bmin ) = array_pad( explode( ':', $p[1] ), 2, '00' );
                                $t_m  = (int) $bh * 60 + (int) $bmin;
                                $dur  = function_exists( 'uqb_calc_duration' ) ? uqb_calc_duration( $scan_details ) : 60;
                                for ( $bm = $t_m; $bm < $t_m + $dur; $bm += 30 ) {
                                    $booked[] = sprintf( '%02d:%02d', intdiv( $bm, 60 ), $bm % 60 );
                                }
                            }
                        }
                    }
                }
            }
        } catch ( Exception $e ) {
            // WC query failed — continue with Bookly data only
        }

        $booked = array_unique( $booked );

        // ── Convert booked times to minutes for forward-look check ────────
        $booked_mins = array();
        foreach ( $booked as $bt ) {
            $bp = explode( ':', $bt );
            $booked_mins[] = (int)$bp[0] * 60 + (int)( isset($bp[1]) ? $bp[1] : 0 );
        }
        $booked_mins = array_unique( $booked_mins );

        // ── Generate half-hour slots ──────────────────────────────────────
        // Generate slots every 30 minutes
        $now_mins = (int)$now->format('H') * 60 + (int)$now->format('i');

        for ( $m = $start_mins; $m < $end_mins; $m += 30 ) {
            $t = sprintf( '%02d:%02d', intdiv($m,60), $m%60 );

            // For TODAY: completely hide slots that are in the past
            if ( $date === $today && $m <= $now_mins ) {
                continue; // skip — don't show past slots at all
            }

            // Forward-look conflict check:
            // All 30-min blocks this scan would occupy must be free.
            // e.g. a 60-min scan at 11:00 needs 11:00 AND 11:30 both free.
            $available = true;
            for ( $check = $m; $check < $m + $scan_dur; $check += 30 ) {
                if ( in_array( $check, $booked_mins ) ) {
                    $available = false;
                    break;
                }
            }

            $slots[]   = array( 'time' => $t, 'available' => $available );
        }

        // Filter slots locked by transient (race condition protection)
        $locked_slots = get_transient( 'uqb_locked_' . $date );
        if ( is_array( $locked_slots ) ) {
            foreach ( $slots as &$slot ) {
                if ( in_array( $slot['time'], $locked_slots ) ) {
                    $slot['available'] = false;
                }
            }
            unset( $slot );
        }

        wp_send_json_success( array( 'slots' => array_values( $slots ) ) );
    }

    // ── Callback (no payment) ────────────────────────────────
    public static function ajax_callback() {
        check_ajax_referer( 'uqb2_nonce', 'nonce' );

        $pt = array(
            'title'    => sanitize_text_field(    isset( $_POST['pt_title']    ) ? $_POST['pt_title']    : '' ),
            'first'    => sanitize_text_field(    isset( $_POST['pt_first']    ) ? $_POST['pt_first']    : '' ),
            'last'     => sanitize_text_field(    isset( $_POST['pt_last']     ) ? $_POST['pt_last']     : '' ),
            'dob'      => sanitize_text_field(    isset( $_POST['pt_dob']      ) ? $_POST['pt_dob']      : '' ),
            'phone'    => sanitize_text_field(    isset( $_POST['pt_phone']    ) ? $_POST['pt_phone']    : '' ),
            'email'    => sanitize_email(         isset( $_POST['pt_email']    ) ? $_POST['pt_email']    : '' ),
            'reason'   => sanitize_textarea_field(isset( $_POST['pt_reason']   ) ? $_POST['pt_reason']   : '' ),
            'referral' => sanitize_text_field(    isset( $_POST['pt_referral'] ) ? $_POST['pt_referral'] : 'self' ),
            'gp_name'  => sanitize_text_field(    isset( $_POST['pt_gp_name']  ) ? $_POST['pt_gp_name']  : '' ),
            'gp_phone' => sanitize_text_field(    isset( $_POST['pt_gp_phone'] ) ? $_POST['pt_gp_phone'] : '' ),
        );

        $sq_keys = array('sq_prev_mri','sq_pregnant','sq_pacemaker','sq_metal_body','sq_items','sq_implants_detail','sq_surgeries','sq_height_unit','sq_height_cm','sq_weight_kg','sq_height_ft','sq_height_in','sq_weight_st','sq_weight_lb');
        $sq = array();
        foreach ( $sq_keys as $k ) {
            $sq[$k] = sanitize_textarea_field( isset( $_POST[$k] ) ? $_POST[$k] : '' );
        }

        $scan  = sanitize_text_field( isset( $_POST['scan_label'] ) ? $_POST['scan_label'] : '' );
        $price = sanitize_text_field( isset( $_POST['scan_price'] ) ? $_POST['scan_price'] : '' );
        $full  = trim( $pt['title'] . ' ' . $pt['first'] . ' ' . $pt['last'] );

        // Height/weight string
        $hw = '';
        if ( isset($sq['sq_height_unit']) && $sq['sq_height_unit'] === 'imperial' ) {
            $hw = $sq['sq_height_ft'] . 'ft ' . $sq['sq_height_in'] . 'in / ' . $sq['sq_weight_st'] . 'st ' . $sq['sq_weight_lb'] . 'lb';
        } else {
            $hw = $sq['sq_height_cm'] . ' cm / ' . $sq['sq_weight_kg'] . ' kg';
        }

        $ref_label = $pt['referral'] === 'gp' ? 'GP / Clinician Referral' : 'Self Referral (18+)';

        // ── Clinic email ─────────────────────────────────────
        $s  = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#0d1f3c;max-width:580px">';
        $s .= '<div style="background:#00a896;padding:16px 14px">';
        $s .= '<div style="color:#fff;font-size:17px;font-weight:bold">New Callback Request</div>';
        $s .= '<div style="color:#d0f5f0;font-size:11px;margin-top:3px">Unirad Private MRI Glasgow</div>';
        $s .= '</div>';

        $s .= uqb_section('Scan Details');
        $s .= '<div style="padding:4px 10px">';
        $s .= uqb_row('Scan', $scan);
        $s .= uqb_row('Price', $price);
        $s .= uqb_row('Payment', 'Callback — no payment taken');
        $s .= '</div>';

        $s .= uqb_section('Patient Details');
        $s .= '<div style="padding:4px 10px">';
        $s .= uqb_row('Name', $full);
        $s .= uqb_row('Date of Birth', $pt['dob']);
        $s .= '<div style="padding:7px 0;border-bottom:1px solid #eee;background:#fffbe6"><span style="display:inline-block;width:160px;font-weight:bold;color:#3a4d68;vertical-align:top">Phone:</span><span style="color:#0d1f3c;font-weight:bold">' . esc_html($pt['phone']) . '</span></div>';
        $s .= uqb_row('Email', $pt['email']);
        $s .= uqb_row('Reason for Scan', $pt['reason']);
        $s .= uqb_row('Referral Type', $ref_label);
        if ( $pt['referral'] === 'gp' ) {
            $s .= uqb_row('GP Name', $pt['gp_name']);
            $s .= uqb_row('GP Phone', $pt['gp_phone']);
        }
        $s .= '</div>';

        $s .= uqb_section('MRI Safety Questionnaire');
        $s .= '<div style="padding:4px 10px">';
        $s .= uqb_row('Previous MRI', isset($sq['sq_prev_mri']) ? $sq['sq_prev_mri'] : '');
        $s .= uqb_row('Pregnant', isset($sq['sq_pregnant']) ? $sq['sq_pregnant'] : '');
        $s .= uqb_row('Pacemaker / Implants', isset($sq['sq_pacemaker']) ? $sq['sq_pacemaker'] : '');
        if ( ! empty($sq['sq_implants_detail']) ) $s .= uqb_row('Implant Detail', $sq['sq_implants_detail']);
        $s .= uqb_row('Metal in Body', isset($sq['sq_metal_body']) ? $sq['sq_metal_body'] : '');
        $s .= uqb_row('Items Worn', ! empty($sq['sq_items']) ? $sq['sq_items'] : 'None');
        $s .= uqb_row('Previous Surgeries', isset($sq['sq_surgeries']) ? $sq['sq_surgeries'] : '');
        $s .= uqb_row('Height / Weight', $hw);
        $s .= '</div>';

        $s .= '<div style="background:#fff8ed;border-left:4px solid #f59e0b;padding:12px 14px;margin-top:16px">';
        $s .= '<div style="font-weight:bold;color:#92400e;font-size:13px">ACTION REQUIRED</div>';
        $s .= '<div style="color:#92400e;margin-top:4px">1. Call patient on <strong>' . esc_html($pt['phone']) . '</strong> to confirm appointment.</div>';
        $s .= '<div style="color:#92400e;margin-top:4px">2. Send questionnaire link if not yet completed: <a href="https://unirad.co.uk/appointment/patient-safety-and-clinical-questionnaire/" style="color:#92400e">https://unirad.co.uk/appointment/patient-safety-and-clinical-questionnaire/</a></div>';
        $s .= '</div>';
        $s .= '</div>';

        self::brevo_send( UQB_NOTIFY_EMAIL, 'Unirad Team', 'Callback: ' . $full . ' - ' . $scan, $s );

        // ── Patient confirmation email ────────────────────────
        if ( $pt['email'] ) {
            $p  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0d1f3c;max-width:560px">';
            $p .= '<div style="background:#00a896;padding:18px 20px">';
            $p .= '<div style="color:#fff;font-size:18px;font-weight:bold">Unirad Private MRI</div>';
            $p .= '<div style="color:#d0f5f0;font-size:11px;margin-top:3px">Glasgow &middot; unirad.co.uk</div>';
            $p .= '</div>';
            $p .= '<div style="padding:20px">';
            $p .= '<p style="margin:0 0 14px">Dear <strong>' . esc_html($pt['title'] . ' ' . $pt['first']) . '</strong>,</p>';
            $p .= '<p style="margin:0 0 14px">Thank you for your enquiry about <strong>' . esc_html($scan) . '</strong>. We have received your callback request and a member of our team will be in touch shortly to arrange your appointment.</p>';
            $p .= '<div style="background:#f0fdfa;border-left:4px solid #00a896;padding:12px 14px;margin-bottom:16px">';
            $p .= '<div style="margin-bottom:6px"><strong>Scan:</strong> ' . esc_html($scan) . '</div>';
            $p .= '<div><strong>Payment:</strong> No payment required at this stage</div>';
            $p .= '</div>';
            // Questionnaire link box
            $p .= '<div style="background:#fff8ed;border:1px solid #fcd34d;border-radius:8px;padding:14px 16px;margin-bottom:16px">';
            $p .= '<div style="font-weight:bold;color:#92400e;font-size:13px;margin-bottom:6px">&#x1F4CB; Action Required Before Your Appointment</div>';
            $p .= '<p style="margin:0 0 10px;font-size:13px;color:#555">Once your appointment is confirmed, please complete your MRI Safety Questionnaire. This helps our radiologist prepare for your scan.</p>';
            $p .= '<a href="' . esc_url( 'https://unirad.co.uk/appointment/patient-safety-and-clinical-questionnaire/' ) . '" style="display:inline-block;background:#00a896;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:13px">Complete MRI Safety Questionnaire &rarr;</a>';
            $p .= '</div>';

            $p .= '<p style="margin:0 0 6px;color:#555;font-size:13px">If you have any questions in the meantime, please do not hesitate to contact us:</p>';
            $p .= '<p style="margin:0;font-size:13px;line-height:2">';
            $p .= '<strong>Tel: 0141 846 9116</strong><br>';
            $p .= 'Email: <a href="mailto:booking@unirad.co.uk" style="color:#00a896">booking@unirad.co.uk</a><br>';
            $p .= 'Web: <a href="https://unirad.co.uk" style="color:#00a896">unirad.co.uk</a>';
            $p .= '</p>';
            $p .= '</div>';
            $p .= '<div style="background:#f7f9fc;padding:10px 20px;font-size:11px;color:#9aadbe;border-top:1px solid #dce4ef">';
            $p .= 'Unirad Private MRI Glasgow | Est-Health Ltd.';
            $p .= '</div>';
            $p .= '</div>';

            self::brevo_send( $pt['email'], $full, 'Your Unirad Callback Request - ' . $scan, $p );
        }

        $cb_date = sanitize_text_field( isset( $_POST['date'] ) ? $_POST['date'] : '' );
        $cb_time = sanitize_text_field( isset( $_POST['time'] ) ? $_POST['time'] : '' );
        if ( $cb_date && $cb_time ) {
            global $wpdb;
            $bt = $wpdb->prefix . 'bookly_appointments';
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $bt ) ) === $bt ) {
                $sdt = $cb_date . ' ' . $cb_time . ':00';
                $edt = date( 'Y-m-d H:i:s', strtotime( $sdt ) + 3600 );
                $ex  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$bt} WHERE start_date=%s AND staff_id=%d LIMIT 1", $sdt, UQB_BOOKLY_STAFF_ID ) );
                if ( ! $ex ) {
                    $now2 = current_time( 'mysql' );
                    $wpdb->insert( $bt, array(
                        'staff_id'             => UQB_BOOKLY_STAFF_ID,
                        'staff_any'            => 0,
                        'service_id'           => UQB_BOOKLY_SERVICE_ID,
                        'start_date'           => $sdt,
                        'end_date'             => $edt,
                        'extras_duration'      => 0,
                        'custom_service_name'  => '[CALLBACK] ' . $scan,
                        'custom_service_price' => 0,
                        'created_from'         => 'bookly',
                        'created_at'           => $now2,
                        'updated_at'           => $now2,
                    ) );
                }
            }
        }
        // Mark lead as callback so it won't receive recovery emails
        if ( $pt['email'] ) {
            global $wpdb;
            $lt = $wpdb->prefix . 'unirad_potential_bookings';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $lt ) ) === $lt ) {
                $wpdb->update(
                    $lt,
                    array( 'status' => 'callback' ),
                    array( 'email' => $pt['email'], 'status' => 'pending' ),
                    array( '%s' ),
                    array( '%s', '%s' )
                );
            }
        }

        wp_send_json_success( array( 'msg' => 'ok' ) );
    }

    // ── WooCommerce checkout ─────────────────────────────────
    public static function ajax_checkout() {
        check_ajax_referer( 'uqb2_nonce', 'nonce' );

        $items    = json_decode( stripslashes( isset( $_POST['items'] ) ? $_POST['items'] : '[]' ), true );
        $date     = sanitize_text_field( isset( $_POST['date'] )     ? $_POST['date']     : '' );
        $time     = sanitize_text_field( isset( $_POST['time'] )     ? $_POST['time']     : '' );
        $mode     = sanitize_text_field( isset( $_POST['mode'] )     ? $_POST['mode']     : 'mri' );
        $pay_mode = sanitize_text_field( isset( $_POST['pay_mode'] ) ? $_POST['pay_mode'] : 'full' );

        $pt = array(
            'title'    => sanitize_text_field(    isset( $_POST['pt_title']   ) ? $_POST['pt_title']   : '' ),
            'first'    => sanitize_text_field(    isset( $_POST['pt_first']   ) ? $_POST['pt_first']   : '' ),
            'last'     => sanitize_text_field(    isset( $_POST['pt_last']    ) ? $_POST['pt_last']    : '' ),
            'dob'      => sanitize_text_field(    isset( $_POST['pt_dob']     ) ? $_POST['pt_dob']     : '' ),
            'phone'    => sanitize_text_field(    isset( $_POST['pt_phone']   ) ? $_POST['pt_phone']   : '' ),
            'email'    => sanitize_email(         isset( $_POST['pt_email']   ) ? $_POST['pt_email']   : '' ),
            'reason'   => sanitize_textarea_field(isset( $_POST['pt_reason']  ) ? $_POST['pt_reason']  : '' ),
            'referral' => sanitize_text_field(    isset( $_POST['pt_ref']     ) ? $_POST['pt_ref']     : 'self' ),
            'gp_name'  => sanitize_text_field(    isset( $_POST['pt_gp_name'] ) ? $_POST['pt_gp_name'] : '' ),
            'gp_phone' => sanitize_text_field(    isset( $_POST['pt_gp_phone']) ? $_POST['pt_gp_phone']: '' ),
        );

        $sq_keys = array('sq_prev_mri','sq_pregnant','sq_pacemaker','sq_metal_body','sq_items','sq_implants_detail','sq_surgeries','sq_height_unit','sq_height_cm','sq_weight_kg','sq_height_ft','sq_height_in','sq_weight_st','sq_weight_lb');
        $sq = array();
        foreach ( $sq_keys as $k ) {
            $sq[$k] = sanitize_textarea_field( isset( $_POST[$k] ) ? $_POST[$k] : '' );
        }

        // ── Server-side lead time check (3-hour minimum) ──────────
        // Re-validate at submission time — prevents stale slots being booked
        if ( $date && $time && $pay_mode !== 'callback' ) {
            $tz          = new DateTimeZone( 'Europe/London' );
            $now_check   = new DateTime( 'now', $tz );
            $appt_dt     = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
            if ( $appt_dt ) {
                $diff_mins = (int) ( ( $appt_dt->getTimestamp() - $now_check->getTimestamp() ) / 60 );
                if ( $diff_mins < 180 ) { // less than 3 hours away
                    wp_send_json_error( array(
                        'msg' => 'Sorry, this time slot is no longer available. Bookings must be made at least 3 hours in advance. Please select a later time.',
                    ) );
                    return;
                }
            }
        }
        // ──────────────────────────────────────────────────────────

        $catalog = self::catalog();

        if ( $mode === 'mri' ) {
            $special_prices = array('breast'=>420,'cardiac'=>575,'prostate'=>495,'small_bowel'=>495,'abdomen_pelvis'=>455);
            $parts = 0; $labels = array(); $special_total = 0;
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    $iid  = sanitize_text_field( isset( $item['id'] )   ? $item['id']   : '' );
                    $side = sanitize_text_field( isset( $item['side'] ) ? $item['side'] : '' );
                    $name = sanitize_text_field( isset( $item['name'] ) ? $item['name'] : '' );
                    $labels[] = $name . ( $side ? ' (' . $side . ')' : '' );
                    if ( array_key_exists( $iid, $special_prices ) ) {
                        $special_total += $special_prices[$iid];
                    } else {
                        $parts += ( $side === 'Both' || $side === 'Bilateral' ) ? 2 : 1;
                    }
                }
            }
            $parts      = min( $parts, 4 );
            $pid_key    = $parts > 0 ? $parts : 1;
            $bp_price   = ( $parts > 0 && isset( $catalog['bundle_prices'][$parts] ) ) ? $catalog['bundle_prices'][$parts] : 0;
            $total_price = $bp_price + $special_total;
            $product_id = isset( $catalog['bundles'][$pid_key] ) ? $catalog['bundles'][$pid_key] : $catalog['bundles'][1];
            $scan_label = implode( ', ', $labels );
        } else {
            $item       = isset( $items[0] ) ? $items[0] : array();
            $product_id = intval( isset( $item['id'] ) ? $item['id'] : 0 );
            $scan_label = sanitize_text_field( isset( $item['name'] ) ? $item['name'] : '' );
        }

        if ( ! $product_id ) { wp_send_json_error( array( 'msg' => 'Invalid product' ) ); return; }

        // Server-side lead capture (fallback if JS fire failed)
        if ( $pt['email'] && $scan_label ) {
            $price_str = isset( $total_price ) ? '£' . number_format( (float) $total_price, 0 ) : '';
            self::log_potential_lead( trim( $pt['first'] . ' ' . $pt['last'] ), $pt['email'], $pt['phone'], $scan_label, $price_str );
        }

        // Deposit option removed — always use full price product
        // $pay_mode === 'deposit' is no longer a valid option

        $cart_meta = array(
            'uqb_booking_date' => $date,
            'uqb_booking_time' => $time,
            'uqb_scan_details' => $scan_label,
            'uqb_pay_mode'     => $pay_mode,
            'uqb_callback'     => 'no',
            'uqb_patient'      => $pt,
            'uqb_safety'       => $sq,
        );

        WC()->cart->empty_cart();
        if ( isset( $total_price ) && $total_price > 0 ) { WC()->session->set( 'uqb_custom_price', $total_price ); }
        // Store booking details in session for reliable Bookly write
        WC()->session->set( 'uqb_booking_date', $date );
        WC()->session->set( 'uqb_booking_time', $time );
        WC()->session->set( 'uqb_scan_label', $scan_label );
        WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_meta );
        wp_send_json_success( array( 'redirect' => wc_get_checkout_url() ) );
    }

    // ── Post-payment booking confirmation email ──────────────
    // Patient confirmation email - fires on payment_complete or order processing
    public static function send_patient_confirmation( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        if ( $order->get_meta( '_uqb_patient_email_sent' ) ) return;

        foreach ( $order->get_items() as $item ) {
            $scan_label = $item->get_meta( 'Scan Details' );
            if ( ! $scan_label ) continue;

            $bdate = $item->get_meta( '_uqb_date' );
            $btime = $item->get_meta( '_uqb_time' );

            if ( ! $bdate || ! $btime ) {
                $appt = $item->get_meta( 'Appointment' );
                if ( $appt ) {
                    $p = explode( ' ', trim($appt) );
                    if ( count($p) >= 2 ) { $bdate = $p[0]; $btime = $p[1]; }
                }
            }

            if ( ! $bdate ) $bdate = $order->get_meta( '_uqb_booking_date' );
            if ( ! $btime ) $btime = $order->get_meta( '_uqb_booking_time' );

            $pt_name  = $item->get_meta( 'Patient Name' );
            $pt_email = $item->get_meta( 'Email' );
            if ( ! $pt_email ) $pt_email = $order->get_billing_email();
            if ( ! $pt_email ) continue;

            $name_parts = explode( ' ', trim($pt_name), 3 );
            $first_name = isset($name_parts[1]) ? $name_parts[1] : ( isset($name_parts[0]) ? $name_parts[0] : 'Patient' );
            $full = $pt_name ? $pt_name : $order->get_formatted_billing_full_name();
            $date_fmt = $bdate ? date( 'l, jS F Y', strtotime($bdate) ) : 'Please check your booking';
            $time_fmt = $btime ? $btime : 'Please check your booking';

            $e  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0d1f3c;max-width:600px">';
            $e .= '<div style="background:#00a896;padding:18px 22px">';
            $e .= '<div style="color:#fff;font-size:19px;font-weight:bold">Unirad Private MRI</div>';
            $e .= '<div style="color:#d0f5f0;font-size:11px;margin-top:3px">Glasgow &middot; unirad.co.uk</div>';
            $e .= '</div>';
            $e .= '<div style="padding:22px">';
            $e .= '<p style="margin:0 0 14px">Dear <strong>' . esc_html($first_name) . '</strong>,</p>';
            $e .= '<p style="margin:0 0 16px">Thank you for booking your MRI scan with Unirad Imaging. Your appointment is confirmed.</p>';

            $e .= '<div style="background:#f0fdfa;border:1px solid #a7f3d0;border-radius:8px;padding:16px;margin-bottom:20px">';
            $e .= '<div style="font-size:14px;font-weight:bold;color:#065f46;margin-bottom:10px">&#x2705; Your Appointment is Confirmed</div>';
            $e .= '<table style="width:100%;border-collapse:collapse">';
            $e .= '<tr><td style="padding:5px 0;color:#555;width:100px"><strong>Date:</strong></td><td style="padding:5px 0;font-weight:bold;color:#0d1f3c">' . esc_html($date_fmt) . '</td></tr>';
            $e .= '<tr><td style="padding:5px 0;color:#555"><strong>Time:</strong></td><td style="padding:5px 0;font-weight:bold;color:#0d1f3c">' . esc_html($time_fmt) . '</td></tr>';
            $e .= '<tr><td style="padding:5px 0;color:#555"><strong>Scan:</strong></td><td style="padding:5px 0;color:#0d1f3c">' . esc_html($scan_label) . '</td></tr>';
            $e .= '<tr><td style="padding:5px 0;color:#555"><strong>Location:</strong></td><td style="padding:5px 0;color:#0d1f3c">22 Loanbank Quadrant, Glasgow G51 3HZ</td></tr>';
            $e .= '</table>';
            $e .= '</div>';

            $e .= '<div style="background:#fffbe6;border:1px solid #fcd34d;border-radius:8px;padding:16px;margin-bottom:20px">';
            $e .= '<div style="font-size:14px;font-weight:bold;color:#92400e;margin-bottom:10px">Important — Please Read Before Your Appointment</div>';
            $e .= '<p style="margin:0 0 8px;font-size:13px"><strong>Please bring:</strong></p>';
            $e .= '<ul style="margin:0 0 12px;padding-left:18px;font-size:13px;color:#555;line-height:2">';
            $e .= '<li>Valid <strong>photo ID</strong> (passport or driving licence)</li>';
            $e .= '<li>Arrive <strong>10-15 minutes early</strong> to complete your safety forms</li>';
            $e .= '</ul>';
            $e .= '<p style="margin:0 0 8px;font-size:13px"><strong>Remove ALL metallic items before your scan:</strong></p>';
            $e .= '<ul style="margin:0 0 12px;padding-left:18px;font-size:12px;color:#555;line-height:1.9">';
            $e .= '<li>All jewellery (rings, necklaces, bracelets, watches, piercings)</li>';
            $e .= '<li>Mobile phones, keys, smartwatches</li>';
            $e .= '<li>Clothing with metal zips, buttons or underwires</li>';
            $e .= '<li>Hearing aids, glasses, hairpins</li>';
            $e .= '</ul>';
            $e .= '<p style="margin:0 0 8px;font-size:13px"><strong>Tell our staff immediately if you have:</strong></p>';
            $e .= '<ul style="margin:0;padding-left:18px;font-size:12px;color:#555;line-height:1.9">';
            $e .= '<li>A pacemaker or any heart/brain implant</li>';
            $e .= '<li>Any metal implants, surgical clips, plates or screws</li>';
            $e .= '<li>Cochlear implants or inner-ear prosthetics</li>';
            $e .= '<li>Any possibility of pregnancy</li>';
            $e .= '</ul>';
            $e .= '</div>';

            $e .= '<p style="margin:0 0 6px;font-size:13px">Questions? We are here to help:</p>';
            $e .= '<p style="margin:0;font-size:13px;line-height:2"><strong>Tel: 0141 846 9116</strong><br>';
            $e .= 'Email: <a href="mailto:booking@unirad.co.uk" style="color:#00a896">booking@unirad.co.uk</a><br>';
            $e .= 'Address: 22 Loanbank Quadrant, Glasgow G51 3HZ</p>';
            $e .= '</div>';
            $e .= '<div style="background:#f7f9fc;padding:10px 22px;font-size:11px;color:#9aadbe;border-top:1px solid #dce4ef">';
            $e .= 'Unirad Private MRI Glasgow | Est-Health Ltd.';
            $e .= '</div>';
            $e .= '</div>';

            self::brevo_send( $pt_email, $full, 'Your MRI Appointment Confirmed - ' . $scan_label . ' - ' . $date_fmt, $e );

            // ── Clinic notification email ─────────────────────────
            $pt_phone = $item->get_meta( 'Phone' ) ?: $order->get_billing_phone();
            $pt_dob   = $item->get_meta( 'Date of Birth' ) ?: '';
            $pt_ref   = $item->get_meta( 'Referral Type' ) ?: 'Self';
            $pt_reason= $item->get_meta( 'Reason' ) ?: '';
            $safety   = $item->get_meta( 'Safety Checklist' ) ?: '';
            $gp_info  = $item->get_meta( 'GP' ) ?: '';
            $order_total = wc_price( $order->get_total() );

            $cl  = '<div style="font-family:Arial,sans-serif;font-size:13px;color:#0d1f3c;max-width:620px">';
            $cl .= '<div style="background:#0d1f3c;padding:16px 20px">';
            $cl .= '<div style="color:#fff;font-size:17px;font-weight:bold">&#x1F4CB; New Booking</div>';
            $cl .= '<div style="color:#00d4b8;font-size:11px;margin-top:2px">Unirad Private MRI Glasgow</div>';
            $cl .= '</div>';
            $cl .= '<div style="background:#f0fdfa;border-left:4px solid #00a896;padding:14px 20px">';
            $cl .= '<div style="font-size:15px;font-weight:bold;color:#065f46">' . esc_html($scan_label) . '</div>';
            $cl .= '<div style="color:#0d1f3c;margin-top:4px"><strong>' . esc_html($date_fmt) . '</strong> at <strong>' . esc_html($time_fmt) . '</strong></div>';
            $cl .= '<div style="color:#64748b;font-size:12px;margin-top:2px">Payment: ' . $order_total . '</div>';
            $cl .= '</div>';
            $cl .= '<div style="padding:16px 20px">';
            $cl .= '<table style="width:100%;border-collapse:collapse;margin-bottom:16px">';
            $cl .= '<tr style="background:#f8fafc"><td style="padding:6px 10px;color:#64748b;width:150px">Patient</td><td style="padding:6px 10px;font-weight:bold;font-size:14px">' . esc_html($full) . '</td></tr>';
            $cl .= '<tr><td style="padding:6px 10px;color:#64748b">Date of Birth</td><td style="padding:6px 10px">' . esc_html($pt_dob) . '</td></tr>';
            $cl .= '<tr style="background:#f8fafc"><td style="padding:6px 10px;color:#64748b">Phone</td><td style="padding:6px 10px;font-weight:bold"><a href="tel:' . esc_attr($pt_phone) . '" style="color:#0d1f3c">' . esc_html($pt_phone) . '</a></td></tr>';
            $cl .= '<tr><td style="padding:6px 10px;color:#64748b">Email</td><td style="padding:6px 10px"><a href="mailto:' . esc_attr($pt_email) . '" style="color:#00a896">' . esc_html($pt_email) . '</a></td></tr>';
            $cl .= '<tr style="background:#f8fafc"><td style="padding:6px 10px;color:#64748b">Referral</td><td style="padding:6px 10px">' . esc_html($pt_ref) . '</td></tr>';
            if ( $gp_info ) $cl .= '<tr><td style="padding:6px 10px;color:#64748b">GP</td><td style="padding:6px 10px">' . esc_html($gp_info) . '</td></tr>';
            $cl .= '<tr' . ($gp_info?'':' style="background:#f8fafc"') . '><td style="padding:6px 10px;color:#64748b">Reason</td><td style="padding:6px 10px">' . esc_html($pt_reason) . '</td></tr>';
            $cl .= '</table>';
            if ( $safety ) {
                $cl .= '<div style="font-weight:bold;font-size:12px;color:#0d1f3c;margin-bottom:6px;border-bottom:2px solid #00a896;padding-bottom:4px">SAFETY CHECKLIST</div>';
                $cl .= '<div style="font-size:12px;color:#555;margin-bottom:16px">' . esc_html($safety) . '</div>';
            }
            $cl .= '</div>';
            $cl .= '<div style="background:#0d1f3c;padding:10px 20px;font-size:11px;color:#64748b;text-align:center">Unirad Private MRI Glasgow | booking@unirad.co.uk | 0141 846 9116</div>';
            $cl .= '</div>';

            $order->update_meta_data( '_uqb_patient_email_sent', '1' );
            $order->save();

            // Mark any abandoned lead as converted
            if ( $pt_email && $scan_label ) {
                self::mark_lead_converted( $pt_email, $scan_label );
            }

            self::brevo_send( UQB_NOTIFY_EMAIL, 'Unirad Team', '[New Booking] ' . $full . ' - ' . $scan_label . ' - ' . $date_fmt . ' ' . $time_fmt, $cl );

            break;
        }
    }

        // Rename order item after it's saved to DB — catches block checkout
    public static function rename_new_order_item( $item_id, $item, $order_id ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) return;
        $scan = $item->get_meta( 'Scan Details' );
        if ( ! $scan ) return;
        $item->set_name( $scan );
        $item->save();
    }

    // Inject CSS to ensure Bookly shows custom_service_name prominently
    public static function bookly_admin_calendar_css() {
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'bookly' ) === false ) return;
        ?>
        <style>
        .fc-event .fc-title { font-size: 11px !important; }
        </style>
        <?php
    }

    // Bookly calendar title override — show custom_service_name (scan label) instead of generic service name
    public static function bookly_calendar_title( $title, $appointment ) {
        global $wpdb;

        // 1. Try from the appointment array itself (some Bookly versions populate this)
        if ( ! empty( $appointment['custom_service_name'] ) ) {
            return esc_html( $appointment['custom_service_name'] );
        }

        // 2. DB fallback — always reliable regardless of Bookly version
        $id = isset( $appointment['id'] ) ? (int) $appointment['id'] : 0;
        if ( $id ) {
            $row = $wpdb->get_var( $wpdb->prepare(
                "SELECT custom_service_name FROM {$wpdb->prefix}bookly_appointments WHERE id = %d LIMIT 1",
                $id
            ) );
            if ( $row ) return esc_html( $row );
        }

        return $title;
    }

    // Bookly calendar appointment data override — populate all title-related keys
    public static function bookly_calendar_appointment_data( $data, $appointment ) {
        global $wpdb;

        // Prefer array value first
        $custom = ! empty( $appointment['custom_service_name'] )
            ? $appointment['custom_service_name']
            : null;

        // DB fallback
        if ( ! $custom ) {
            $id = isset( $appointment['id'] ) ? (int) $appointment['id'] : 0;
            if ( $id ) {
                $custom = $wpdb->get_var( $wpdb->prepare(
                    "SELECT custom_service_name FROM {$wpdb->prefix}bookly_appointments WHERE id = %d LIMIT 1",
                    $id
                ) );
            }
        }

        if ( $custom ) {
            $safe = esc_html( $custom );
            // Cover all keys Bookly may use across versions
            $data['title']        = $safe;
            $data['serviceName']  = $safe;
            $data['serviceTitle'] = $safe;
            $data['service']      = $safe;
        }

        return $data;
    }

    // Rename order line item at creation time — most reliable method
    public static function rename_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['uqb_scan_details'] ) && ! empty( $values['uqb_scan_details'] ) ) {
            $item->set_name( $values['uqb_scan_details'] );
        }
    }

    // Show scan name in cart instead of product name
    public static function filter_cart_item_name( $name, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['uqb_scan_details'] ) && ! empty( $cart_item['uqb_scan_details'] ) ) {
            return esc_html( $cart_item['uqb_scan_details'] );
        }
        return $name;
    }

    // Show scan name in order details + emails
    public static function filter_order_item_name( $name, $item ) {
        // 1. Check item meta 'Scan Details'
        $scan = $item->get_meta( 'Scan Details' );
        if ( $scan ) return esc_html( $scan );

        // 2. Fallback: check order-level meta
        if ( is_a( $item, 'WC_Order_Item' ) ) {
            $order = $item->get_order();
            if ( $order ) {
                $scan = $order->get_meta( '_uqb_scan_label' );
                if ( $scan ) return esc_html( $scan );
            }
        }

        // 3. If product name contains "body part" / "Body Part", replace with generic
        if ( stripos( $name, 'body part' ) !== false ) {
            return 'Private MRI Scan';
        }

        return $name;
    }

    public static function filter_cart_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        $price = WC()->session ? WC()->session->get( 'uqb_custom_price' ) : null;
        if ( ! $price ) return;
        foreach ( $cart->get_cart() as $item ) {
            if ( isset( $item['uqb_scan_details'] ) ) {
                $item['data']->set_price( floatval( $price ) );
            }
        }
        WC()->session->set( 'uqb_custom_price', null );
    }

    // Save booking date/time directly on order at creation time
    public static function save_booking_to_order( $order, $data ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['uqb_booking_date'] ) ) continue;
            $d = $cart_item['uqb_booking_date'];
            $t = $cart_item['uqb_booking_time'];
            $s = isset( $cart_item['uqb_scan_details'] ) ? $cart_item['uqb_scan_details'] : '';
            if ( $d && $t ) {
                $order->update_meta_data( '_uqb_booking_date', $d );
                $order->update_meta_data( '_uqb_booking_time', $t );
                $order->update_meta_data( '_uqb_scan_label',   $s );
            }
            break;
        }
    }

    // Called directly from checkout — receives order object
    public static function write_to_bookly_checkout( $order_id, $posted_data, $order ) {
        self::write_to_bookly( $order_id );
    }

    public static function write_to_bookly( $order_id ) {
        global $wpdb;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        $bt = $wpdb->prefix . 'bookly_appointments';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$bt}'" ) ) return;

        // Try session first (most reliable — set during checkout)
        $sess_date  = WC()->session ? WC()->session->get( 'uqb_booking_date' ) : '';
        $sess_time  = WC()->session ? WC()->session->get( 'uqb_booking_time' ) : '';
        $sess_scan  = WC()->session ? WC()->session->get( 'uqb_scan_label' )   : '';

        // Also read from order meta as fallback
        foreach ( $order->get_items() as $item ) {
            $appt = $item->get_meta( 'Appointment' );
            $scan = $item->get_meta( 'Scan Details' );

            // Use order-level meta if item meta not populated yet
            if ( ! $appt ) {
                $ord_date = $order->get_meta( '_uqb_booking_date' );
                $ord_time = $order->get_meta( '_uqb_booking_time' );
                if ( $ord_date && $ord_time ) $appt = $ord_date . ' ' . $ord_time;
                elseif ( $sess_date && $sess_time ) $appt = $sess_date . ' ' . $sess_time;
            }
            if ( ! $scan ) {
                $scan = $order->get_meta( '_uqb_scan_label' ) ?: $sess_scan;
            }

            if ( ! $appt || ! $scan ) continue;
            $p = explode( ' ', trim( $appt ) );
            if ( count( $p ) < 2 ) continue;
            // Bookly uses WordPress timezone (London) — write local time directly
            $sdt = $p[0] . ' ' . $p[1] . ':00';

            // ── Duration from actual scan label ───────────────────
            // uqb_calc_duration() applies the clinic's rules:
            //   Silver=3h, Gold/Platinum=4h, bilateral/multi-region=1.5h, single=1h
            $duration_mins = uqb_calc_duration( $scan );
            $edt = date( 'Y-m-d H:i:s', strtotime( $sdt ) + ( (int)$duration_mins * 60 ) );

            // Look up the correct Bookly service_id for this WC product
            $wc_product_id  = 0;
            foreach ( $order->get_items() as $_item ) {
                $wc_product_id = (int) $_item->get_product_id();
                break;
            }
            $use_service_id = uqb_get_bookly_service_id( $wc_product_id );

            $ex  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$bt} WHERE start_date=%s AND staff_id=%d LIMIT 1", $sdt, UQB_BOOKLY_STAFF_ID ) );
            if ( $ex ) continue;
            $now = current_time( 'mysql' );
            $result = $wpdb->insert( $bt, array(
                'staff_id'             => UQB_BOOKLY_STAFF_ID,
                'staff_any'            => 0,
                'service_id'           => $use_service_id,
                'start_date'           => $sdt,
                'end_date'             => $edt,
                'extras_duration'      => 0,
                'custom_service_name'  => $scan,
                'custom_service_price' => $order->get_total(),
                'created_from'         => 'bookly',
                'created_at'           => $now,
                'updated_at'           => $now,
            ) );
            $appt_id = $result !== false ? (int) $wpdb->insert_id : 0;
            if ( ! $appt_id ) {
                error_log( 'UQB Bookly insert failed: ' . $wpdb->last_error );
            } else {
                // Force custom_service_name to be used as display title in Bookly
                // Update the appointment to ensure custom_service_name is set correctly
                $wpdb->update(
                    $bt,
                    array( 'custom_service_name' => $scan ),
                    array( 'id' => $appt_id ),
                    array( '%s' ),
                    array( '%d' )
                );
                // Clear session booking data after successful write
                if ( WC()->session ) {
                    WC()->session->set( 'uqb_booking_date', '' );
                    WC()->session->set( 'uqb_booking_time', '' );
                    WC()->session->set( 'uqb_scan_label',   '' );
                }
            }

            // Link customer to appointment via bookly_customer_appointments
            if ( $appt_id ) {
                $customer_email = $item->get_meta( 'Email' );
                $customer_name  = $item->get_meta( 'Patient Name' );
                $customer_phone = $item->get_meta( 'Phone' );
                $ca_table = $wpdb->prefix . 'bookly_customer_appointments';
                if ( $customer_email && $wpdb->get_var( "SHOW TABLES LIKE '{$ca_table}'" ) ) {
                    $cust_id = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}bookly_customers WHERE email = %s LIMIT 1",
                        $customer_email
                    ) );
                    if ( ! $cust_id ) {
                        $wpdb->insert( $wpdb->prefix . 'bookly_customers', array(
                            'full_name'  => $customer_name ? $customer_name : 'Patient',
                            'email'      => $customer_email,
                            'phone'      => $customer_phone ? $customer_phone : '',
                            'created_at' => $now,
                        ) );
                        $cust_id = (int) $wpdb->insert_id;
                    }
                    if ( $cust_id ) {
                        $wpdb->insert( $ca_table, array(
                            'appointment_id' => $appt_id,
                            'customer_id'    => $cust_id,
                            'payment_id'     => null,
                            'token'          => wp_generate_password( 12, false ),
                            'status'         => 'approved',
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ) );
                    }
                }
            }
        }
    }

    public static function get_item_data( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['uqb_scan_details'] ) ) return $item_data;
        $item_data[] = array( 'name' => 'Scan Details', 'value' => esc_html( $cart_item['uqb_scan_details'] ) );
        $d = isset( $cart_item['uqb_booking_date'] ) ? $cart_item['uqb_booking_date'] : '';
        $t = isset( $cart_item['uqb_booking_time'] ) ? $cart_item['uqb_booking_time'] : '';
        if ( trim( $d . $t ) ) $item_data[] = array( 'name' => 'Appointment', 'value' => esc_html( trim( $d . ' ' . $t ) ) );
        $pm = isset( $cart_item['uqb_pay_mode'] ) ? $cart_item['uqb_pay_mode'] : 'full';
        // deposit option removed — always full payment
        $pt = isset( $cart_item['uqb_patient'] ) ? $cart_item['uqb_patient'] : array();
        if ( ! empty( $pt['first'] ) ) {
            $item_data[] = array( 'name' => 'Patient', 'value' => esc_html( trim( $pt['title'] . ' ' . $pt['first'] . ' ' . $pt['last'] ) ) );
            $item_data[] = array( 'name' => 'Phone',   'value' => esc_html( $pt['phone'] ) );
        }
        return $item_data;
    }

    // ── WooCommerce order meta ───────────────────────────────
    public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! isset( $values['uqb_scan_details'] ) ) return;
        $d  = isset( $values['uqb_booking_date'] ) ? $values['uqb_booking_date'] : '';
        $t  = isset( $values['uqb_booking_time'] ) ? $values['uqb_booking_time'] : '';
        $pm = isset( $values['uqb_pay_mode'] )     ? $values['uqb_pay_mode']     : 'full';
        $item->add_meta_data( 'Scan Details',  $values['uqb_scan_details'] );
        $item->add_meta_data( 'Appointment',   trim( $d . ' ' . $t ) );
        $item->add_meta_data( 'Payment Mode', 'Full payment' );
        // Indexed keys used by slot-blocking query to prevent double-booking
        if ( $d ) $item->add_meta_data( '_uqb_date', $d, true );
        if ( $t ) $item->add_meta_data( '_uqb_time', $t, true );
        $pt = isset( $values['uqb_patient'] ) ? $values['uqb_patient'] : array();
        if ( ! empty( $pt['first'] ) ) {
            $item->add_meta_data( 'Patient Name',  trim( $pt['title'] . ' ' . $pt['first'] . ' ' . $pt['last'] ) );
            $item->add_meta_data( 'Date of Birth', $pt['dob'] );
            $item->add_meta_data( 'Phone',         $pt['phone'] );
            $item->add_meta_data( 'Email',         $pt['email'] );
            $item->add_meta_data( 'Referral Type', ucfirst( $pt['referral'] ) );
            if ( ! empty( $pt['gp_name'] ) ) $item->add_meta_data( 'GP', $pt['gp_name'] . ( $pt['gp_phone'] ? ' / ' . $pt['gp_phone'] : '' ) );
            $item->add_meta_data( 'Reason', $pt['reason'] );
        }
        $sq = isset( $values['uqb_safety'] ) ? $values['uqb_safety'] : array();
        if ( ! empty( $sq ) ) {
            $lines = array();
            $map = array( 'sq_prev_mri' => 'Previous MRI', 'sq_pregnant' => 'Pregnant', 'sq_pacemaker' => 'Pacemaker/Implants', 'sq_implants_detail' => 'Implant Detail', 'sq_metal_body' => 'Metal in Body', 'sq_items' => 'Items Worn', 'sq_surgeries' => 'Surgeries' );
            foreach ( $map as $k => $lbl ) { if ( ! empty( $sq[$k] ) ) $lines[] = $lbl . ': ' . $sq[$k]; }
            $hu = isset( $sq['sq_height_unit'] ) ? $sq['sq_height_unit'] : 'metric';
            if ( $hu === 'metric' && ( ! empty( $sq['sq_height_cm'] ) || ! empty( $sq['sq_weight_kg'] ) ) ) {
                $lines[] = 'Height: ' . $sq['sq_height_cm'] . 'cm, Weight: ' . $sq['sq_weight_kg'] . 'kg';
            } elseif ( $hu === 'imperial' && ! empty( $sq['sq_height_ft'] ) ) {
                $lines[] = 'Height: ' . $sq['sq_height_ft'] . 'ft ' . $sq['sq_height_in'] . 'in, Weight: ' . $sq['sq_weight_st'] . 'st ' . $sq['sq_weight_lb'] . 'lb';
            }
            if ( ! empty( $lines ) ) $item->add_meta_data( 'Safety Checklist', implode( ' | ', $lines ) );
        }
    }

    public static function enqueue() {
        wp_enqueue_style( 'uqb-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap' );
    }

    // ── Catalog ──────────────────────────────────────────────
    public static function catalog() {
        return array(
            // WooCommerce product IDs for MRI bundles
            'bundles'       => array( 1 => 5253, 2 => 5141, 3 => 5234, 4 => 5235 ),
            'bundle_prices' => array( 1 => 290,  2 => 455,  3 => 600,  4 => 700  ),
            // Deposit product (WooCommerce ID 181 = Pay £50 Deposit)
            'deposit_id'    => 181,
            'deposit_price' => 50,
            'mri_items' => array(
                array( 'id' => 'abdomen_pelvis', 'name' => 'Abdomen & Pelvis Scan',     'laterality' => false, 'cb' => true,  'price' => 455  ),
                array( 'id' => 'abdomen_mri',    'name' => 'Abdomen MRI Scan',          'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'achilles',       'name' => 'Achilles Tendon MRI',       'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'angiography',    'name' => 'Angiography - Brain',       'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'ankle',          'name' => 'Ankle MRI Scan',            'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'axilla',         'name' => 'Axilla MRI',                'laterality' => true,  'cb' => true,  'price' => 290  ),
                array( 'id' => 'brachial',       'name' => 'Brachial Plexus MRI',       'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'brain_head',     'name' => 'Brain MRI Scan',     'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'breast',         'name' => 'Breast MRI',                'laterality' => false, 'cb' => true,  'price' => 420  ),
                array( 'id' => 'calf',           'name' => 'Calf MRI Scan',             'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'cardiac',        'name' => 'Cardiac MRI Scan (Morphology-Functional)', 'laterality' => false, 'cb' => true,  'price' => 575  ),
                array( 'id' => 'cervical',       'name' => 'Cervical Spine MRI',        'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'clavicle',       'name' => 'Clavicle MRI',              'laterality' => true,  'cb' => true,  'price' => 290  ),
                array( 'id' => 'coccyx',         'name' => 'Coccyx Spine MRI',          'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'elbow',          'name' => 'Elbow MRI Scan',            'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'face',           'name' => 'Face MRI',                  'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'femur',          'name' => 'Femur (Upper Leg) MRI',     'laterality' => true,  'cb' => true,  'price' => 290  ),
                array( 'id' => 'fingers',        'name' => 'Fingers/Thumb MRI',         'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'foot',           'name' => 'Foot MRI',                  'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'forearm',        'name' => 'Forearm MRI',               'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'hand',           'name' => 'Hand MRI',                  'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'hip',            'name' => 'Hip MRI Scan',              'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'iam',            'name' => 'IAM MRI Scan',              'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'knee',           'name' => 'Knee MRI Scan',             'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'liver',          'name' => 'Liver MRI Scan',            'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'lumbar',         'name' => 'Lumbar Spine MRI',          'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'mrcp',           'name' => 'MRCP Scan',                 'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'orbit',          'name' => 'Orbit MRI Scan',            'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'pancreas',       'name' => 'Pancreas MRI Scan',         'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'pelvis_sports',  'name' => 'Pelvis (Sports Groin) MRI', 'laterality' => false, 'cb' => true,  'price' => 380  ),
                array( 'id' => 'pelvis_gynae',   'name' => 'Pelvis Gynae MRI',          'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'pelvis_scan',    'name' => 'Pelvis MRI Scan',           'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'perianal',       'name' => 'Perianal Fistula MRI',      'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'pituitary',      'name' => 'Pituitary MRI Scan',        'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'prostate',       'name' => 'Prostate MRI Scan',         'laterality' => false, 'cb' => true,  'price' => 495  ),
                array( 'id' => 'sacroiliac',     'name' => 'Sacroiliac Joints MRI',     'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'sacrum',         'name' => 'Sacrum MRI',                'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'scapula',        'name' => 'Scapula MRI',               'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'shoulder',       'name' => 'Shoulder MRI Scan',         'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'sinuses',        'name' => 'Sinuses MRI Scan',          'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'small_bowel',    'name' => 'Small Bowel MRI Scan',      'laterality' => false, 'cb' => true,  'price' => 495  ),
                array( 'id' => 'soft_tissue',    'name' => 'Soft Tissue Neck MRI',      'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'sternum',        'name' => 'Sternum MRI',               'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'thigh',          'name' => 'Thigh MRI Scan',            'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'thoracic',       'name' => 'Thoracic Spine MRI',        'laterality' => false, 'cb' => false, 'price' => 290  ),
                array( 'id' => 'tibia',          'name' => 'Tibia (Lower Leg) MRI',     'laterality' => true,  'cb' => true,  'price' => 290  ),
                array( 'id' => 'tmj',            'name' => 'TMJ Joint MRI',             'laterality' => false, 'cb' => true,  'price' => 290  ),
                array( 'id' => 'upper_arm',      'name' => 'Upper Arm MRI',             'laterality' => true,  'cb' => false, 'price' => 290  ),
                array( 'id' => 'wrist',          'name' => 'Wrist MRI Scan',            'laterality' => true,  'cb' => false, 'price' => 290  ),
            ),
            'full_body' => array(
                array( 'id' => '1332', 'name' => 'Full Body MRI Silver Package – Brain, Abdomen & Pelvis',                      'price' => 590,  'cb' => true ),
                array( 'id' => '1464', 'name' => 'Full Body MRI Gold Package – Brain, Spine, Abdomen & Pelvis',                       'price' => 1210, 'cb' => true ),
                array( 'id' => '1465', 'name' => 'Full Body MRI Platinum Package – Heart, Brain, Spine, Abdomen & Pelvis',             'price' => 1660, 'cb' => true ),
            ),
            'gp' => array(
                array( 'id' => '6388', 'name' => 'GP Consultation (In-Clinic)', 'price' => 99,  'cb' => true ),
                array( 'id' => '6387', 'name' => 'GP Consultation (Online)',    'price' => 45,  'cb' => true ),
            ),
        );
    }

    // ── Shortcode ────────────────────────────────────────────
    public static function shortcode() {
        $catalog  = self::catalog();
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'uqb2_nonce' );
        $today    = date( 'Y-m-d' );
        $cat_json = wp_json_encode( $catalog );

        $html = '
<div id="uqb" data-nonce="' . esc_attr($nonce) . '" data-ajax="' . esc_url($ajax_url) . '">

<div class="uqb-steps">
  <div class="uqb-step uqb-step--active" data-s="1"><span>1</span> Select Scan</div>
  <div class="uqb-arr">&#x203A;</div>
  <div class="uqb-step" data-s="2"><span>2</span> Your Details</div>
  <div class="uqb-arr">&#x203A;</div>
  <div class="uqb-step" data-s="3"><span>3</span> Choose Time</div>
</div>

<!-- STEP 1 -->
<div class="uqb-panel" id="uqb-p1">
  <div class="uqb-topbar">
    <div class="uqb-tb-col">
      <span class="uqb-tb-lbl">SCAN TYPE</span>
      <div class="uqb-tb-val" id="uqbTypeBtn">
        <span id="uqbTypeText">MRI Scan</span> &#9662;
        <div class="uqb-dd" id="uqbTypeMenu" style="display:none">
          <button data-cat="mri">MRI Scan</button>
          <button data-cat="full">Full Body MRI</button>
          <button data-cat="gp">GP Consultation</button>
        </div>
      </div>
    </div>
    <div class="uqb-tb-div"></div>
    <div class="uqb-tb-col" style="flex:1;min-width:0">
      <span class="uqb-tb-lbl">SELECTION</span>
      <div id="uqbSelText" style="font-size:13px;font-weight:500;color:#6b7e9c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Click to select...</div>
    </div>
    <div class="uqb-tb-div"></div>
    <div class="uqb-tb-col" style="text-align:right;white-space:nowrap">
      <div class="uqb-price" id="uqbPrice">&#163;0.00</div>
      <div style="font-size:10px;color:#9aadbe">Selected Total</div>
    </div>
    <button class="uqb-btn" id="uqbBookBtn" disabled>Book Now &#8594;</button>
  </div>
  <div class="uqb-list" id="uqbList"></div>
</div>

<!-- STEP 2 -->
<div class="uqb-panel" id="uqb-p2" style="display:none">
  <div class="uqb-phead">
    <button class="uqb-back" id="uqbBack1">&#8592; Back to scan</button>
    <div class="uqb-badge" id="uqbBadge2"></div>
  </div>
  <div class="uqb-two-col">
    <div class="uqb-col">
      <h3 class="uqb-col-title">Patient Details</h3>
      <div class="uqb-row3">
        <div class="uqb-f uqb-f--xs"><label>Title</label>
          <select id="pt_title"><option>Mr.</option><option>Mrs.</option><option>Miss.</option><option>Ms.</option><option>Dr.</option><option>Other</option></select>
        </div>
        <div class="uqb-f uqb-f--g"><label>First Name *</label><input type="text" id="pt_first" placeholder="First name"></div>
        <div class="uqb-f uqb-f--g"><label>Last Name *</label><input type="text" id="pt_last" placeholder="Last name"></div>
      </div>
      <div class="uqb-row2">
        <div class="uqb-f"><label>Date of Birth *</label><input type="date" id="pt_dob"></div>
        <div class="uqb-f"><label>Phone *</label><input type="tel" id="pt_phone" placeholder="+44 7xxx xxxxxx"></div>
      </div>
      <div class="uqb-f"><label>Email *</label><input type="email" id="pt_email" placeholder="you@example.com"></div>
      <div class="uqb-f"><label>Address *</label><input type="text" id="pt_address" placeholder="First line of address"></div>
      <div class="uqb-f"><label>Reason for Scan *</label><textarea id="pt_reason" rows="3" placeholder="Describe your symptoms and reason for this scan"></textarea></div>
      <div class="uqb-f">
        <label>Have you got any previous related exams?</label>
        <div style="display:flex;flex-direction:column;gap:7px;margin-top:6px">
          <label class="uqb-rl"><input type="radio" name="pt_prev_exam" value="No" checked> No</label>
          <label class="uqb-rl"><input type="radio" name="pt_prev_exam" value="Yes"> Yes (follow-up scan)</label>
          <label class="uqb-rl"><input type="radio" name="pt_prev_exam" value="Other"> Other</label>
        </div>
      </div>
      <div id="uqb-prev-exam-upload" style="display:none">
        <div class="uqb-gp-box" style="border-color:#c7d7e8;background:#f7fbff">
          <div class="uqb-gp-title" style="color:#0369a1">Upload Previous Scan / Report</div>
          <div class="uqb-f">
            <div class="uqb-upload-area" id="uqbPrevUploadArea">
              <input type="file" id="prev_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none">
              <div class="uqb-upload-inner" id="uqbPrevUploadInner">
                <span style="font-size:22px">&#128247;</span>
                <span>Click to upload previous report or image</span>
                <span style="font-size:11px;color:#9aadbe">PDF, JPG, PNG or Word -- max 10MB</span>
              </div>
              <div id="uqbPrevFileChosen" style="display:none;padding:10px 14px;background:#f0fdfa;font-size:12px;font-weight:600;color:#00a896"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="uqb-f">
        <label>Referral Type *</label>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
          <label class="uqb-rl"><input type="radio" name="pt_ref" value="self" checked> I am referring myself (18+)</label>
          <label class="uqb-rl"><input type="radio" name="pt_ref" value="gp"> I have a GP / Clinician referral</label>
        </div>
      </div>
      <div id="uqb-gp-row" style="display:none">
        <div class="uqb-gp-box">
          <div class="uqb-gp-title">GP / Clinician Details</div>
          <div class="uqb-row2">
            <div class="uqb-f"><label>GP / Doctor Name *</label><input type="text" id="gp_name" placeholder="Dr. Smith"></div>
            <div class="uqb-f"><label>GP Phone</label><input type="tel" id="gp_phone" placeholder="+44 ..."></div>
          </div>
          <div class="uqb-f">
            <label>Upload Referral Letter (PDF, Image or Word)</label>
            <div class="uqb-upload-area" id="uqbUploadArea">
              <input type="file" id="gp_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none">
              <div class="uqb-upload-inner" id="uqbUploadInner">
                <span style="font-size:22px">&#128196;</span>
                <span>Click to upload or drag &amp; drop</span>
                <span style="font-size:11px;color:#9aadbe">PDF, JPG, PNG or Word -- max 10MB</span>
              </div>
              <div id="uqbFileChosen" style="display:none;padding:10px 14px;background:#f0fdfa;font-size:12px;font-weight:600;color:#00a896"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="uqb-col">
      <h3 class="uqb-col-title">MRI Safety Questionnaire</h3>
      <p class="uqb-note">Please answer all questions to ensure your scan is safe and suitable for you.</p>
      <div class="uqb-sq-row">
        <span class="uqb-sq-q">Have you had an MRI Scan before?</span>
        <div class="uqb-yn"><label><input type="radio" name="sq_prev_mri" value="No" checked> No</label><label><input type="radio" name="sq_prev_mri" value="Yes"> Yes</label></div>
      </div>
      <div class="uqb-sq-row">
        <span class="uqb-sq-q">Are you, or could you be, pregnant?</span>
        <div class="uqb-yn"><label><input type="radio" name="sq_pregnant" value="No" checked> No</label><label><input type="radio" name="sq_pregnant" value="Yes"> Yes</label></div>
      </div>
      <div class="uqb-sq-row">
        <span class="uqb-sq-q">Do you have or have you ever had a cardiac pacemaker, defibrillator, cerebral aneurysm clips, cochlear implant, artificial heart valve, or stents/clips/coils?</span>
        <div class="uqb-yn"><label><input type="radio" name="sq_pacemaker" value="No" checked> No</label><label><input type="radio" name="sq_pacemaker" value="Yes"> Yes</label></div>
      </div>
      <div id="uqb-impl-row" style="display:none;background:#fff8ed;border:1px solid #f0c040;border-radius:8px;padding:10px;margin-bottom:6px">
        <label style="font-size:11px;font-weight:700;color:#7a5500;display:block;margin-bottom:4px;text-transform:uppercase">Please specify:</label>
        <textarea id="sq_implants_detail" rows="2" style="width:100%;border:1px solid #f0c040;border-radius:6px;padding:7px;font-size:12px;font-family:inherit;resize:vertical" placeholder="Type, material, year if known"></textarea>
      </div>
      <div class="uqb-sq-row">
        <span class="uqb-sq-q">Do you have any metal in your body from surgery or injury?</span>
        <div class="uqb-yn"><label><input type="radio" name="sq_metal_body" value="No" checked> No</label><label><input type="radio" name="sq_metal_body" value="Yes"> Yes</label></div>
      </div>
      <div style="font-size:11px;font-weight:700;color:#3a4d68;text-transform:uppercase;letter-spacing:.5px;margin:10px 0 6px">Are you wearing or do you have any of these?</div>
      <div class="uqb-check-grid">
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Hearing aid"> Hearing aid</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Dentures"> Dentures</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Artificial limb"> Artificial limb</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Skin patch"> Skin patch</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="False eye"> False eye</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Body piercings"> Body piercings</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Tattoos"> Permanent tattoos</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Glucose monitor"> Glucose monitor</label>
        <label class="uqb-chk-lbl"><input type="checkbox" name="sq_items" value="Wearable devices"> Wearable devices</label>
      </div>
      <div class="uqb-f" style="margin-top:10px"><label>List all previous surgeries *</label>
        <textarea id="sq_surgeries" rows="2" placeholder="e.g. appendectomy 2015, or N/A"></textarea>
      </div>
      <div class="uqb-f">
        <label>Height &amp; Weight</label>
        <div style="display:flex;gap:6px;margin-bottom:8px">
          <button type="button" class="uqb-hw-tab uqb-hw-tab--on" data-unit="metric">Metric (cm/kg)</button>
          <button type="button" class="uqb-hw-tab" data-unit="imperial">Imperial (ft/lb)</button>
        </div>
        <input type="hidden" id="sq_height_unit" value="metric">
        <div id="uqb-hw-metric">
          <div class="uqb-row2">
            <div class="uqb-f"><label>Height (cm)</label><input type="number" id="sq_height_cm" placeholder="170"></div>
            <div class="uqb-f"><label>Weight (kg)</label><input type="number" id="sq_weight_kg" placeholder="70"></div>
          </div>
        </div>
        <div id="uqb-hw-imperial" style="display:none">
          <div class="uqb-row2">
            <div class="uqb-f"><label>Ft</label><input type="number" id="sq_height_ft" placeholder="5"></div>
            <div class="uqb-f"><label>In</label><input type="number" id="sq_height_in" placeholder="10"></div>
          </div>
          <div class="uqb-row2">
            <div class="uqb-f"><label>Stone</label><input type="number" id="sq_weight_st" placeholder="11"></div>
            <div class="uqb-f"><label>Lbs</label><input type="number" id="sq_weight_lb" placeholder="0"></div>
          </div>
        </div>
      </div>
      <div class="uqb-consent">
        <label><input type="checkbox" id="uqbConsent">
          I confirm that all information provided is accurate and I consent to my data being processed under UK GDPR for the purpose of arranging my MRI scan. I understand all jewellery and metallic items must be removed prior to scanning.
        </label>
      </div>
    </div>
  </div>
  <div class="uqb-foot">
    <button class="uqb-btn" id="uqbNext2">Continue to Choose Time &#8594;</button>
    <span class="uqb-secure">&#128274; Your data is secure and confidential</span>
  </div>
</div>

<!-- STEP 3 -->
<div class="uqb-panel" id="uqb-p3" style="display:none">
  <div class="uqb-phead">
    <button class="uqb-back" id="uqbBack2">&#8592; Back to details</button>
    <div class="uqb-badge" id="uqbBadge3"></div>
  </div>
  <div class="uqb-two-col">
    <div class="uqb-col">
      <h3 class="uqb-col-title">Select Date</h3>
      <p class="uqb-note">Mon / Wed / Fri &middot; 10:00&ndash;19:00 &nbsp;&bull;&nbsp; Tue / Thu &middot; 10:00&ndash;16:00 &nbsp;&bull;&nbsp; Sat &middot; 10:00&ndash;15:00</p>
      <input type="date" id="uqbDate" min="' . esc_attr($today) . '" value="' . esc_attr($today) . '" class="uqb-date-in">
    </div>
    <div class="uqb-col">
      <h3 class="uqb-col-title">Select Time</h3>
      <div class="uqb-slots" id="uqbSlots"><p class="uqb-note">Pick a date first</p></div>
    </div>
  </div>
  <div style="border-top:1px solid #dce4ef;padding:18px 22px 6px">
    <h3 class="uqb-col-title">Payment Option</h3>
    <div class="uqb-pay-grid" id="uqbPayGrid">
      <label class="uqb-pay-card uqb-pay-card--on" id="uqbPayFull">
        <input type="radio" name="uqb_pay" value="full" checked style="display:none">
        <div class="uqb-pay-radio"></div>
        <div style="flex:1">
          <div class="uqb-pay-title">Pay in Full</div>
          <div class="uqb-pay-sub">Apple Pay, Google Pay or card.</div>
        </div>
        <div class="uqb-pay-amount" id="uqbFullAmt">&#163;290</div>
      </label>

      <label class="uqb-pay-card" id="uqbPayCb">
        <input type="radio" name="uqb_pay" value="callback" style="display:none">
        <div class="uqb-pay-radio"></div>
        <div style="flex:1">
          <div class="uqb-pay-title">Request Callback</div>
          <div class="uqb-pay-sub">No payment now &#8212; we will call you.</div>
        </div>
        <div class="uqb-pay-amount" style="color:#f59e0b">FREE</div>
      </label>
    </div>
  </div>
  <div class="uqb-foot">
    <button class="uqb-btn" id="uqbConfirm" disabled>Confirm &amp; Pay &#8594;</button>
    <span class="uqb-secure">&#128274; Apple Pay &amp; Google Pay accepted</span>
  </div>
</div>

<!-- STEP 4: Callback success -->
<div class="uqb-panel" id="uqb-p4" style="display:none">
  <div style="padding:48px 32px;text-align:center">
    <div style="font-size:48px;margin-bottom:16px">&#9989;</div>
    <h2 style="font-size:22px;font-weight:700;color:#0d1f3c;margin:0 0 10px">Callback Request Received!</h2>
    <p style="font-size:14px;color:#3a4d68;max-width:420px;margin:0 auto;line-height:1.6">Thank you! Our team will call you on <strong>0141 846 9116</strong> to arrange your <strong id="uqbCbScan"></strong> booking. A confirmation email has been sent to you.</p>
  </div>
</div>

<div id="uqbLoading" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,.88);z-index:9999;flex-direction:column;align-items:center;justify-content:center;gap:14px;font-size:15px;font-weight:600;color:#0d1f3c">
  <div style="width:38px;height:38px;border:4px solid #dce4ef;border-top-color:#00a896;border-radius:50%;animation:uqb-spin .75s linear infinite"></div>
  <p>Preparing your booking...</p>
</div>
</div>

<style>
#uqb,#uqb *{box-sizing:border-box}
#uqb{font-family:\'DM Sans\',sans-serif;color:#0d1f3c;max-width:100%}
#uqb a{color:#00a896}
.uqb-steps{display:flex;align-items:center;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.uqb-step{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#aabcce;white-space:nowrap}
.uqb-step span{width:26px;height:26px;border-radius:50%;background:#e2eaf2;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.uqb-step--active{color:#0d1f3c}
.uqb-step--active span,.uqb-step--done span{background:#00a896;color:#fff}
.uqb-arr{color:#c8d5e0;font-size:20px}
.uqb-panel{background:#fff;border-radius:16px;border:1px solid #dce4ef;overflow:hidden;margin-bottom:20px}
.uqb-topbar{display:flex;align-items:center;flex-wrap:wrap;border-bottom:1px solid #dce4ef}
.uqb-tb-col{padding:12px 16px}
.uqb-tb-div{width:1px;height:46px;background:#dce4ef;align-self:center;flex-shrink:0}
.uqb-tb-lbl{font-size:10px;font-weight:700;color:#9aadbe;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:3px}
.uqb-tb-val{font-size:15px;font-weight:700;color:#0d1f3c;cursor:pointer;position:relative;user-select:none;white-space:nowrap}
.uqb-price{font-size:22px;font-weight:800;color:#00a896;line-height:1}
.uqb-dd{position:absolute;top:calc(100% + 8px);left:0;background:#fff;border:1px solid #dce4ef;border-radius:10px;box-shadow:0 10px 24px rgba(13,31,60,.1);padding:6px;z-index:200;min-width:190px}
.uqb-dd button{display:block;width:100%;text-align:left;padding:9px 12px;border:none;background:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;color:#0d1f3c}
.uqb-dd button:hover{background:#f7f9fc}
.uqb-btn{background:#00a896;color:#fff;border:none;border-radius:10px;padding:12px 22px;font-size:15px;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap;margin:10px 14px;flex-shrink:0}
.uqb-btn:disabled{background:#c8d5e0;cursor:not-allowed}
.uqb-list{padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:7px}
.uqb-card{border:1.5px solid #dce4ef;border-radius:10px;cursor:pointer;overflow:hidden;transition:border-color .15s}
.uqb-card:hover{border-color:#a8c5e0}
.uqb-card.sel{border-color:#00a896;background:#f0fdfa}
.uqb-card-head{display:flex;align-items:center;padding:10px 12px;gap:8px}
.uqb-chk{width:16px;height:16px;border:2px solid #dce4ef;border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.15s}
.uqb-card.sel .uqb-chk{background:#00a896;border-color:#00a896}
.uqb-card.sel .uqb-chk::after{content:"\\2713";color:#fff;font-size:9px;font-weight:700}
.uqb-card-name{font-size:12px;font-weight:600;flex:1;line-height:1.3}
.uqb-price-tag{font-size:11px;font-weight:700;color:#007f72;white-space:nowrap;flex-shrink:0;background:#f0fdfa;padding:2px 6px;border-radius:4px}
/* Left/Right/Both buttons - explicit sizing to override Elementor/theme */
#uqb .uqb-sides{display:none;padding:4px 10px 9px;gap:4px;flex-direction:row}
#uqb .uqb-card.sel .uqb-sides{display:flex!important}
#uqb .uqb-side{flex:1;padding:5px 0!important;border:1.5px solid #dce4ef!important;border-radius:6px!important;font-size:11px!important;font-weight:600!important;background:#fff!important;color:#3a4d68!important;cursor:pointer;transition:.15s;text-align:center;height:28px!important;line-height:28px!important;min-height:0!important;max-height:28px!important;margin:0!important;display:inline-block!important;visibility:visible!important;opacity:1!important}
#uqb .uqb-side:hover{border-color:#00a896!important;background:#f0fdfa!important;color:#00a896!important}
#uqb .uqb-side.on{background:#00a896!important;color:#fff!important;border-color:#00a896!important}
.uqb-phead{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #dce4ef;flex-wrap:wrap;gap:8px}
.uqb-back{background:none;border:1px solid #dce4ef;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;color:#3a4d68;cursor:pointer}
.uqb-back:hover{border-color:#00a896;color:#00a896}
.uqb-badge{background:#f7f9fc;border:1px solid #dce4ef;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:600;color:#3a4d68;max-width:60%;word-break:break-word}
.uqb-two-col{display:grid;grid-template-columns:1fr 1fr}
.uqb-col{padding:20px 22px}
.uqb-col+.uqb-col{border-left:1px solid #dce4ef}
.uqb-col-title{font-size:15px;font-weight:700;margin:0 0 14px;color:#0d1f3c}
.uqb-note{font-size:12px;color:#3a4d68;margin-bottom:12px;line-height:1.5}
.uqb-f{margin-bottom:11px}
.uqb-f label{display:block;font-size:11px;font-weight:700;color:#3a4d68;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
.uqb-f input,.uqb-f select,.uqb-f textarea{width:100%;padding:8px 10px;border:1.5px solid #dce4ef;border-radius:8px;font-size:13px;color:#0d1f3c;background:#fff;transition:border-color .15s;font-family:inherit}
.uqb-f input:focus,.uqb-f select:focus,.uqb-f textarea:focus{outline:none;border-color:#00a896}
.uqb-f textarea{resize:vertical}
.uqb-row2{display:flex;gap:8px}
.uqb-row2 .uqb-f{flex:1;min-width:0}
.uqb-row3{display:flex;gap:8px}
.uqb-row3 .uqb-f{flex:1;min-width:0}
.uqb-f--xs{flex:0 0 62px!important}
.uqb-f--g{flex:1;min-width:0}
.uqb-rl{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:500;cursor:pointer;text-transform:none;letter-spacing:0}
.uqb-rl input{width:15px;height:15px;flex-shrink:0}
.uqb-gp-box{background:#f0f9ff;border:1px solid #bae0fd;border-radius:10px;padding:14px;margin-top:4px}
.uqb-gp-title{font-size:13px;font-weight:700;color:#0369a1;margin-bottom:10px}
.uqb-upload-area{border:2px dashed #dce4ef;border-radius:8px;cursor:pointer;transition:.15s;overflow:hidden}
.uqb-upload-area:hover{border-color:#00a896}
.uqb-upload-inner{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:16px;font-size:12px;color:#6b7e9c;font-weight:500;text-align:center}
.uqb-sq-row{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;background:#f7f9fc;border-radius:7px;padding:7px 9px;margin-bottom:5px}
.uqb-sq-q{font-size:12px;font-weight:500;color:#0d1f3c;flex:1;line-height:1.4}
.uqb-yn{display:flex;gap:10px;flex-shrink:0;padding-top:1px}
.uqb-yn label{font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;white-space:nowrap;text-transform:none;letter-spacing:0}
.uqb-check-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:4px}
.uqb-chk-lbl{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;cursor:pointer;padding:5px 7px;border-radius:6px;background:#f7f9fc;text-transform:none;letter-spacing:0}
.uqb-chk-lbl input{width:14px;height:14px;flex-shrink:0}
.uqb-hw-tab{padding:6px 12px;border:1.5px solid #dce4ef;border-radius:7px;font-size:12px;font-weight:600;background:#fff;cursor:pointer;color:#6b7e9c;transition:.15s;font-family:inherit}
.uqb-hw-tab--on{border-color:#00a896;color:#00a896;background:#f0fdfa}
.uqb-consent{margin-top:12px;font-size:11px;color:#3a4d68;line-height:1.5;background:#f7f9fc;border-radius:8px;padding:10px}
.uqb-consent label{display:flex;align-items:flex-start;gap:7px;cursor:pointer;font-size:11px;font-weight:400;text-transform:none;letter-spacing:0}
.uqb-consent input{margin-top:2px;flex-shrink:0}
.uqb-date-in{width:100%;padding:10px;border:1.5px solid #dce4ef;border-radius:8px;font-size:15px;color:#0d1f3c;font-family:inherit}
.uqb-date-in:focus{outline:none;border-color:#00a896}
.uqb-slots{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;max-height:260px;overflow-y:auto}
.uqb-slot{padding:8px;border:1.5px solid #dce4ef;border-radius:7px;text-align:center;font-size:13px;font-weight:600;cursor:pointer;transition:.15s;color:#0d1f3c}
.uqb-slot:hover{border-color:#00a896;background:#f0fdfa}
.uqb-slot.on{background:#00a896;color:#fff;border-color:#00a896}
.uqb-slot.off{opacity:.4;cursor:default;pointer-events:none}
.uqb-slot--booked{background:#fdf4f4!important;border-color:#f5d0d0!important;cursor:not-allowed!important;pointer-events:none;}
.uqb-pay-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px}
.uqb-pay-card{display:flex;align-items:flex-start;gap:10px;border:1.5px solid #dce4ef;border-radius:10px;padding:14px;cursor:pointer;transition:.15s;background:#fff}
.uqb-pay-card--on{border-color:#00a896;background:#f0fdfa}
.uqb-pay-radio{width:18px;height:18px;border:2px solid #dce4ef;border-radius:50%;flex-shrink:0;margin-top:2px;transition:.15s;display:flex;align-items:center;justify-content:center}
.uqb-pay-card--on .uqb-pay-radio{background:#00a896;border-color:#00a896}
.uqb-pay-card--on .uqb-pay-radio::after{content:"";width:6px;height:6px;background:#fff;border-radius:50%}
.uqb-pay-title{font-size:13px;font-weight:700;color:#0d1f3c;margin-bottom:3px}
.uqb-pay-sub{font-size:11px;color:#6b7e9c;line-height:1.4}
.uqb-pay-amount{font-size:18px;font-weight:800;color:#00a896;margin-left:auto;white-space:nowrap;padding-left:8px;flex-shrink:0}
.uqb-foot{padding:14px 22px;border-top:1px solid #dce4ef;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.uqb-secure{font-size:12px;color:#9aadbe}
@keyframes uqb-spin{to{transform:rotate(360deg)}}
@media(max-width:820px){
  .uqb-two-col{grid-template-columns:1fr}
  .uqb-col+.uqb-col{border-left:none;border-top:1px solid #dce4ef}
  .uqb-pay-grid{grid-template-columns:1fr}
  .uqb-tb-div{display:none}
  .uqb-list{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}
  .uqb-check-grid{grid-template-columns:1fr}
}
@media(max-width:480px){
  .uqb-list{grid-template-columns:1fr 1fr}
  .uqb-btn{margin:8px 10px;font-size:14px;padding:11px 16px}
  .uqb-row2,.uqb-row3{flex-wrap:wrap}
  .uqb-steps{gap:4px}
  .uqb-step{font-size:12px}
  .uqb-step span{width:22px;height:22px;font-size:11px}
}
@media(max-width:380px){
  .uqb-list{grid-template-columns:1fr}
}
</style>

<script>
(function(){
var ROOT=document.getElementById("uqb"),
    NONCE=ROOT.dataset.nonce,
    AJAX=ROOT.dataset.ajax,
    CAT=' . $cat_json . ';
var S={mode:"mri",picks:{},date:"",time:"",payMode:"full"};
function q(s){return ROOT.querySelector(s);}
function qq(s){return Array.from(ROOT.querySelectorAll(s));}
function fmt(n){return"\u00a3"+parseFloat(n||0).toFixed(2);}
function keys(){return Object.keys(S.picks);}
function total(){
  if(S.mode!=="mri"){var t=0;keys().forEach(function(k){t+=S.picks[k].price||0;});return t;}
  var SPECIAL={breast:420,cardiac:575,prostate:495,small_bowel:495,abdomen_pelvis:455,pelvis_sports:380};
  var sp=0,bp=0;
  keys().forEach(function(k){
    var p=S.picks[k];
    if(SPECIAL.hasOwnProperty(p.id)){sp+=SPECIAL[p.id];}
    else{bp+=(p.side==="Both"||p.side==="Bilateral")?2:1;}
  });
  bp=Math.min(bp,4);
  return sp+(bp>0?(CAT.bundle_prices[bp]||0):0);
}
function selLabel(){
  if(!keys().length)return"Click to select...";
  return keys().map(function(k){var p=S.picks[k];return p.name+(p.side?" ("+p.side+")":"");}).join(", ");
}
function goStep(n){
  [1,2,3,4].forEach(function(i){var p=q("#uqb-p"+i);if(p)p.style.display=(i===n?"block":"none");});
  qq(".uqb-step").forEach(function(el){var si=parseInt(el.dataset.s,10);el.classList.toggle("uqb-step--active",si===n);el.classList.toggle("uqb-step--done",si<n);});
  ROOT.scrollIntoView({behavior:"smooth",block:"start"});
}
function updateTopbar(){
  q("#uqbPrice").textContent=fmt(total());
  var sl=q("#uqbSelText");sl.textContent=selLabel();sl.style.color=keys().length?"#0d1f3c":"#6b7e9c";
  var hasCb=keys().some(function(k){return S.picks[k].cb;});
  var allCb=keys().length>0&&keys().every(function(k){return S.picks[k].cb;});
  var btn=q("#uqbBookBtn");btn.disabled=!keys().length;
  btn.textContent=hasCb?"Request Callback \u2192":"Book Now \u2192";
  // If ALL selected items are cb-only, show a note under the button
  var note=q("#uqbCbNote");
  if(allCb){
    if(!note){
      note=document.createElement("p");note.id="uqbCbNote";
      note.style.cssText="font-size:11px;color:#b45309;background:#fffbe6;border:1px solid #fcd34d;border-radius:6px;padding:6px 10px;margin-top:8px;max-width:420px;";
      note.textContent="This scan requires a clinical review before booking. We will call you to confirm your appointment — no payment needed now.";
      btn.parentNode.insertBefore(note,btn.nextSibling);
    }
  } else {
    if(note)note.remove();
  }
  var fa=q("#uqbFullAmt");if(fa)fa.textContent=fmt(total());
}
function renderList(){
  var el=q("#uqbList");el.innerHTML="";
  var items=S.mode==="mri"?CAT.mri_items:(S.mode==="full"?CAT.full_body:CAT.gp);
  var sorted=items.slice().sort(function(a,b){return a.name.localeCompare(b.name);});
  sorted.forEach(function(item){
    var sel=!!S.picks[item.id],info=S.picks[item.id]||{};
    var card=document.createElement("div");card.className="uqb-card"+(sel?" sel":"");
    var head=document.createElement("div");head.className="uqb-card-head";
    var priceTag=item.price?"<span class=\"uqb-price-tag\">"+fmt(item.price)+"</span>":"";
    head.innerHTML="<div class=\"uqb-chk\"></div><span class=\"uqb-card-name\">"+item.name+"</span>"+priceTag;
    var sides=null;
    if(item.laterality){
      sides=document.createElement("div");sides.className="uqb-sides";
      ["Left","Right","Both"].forEach(function(s){
        var b=document.createElement("button");b.type="button";b.className="uqb-side"+(info.side===s?" on":"");b.textContent=s;
        b.onclick=function(e){e.stopPropagation();if(S.picks[item.id]){S.picks[item.id].side=s;renderList();updateTopbar();}};
        sides.appendChild(b);
      });
    }
    head.onclick=function(){
      if(S.mode!=="mri"){S.picks={};if(!sel){var cp={};Object.keys(item).forEach(function(k){cp[k]=item[k];});cp.side=null;S.picks[item.id]=cp;}}
      else{
        if(sel){delete S.picks[item.id];}
        else{var cur=0;keys().forEach(function(k){cur+=S.picks[k].side==="Both"?2:1;});if(cur>=4){alert("Maximum 4 scan areas selected.");return;}var cp2={};Object.keys(item).forEach(function(k){cp2[k]=item[k];});cp2.side=item.laterality?"Left":null;S.picks[item.id]=cp2;}
      }
      renderList();updateTopbar();
      // auto-callback if safety concern already flagged
      checkSafetyCallback();
    };
    card.appendChild(head);
    if(sides){
      card.appendChild(sides);
    }
    el.appendChild(card);
  });
}
function setMode(mode){
  S.mode=mode;S.picks={};
  var labels={mri:"MRI Scan",full:"Full Body MRI",gp:"GP Consultation"};
  q("#uqbTypeText").textContent=labels[mode];renderList();updateTopbar();
}
// Calculate scan duration (minutes) from current picks — mirrors PHP uqb_calc_duration()
function calcDuration(){
  var keys=Object.keys(S.picks);
  if(!keys.length) return 60;
  // Check for packages (Silver/Gold/Platinum)
  for(var i=0;i<keys.length;i++){
    var n=(S.picks[keys[i]].name||"").toLowerCase();
    if(n.indexOf("silver")!==-1) return 180;
    if(n.indexOf("gold")!==-1)   return 240;
    if(n.indexOf("platinum")!==-1) return 240;
  }
  // Check for bilateral
  for(var i=0;i<keys.length;i++){
    var sd=(S.picks[keys[i]].side||"").toLowerCase();
    if(sd==="both"||sd==="bilateral") return 90;
  }
  // Count distinct regions
  var cnt=keys.length;
  if(cnt>=4) return 180;
  if(cnt>=3) return 120;
  if(cnt>=2) return 90;
  return 60;
}
function loadSlots(date){
  var cont=q("#uqbSlots");cont.innerHTML="<p class=\"uqb-note\">Loading slots...</p>";
  S.time="";updateConfirmBtn();
  fetch(AJAX,{method:"POST",body:new URLSearchParams({action:"unirad_qb2_slots",nonce:NONCE,date:date,duration:calcDuration()})})
  .then(function(r){return r.json();})
  .then(function(data){
    if(!data.success||!data.data.slots.length){cont.innerHTML="<p class=\"uqb-note\">No availability on this day. Please try another date.</p>";return;}
    cont.innerHTML="";
    data.data.slots.forEach(function(slot){
      var d=document.createElement("div");
      d.className="uqb-slot"+(slot.available?"":" off");
      d.textContent=slot.time;
      if(slot.available){d.onclick=function(){qq(".uqb-slot").forEach(function(x){x.classList.remove("on");});d.classList.add("on");S.time=slot.time;updateConfirmBtn();};}
      cont.appendChild(d);
    });
  })
  .catch(function(){cont.innerHTML="<p class=\"uqb-note\" style=\"color:red\">Error loading slots. Please refresh.</p>";});
}
function checkSafetyCallback(){
  var danger=["sq_pregnant","sq_pacemaker","sq_metal_body"];
  var flagged=danger.some(function(f){var el=ROOT.querySelector("[name=\""+f+"\"]:checked");return el&&el.value==="Yes";});
  if(flagged){
    S.payMode="callback";
    qq(".uqb-pay-card").forEach(function(c){c.classList.remove("uqb-pay-card--on");});
    var cb=q("#uqbPayCb");if(cb){cb.classList.add("uqb-pay-card--on");var r=cb.querySelector("input[type=\"radio\"]");if(r)r.checked=true;}
    // disable pay options
    var pf=q("#uqbPayFull");if(pf){pf.style.opacity="0.4";pf.style.pointerEvents="none";}
    var note=q("#uqbSafetyNote");
    if(!note){
      note=document.createElement("p");note.id="uqbSafetyNote";
      note.style.cssText="font-size:12px;color:#b45309;background:#fffbe6;border:1px solid #fcd34d;border-radius:7px;padding:8px 10px;margin-bottom:10px";
      note.textContent="Based on your safety answers, a callback is required before booking. Our team will call to confirm your appointment.";
      var pg=q("#uqbPayGrid");if(pg)pg.parentNode.insertBefore(note,pg);
    }
    updateConfirmBtn();
  } else {
    // re-enable
    var pf=q("#uqbPayFull");if(pf){pf.style.opacity="";pf.style.pointerEvents="";}
    var n=q("#uqbSafetyNote");if(n)n.remove();
  }
}
function updateConfirmBtn(){
  var btn=q("#uqbConfirm");if(!btn)return;
  if(S.payMode==="callback"){btn.disabled=false;btn.textContent="Request Callback \u2192";}
  else{btn.disabled=!S.time;btn.textContent="Confirm & Pay \u2192";}
}
function validateStep2(){
  var req=["pt_first","pt_last","pt_dob","pt_phone","pt_email","pt_reason","sq_surgeries"];
  for(var i=0;i<req.length;i++){
    var el=q("#"+req[i]);
    if(!el||!el.value.trim()){if(el){el.focus();el.style.borderColor="#ef4444";}alert("Please complete all required fields.");return false;}
  }
  var ref=ROOT.querySelector("[name=\"pt_ref\"]:checked");
  if(ref&&ref.value==="gp"){var gpn=q("#gp_name");if(!gpn||!gpn.value.trim()){if(gpn)gpn.focus();alert("Please enter your GP / Doctor name.");return false;}}
  if(!q("#uqbConsent").checked){alert("Please accept the patient declaration to continue.");return false;}
  return true;
}
function getPatient(){
  var ref=ROOT.querySelector("[name=\"pt_ref\"]:checked");
  return{title:q("#pt_title").value,first:q("#pt_first").value.trim(),last:q("#pt_last").value.trim(),dob:q("#pt_dob").value,phone:q("#pt_phone").value.trim(),email:q("#pt_email").value.trim(),address:(q("#pt_address")?q("#pt_address").value.trim():""),reason:q("#pt_reason").value.trim(),prev_exam:(function(){var r=ROOT.querySelector("[name=\"pt_prev_exam\"]:checked");return r?r.value:"No";})(),referral:ref?ref.value:"self",gp_name:(q("#gp_name")?q("#gp_name").value.trim():""),gp_phone:(q("#gp_phone")?q("#gp_phone").value.trim():"")};
}
function getSafety(){
  var sq={};
  ["sq_prev_mri","sq_pregnant","sq_pacemaker","sq_metal_body"].forEach(function(f){var el=ROOT.querySelector("[name=\""+f+"\"]:checked");sq[f]=el?el.value:"No";});
  sq.sq_items=Array.from(ROOT.querySelectorAll("[name=\"sq_items\"]:checked")).map(function(c){return c.value;}).join(", ");
  sq.sq_implants_detail=(q("#sq_implants_detail")?q("#sq_implants_detail").value:"");
  sq.sq_surgeries=(q("#sq_surgeries")?q("#sq_surgeries").value.trim():"");
  sq.sq_height_unit=(q("#sq_height_unit")?q("#sq_height_unit").value:"metric");
  ["sq_height_cm","sq_weight_kg","sq_height_ft","sq_height_in","sq_weight_st","sq_weight_lb"].forEach(function(f){sq[f]=(q("#"+f)?q("#"+f).value:"");});
  return sq;
}
function getItems(){return keys().map(function(k){var p=S.picks[k];return{id:p.id,name:p.name,side:p.side||"",price:p.price||0};});}
function doCallback(){
  document.getElementById("uqbLoading").style.display="flex";
  var pt=getPatient(),sq=getSafety();
  var body=new FormData();
  body.append("action","unirad_qb2_callback");body.append("nonce",NONCE);
  body.append("scan_label",selLabel());body.append("scan_price",fmt(total()));
  Object.keys(pt).forEach(function(k){body.append("pt_"+k,pt[k]);});
  Object.keys(sq).forEach(function(k){body.append(k,sq[k]);});
  var pfi2=q("#prev_file");if(pfi2&&pfi2.files&&pfi2.files[0])body.append("prev_file",pfi2.files[0]);
  fetch(AJAX,{method:"POST",body:body})
  .then(function(r){return r.json();})
  .then(function(){document.getElementById("uqbLoading").style.display="none";window.location.href="/appointment/clinical-callback/thank-you-callback/?scan="+encodeURIComponent(selLabel());})
  .catch(function(){document.getElementById("uqbLoading").style.display="none";alert("Connection error. Please try again.");});
}
function doCheckout(){
  document.getElementById("uqbLoading").style.display="flex";
  var pt=getPatient(),sq=getSafety(),items=getItems();
  var body=new FormData();
  body.append("action","unirad_qb2_checkout");body.append("nonce",NONCE);
  body.append("items",JSON.stringify(items));body.append("mode",S.mode);
  body.append("date",S.date);body.append("time",S.time);body.append("pay_mode",S.payMode);
  Object.keys(pt).forEach(function(k){body.append("pt_"+k,pt[k]);});
  Object.keys(sq).forEach(function(k){body.append(k,sq[k]);});
  var fi=q("#gp_file");if(fi&&fi.files&&fi.files[0])body.append("gp_file",fi.files[0]);
  var pfi3=q("#prev_file");if(pfi3&&pfi3.files&&pfi3.files[0])body.append("prev_file",pfi3.files[0]);
  fetch(AJAX,{method:"POST",body:body})
  .then(function(r){return r.json();})
  .then(function(data){if(data.success){window.location.href=data.data.redirect;}else{document.getElementById("uqbLoading").style.display="none";alert("Something went wrong. Please try again.");}})
  .catch(function(){document.getElementById("uqbLoading").style.display="none";alert("Connection error. Please try again.");});
}
function init(){
  q("#uqbTypeBtn").onclick=function(){var m=q("#uqbTypeMenu");m.style.display=m.style.display==="none"?"block":"none";};
  q("#uqbTypeMenu").onclick=function(e){var b=e.target.closest("button[data-cat]");if(!b)return;q("#uqbTypeMenu").style.display="none";setMode(b.dataset.cat);};
  document.addEventListener("click",function(e){var m=q("#uqbTypeMenu"),btn=q("#uqbTypeBtn");if(m&&btn&&!btn.contains(e.target)&&!m.contains(e.target))m.style.display="none";});
  q("#uqbBookBtn").onclick=function(){var badge=selLabel()+" \u2014 "+fmt(total());[q("#uqbBadge2"),q("#uqbBadge3")].forEach(function(b){if(b)b.textContent=badge;});goStep(2);};
  q("#uqbNext2").onclick=function(){if(!validateStep2())return;
    // ── Silent lead capture (fire & forget) ───────────────────
    (function(){
      var pt=getPatient();
      fetch(AJAX,{method:"POST",body:new URLSearchParams({
        action:"unirad_qb2_save_lead",nonce:NONCE,
        name:(pt.first+" "+pt.last).trim(),
        email:pt.email,phone:pt.phone,
        scan_label:selLabel(),scan_price:fmt(total())
      })}).catch(function(){});
    })();
    // ── CB-only: cb items CAN pick date/time but CANNOT pay ──
    var allCb=keys().length>0&&keys().every(function(k){return S.picks[k].cb;});
    if(allCb){
      S.payMode="callback";
      // Hide "Pay in Full", show only "Request Callback"
      var pf=q("#uqbPayFull");if(pf)pf.style.display="none";
      var cb=q("#uqbPayCb");if(cb){cb.classList.add("uqb-pay-card--on");var r=cb.querySelector("input[type=\"radio\"]");if(r)r.checked=true;}
      // Show note above pay grid
      var pg=q("#uqbPayGrid");
      if(pg&&!q("#uqbCbOnlyNote")){
        var n=document.createElement("p");n.id="uqbCbOnlyNote";
        n.style.cssText="font-size:12px;color:#b45309;background:#fffbe6;border:1px solid #fcd34d;border-radius:8px;padding:10px 12px;margin-bottom:12px;line-height:1.5;";
        n.textContent="This scan requires a clinical review before booking. Please select a preferred date and time — our team will call to confirm. No payment is taken now.";
        pg.parentNode.insertBefore(n,pg);
      }
    } else {
      // Restore "Pay in Full" if user went back and changed selection
      var pf=q("#uqbPayFull");if(pf)pf.style.display="";
      var n=q("#uqbCbOnlyNote");if(n)n.remove();
      if(S.payMode==="callback"){S.payMode="full";var pf2=q("#uqbPayFull");if(pf2){pf2.classList.add("uqb-pay-card--on");var r=pf2.querySelector("input[type=\"radio\"]");if(r)r.checked=true;}var cbc=q("#uqbPayCb");if(cbc)cbc.classList.remove("uqb-pay-card--on");}
    }
    goStep(3);S.date=q("#uqbDate").value;if(S.date)loadSlots(S.date);
    updateConfirmBtn();
  };
  q("#uqbDate").addEventListener("change",function(e){S.date=e.target.value;loadSlots(S.date);});
  q("#uqbConfirm").onclick=function(){if(S.payMode==="callback"){doCallback();}else{if(!S.time){alert("Please select a time slot.");return;}doCheckout();}};
  q("#uqbBack1").onclick=function(){goStep(1);};
  q("#uqbBack2").onclick=function(){goStep(2);};
  ROOT.querySelectorAll("[name=\"sq_pacemaker\"]").forEach(function(r){r.addEventListener("change",function(){var row=q("#uqb-impl-row");var c=ROOT.querySelector("[name=\"sq_pacemaker\"]:checked");if(row)row.style.display=(c&&c.value==="Yes")?"block":"none";checkSafetyCallback();});});
  ROOT.querySelectorAll("[name=\"sq_pregnant\"]").forEach(function(r){r.addEventListener("change",function(){checkSafetyCallback();});});
  ROOT.querySelectorAll("[name=\"pt_prev_exam\"]").forEach(function(r){r.addEventListener("change",function(){var v=ROOT.querySelector("[name=\"pt_prev_exam\"]:checked");var box=q("#uqb-prev-exam-upload");if(box)box.style.display=(v&&v.value!=="No")?"block":"none";});});
  var pua=q("#uqbPrevUploadArea"),pfi=q("#prev_file");
  if(pua&&pfi){
    pua.addEventListener("click",function(){pfi.click();});
    pfi.addEventListener("change",function(){if(pfi.files&&pfi.files[0]){q("#uqbPrevUploadInner").style.display="none";var ch=q("#uqbPrevFileChosen");ch.style.display="block";ch.innerHTML="\u2705 "+pfi.files[0].name;}});
  }
  ROOT.querySelectorAll("[name=\"sq_metal_body\"]").forEach(function(r){r.addEventListener("change",function(){checkSafetyCallback();});});
  ROOT.querySelectorAll("[name=\"pt_ref\"]").forEach(function(r){r.addEventListener("change",function(){var row=q("#uqb-gp-row");var c=ROOT.querySelector("[name=\"pt_ref\"]:checked");if(row)row.style.display=(c&&c.value==="gp")?"block":"none";});});
  var ua=q("#uqbUploadArea"),fi=q("#gp_file");
  if(ua&&fi){
    ua.addEventListener("click",function(){fi.click();});
    fi.addEventListener("change",function(){if(fi.files&&fi.files[0]){q("#uqbUploadInner").style.display="none";var ch=q("#uqbFileChosen");ch.style.display="block";ch.innerHTML="\u2705 "+fi.files[0].name;}});
    ua.addEventListener("dragover",function(e){e.preventDefault();ua.style.borderColor="#00a896";});
    ua.addEventListener("dragleave",function(){ua.style.borderColor="#dce4ef";});
    ua.addEventListener("drop",function(e){e.preventDefault();ua.style.borderColor="#dce4ef";if(e.dataTransfer.files[0]){try{fi.files=e.dataTransfer.files;}catch(x){}q("#uqbUploadInner").style.display="none";var ch=q("#uqbFileChosen");ch.style.display="block";ch.innerHTML="\u2705 "+e.dataTransfer.files[0].name;}});
  }
  qq(".uqb-hw-tab").forEach(function(tab){tab.addEventListener("click",function(){qq(".uqb-hw-tab").forEach(function(t){t.classList.remove("uqb-hw-tab--on");});tab.classList.add("uqb-hw-tab--on");var unit=tab.dataset.unit;q("#sq_height_unit").value=unit;q("#uqb-hw-metric").style.display=unit==="metric"?"block":"none";q("#uqb-hw-imperial").style.display=unit==="imperial"?"block":"none";});});
  ["uqbPayFull","uqbPayCb"].forEach(function(id){var el=q("#"+id);if(!el)return;el.addEventListener("click",function(){qq(".uqb-pay-card").forEach(function(c){c.classList.remove("uqb-pay-card--on");});el.classList.add("uqb-pay-card--on");var r=el.querySelector("input[type=\"radio\"]");if(r){r.checked=true;S.payMode=r.value;}updateConfirmBtn();});});
  ROOT.querySelectorAll("input,textarea,select").forEach(function(el){el.addEventListener("focus",function(){el.style.borderColor="";});});
  // URL param pre-selection (from hero booking bar)
  (function(){
    var p=new URLSearchParams(window.location.search);
    var smode=p.get("scan_mode")||"mri";
    var allItems=(CAT.mri_items||[]).concat(CAT.full_body||[]).concat(CAT.gp||[]);

    // ── Multi-scan: ?scan_ids=knee,hip&side_knee=Left&side_hip=Both ──
    var sids=p.get("scan_ids");
    if(sids){
      setMode(smode);
      S.picks={};
      sids.split(",").forEach(function(id){
        id=id.trim();
        if(!id)return;
        var found=null;
        for(var i=0;i<allItems.length;i++){if(allItems[i].id===id){found=allItems[i];break;}}
        if(!found)return;
        var side=p.get("side_"+id)||null;
        if(side==="Bilateral"||side==="Both")side="Both";
        else if(!side&&found.laterality)side="Left";
        S.picks[found.id]=Object.assign({},found,{side:side});
      });
      renderList();updateTopbar();
      var badge=selLabel()+" \u2014 "+fmt(total());
      [q("#uqbBadge2"),q("#uqbBadge3")].forEach(function(b){if(b)b.textContent=badge;});
      goStep(2);
      return;
    }

    // ── Single scan: ?scan_id=knee&scan_mode=mri&scan_side=Left ──
    var sid=p.get("scan_id"), sside=p.get("scan_side")||null;
    if(sside==="Bilateral")sside="Both";
    if(!sid){ setMode("mri"); goStep(1); return; }
    setMode(smode);
    var found=null;
    for(var i=0;i<allItems.length;i++){ if(allItems[i].id===sid){ found=allItems[i]; break; } }
    if(!found){ setMode("mri"); goStep(1); return; }
    S.picks={};
    S.picks[found.id]=Object.assign({},found,{side:sside||null});
    if(found.laterality&&!sside) S.picks[found.id].side="Left";
    renderList();updateTopbar();
    var badge=selLabel()+" \u2014 "+fmt(total());
    [q("#uqbBadge2"),q("#uqbBadge3")].forEach(function(b){if(b)b.textContent=badge;});
    goStep(2);
  })();
}
if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}

// ── Hero v5 Bridge ─────────────────────────────────────────────
window.uqbPreselect = function(scan_id, scan_mode, scan_side) {
  if (!scan_id) return;
  setMode(scan_mode || "mri");
  var allItems = (CAT.mri_items||[]).concat(CAT.full_body||[]).concat(CAT.gp||[]);
  var found = null;
  for (var i = 0; i < allItems.length; i++) { if (allItems[i].id === scan_id) { found = allItems[i]; break; } }
  if (!found) return;
  S.picks = {};
  S.picks[found.id] = Object.assign({}, found, { side: scan_side || null });
  if (found.laterality && !scan_side) S.picks[found.id].side = "Left";
  renderList(); updateTopbar(); goStep(1);
  var badge = selLabel() + " \u2014 " + fmt(total());
  [q("#uqbBadge2"),q("#uqbBadge3")].forEach(function(b){if(b)b.textContent=badge;});
};
document.addEventListener("uqb:preselect", function(e) {
  var d = e.detail || {};
  if (window.uqbPreselect) window.uqbPreselect(d.scan_id, d.scan_mode, d.scan_side);
});

})();
</script>';

        return $html;
    }

    // ══════════════════════════════════════════════════════════════
    // ABANDONED BOOKING RECOVERY — Lead Capture & Recovery Emails
    // ══════════════════════════════════════════════════════════════

    // AJAX: fired from JS when patient completes Step 2 ("Continue to Choose Time")
    public static function ajax_save_lead() {
        check_ajax_referer( 'uqb2_nonce', 'nonce' );
        $name       = sanitize_text_field( isset( $_POST['name'] )       ? $_POST['name']       : '' );
        $email      = sanitize_email(      isset( $_POST['email'] )      ? $_POST['email']      : '' );
        $phone      = sanitize_text_field( isset( $_POST['phone'] )      ? $_POST['phone']      : '' );
        $scan_label = sanitize_text_field( isset( $_POST['scan_label'] ) ? $_POST['scan_label'] : '' );
        $scan_price = sanitize_text_field( isset( $_POST['scan_price'] ) ? $_POST['scan_price'] : '' );
        if ( ! $email || ! $scan_label ) { wp_send_json_success(); return; }
        self::log_potential_lead( $name, $email, $phone, $scan_label, $scan_price );
        wp_send_json_success();
    }

    private static function log_potential_lead( $name, $email, $phone, $scan_label, $scan_price = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'unirad_potential_bookings';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(30) NOT NULL,
            scan_type text NOT NULL,
            scan_price varchar(20) DEFAULT '',
            status varchar(20) DEFAULT 'pending' NOT NULL,
            recovery_sent tinyint(1) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Skip if this email+scan already has a pending lead in the last 24h
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE email = %s AND scan_type = %s AND status = 'pending'
               AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1",
            $email, $scan_label
        ) );
        if ( $existing ) return;

        $wpdb->insert( $table, array(
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'scan_type'  => $scan_label,
            'scan_price' => $scan_price,
            'status'     => 'pending',
        ) );
        $lead_id = (int) $wpdb->insert_id;

        do_action( 'unirad_lead_created', [
            'lead_id'   => $lead_id,
            'name'      => $name,
            'email'     => $email,
            'scan_type' => $scan_label,
        ] );
    }

    public static function mark_lead_converted( $email, $scan_label ) {
        global $wpdb;
        $table = $wpdb->prefix . 'unirad_potential_bookings';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return;
        $wpdb->update(
            $table,
            array( 'status' => 'converted' ),
            array( 'email' => $email, 'scan_type' => $scan_label, 'status' => 'pending' ),
            array( '%s' ), array( '%s', '%s', '%s' )
        );
        do_action( 'unirad_lead_converted', [
            'email'     => $email,
            'name'      => '',
            'scan_type' => $scan_label,
        ] );
    }

    // Cron: runs every hour, sends recovery email to leads 60+ min old that haven't converted
    public static function send_abandoned_recovery_emails() {
        global $wpdb;
        $table = $wpdb->prefix . 'unirad_potential_bookings';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return;

        $leads = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'pending' AND recovery_sent = 0
               AND created_at < DATE_SUB(NOW(), INTERVAL 60 MINUTE)
               AND scan_type NOT LIKE '%Callback%'
             LIMIT 20"
        );

        foreach ( $leads as $lead ) {
            if ( ! $lead->email ) continue;
            $parts      = explode( ' ', trim( $lead->name ) );
            $first_name = $parts[0] ?: 'there';
            $price_str  = $lead->scan_price ? ' (' . $lead->scan_price . ')' : '';
            // A/B test: rotate subject line by lead ID (even = control, odd = urgency)
            $ab_variant = (int) $lead->id % 2;
            $subject = ( $ab_variant === 0 )
                ? 'Complete your ' . $lead->scan_type . ' booking — Unirad Glasgow'
                : 'URGENT: Your priority MRI slot is expiring soon ⏰';

            $b  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0d1f3c;max-width:560px">';
            $b .= '<div style="background:#00a896;padding:18px 20px">';
            $b .= '<div style="color:#fff;font-size:18px;font-weight:bold">Unirad Private MRI</div>';
            $b .= '<div style="color:#d0f5f0;font-size:11px;margin-top:3px">Glasgow &middot; unirad.co.uk</div>';
            $b .= '</div>';
            $b .= '<div style="padding:22px">';
            $b .= '<p style="margin:0 0 14px">Hi <strong>' . esc_html( $first_name ) . '</strong>,</p>';
            $b .= '<p style="margin:0 0 16px">We noticed you started booking a <strong>' . esc_html( $lead->scan_type ) . '</strong>' . esc_html( $price_str ) . ' — did something get in the way?</p>';
            $b .= '<div style="background:#f0fdfa;border-left:4px solid #00a896;padding:14px 16px;margin-bottom:20px;border-radius:0 6px 6px 0">';
            $b .= '<p style="margin:0 0 8px;font-weight:bold;color:#065f46">Your slot is waiting — appointments available this week:</p>';
            $b .= '<ul style="margin:0;padding-left:18px;font-size:13px;color:#374151;line-height:2.2">';
            $b .= '<li>Self-referral available for selected scans — our clinical team assesses your suitability before booking</li>';
            $b .= '<li>Radiologist report within 5 working days</li>';
            $b .= '<li>Secure online payment — Apple Pay &amp; Google Pay accepted</li>';
            $b .= '</ul>';
            $b .= '</div>';
            $btn_price = $lead->scan_price ? $lead->scan_price : '£290';
            $b .= '<p style="margin:0 0 10px;font-size:13px;color:#b91c1c;font-weight:bold">&#9200; Only 3 appointment slots remaining this week in Glasgow.</p>';
            $b .= '<a href="https://unirad.co.uk/book-your-scan/" style="display:inline-block;background:#00a896;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px">Claim Your Priority Slot &mdash; ' . esc_html( $btn_price ) . ' (No Hidden Fees) &rarr;</a>';
            $b .= '<p style="margin:20px 0 4px;font-size:13px;color:#555">Prefer to speak to someone? Call us:</p>';
            $b .= '<p style="margin:0;font-size:15px;font-weight:bold">0141 846 9116</p>';
            $b .= '</div>';
            $b .= '<div style="background:#f7f9fc;padding:10px 22px;font-size:11px;color:#9aadbe;border-top:1px solid #dce4ef">';
            $b .= 'Unirad Private MRI Glasgow | Est-Health Ltd. | <a href="https://unirad.co.uk" style="color:#9aadbe">unirad.co.uk</a>';
            $b .= '</div></div>';

            self::brevo_send( $lead->email, $lead->name, $subject, $b );

            $update_data   = array( 'recovery_sent' => 1 );
            $update_format = array( '%d' );
            $cols          = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
            if ( in_array( 'subject_ab', $cols, true ) ) {
                $update_data['subject_ab'] = $ab_variant;
                $update_format[]           = '%d';
            }
            $wpdb->update( $table, $update_data, array( 'id' => $lead->id ), $update_format, array( '%d' ) );

            do_action( 'unirad_lead_abandoned', $lead );
        }
    }

    // ── Admin Dashboard ──────────────────────────────────────────
    public static function admin_menu() {
        add_menu_page(
            'Abandoned Bookings',
            'Abandoned Leads',
            'manage_woocommerce',
            'unirad-abandoned',
            array( __CLASS__, 'admin_page' ),
            'dashicons-groups',
            58
        );
    }

    public static function admin_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'unirad_potential_bookings';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            echo '<div class="wrap"><h1>Abandoned Bookings</h1><p>No data yet — the table is created automatically when the first visitor reaches Step 2 of the booking form.</p></div>';
            return;
        }
        $leads    = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
        $pending   = count( array_filter( $leads, function($l){ return $l->status === 'pending'; } ) );
        $converted = count( array_filter( $leads, function($l){ return $l->status === 'converted'; } ) );
        $recovery  = count( array_filter( $leads, function($l){ return $l->status === 'pending' && $l->recovery_sent; } ) );

        echo '<div class="wrap">';
        echo '<h1 style="margin-bottom:6px">Abandoned Booking Leads</h1>';
        echo '<p style="color:#555;margin-bottom:20px">Patients who reached Step 2 (entered contact details) but did not complete payment.</p>';
        echo '<div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap">';
        foreach ( array(
            array( 'label' => 'Pending',    'val' => $pending,   'color' => '#ef4444' ),
            array( 'label' => 'Recovery Sent', 'val' => $recovery, 'color' => '#f59e0b' ),
            array( 'label' => 'Converted',  'val' => $converted, 'color' => '#00a896' ),
            array( 'label' => 'Total',      'val' => count($leads), 'color' => '#0d1f3c' ),
        ) as $stat ) {
            echo '<div style="background:#fff;border:1px solid #dce4ef;border-radius:10px;padding:14px 20px;min-width:110px;text-align:center">';
            echo '<div style="font-size:28px;font-weight:800;color:' . $stat['color'] . '">' . $stat['val'] . '</div>';
            echo '<div style="font-size:11px;color:#6b7e9c;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px">' . $stat['label'] . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<table class="widefat striped" style="font-size:13px">';
        echo '<thead><tr><th>Date / Time</th><th>Name</th><th>Email</th><th>Phone</th><th>Scan</th><th>Price</th><th>Status</th><th>Recovery</th></tr></thead><tbody>';
        foreach ( $leads as $l ) {
            $sc = $l->status === 'converted' ? '#00a896' : ( $l->recovery_sent ? '#f59e0b' : '#ef4444' );
            echo '<tr>';
            echo '<td>' . esc_html( $l->created_at ) . '</td>';
            echo '<td><strong>' . esc_html( $l->name ) . '</strong></td>';
            echo '<td><a href="mailto:' . esc_attr( $l->email ) . '">' . esc_html( $l->email ) . '</a></td>';
            echo '<td><a href="tel:' . esc_attr( $l->phone ) . '">' . esc_html( $l->phone ) . '</a></td>';
            echo '<td>' . esc_html( $l->scan_type ) . '</td>';
            echo '<td>' . esc_html( $l->scan_price ) . '</td>';
            echo '<td><span style="color:' . $sc . ';font-weight:700">' . esc_html( ucfirst( $l->status ) ) . '</span></td>';
            echo '<td>' . ( $l->recovery_sent ? '<span style="color:#f59e0b">&#10003; Sent</span>' : '<span style="color:#9aadbe">Not yet</span>' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
Unirad_Quick_Booking_V2::init();