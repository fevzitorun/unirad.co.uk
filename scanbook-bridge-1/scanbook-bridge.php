<?php
/**
 * Plugin Name: ScanBook Bridge
 * Description: REST API köprüsü — Bookly slot ve randevu verilerini ScanBook'a açar.
 * Version:     1.1.0
 * Author:      ScanBook UK
 *
 * Kurulum:
 *   1. Bu dosyayı /wp-content/plugins/scanbook-bridge/ klasörüne yükle
 *   2. WP Admin → Plugins → "ScanBook Bridge" aktif et
 *   3. WP Admin → Settings → ScanBook Bridge → API Key oluştur ve kaydet
 *   4. Bu key'i ScanBook admin paneline gir
 *
 * Endpoints:
 *   GET  /wp-json/scanbook/v1/slots?service_id=X&staff_id=Y&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
 *   POST /wp-json/scanbook/v1/appointments  { service_id, staff_id, start, customer_* }
 *   POST /wp-json/scanbook/v1/appointments/{id}/cancel
 *   GET  /wp-json/scanbook/v1/ping
 */

defined( 'ABSPATH' ) || exit;

/* ─── REST API ─────────────────────────────────────────────────────────────── */

add_action( 'rest_api_init', function () {

    register_rest_route( 'scanbook/v1', '/ping', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scanbook_ping',
        'permission_callback' => '__return_true', // public — just to test connectivity
    ] );

    register_rest_route( 'scanbook/v1', '/slots', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scanbook_get_slots',
        'permission_callback' => 'scanbook_check_auth',
    ] );

    register_rest_route( 'scanbook/v1', '/appointments', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scanbook_get_appointments',
        'permission_callback' => 'scanbook_check_auth',
    ] );

    register_rest_route( 'scanbook/v1', '/appointments', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'scanbook_create_appointment',
        'permission_callback' => 'scanbook_check_auth',
    ] );

    register_rest_route( 'scanbook/v1', '/appointments/(?P<id>\d+)/cancel', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'scanbook_cancel_appointment',
        'permission_callback' => 'scanbook_check_auth',
    ] );
} );

/* ─── Auth ──────────────────────────────────────────────────────────────────── */

function scanbook_check_auth( WP_REST_Request $request ): bool {
    $stored = get_option( 'scanbook_api_key', '' );
    if ( ! $stored ) return false;
    $given  = $request->get_header( 'X-ScanBook-Key' );
    return $given && hash_equals( $stored, $given );
}

/* ─── Ping ──────────────────────────────────────────────────────────────────── */

function scanbook_ping(): WP_REST_Response {
    return new WP_REST_Response( [ 'ok' => true, 'site' => get_bloginfo( 'name' ) ], 200 );
}

/* ─── GET /slots ─────────────────────────────────────────────────────────────── */

