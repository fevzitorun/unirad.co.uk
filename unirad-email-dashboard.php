<?php
/*
Plugin Name: Unirad Email Dashboard
Plugin URI:  https://unirad.co.uk
Description: WP Admin dashboard for Brevo email stats (sent, opens, clicks, bounces, spam) and recent leads.
Version:     1.0.0
Author:      Unirad
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Brevo API Key ────────────────────────────────────────────────────────────
// Key is stored in the WordPress database (wp_options).
// Go to WP Admin → 📧 Email Dashboard → ⚙️ API Keys to enter your Brevo key.
define( 'UNIRAD_DASH_CACHE_TTL', 300 );

function unirad_brevo_get_key() {
    $opts = get_option( 'unirad_dash_settings', [] );
    return $opts['brevo_key'] ?? '';
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'unirad_dash_menu' );
add_action( 'admin_enqueue_scripts', 'unirad_dash_assets' );
add_action( 'wp_ajax_unirad_dash_refresh', 'unirad_dash_ajax_refresh' );

// ── Admin Menu ───────────────────────────────────────────────────────────────

function unirad_dash_menu() {
    add_menu_page(
        'Email Dashboard',
        '📧 Email Dashboard',
        'manage_options',
        'unirad-email-dashboard',
        'unirad_dash_page',
        'dashicons-email-alt',
        57
    );
    add_submenu_page(
        'unirad-email-dashboard',
        'API Keys',
        '&#9881;&#65039; API Keys',
        'manage_options',
        'unirad-api-keys',
        'unirad_dash_api_keys_page'
    );
}

function unirad_dash_api_keys_page() {
    if ( isset( $_POST['unirad_dash_keys_save'] ) ) {
        check_admin_referer( 'unirad_dash_keys_save' );
        $brevo = sanitize_text_field( wp_unslash( $_POST['brevo_key'] ?? '' ) );
        update_option( 'unirad_dash_settings', [ 'brevo_key' => $brevo ] );
        delete_transient( 'unirad_dash_stats_7d' );
        delete_transient( 'unirad_dash_stats_30d' );
        delete_transient( 'unirad_dash_emails' );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Saved.</strong> API keys updated and cache cleared.</p></div>';
    }

    $opts  = get_option( 'unirad_dash_settings', [] );
    $brevo = $opts['brevo_key'] ?? '';
    ?>
    <div class="wrap" style="max-width:640px;">
    <h1>&#9881;&#65039; API Keys</h1>
    <p style="color:#555;margin-bottom:24px;">All API keys are stored securely in the WordPress database (wp_options). They are <strong>never</strong> written to PHP source files or committed to version control.</p>

    <?php if ( empty( $brevo ) ) : ?>
    <div class="notice notice-warning"><p>&#9888; Brevo key not set — Email Dashboard stats will be unavailable.</p></div>
    <?php endif; ?>

    <form method="post">
    <?php wp_nonce_field( 'unirad_dash_keys_save' ); ?>

    <h2 style="font-size:15px;border-bottom:1px solid #e0e0e0;padding-bottom:8px;margin:20px 0 16px;">Brevo (Email Service)</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="brevo_key">Brevo API Key</label></th>
        <td>
          <input id="brevo_key" name="brevo_key" type="password" class="large-text"
            value="<?php echo esc_attr( $brevo ); ?>"
            placeholder="xkeysib-...">
          <p class="description">Get your key at <a href="https://app.brevo.com/settings/keys/api" target="_blank">app.brevo.com → Settings → API Keys</a></p>
          <?php if ( $brevo ) : ?>
          <p style="margin-top:8px;color:#00a896;font-weight:600;">&#10003; Key is set (<?php echo esc_html( substr( $brevo, 0, 16 ) ); ?>...)</p>
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <p><button type="submit" name="unirad_dash_keys_save" class="button button-primary button-large">Save API Keys</button></p>
    </form>
    </div>
    <?php
}

// ── Assets ───────────────────────────────────────────────────────────────────

function unirad_dash_assets( $hook ) {
    if ( strpos( $hook, 'unirad-email-dashboard' ) === false ) return;

    wp_enqueue_style(
        'unirad-dash',
        false,
        [],
        '1.0.0'
    );
}

// ── Brevo API Helper ──────────────────────────────────────────────────────────

function unirad_brevo_get( $endpoint, $params = [] ) {
    $key = unirad_brevo_get_key();
    if ( empty( $key ) ) {
        return new WP_Error( 'no_key', 'Brevo API key not configured.' );
    }

    $url = add_query_arg( $params, 'https://api.brevo.com/v3/' . ltrim( $endpoint, '/' ) );

    $response = wp_remote_get( $url, [
        'timeout' => 15,
        'headers' => [
            'api-key' => $key,
            'Accept'  => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 ) {
        $msg = isset( $body['message'] ) ? $body['message'] : "Brevo API error {$code}";
        return new WP_Error( 'brevo_error', $msg );
    }

    return $body;
}

// ── Data Fetchers (with transient cache) ──────────────────────────────────────

function unirad_dash_get_stats( $days = 7 ) {
    $cache_key = "unirad_dash_stats_{$days}d";
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $end   = gmdate( 'Y-m-d' );
    $start = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

    $data = unirad_brevo_get( 'smtp/statistics/aggregatedReport', [
        'startDate' => $start,
        'endDate'   => $end,
    ] );

    if ( is_wp_error( $data ) ) return $data;

    set_transient( $cache_key, $data, UNIRAD_DASH_CACHE_TTL );
    return $data;
}

function unirad_dash_get_recent_emails( $limit = 25 ) {
    $cache_key = 'unirad_dash_emails';
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $end   = gmdate( 'Y-m-d' );
    $start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

    // Use 'delivered' event filter — shows all recently delivered transactional emails
    $data = unirad_brevo_get( 'smtp/statistics/events', [
        'limit'     => $limit,
        'sort'      => 'desc',
        'event'     => 'delivered',
        'startDate' => $start,
        'endDate'   => $end,
    ] );

    if ( is_wp_error( $data ) ) return $data;

    $emails = isset( $data['events'] ) ? $data['events'] : [];
    set_transient( $cache_key, $emails, UNIRAD_DASH_CACHE_TTL );
    return $emails;
}

function unirad_dash_get_events( $event_type, $limit = 20 ) {
    $cache_key = "unirad_dash_events_{$event_type}";
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $end   = gmdate( 'Y-m-d' );
    $start = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

    $data = unirad_brevo_get( 'smtp/statistics/events', [
        'limit'     => $limit,
        'sort'      => 'desc',
        'event'     => $event_type,
        'startDate' => $start,
        'endDate'   => $end,
    ] );

    if ( is_wp_error( $data ) ) return $data;

    $events = isset( $data['events'] ) ? $data['events'] : [];
    set_transient( $cache_key, $events, UNIRAD_DASH_CACHE_TTL );
    return $events;
}

function unirad_dash_get_leads( $limit = 20 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'unirad_potential_bookings';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return [];
    }
    return $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit )
    );
}

// ── AJAX: Refresh (clears cache) ──────────────────────────────────────────────

function unirad_dash_ajax_refresh() {
    check_ajax_referer( 'unirad_dash_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

    foreach ( [ 'unirad_dash_stats_7d', 'unirad_dash_stats_30d', 'unirad_dash_emails',
                'unirad_dash_events_opened', 'unirad_dash_events_clicks',
                'unirad_dash_events_hardBounces', 'unirad_dash_events_softBounces',
                'unirad_dash_events_spamReports' ] as $k ) {
        delete_transient( $k );
    }
    wp_send_json_success();
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function unirad_pct( $num, $denom ) {
    if ( empty( $denom ) ) return '—';
    return round( $num / $denom * 100, 1 ) . '%';
}

function unirad_event_badge( $event ) {
    $map = [
        'delivered'   => ['Delivered',  '#00a896', '#e6f7f5'],
        'opened'      => ['Opened',     '#2271b1', '#e8f0fa'],
        'clicks'      => ['Clicked',    '#7c3aed', '#f0ebff'],
        'hardBounces' => ['Bounced',    '#c0392b', '#fdecea'],
        'softBounces' => ['Soft Bounce','#e67e22', '#fff3e0'],
        'spamReports' => ['Spam',       '#8b0000', '#ffe5e5'],
        'blocked'     => ['Blocked',    '#555',    '#f0f0f0'],
        'invalid'     => ['Invalid',    '#888',    '#f5f5f5'],
        'requests'    => ['Sent',       '#2c3e50', '#f0f4f8'],
    ];
    $e = isset( $map[ $event ] ) ? $map[ $event ] : [ ucfirst( $event ), '#555', '#f0f0f0' ];
    return sprintf(
        '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:%s;color:%s">%s</span>',
        esc_attr( $e[2] ), esc_attr( $e[1] ), esc_html( $e[0] )
    );
}

// ── Admin Page ────────────────────────────────────────────────────────────────

function unirad_dash_page() {
    $nonce   = wp_create_nonce( 'unirad_dash_nonce' );
    $ajax    = esc_url( admin_url( 'admin-ajax.php' ) );
    $key_set = ! empty( unirad_brevo_get_key() );

    $stats7  = $key_set ? unirad_dash_get_stats( 7 )  : null;
    $stats30 = $key_set ? unirad_dash_get_stats( 30 ) : null;
    $emails  = $key_set ? unirad_dash_get_recent_emails( 25 ) : [];
    $leads   = unirad_dash_get_leads( 25 );

    $s7 = ( $stats7 && ! is_wp_error( $stats7 ) ) ? $stats7 : null;
    $s3 = ( $stats30 && ! is_wp_error( $stats30 ) ) ? $stats30 : null;
    ?>
    <style>
    #unirad-dash{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:1200px;}
    #unirad-dash h1{display:flex;align-items:center;gap:10px;font-size:22px;color:#1e1e1e;margin-bottom:6px;}
    #unirad-dash .sub{color:#666;font-size:13px;margin-bottom:22px;}

    /* Stat cards */
    .ud-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:28px;}
    .ud-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px 18px;position:relative;}
    .ud-card .ud-label{font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
    .ud-card .ud-val{font-size:28px;font-weight:700;color:#1e1e1e;line-height:1;}
    .ud-card .ud-sub{font-size:11px;color:#aaa;margin-top:4px;}
    .ud-card .ud-icon{position:absolute;top:14px;right:14px;font-size:18px;opacity:.18;}
    .ud-card.green{border-top:3px solid #00a896;}
    .ud-card.blue {border-top:3px solid #2271b1;}
    .ud-card.purple{border-top:3px solid #7c3aed;}
    .ud-card.red  {border-top:3px solid #c0392b;}
    .ud-card.orange{border-top:3px solid #e67e22;}

    /* Section */
    .ud-section{background:#fff;border:1px solid #e0e0e0;border-radius:10px;margin-bottom:24px;overflow:hidden;}
    .ud-section-hd{padding:14px 18px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;}
    .ud-section-hd h2{font-size:14px;font-weight:700;color:#1e1e1e;margin:0;}
    .ud-section-hd .ud-hint{font-size:11px;color:#999;}

    /* Table */
    .ud-table{width:100%;border-collapse:collapse;}
    .ud-table th{font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.4px;padding:10px 16px;text-align:left;border-bottom:1px solid #f0f0f0;background:#fafafa;}
    .ud-table td{padding:9px 16px;font-size:12.5px;color:#333;border-bottom:1px solid #f7f7f7;vertical-align:middle;}
    .ud-table tr:last-child td{border-bottom:none;}
    .ud-table tr:hover td{background:#fafdf9;}
    .ud-table .mono{font-family:monospace;font-size:11px;color:#555;}
    .ud-table .muted{color:#aaa;font-size:11px;}

    /* Period tabs */
    .ud-tabs{display:flex;gap:6px;}
    .ud-tab{border:1px solid #d0d0d0;background:#fff;color:#444;font-size:11px;font-weight:600;padding:4px 12px;border-radius:16px;cursor:pointer;transition:all .12s;}
    .ud-tab.active,.ud-tab:hover{background:#00a896;border-color:#00a896;color:#fff;}

    /* Notice */
    .ud-notice{padding:14px 18px;font-size:13px;}
    .ud-notice.warn{background:#fff8e1;border-left:4px solid #ffc107;color:#7a5900;}
    .ud-notice.err{background:#fdecea;border-left:4px solid #e53935;color:#7b1010;}
    .ud-notice code{background:rgba(0,0,0,.06);padding:2px 6px;border-radius:3px;font-size:12px;}

    /* Refresh btn */
    #ud-refresh{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#00a896;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;transition:background .12s;}
    #ud-refresh:hover{background:#008a7d;}
    #ud-refresh.spinning svg{animation:ud-spin .8s linear infinite;}
    @keyframes ud-spin{to{transform:rotate(360deg)}}

    /* Status dot for leads */
    .ud-dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:5px;}
    .ud-dot.pending{background:#f59e0b;}
    .ud-dot.contacted{background:#00a896;}
    .ud-dot.booked{background:#7c3aed;}
    </style>

    <div id="unirad-dash">
      <h1>
        <span style="color:#00a896;">&#9829;</span> Unirad Email Dashboard
        <button id="ud-refresh" title="Refresh data (clears 5-min cache)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
          Refresh
        </button>
      </h1>
      <p class="sub">Email stats via Brevo API &middot; Leads from booking database &middot; Auto-refreshes every 5 minutes &middot; <?php echo esc_html( gmdate( 'H:i \o\n j M Y' ) ); ?></p>

      <?php if ( ! $key_set ) : ?>
        <div class="ud-notice warn">
          <strong>Brevo API key not set.</strong> Open <code>unirad-email-dashboard.php</code> on line 14 and replace the placeholder with your Brevo API key (starts with <code>xkeysib-</code>), then re-upload.
        </div>
      <?php elseif ( is_wp_error( $stats7 ) ) : ?>
        <div class="ud-notice err">
          <strong>Brevo API error:</strong> <?php echo esc_html( $stats7->get_error_message() ); ?>
        </div>
      <?php endif; ?>

      <?php if ( $key_set ) : ?>

      <!-- ── Stats overview ──────────────────────────────────────── -->
      <div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;">
        <strong style="font-size:13px;color:#444;">Last:</strong>
        <div class="ud-tabs">
          <button class="ud-tab active" data-days="7">7 days</button>
          <button class="ud-tab" data-days="30">30 days</button>
        </div>
      </div>

      <div class="ud-cards" id="ud-stat-cards">
        <?php
        $s = $s7; // default to 7-day stats
        $sent      = $s ? intval( $s['requests']    ?? 0 ) : 0;
        $delivered = $s ? intval( $s['delivered']   ?? 0 ) : 0;
        $opens     = $s ? intval( $s['uniqueOpens'] ?? 0 ) : 0;
        $clicks    = $s ? intval( $s['uniqueClicks'] ?? 0 ) : 0;
        $bounces   = $s ? intval( ($s['hardBounces'] ?? 0) + ($s['softBounces'] ?? 0) ) : 0;
        $spam      = $s ? intval( $s['spamReports'] ?? 0 ) : 0;
        ?>
        <div class="ud-card green">
          <div class="ud-icon">&#128231;</div>
          <div class="ud-label">Sent</div>
          <div class="ud-val"><?php echo esc_html( $sent ?: '—' ); ?></div>
          <div class="ud-sub">emails dispatched</div>
        </div>
        <div class="ud-card green">
          <div class="ud-icon">&#10003;</div>
          <div class="ud-label">Delivered</div>
          <div class="ud-val"><?php echo esc_html( $sent ? unirad_pct( $delivered, $sent ) : '—' ); ?></div>
          <div class="ud-sub"><?php echo esc_html( $delivered ); ?> emails</div>
        </div>
        <div class="ud-card blue">
          <div class="ud-icon">&#128065;</div>
          <div class="ud-label">Opened</div>
          <div class="ud-val"><?php echo esc_html( $sent ? unirad_pct( $opens, $delivered ?: $sent ) : '—' ); ?></div>
          <div class="ud-sub"><?php echo esc_html( $opens ); ?> unique opens</div>
        </div>
        <div class="ud-card purple">
          <div class="ud-icon">&#128070;</div>
          <div class="ud-label">Clicked</div>
          <div class="ud-val"><?php echo esc_html( $sent ? unirad_pct( $clicks, $delivered ?: $sent ) : '—' ); ?></div>
          <div class="ud-sub"><?php echo esc_html( $clicks ); ?> unique clicks</div>
        </div>
        <div class="ud-card red">
          <div class="ud-icon">&#9747;</div>
          <div class="ud-label">Bounced</div>
          <div class="ud-val"><?php echo esc_html( $bounces ?: ( $sent ? '0' : '—' ) ); ?></div>
          <div class="ud-sub">hard + soft</div>
        </div>
        <div class="ud-card orange">
          <div class="ud-icon">&#9888;</div>
          <div class="ud-label">Spam</div>
          <div class="ud-val" style="<?php echo $spam > 0 ? 'color:#c0392b;' : ''; ?>"><?php echo esc_html( $spam ?: ( $sent ? '0' : '—' ) ); ?></div>
          <div class="ud-sub">spam reports</div>
        </div>
      </div>

      <!-- ── Recent emails ──────────────────────────────────────── -->
      <div class="ud-section">
        <div class="ud-section-hd">
          <h2>&#128231; Recent Emails</h2>
          <span class="ud-hint">Last 30 days &middot; most recent first</span>
        </div>
        <?php if ( is_wp_error( $emails ) ) : ?>
          <div class="ud-notice err"><?php echo esc_html( $emails->get_error_message() ); ?></div>
        <?php elseif ( empty( $emails ) ) : ?>
          <div class="ud-notice warn">No emails found for the last 30 days.</div>
        <?php else : ?>
          <table class="ud-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>To</th>
                <th>Subject</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $emails as $email ) :
                $date    = isset( $email['date'] ) ? wp_date( 'd M, H:i', strtotime( $email['date'] ) ) : '—';
                $to      = $email['email'] ?? '—';
                $subject = $email['subject'] ?? '(no subject)';
                $event   = $email['event'] ?? 'unknown';
              ?>
                <tr>
                  <td class="mono"><?php echo esc_html( $date ); ?></td>
                  <td><?php echo esc_html( $to ); ?></td>
                  <td><?php echo esc_html( $subject ); ?></td>
                  <td><?php echo unirad_event_badge( $event ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <?php endif; // key_set ?>

      <!-- ── Recent Leads ───────────────────────────────────────── -->
      <div class="ud-section">
        <div class="ud-section-hd">
          <h2>&#128101; Recent Leads</h2>
          <span class="ud-hint">From booking database &middot; most recent first</span>
        </div>
        <?php if ( empty( $leads ) ) : ?>
          <div class="ud-notice warn">No leads found. The <code>unirad_potential_bookings</code> table may not exist yet — activate the Unirad Quick Booking plugin first.</div>
        <?php else : ?>
          <table class="ud-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Email</th>
                <th>Scan</th>
                <th>Price</th>
                <th>Status</th>
                <th>Recovery Email</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $leads as $lead ) :
                $date   = wp_date( 'd M, H:i', strtotime( $lead->created_at ) );
                $status = $lead->status ?? 'pending';
                $sent   = ! empty( $lead->recovery_sent );
              ?>
                <tr>
                  <td class="mono"><?php echo esc_html( $date ); ?></td>
                  <td><?php echo esc_html( $lead->name ?: '—' ); ?></td>
                  <td style="font-size:11.5px;"><?php echo esc_html( $lead->email ?: '—' ); ?></td>
                  <td style="font-size:11.5px;"><?php echo esc_html( $lead->scan_type ?: '—' ); ?></td>
                  <td><?php
                    $p = preg_replace( '/[^\d.]/', '', $lead->scan_price ?? '' );
                    echo $p ? esc_html( html_entity_decode( '&pound;' ) . $p ) : '<span class="muted">—</span>';
                  ?></td>
                  <td>
                    <?php if ( $status === 'callback' ) : ?>
                      <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fff3e0;color:#e67e22;">&#128222; Callback</span>
                    <?php elseif ( $status === 'converted' ) : ?>
                      <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#e6f7f5;color:#00a896;">&#10003; Converted</span>
                    <?php else : ?>
                      <span class="ud-dot <?php echo esc_attr( $status ); ?>"></span>
                      <?php echo esc_html( ucfirst( $status ) ); ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ( $status === 'callback' ) : ?>
                      <span class="muted">N/A</span>
                    <?php elseif ( $sent ) : ?>
                      <span style="color:#00a896;font-weight:600;font-size:11px;">&#10003; Sent</span>
                    <?php else : ?>
                      <span class="muted">Not sent</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <?php
      // ── Chat Logs Section ────────────────────────────────────────────────────
      global $wpdb;
      $chat_table = $wpdb->prefix . 'unirad_chat_logs';
      $chat_logs  = [];
      if ( $wpdb->get_var( "SHOW TABLES LIKE '{$chat_table}'" ) === $chat_table ) {
          $chat_logs = $wpdb->get_results(
              "SELECT * FROM {$chat_table} ORDER BY updated_at DESC LIMIT 50"
          );
      }
      ?>
      <div class="ud-section">
        <div class="ud-section-hd">
          <h2>💬 Aria Chat Logs <span style="font-size:11px;color:#aaa;font-weight:400;">(last 50 conversations)</span></h2>
        </div>
        <?php if ( empty( $chat_logs ) ) : ?>
          <div class="ud-notice warn">No chat logs yet — conversations will appear here once patients start chatting.</div>
        <?php else : ?>
          <table class="ud-table">
            <thead><tr>
              <th>Date / Time</th>
              <th>Scan Mentioned</th>
              <th>Turns</th>
              <th>Callback?</th>
              <th>Conversation</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $chat_logs as $log ) :
              $dt       = date( 'd M H:i', strtotime( $log->created_at ) );
              $msgs     = json_decode( $log->messages, true );
              $preview  = '';
              if ( is_array( $msgs ) ) {
                  foreach ( $msgs as $m ) {
                      if ( $m['role'] === 'user' ) {
                          $preview = esc_html( mb_substr( $m['content'], 0, 80 ) ) . ( strlen( $m['content'] ) > 80 ? '…' : '' );
                          break;
                      }
                  }
              }
            ?>
              <tr>
                <td class="mono"><?php echo esc_html( $dt ); ?></td>
                <td><?php echo $log->scan_mention ? '<span style="background:#e6f9f7;color:#007a6e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">' . esc_html( ucfirst( $log->scan_mention ) ) . '</span>' : '<span class="muted">—</span>'; ?></td>
                <td style="text-align:center;"><?php echo (int) $log->turn_count; ?></td>
                <td><?php echo $log->has_callback ? '<span style="color:#e67e22;font-weight:700;">📞 Yes</span>' : '<span class="muted">—</span>'; ?></td>
                <td>
                  <details style="font-size:11.5px;">
                    <summary style="cursor:pointer;color:#00a896;font-weight:600;">View (<?php echo is_array( $msgs ) ? count( $msgs ) : 0; ?> messages)</summary>
                    <div style="margin-top:8px;max-height:300px;overflow-y:auto;border:1px solid #eee;border-radius:6px;padding:10px;">
                    <?php if ( is_array( $msgs ) ) : foreach ( $msgs as $m ) : ?>
                      <div style="margin-bottom:8px;<?php echo $m['role'] === 'user' ? 'text-align:right;' : ''; ?>">
                        <span style="display:inline-block;max-width:85%;padding:5px 9px;border-radius:10px;font-size:11px;line-height:1.5;<?php echo $m['role'] === 'user' ? 'background:#e8f5e9;color:#2e7d32;' : 'background:#f0faf9;color:#005a52;'; ?>">
                          <strong><?php echo $m['role'] === 'user' ? 'Patient' : 'Aria'; ?>:</strong> <?php echo esc_html( mb_substr( $m['content'], 0, 300 ) ) . ( strlen( $m['content'] ) > 300 ? '…' : '' ); ?>
                        </span>
                      </div>
                    <?php endforeach; endif; ?>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div><!-- #unirad-dash -->

    <script>
    (function(){
    var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
    var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

    // ── Period tab toggle (client-side; full stats are pre-rendered for both) ──
    var s7  = <?php echo wp_json_encode( $s7 ); ?>;
    var s30 = <?php echo wp_json_encode( $s3 ); ?>;

    document.querySelectorAll('.ud-tab').forEach(function(tab){
      tab.addEventListener('click', function(){
        document.querySelectorAll('.ud-tab').forEach(function(t){ t.classList.remove('active'); });
        this.classList.add('active');
        var days = this.dataset.days;
        var s    = days === '30' ? s30 : s7;
        if(!s) return;
        renderCards(s);
      });
    });

    function fmt(n){ return n !== undefined && n !== null ? n : 0; }
    function pct(n, d){ if(!d) return '—'; return (n/d*100).toFixed(1) + '%'; }

    function renderCards(s){
      var sent      = fmt(s.requests);
      var delivered = fmt(s.delivered);
      var opens     = fmt(s.uniqueOpens);
      var clicks    = fmt(s.uniqueClicks);
      var bounces   = fmt(s.hardBounces) + fmt(s.softBounces);
      var spam      = fmt(s.spamReports);
      var base      = delivered || sent;

      var vals = [
        sent || '—',
        sent ? pct(delivered, sent) : '—',
        sent ? pct(opens, base) : '—',
        sent ? pct(clicks, base) : '—',
        sent ? bounces : '—',
        sent ? spam : '—'
      ];
      var subs = [
        'emails dispatched',
        delivered + ' emails',
        opens + ' unique opens',
        clicks + ' unique clicks',
        'hard + soft',
        'spam reports'
      ];

      document.querySelectorAll('.ud-card .ud-val').forEach(function(el, i){
        if(vals[i] !== undefined) el.textContent = vals[i];
      });
      document.querySelectorAll('.ud-card .ud-sub').forEach(function(el, i){
        if(subs[i] !== undefined) el.textContent = subs[i];
      });
    }

    // ── Refresh button ─────────────────────────────────────────────────────────
    document.getElementById('ud-refresh').addEventListener('click', function(){
      var btn = this;
      btn.classList.add('spinning');
      var fd = new FormData();
      fd.append('action', 'unirad_dash_refresh');
      fd.append('nonce',  NONCE);
      fetch(AJAX, {method:'POST', body:fd})
        .then(function(){ window.location.reload(); })
        .catch(function(){ btn.classList.remove('spinning'); });
    });

    })();
    </script>
    <?php
}
