<?php
/*
Plugin Name: Unirad CRM Engine
Plugin URI:  https://unirad.co.uk
Description: Brevo CRM contact tagging, Aria lead scoring, and A/B test analytics for recovery emails.
Version:     1.0.0
Author:      Unirad
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Bootstrap ──────────────────────────────────────────────────────────────────
add_action( 'init',       'unirad_crm_maybe_upgrade_db' );
add_action( 'admin_menu', 'unirad_crm_menu' );

// Hooks fired by other Unirad plugins
add_action( 'unirad_lead_created',       'unirad_crm_on_lead_created'   );
add_action( 'unirad_lead_abandoned',     'unirad_crm_on_lead_abandoned'  );
add_action( 'unirad_lead_converted',     'unirad_crm_on_lead_converted'  );
add_action( 'unirad_aria_high_interest', 'unirad_crm_on_high_interest'   );

// ── DB Upgrade ─────────────────────────────────────────────────────────────────

function unirad_crm_maybe_upgrade_db() {
    global $wpdb;
    $table = $wpdb->prefix . 'unirad_potential_bookings';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return;

    $cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );

    if ( ! in_array( 'subject_ab', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN subject_ab tinyint(1) DEFAULT NULL COMMENT '0=control 1=urgency'" );
    }
    if ( ! in_array( 'interest_score', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN interest_score tinyint DEFAULT 0 COMMENT '0=normal 1=warm 2=hot'" );
    }
    if ( ! in_array( 'crm_tagged', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN crm_tagged tinyint(1) DEFAULT 0" );
    }
}

// ── Brevo CRM Contact Upsert ───────────────────────────────────────────────────

function unirad_crm_tag_contact( $email, $name, $scan_type, $status = 'Lead' ) {
    if ( empty( $email ) ) return false;

    $brevo_key = get_option( 'unirad_dash_settings', [] )['brevo_key'] ?? '';
    if ( empty( $brevo_key ) ) return false;

    $parts = explode( ' ', trim( $name ) );
    $first = $parts[0] ?? '';
    $last  = trim( implode( ' ', array_slice( $parts, 1 ) ) );

    $body = [
        'email'         => $email,
        'attributes'    => [
            'FIRSTNAME'      => $first,
            'LASTNAME'       => $last,
            'SCAN_INTEREST'  => $scan_type,
            'LEAD_STATUS'    => $status,
            'CLINIC'         => 'Unirad Glasgow',
        ],
        'updateEnabled' => true,
    ];

    $response = wp_remote_post( 'https://api.brevo.com/v3/contacts', [
        'timeout' => 10,
        'headers' => [
            'api-key'      => $brevo_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode( $body ),
    ] );

    return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 300;
}

function unirad_crm_mark_tagged( $lead_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'unirad_potential_bookings';
    $wpdb->update( $table, [ 'crm_tagged' => 1 ], [ 'id' => $lead_id ], [ '%d' ], [ '%d' ] );
}

// ── Action Handlers ────────────────────────────────────────────────────────────

function unirad_crm_on_lead_created( $data ) {
    $ok = unirad_crm_tag_contact( $data['email'], $data['name'], $data['scan_type'], 'Lead' );
    if ( $ok && ! empty( $data['lead_id'] ) ) unirad_crm_mark_tagged( $data['lead_id'] );
}

function unirad_crm_on_lead_abandoned( $lead ) {
    $ok = unirad_crm_tag_contact( $lead->email, $lead->name, $lead->scan_type, 'Abandoned' );
    if ( $ok ) unirad_crm_mark_tagged( $lead->id );
}

function unirad_crm_on_lead_converted( $data ) {
    unirad_crm_tag_contact( $data['email'], $data['name'], $data['scan_type'], 'Converted' );
}

function unirad_crm_on_high_interest( $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'unirad_potential_bookings';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return;

    if ( ! empty( $data['email'] ) ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE `{$table}` SET interest_score = 2 WHERE email = %s AND status = 'pending' AND interest_score < 2",
            $data['email']
        ) );
    } elseif ( ! empty( $data['session_key'] ) ) {
        // Store transient flag so it can be applied when lead email is captured
        set_transient( 'unirad_hi_' . md5( $data['session_key'] ), 1, HOUR_IN_SECONDS * 2 );
    }
}

// ── Admin Menu ─────────────────────────────────────────────────────────────────

function unirad_crm_menu() {
    add_submenu_page(
        'unirad-email-dashboard',
        'CRM Intelligence',
        '&#129504; CRM Intelligence',
        'manage_options',
        'unirad-crm',
        'unirad_crm_page'
    );
}

// ── Admin Page ─────────────────────────────────────────────────────────────────

function unirad_crm_page() {
    global $wpdb;
    $table     = $wpdb->prefix . 'unirad_potential_bookings';
    $has_table = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;

    $col_ok = false;
    if ( $has_table ) {
        $cols   = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
        $col_ok = in_array( 'subject_ab', $cols, true );
    }

    // A/B test data
    $ab_data = [];
    if ( $has_table && $col_ok ) {
        $rows = $wpdb->get_results(
            "SELECT subject_ab, status, COUNT(*) as total
             FROM `{$table}`
             WHERE subject_ab IS NOT NULL
             GROUP BY subject_ab, status"
        );
        foreach ( $rows as $r ) {
            $ab_data[ (int) $r->subject_ab ][ $r->status ] = (int) $r->total;
        }
    }

    // High interest leads
    $high_leads = $has_table && $col_ok
        ? $wpdb->get_results( "SELECT * FROM `{$table}` WHERE interest_score >= 2 ORDER BY created_at DESC LIMIT 30" )
        : [];

    // CRM tag count
    $crm_count = $has_table && $col_ok
        ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE crm_tagged = 1" )
        : 0;

    $brevo_set = ! empty( get_option( 'unirad_dash_settings', [] )['brevo_key'] ?? '' );

    ?>
    <div class="wrap" id="unirad-crm" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:1100px;">
    <h1 style="display:flex;align-items:center;gap:10px;">&#129504; CRM Intelligence</h1>
    <p style="color:#666;font-size:13px;margin-bottom:24px;">Real-time Brevo CRM sync &middot; A/B test analytics &middot; Aria lead scoring</p>

    <?php if ( ! $brevo_set ) : ?>
    <div class="notice notice-warning" style="margin-bottom:20px;"><p>&#9888; Brevo API key not set. <a href="<?php echo admin_url('admin.php?page=unirad-api-keys'); ?>">Add it in API Keys →</a> to enable CRM contact sync.</p></div>
    <?php endif; ?>

    <!-- ── Summary Cards ───────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:32px;">

      <?php
      $ab_totals = [0 => 0, 1 => 0];
      $ab_conv   = [0 => 0, 1 => 0];
      foreach ( $ab_data as $v => $statuses ) {
          foreach ( $statuses as $s => $n ) {
              $ab_totals[ $v ] += $n;
              if ( $s === 'converted' ) $ab_conv[ $v ] = $n;
          }
      }

      $cards = [
          [ 'A/B Variant A', $ab_totals[0], 'Sent (Control)', '#2271b1' ],
          [ 'A/B Variant B', $ab_totals[1], 'Sent (URGENT ⏰)', '#9333ea' ],
          [ 'Brevo CRM Synced', $crm_count, 'Contacts tagged', '#00a896' ],
          [ '🔥 High Interest', count( $high_leads ), 'Aria 5+ turn leads', '#dc2626' ],
      ];
      foreach ( $cards as [$label, $val, $sub, $color] ) :
      ?>
      <div style="background:#fff;border:1px solid #e0e0e0;border-top:3px solid <?php echo esc_attr($color); ?>;border-radius:10px;padding:16px 18px;">
        <div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php echo esc_html($label); ?></div>
        <div style="font-size:30px;font-weight:700;color:#1e1e1e;line-height:1;"><?php echo esc_html($val); ?></div>
        <div style="font-size:11px;color:#aaa;margin-top:4px;"><?php echo esc_html($sub); ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── A/B Test Detail ─────────────────────────────────────── -->
    <h2 style="font-size:16px;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:16px;">Recovery Email A/B Test — Subject Line</h2>

    <?php if ( ! $col_ok ) : ?>
    <p style="color:#888;font-size:13px;">Tracking columns not yet created — load the page again to trigger DB upgrade, or wait for the first recovery email to be sent.</p>
    <?php else : ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;margin-bottom:32px;">
      <thead>
        <tr style="background:#f7f9fc;">
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Variant</th>
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Subject Line</th>
          <th style="text-align:center;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Sent</th>
          <th style="text-align:center;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Converted</th>
          <th style="text-align:center;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Conv. Rate</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( [0 => 'Complete your [scan] booking — Unirad Glasgow', 1 => 'URGENT: Your priority MRI slot is expiring soon ⏰'] as $v => $subject ) :
          $sent = $ab_totals[ $v ];
          $conv = $ab_conv[ $v ];
          $rate = $sent > 0 ? round( $conv / $sent * 100, 1 ) : '—';
          $winner_style = $v === 1 && $ab_conv[1] > $ab_conv[0] ? 'background:#f0fdf4;' : '';
      ?>
        <tr style="<?php echo $winner_style; ?>">
          <td style="padding:11px 16px;border-bottom:1px solid #f5f5f5;font-weight:700;color:<?php echo $v === 0 ? '#2271b1' : '#9333ea'; ?>;">Variant <?php echo $v === 0 ? 'A' : 'B'; ?></td>
          <td style="padding:11px 16px;border-bottom:1px solid #f5f5f5;"><?php echo esc_html($subject); ?></td>
          <td style="padding:11px 16px;border-bottom:1px solid #f5f5f5;text-align:center;"><?php echo $sent ?: '0'; ?></td>
          <td style="padding:11px 16px;border-bottom:1px solid #f5f5f5;text-align:center;color:#00a896;font-weight:700;"><?php echo $conv ?: '0'; ?></td>
          <td style="padding:11px 16px;border-bottom:1px solid #f5f5f5;text-align:center;font-weight:700;"><?php echo $rate !== '—' ? $rate . '%' : '—'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:12px;color:#888;margin-top:-24px;margin-bottom:32px;">&#9432; Collect at least 50 sends per variant before drawing conclusions. Declare a winner at 95%+ statistical confidence.</p>
    <?php endif; ?>

    <!-- ── High Interest Leads ─────────────────────────────────── -->
    <h2 style="font-size:16px;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:16px;">&#128293; High-Interest Aria Leads (5+ conversation turns)</h2>
    <?php if ( empty( $high_leads ) ) : ?>
      <p style="color:#888;font-size:13px;">No high-interest leads yet. Patients who have 5 or more turns with Aria without booking will appear here — ideal targets for a personal follow-up call.</p>
    <?php else : ?>
    <table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">
      <thead>
        <tr style="background:#fff8f0;">
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Name</th>
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Email</th>
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Scan Interest</th>
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Status</th>
          <th style="text-align:left;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Date</th>
          <th style="text-align:center;padding:10px 16px;color:#555;font-weight:600;border-bottom:1px solid #e0e0e0;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $high_leads as $l ) :
          $sc = $l->status === 'converted' ? '#00a896' : ( $l->recovery_sent ? '#f59e0b' : '#dc2626' );
      ?>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-weight:600;"><?php echo esc_html( $l->name ); ?></td>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;"><a href="mailto:<?php echo esc_attr($l->email); ?>" style="color:#2271b1;"><?php echo esc_html($l->email); ?></a></td>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;"><?php echo esc_html($l->scan_type); ?></td>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;"><span style="color:<?php echo $sc; ?>;font-weight:700;"><?php echo esc_html(ucfirst($l->status)); ?></span></td>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;color:#888;"><?php echo esc_html(date('d M Y', strtotime($l->created_at))); ?></td>
          <td style="padding:10px 16px;border-bottom:1px solid #f5f5f5;text-align:center;">
            <?php if ( $l->email && $l->status !== 'converted' ) : ?>
            <a href="mailto:<?php echo esc_attr($l->email); ?>?subject=Your%20MRI%20Enquiry%20%E2%80%94%20Unirad%20Glasgow&body=Hi%20<?php echo rawurlencode(explode(' ',$l->name)[0]); ?>%2C%0A%0A" class="button button-small">Follow Up</a>
            <?php elseif ($l->status === 'converted') : ?>
            <span style="color:#00a896;">&#10003; Booked</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    </div>
    <?php
}