function scanbook_get_slots( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $service_id = absint( $request->get_param( 'service_id' ) );
    $staff_id   = absint( $request->get_param( 'staff_id' ) );
    $date_from  = sanitize_text_field( $request->get_param( 'date_from' ) ?: date( 'Y-m-d' ) );
    $date_to    = sanitize_text_field( $request->get_param( 'date_to' )   ?: date( 'Y-m-d', strtotime( '+14 days' ) ) );

    if ( ! $service_id || ! $staff_id ) {
        return new WP_REST_Response( [ 'error' => 'service_id and staff_id are required' ], 400 );
    }

    // Service süresi (saniye cinsinden Bookly'de saklanır)
    $service = $wpdb->get_row( $wpdb->prepare(
        "SELECT duration FROM {$wpdb->prefix}bookly_services WHERE id = %d",
        $service_id
    ) );

    if ( ! $service ) {
        return new WP_REST_Response( [ 'error' => 'Service not found' ], 404 );
    }

    $slot_secs = max( 1800, intval( $service->duration ) ); // en az 30 dk
    $slot_mins = $slot_secs / 60;

    // Staff çalışma takvimi (day_index: 0=Paz, 1=Pzt ... 6=Cmt)
    $schedule = $wpdb->get_results( $wpdb->prepare(
        "SELECT day_index, start_time, end_time, available
         FROM {$wpdb->prefix}bookly_staff_schedule
         WHERE staff_id = %d AND available = 1
         ORDER BY day_index",
        $staff_id
    ), ARRAY_A );

    $hours_by_dow = [];
    foreach ( $schedule as $row ) {
        $hours_by_dow[ intval( $row['day_index'] ) ] = [
            'start' => $row['start_time'],
            'end'   => $row['end_time'],
        ];
    }

    // Tatil günleri
    $holidays = $wpdb->get_col( $wpdb->prepare(
        "SELECT `date` FROM {$wpdb->prefix}bookly_holidays
         WHERE ( staff_id = %d OR staff_id IS NULL )
         AND `date` BETWEEN %s AND %s",
        $staff_id, $date_from, $date_to
    ) );
    $holiday_set = array_flip( $holidays );

    // Mevcut randevular (o staff, o tarih aralığı)
    $booked_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT start_date, end_date
         FROM {$wpdb->prefix}bookly_appointments
         WHERE staff_id = %d
           AND DATE(start_date) BETWEEN %s AND %s
           AND status IN ('approved','pending')",
        $staff_id, $date_from, $date_to
    ), ARRAY_A );

    $booked = array_map( function ( $r ) {
        return [
            'from' => strtotime( $r['start_date'] ),
            'to'   => strtotime( $r['end_date'] ),
        ];
    }, $booked_rows );

    // Minimum önceden rezervasyon süresi (6 saat)
    $min_advance = 6 * 3600;
    $now         = time();

    // Slot üretimi
    $slots = [];
    $tz    = new DateTimeZone( 'Europe/London' );
    $cur   = new DateTime( $date_from, $tz );
    $until = new DateTime( $date_to,   $tz );
    $until->modify( '+1 day' );

    while ( $cur < $until ) {
        $date_str = $cur->format( 'Y-m-d' );
        // Bookly day_index: 0=Mon … 6=Sun (ISO). PHP 'N' gives 1=Mon … 7=Sun.
        $dow      = intval( $cur->format( 'N' ) ) - 1; // 0=Mon … 6=Sun

        if ( ! isset( $holiday_set[ $date_str ] ) && isset( $hours_by_dow[ $dow ] ) ) {
            $day_start = strtotime( $date_str . ' ' . $hours_by_dow[ $dow ]['start'] );
            $day_end   = strtotime( $date_str . ' ' . $hours_by_dow[ $dow ]['end'] );

            $slot_start = $day_start;
            while ( $slot_start + $slot_secs <= $day_end ) {
                $slot_end = $slot_start + $slot_secs;

                if ( $slot_start >= $now + $min_advance ) {
                    $is_free = true;
                    foreach ( $booked as $b ) {
                        if ( $slot_start < $b['to'] && $slot_end > $b['from'] ) {
                            $is_free = false;
                            break;
                        }
                    }
                    if ( $is_free ) {
                        $slots[] = [
                            'start' => date( 'Y-m-d\TH:i:s', $slot_start ),
                            'end'   => date( 'Y-m-d\TH:i:s', $slot_end ),
                        ];
                    }
                }
                $slot_start += $slot_secs;
            }
        }
        $cur->modify( '+1 day' );
    }

    return new WP_REST_Response( [
        'slots'      => $slots,
        'count'      => count( $slots ),
        'service_id' => $service_id,
        'staff_id'   => $staff_id,
        'date_from'  => $date_from,
        'date_to'    => $date_to,
    ], 200 );
}

/* ─── GET /appointments ──────────────────────────────────────────────────────── */

function scanbook_get_appointments( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $staff_id   = absint( $request->get_param( 'staff_id' ) ?: 0 );
    $date_from  = sanitize_text_field( $request->get_param( 'date_from' ) ?: date( 'Y-m-d' ) );
    $date_to    = sanitize_text_field( $request->get_param( 'date_to' )   ?: date( 'Y-m-d', strtotime( '+30 days' ) ) );

    $where      = "WHERE DATE(a.start_date) BETWEEN %s AND %s AND a.status != 'cancelled'";
    $params     = [ $date_from, $date_to ];

    if ( $staff_id ) {
        $where   .= ' AND a.staff_id = %d';
        $params[] = $staff_id;
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.id, a.staff_id, a.service_id, a.start_date, a.end_date,
                a.status, a.internal_note, a.custom_service_name,
                c.first_name, c.last_name, c.email, c.phone
         FROM {$wpdb->prefix}bookly_appointments a
         LEFT JOIN {$wpdb->prefix}bookly_customer_appointments ca ON ca.appointment_id = a.id
         LEFT JOIN {$wpdb->prefix}bookly_customers c ON c.id = ca.customer_id
         {$where}
         ORDER BY a.start_date ASC",
        ...$params
    ), ARRAY_A );

    return new WP_REST_Response( [
        'appointments' => $rows,
        'count'        => count( $rows ),
    ], 200 );
}

/* ─── POST /appointments ─────────────────────────────────────────────────────── */

function scanbook_create_appointment( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $service_id   = absint( $request->get_param( 'service_id' ) );
    $staff_id     = absint( $request->get_param( 'staff_id' ) );
    $start        = sanitize_text_field( $request->get_param( 'start' ) );
    $cust_name    = sanitize_text_field( $request->get_param( 'customer_name' ) );
    $cust_email   = sanitize_email( $request->get_param( 'customer_email' ) );
    $cust_phone   = sanitize_text_field( $request->get_param( 'customer_phone' ) ?? '' );
    $notes        = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );
    $scanbook_ref = sanitize_text_field( $request->get_param( 'scanbook_ref' ) ?? '' );

    if ( ! $service_id || ! $staff_id || ! $start || ! $cust_email ) {
        return new WP_REST_Response( [ 'error' => 'Missing required fields: service_id, staff_id, start, customer_email' ], 400 );
    }

    // Servis süresini al
    $service = $wpdb->get_row( $wpdb->prepare(
        "SELECT duration FROM {$wpdb->prefix}bookly_services WHERE id = %d",
        $service_id
    ) );
    if ( ! $service ) {
        return new WP_REST_Response( [ 'error' => 'Service not found' ], 404 );
    }

    $tz       = new DateTimeZone( 'Europe/London' );
    $start_dt = new DateTime( $start, $tz );
    $end_dt   = clone $start_dt;
    $end_dt->modify( '+' . intval( $service->duration ) . ' seconds' );
    $start_str = $start_dt->format( 'Y-m-d H:i:s' );
    $end_str   = $end_dt->format( 'Y-m-d H:i:s' );

    // Çakışma kontrolü
    $conflict = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bookly_appointments
         WHERE staff_id = %d AND status IN ('approved','pending')
           AND start_date < %s AND end_date > %s",
        $staff_id, $end_str, $start_str
    ) );

    if ( $conflict > 0 ) {
        return new WP_REST_Response( [ 'error' => 'Slot is no longer available' ], 409 );
    }

    // Müşteriyi bul ya da oluştur
    $customer = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bookly_customers WHERE email = %s LIMIT 1",
        $cust_email
    ) );

    if ( $customer ) {
        $customer_id = $customer->id;
    } else {
        $parts = explode( ' ', trim( $cust_name ), 2 );
        $wpdb->insert( "{$wpdb->prefix}bookly_customers", [
            'first_name' => $parts[0] ?? '',
            'last_name'  => $parts[1] ?? '',
            'phone'      => $cust_phone,
            'email'      => $cust_email,
            'created_at' => current_time( 'mysql' ),
        ] );
        $customer_id = $wpdb->insert_id;
    }

    // Randevu oluştur
    $internal_note = trim( "ScanBook Ref: {$scanbook_ref}\n{$notes}" );
    $token         = wp_generate_password( 32, false ); // Bookly cancel/manage linkleri için
    $wpdb->insert( "{$wpdb->prefix}bookly_appointments", [
        'staff_id'      => $staff_id,
        'service_id'    => $service_id,
        'start_date'    => $start_str,
        'end_date'      => $end_str,
        'status'        => 'approved',
        'internal_note' => $internal_note,
        'created_from'  => 'api',
        'token'         => $token,
    ] );
    $appt_id = $wpdb->insert_id;

    if ( ! $appt_id ) {
        return new WP_REST_Response( [ 'error' => 'Failed to create appointment' ], 500 );
    }

    // Müşteri-randevu ilişkisi
    $wpdb->insert( "{$wpdb->prefix}bookly_customer_appointments", [
        'customer_id'        => $customer_id,
        'appointment_id'     => $appt_id,
        'status'             => 'approved',
        'number_of_persons'  => 1,
        'custom_fields'      => '[]',
    ] );

    // Bookly'nin kendi bildirim sistemini tetikle (email/SMS onayı)
    do_action( 'bookly_appointment_created', $appt_id );

    return new WP_REST_Response( [
        'appointment_id' => $appt_id,
        'start'          => $start_str,
        'end'            => $end_str,
        'token'          => $token,
    ], 201 );
}

/* ─── POST /appointments/{id}/cancel ────────────────────────────────────────── */

function scanbook_cancel_appointment( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $id = absint( $request->get_param( 'id' ) );
    $result = $wpdb->update(
        "{$wpdb->prefix}bookly_appointments",
        [ 'status' => 'cancelled' ],
        [ 'id'     => $id ]
    );

    if ( $result === false ) {
        return new WP_REST_Response( [ 'error' => 'Update failed' ], 500 );
    }
    return new WP_REST_Response( [ 'ok' => true, 'appointment_id' => $id ], 200 );
}

/* ─── Admin ayarlar sayfası ─────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
    add_options_page(
        'ScanBook Bridge',
        'ScanBook Bridge',
        'manage_options',
        'scanbook-bridge',
        'scanbook_settings_page'
    );
} );

function scanbook_settings_page(): void {
    if ( isset( $_POST['scanbook_save'] ) && check_admin_referer( 'scanbook_settings' ) ) {
        $new_key = sanitize_text_field( $_POST['scanbook_api_key'] ?? '' );
        if ( $new_key ) {
            update_option( 'scanbook_api_key', $new_key );
            echo '<div class="notice notice-success is-dismissible"><p><strong>API key kaydedildi.</strong></p></div>';
        }
    }

    $key = get_option( 'scanbook_api_key', '' );
    ?>
    <div class="wrap">
        <h1>ScanBook Bridge</h1>
        <p>Bu eklenti, ScanBook'un Bookly randevu verilerine güvenli erişimini sağlar.</p>
        <form method="post">
            <?php wp_nonce_field( 'scanbook_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="scanbook_api_key">API Key</label></th>
                    <td>
                        <input type="text" id="scanbook_api_key" name="scanbook_api_key"
                               value="<?php echo esc_attr( $key ); ?>"
                               class="regular-text" placeholder="Boş bırakırsanız mevcut key korunur" />
                        <p class="description">Bu key'i ScanBook admin panelindeki klinik Bookly ayarlarına girin.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button name="scanbook_save" type="submit" class="button-primary">Kaydet</button>
                <a href="<?php echo esc_url( rest_url( 'scanbook/v1/ping' ) ); ?>" target="_blank" class="button" style="margin-left:8px">
                    Bağlantıyı Test Et
                </a>
            </p>
        </form>

        <?php if ( $key ): ?>
        <hr />
        <h2>Endpoint'ler</h2>
        <table class="widefat" style="max-width:700px">
            <tr><td><code>GET</code></td><td><code><?php echo esc_html( rest_url( 'scanbook/v1/slots' ) ); ?>?service_id=177&amp;staff_id=1&amp;date_from=2026-07-05&amp;date_to=2026-07-12</code></td></tr>
            <tr><td><code>POST</code></td><td><code><?php echo esc_html( rest_url( 'scanbook/v1/appointments' ) ); ?></code></td></tr>
            <tr><td><code>POST</code></td><td><code><?php echo esc_html( rest_url( 'scanbook/v1/appointments/{id}/cancel' ) ); ?></code></td></tr>
        </table>
        <?php endif; ?>
    </div>
    <?php
}
