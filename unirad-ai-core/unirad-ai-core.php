<?php
/*
Plugin Name: Unirad AI Core
Plugin URI:  https://unirad.co.uk
Description: AI MRI concierge chatbot (Aria) powered by Anthropic Claude. Floating widget on all pages.
Version:     2.0.0
Author:      Unirad
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ── API Configuration ────────────────────────────────────────────────────────
// API key is stored securely in the WordPress database (wp_options).
// Go to WP Admin → 📧 Email Dashboard → 🤖 Aria Settings to enter your key.
define( 'UNIRAD_AI_MODEL',     'claude-haiku-4-5' );
define( 'UNIRAD_AI_MAX_TURNS', 20 );
define( 'UNIRAD_AI_TTL',       3600 );

function unirad_ai_get_key() {
    $opts = get_option( 'unirad_ai_settings', [] );
    return $opts['api_key'] ?? '';
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'wp_footer',         'unirad_ai_widget'   );
add_action( 'admin_menu',        'unirad_ai_settings_menu' );
add_action( 'wp_ajax_unirad_ai_chat',           'unirad_ai_handle_chat'     );
add_action( 'wp_ajax_nopriv_unirad_ai_chat',    'unirad_ai_handle_chat'     );
add_action( 'wp_ajax_unirad_ai_reset',          'unirad_ai_handle_reset'    );
add_action( 'wp_ajax_nopriv_unirad_ai_reset',   'unirad_ai_handle_reset'    );
add_action( 'wp_ajax_unirad_ai_callback',       'unirad_ai_handle_callback' );
add_action( 'wp_ajax_nopriv_unirad_ai_callback','unirad_ai_handle_callback' );

// ── Admin Settings ────────────────────────────────────────────────────────────

function unirad_ai_settings_menu() {
    add_submenu_page(
        'unirad-email-dashboard',
        'Aria AI Settings',
        '&#129302; Aria Settings',
        'manage_options',
        'unirad-aria-settings',
        'unirad_ai_settings_page'
    );
}

function unirad_ai_settings_page() {
    if ( isset( $_POST['unirad_ai_save'] ) ) {
        check_admin_referer( 'unirad_ai_save' );
        $key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        update_option( 'unirad_ai_settings', [ 'api_key' => $key ] );
        echo '<div class="notice notice-success is-dismissible"><p><strong>Saved.</strong> Aria is now using the new API key.</p></div>';
    }

    $opts    = get_option( 'unirad_ai_settings', [] );
    $key     = $opts['api_key'] ?? '';
    $key_set = ! empty( $key );
    ?>
    <div class="wrap" style="max-width:640px;">
    <h1>&#129302; Aria AI Settings</h1>
    <p style="color:#555;margin-bottom:24px;">Your Anthropic API key is stored in the WordPress database (wp_options) — it is <strong>never</strong> written to PHP source files or committed to version control.</p>

    <?php if ( ! $key_set ) : ?>
    <div class="notice notice-warning" style="margin-bottom:20px;"><p>&#9888; No API key set — Aria will not respond until you save a valid key below.</p></div>
    <?php endif; ?>

    <form method="post">
    <?php wp_nonce_field( 'unirad_ai_save' ); ?>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="api_key">Anthropic API Key</label></th>
        <td>
          <input id="api_key" name="api_key" type="password" class="large-text"
            value="<?php echo esc_attr( $key ); ?>"
            placeholder="sk-ant-api03-...">
          <p class="description">
            Get your key at <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>.
            Model in use: <strong><?php echo esc_html( UNIRAD_AI_MODEL ); ?></strong>
          </p>
          <?php if ( $key_set ) : ?>
          <p style="margin-top:8px;color:#00a896;font-weight:600;">&#10003; Key is set (<?php echo esc_html( substr( $key, 0, 14 ) ); ?>...)</p>
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <p><button type="submit" name="unirad_ai_save" class="button button-primary button-large">Save Key</button></p>
    </form>
    </div>
    <?php
}

// ── Session / History ────────────────────────────────────────────────────────

function unirad_ai_session_key() {
    $cookie = 'uai_sid';
    if ( empty( $_COOKIE[ $cookie ] ) ) {
        $sid = 'uai_' . bin2hex( random_bytes( 16 ) );
        if ( ! headers_sent() ) {
            setcookie( $cookie, $sid, time() + UNIRAD_AI_TTL, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        $_COOKIE[ $cookie ] = $sid;
    }
    $safe = preg_replace( '/[^a-zA-Z0-9_]/', '', $_COOKIE[ $cookie ] );
    return 'unirad_ai_hist_' . $safe;
}

function unirad_ai_get_history() {
    $history = get_transient( unirad_ai_session_key() );
    return is_array( $history ) ? $history : [];
}

function unirad_ai_save_history( array $history ) {
    if ( count( $history ) > UNIRAD_AI_MAX_TURNS ) {
        $history = array_slice( $history, -UNIRAD_AI_MAX_TURNS );
    }
    set_transient( unirad_ai_session_key(), $history, UNIRAD_AI_TTL );
}

// ── System Prompt ────────────────────────────────────────────────────────────

function unirad_ai_build_catalog_text() {
    if ( ! function_exists( 'uhr9_catalog' ) ) {
        return "- Brain, Spine, Joint and Body MRI scans from £290\n- Specialist scans (Cardiac £575, Prostate £495, Breast £420)\n- Full list at unirad.co.uk";
    }

    $cat   = uhr9_catalog();
    $lines = [];

    foreach ( $cat['mri'] as $item ) {
        $extra = $item['lat'] ? ' | bilateral: £' . $item['priceBoth'] : '';
        $lines[] = '• ' . $item['name'] . ': £' . $item['price'] . $extra;
    }

    return implode( "\n", $lines );
}

function unirad_ai_system_prompt() {
    $catalog = unirad_ai_build_catalog_text();

    return "You are Aria, the friendly MRI concierge at Unirad Diagnostic Imaging — Glasgow's leading private MRI clinic. You help patients choose the right scan, understand preparation, and book quickly.

CLINIC FACTS:
• Location: Glasgow, Scotland
• Same-week appointments available — no NHS waiting lists
• Expert radiologist report included in every price, delivered within 5 working days
• No GP referral required — self-refer and book online
• Online booking: https://unirad.co.uk/book-your-scan/
• Phone: 0141 846 9116 (Mon–Fri, 10am–7pm)
• 5-star Google rating · Free on-site parking · Secure payment

MRI SCAN CATALOG (all prices include radiologist report):
$catalog

FULL BODY MRI PACKAGES:
• Silver Package — Brain + Abdomen & Pelvis: £590
• Gold Package — Brain + Whole Spine + Abdomen & Pelvis: £1,210
• Platinum Package — Heart + Brain + Whole Spine + Abdomen & Pelvis: £1,660

GP SERVICES:
• GP Referral Letter: £40
• Online GP Consultation: £45
• GP Consultation After Scan: £50
• In-Clinic GP Consultation: £99

COMMON QUESTIONS:
Q: Do I need a referral? A: No — self-refer and book directly online.
Q: How do I prepare? A: Remove metal jewellery, wear comfortable clothing. For abdominal/MRCP scans fast for 4 hours. Most other scans need no prep.
Q: I'm claustrophobic. A: We use a wide-bore scanner (larger opening) which most patients find comfortable. Mention claustrophobia when booking so we can assist.
Q: Will I need an injection (contrast)? A: Some scans use gadolinium contrast — we confirm this at booking. No fasting needed for contrast.
Q: How long does a scan take? A: Most take 30–60 minutes including positioning.
Q: When will I get my report? A: Written radiologist report within 5 working days. Urgent 24–48 hr turnaround available on request.
Q: Are prices all-inclusive? A: Yes. Price covers scan, radiologist report and admin. No hidden fees.

BOOKING DEEP LINKS — use these when patient wants to book a specific scan:
• Knee MRI: https://unirad.co.uk/book-your-scan/?scan_id=knee&scan_mode=mri
• Brain MRI: https://unirad.co.uk/book-your-scan/?scan_id=brain_head&scan_mode=mri
• Shoulder MRI: https://unirad.co.uk/book-your-scan/?scan_id=shoulder&scan_mode=mri
• Hip MRI: https://unirad.co.uk/book-your-scan/?scan_id=hip&scan_mode=mri
• Lumbar Spine MRI: https://unirad.co.uk/book-your-scan/?scan_id=lumbar&scan_mode=mri
• Cervical Spine MRI: https://unirad.co.uk/book-your-scan/?scan_id=cervical&scan_mode=mri
• Thoracic Spine MRI: https://unirad.co.uk/book-your-scan/?scan_id=thoracic&scan_mode=mri
• Ankle MRI: https://unirad.co.uk/book-your-scan/?scan_id=ankle&scan_mode=mri
• Wrist MRI: https://unirad.co.uk/book-your-scan/?scan_id=wrist&scan_mode=mri
• Elbow MRI: https://unirad.co.uk/book-your-scan/?scan_id=elbow&scan_mode=mri
• Foot MRI: https://unirad.co.uk/book-your-scan/?scan_id=foot&scan_mode=mri
• Abdomen MRI: https://unirad.co.uk/book-your-scan/?scan_id=abdomen_mri&scan_mode=mri
• Prostate MRI: https://unirad.co.uk/book-your-scan/?scan_id=prostate&scan_mode=mri
• Breast MRI: https://unirad.co.uk/book-your-scan/?scan_id=breast&scan_mode=mri
• Cardiac MRI: https://unirad.co.uk/book-your-scan/?scan_id=cardiac&scan_mode=mri
• General booking: https://unirad.co.uk/book-your-scan/
• Full Body Silver: https://unirad.co.uk/book-your-scan/?scan_id=1332&scan_mode=full
• Full Body Gold: https://unirad.co.uk/book-your-scan/?scan_id=1464&scan_mode=full
• Full Body Platinum: https://unirad.co.uk/book-your-scan/?scan_id=1465&scan_mode=full

RULES:
1. Keep replies concise — 2–4 sentences unless listing options or answering multi-part questions.
2. Be warm, friendly and reassuring. Many patients are anxious about MRI.
3. You are NOT a medical professional. Describe services only. Never diagnose. If asked for clinical advice say: \"I can't give medical advice — your radiologist report will provide the clinical picture. A GP can interpret it with you.\"
4. Always mention the report is included and no referral needed when relevant.
5. When a patient mentions a body part, suggest the matching scan with its price and provide the booking link.
6. Use £ not GBP. Say 'scan' not 'examination'. Say 'report' not 'results letter'.
7. If asked about something outside our services (e.g. CT, X-ray, blood tests), politely explain we specialise in MRI and offer what's relevant.
8. For bilateral scans (both sides), mention the bilateral price and that side can be selected at booking.
9. If the patient prefers to speak with a human or asks for a phone number, provide: 0141 846 9116. Mention our team is available Mon–Fri, 10am–7pm. They can also book online 24/7.";
}

// ── Anthropic API Call ───────────────────────────────────────────────────────

function unirad_ai_call( array $messages ) {
    $key = unirad_ai_get_key();

    if ( empty( $key ) ) {
        return [ 'error' => 'API key not configured. Please add your Anthropic key to the plugin file.' ];
    }

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode( [
            'model'      => UNIRAD_AI_MODEL,
            'max_tokens' => 600,
            'system'     => unirad_ai_system_prompt(),
            'messages'   => $messages,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'error' => 'Connection error: ' . $response->get_error_message() ];
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code !== 200 ) {
        $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "API error {$code}";
        return [ 'error' => $msg ];
    }

    $text = '';
    foreach ( (array) $data['content'] as $block ) {
        if ( isset( $block['type'] ) && $block['type'] === 'text' ) {
            $text .= $block['text'];
        }
    }

    return [ 'text' => $text ];
}

// ── AJAX: Chat ───────────────────────────────────────────────────────────────

function unirad_ai_handle_chat() {
    check_ajax_referer( 'unirad_ai_nonce', 'nonce' );

    $user_msg = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
    if ( $user_msg === '' ) {
        wp_send_json_error( 'Empty message.' );
    }

    $history   = unirad_ai_get_history();
    $history[] = [ 'role' => 'user', 'content' => $user_msg ];

    $result = unirad_ai_call( $history );

    if ( isset( $result['error'] ) ) {
        wp_send_json_error( $result['error'] );
    }

    $reply      = $result['text'];
    $history[]  = [ 'role' => 'assistant', 'content' => $reply ];
    unirad_ai_save_history( $history );

    $turn_count = (int) floor( count( $history ) / 2 );

    // Fire high-interest signal once when the patient crosses 5 turns
    if ( $turn_count === 5 ) {
        do_action( 'unirad_aria_high_interest', [
            'session_key' => unirad_ai_session_key(),
            'email'       => '',
            'turns'       => $turn_count,
        ] );
    }

    wp_send_json_success( [
        'reply'      => $reply,
        'turn_count' => $turn_count,
    ] );
}

// ── AJAX: Reset ──────────────────────────────────────────────────────────────

function unirad_ai_handle_reset() {
    check_ajax_referer( 'unirad_ai_nonce', 'nonce' );
    delete_transient( unirad_ai_session_key() );
    // Clear the cookie so a new session starts
    if ( ! headers_sent() ) {
        setcookie( 'uai_sid', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
    }
    wp_send_json_success();
}

// ── AJAX: Callback request ───────────────────────────────────────────────────

function unirad_ai_handle_callback() {
    check_ajax_referer( 'unirad_ai_nonce', 'nonce' );

    $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $name  = isset( $_POST['name']  ) ? sanitize_text_field( wp_unslash( $_POST['name']  ) ) : '';
    $note  = isset( $_POST['note']  ) ? sanitize_text_field( wp_unslash( $_POST['note']  ) ) : 'AI Chat Callback';

    if ( $phone === '' ) {
        wp_send_json_error( 'Phone number required.' );
    }

    // Save to abandoned leads table if the booking plugin's table exists
    global $wpdb;
    $table = $wpdb->prefix . 'unirad_potential_bookings';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        $wpdb->insert( $table, [
            'name'          => $name ?: 'AI Chat Visitor',
            'email'         => '',
            'phone'         => $phone,
            'scan_type'     => $note,
            'scan_price'    => '',
            'status'        => 'callback',
            'recovery_sent' => 0,
            'created_at'    => current_time( 'mysql' ),
        ] );
    }

    // Email clinic notification — use Brevo so it arrives reliably
    $notify = 'booking@unirad.co.uk';
    $time   = current_time( 'mysql' );
    $body   = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0d1f3c;max-width:500px">'
            . '<div style="background:#00a896;padding:14px 18px;color:#fff;font-size:16px;font-weight:bold">&#128222; AI Chat Callback Request</div>'
            . '<div style="padding:18px">'
            . '<p style="margin:0 0 10px"><strong>Name:</strong> ' . esc_html( $name ?: '—' ) . '</p>'
            . '<p style="margin:0 0 10px"><strong>Phone:</strong> <a href="tel:' . esc_attr( $phone ) . '" style="color:#00a896;font-size:16px;font-weight:bold">' . esc_html( $phone ) . '</a></p>'
            . '<p style="margin:0 0 10px"><strong>Note:</strong> ' . esc_html( $note ) . '</p>'
            . '<p style="margin:0;color:#888;font-size:11px">Received: ' . esc_html( $time ) . '</p>'
            . '</div></div>';

    if ( function_exists( 'UQB_Booking::brevo_send' ) ) {
        UQB_Booking::brevo_send( $notify, 'Unirad Team', '[Unirad AI] Callback: ' . ( $name ?: $phone ), $body );
    } else {
        wp_mail( $notify, '[Unirad AI] Callback Request from Chat Widget',
            "Name: {$name}\nPhone: {$phone}\nNote: {$note}\nTime: {$time}" );
    }

    wp_send_json_success();
}

// ── Widget HTML + JS ─────────────────────────────────────────────────────────

function unirad_ai_widget() {
    $nonce = wp_create_nonce( 'unirad_ai_nonce' );
    $ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
?>
<style>
/* ── Unirad AI Chat Widget ────────────────────────────────────────────── */
#uai{position:fixed;bottom:24px;right:24px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,"DM Sans","Segoe UI",Helvetica,sans-serif;font-size:14px;}
#uai *{box-sizing:border-box;margin:0;padding:0;}

/* Toggle button */
#uai-btn{
  width:58px;height:58px;background:#00a896;border:none;border-radius:50%;
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 20px rgba(0,168,150,.45);
  transition:transform .15s,background .15s;
  position:relative;
}
#uai-btn:hover{background:#008a7d;transform:scale(1.06);}
#uai-btn .ico{width:24px;height:24px;fill:#fff;}
#uai-btn .ico-x{display:none;}
#uai.open #uai-btn .ico-chat{display:none;}
#uai.open #uai-btn .ico-x{display:block;}
#uai-badge{
  position:absolute;top:-2px;right:-2px;
  background:#e84040;color:#fff;border:2px solid #fff;border-radius:50%;
  width:17px;height:17px;font-size:9px;font-weight:700;
  display:none;align-items:center;justify-content:center;
}
#uai-badge.on{display:flex;}

/* Window */
#uai-win{
  display:none;position:absolute;bottom:70px;right:0;
  width:344px;background:#fff;border-radius:16px;
  box-shadow:0 16px 60px rgba(0,20,60,.2);
  flex-direction:column;overflow:hidden;
  animation:uai-pop .18s ease;
}
#uai.open #uai-win{display:flex;}
@keyframes uai-pop{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* Header */
#uai-hd{background:#08192a;padding:13px 15px;display:flex;align-items:center;gap:10px;}
#uai-av{width:36px;height:36px;background:rgba(0,168,150,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
#uai-av svg{width:20px;height:20px;fill:#00d4b8;}
#uai-hd-txt{flex:1;}
#uai-hd-name{font-size:13px;font-weight:700;color:#fff;line-height:1.2;}
#uai-hd-sub{font-size:10px;color:#4ddece;display:flex;align-items:center;gap:4px;margin-top:2px;}
#uai-pulse{width:5px;height:5px;background:#00d4b8;border-radius:50%;animation:uai-pulse 2s infinite;}
@keyframes uai-pulse{0%,100%{opacity:1}50%{opacity:.28}}
#uai-new{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.45);font-size:18px;line-height:1;padding:2px 4px;transition:color .12s;}
#uai-new:hover{color:#fff;}

/* Messages */
#uai-msgs{flex:1;padding:14px 13px 4px;overflow-y:auto;min-height:200px;max-height:350px;scroll-behavior:smooth;}
#uai-msgs::-webkit-scrollbar{width:3px;}
#uai-msgs::-webkit-scrollbar-thumb{background:rgba(0,168,150,.2);border-radius:2px;}

.uai-m{margin-bottom:10px;display:flex;}
.uai-m.u{justify-content:flex-end;}
.uai-m.b{justify-content:flex-start;}
.uai-bub{max-width:86%;padding:9px 12px;border-radius:14px;font-size:12.5px;line-height:1.55;word-break:break-word;}
.uai-m.u .uai-bub{background:#00a896;color:#fff;border-radius:14px 14px 3px 14px;}
.uai-m.b .uai-bub{background:#f0f4f8;color:#0b2240;border-radius:14px 14px 14px 3px;}
.uai-bub a{color:#00a896;font-weight:600;text-decoration:none;}
.uai-m.u .uai-bub a{color:#b3f5e8;}
.uai-bub a:hover{text-decoration:underline;}
.uai-bub strong{font-weight:700;}

/* Typing dots */
#uai-typing{display:none;margin:0 0 10px 0;}
#uai-typing.on{display:flex;}
#uai-typing-bub{background:#f0f4f8;border-radius:14px 14px 14px 3px;padding:10px 14px;display:flex;gap:4px;align-items:center;}
.uai-d{width:5px;height:5px;background:#a8bfcc;border-radius:50%;animation:uai-bounce .9s ease infinite;}
.uai-d:nth-child(2){animation-delay:.15s;}
.uai-d:nth-child(3){animation-delay:.3s;}
@keyframes uai-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}

/* Lead capture */
#uai-lead{display:none;margin:4px 13px 10px;background:rgba(0,168,150,.07);border:1px solid rgba(0,168,150,.22);border-radius:10px;padding:10px 12px;}
#uai-lead.on{display:block;}
#uai-lead-p{font-size:11.5px;font-weight:700;color:#07776c;margin-bottom:7px;}
#uai-lead-row{display:flex;gap:6px;}
#uai-lead-in{flex:1;border:1px solid rgba(0,168,150,.25);border-radius:18px;padding:6px 11px;font-size:11.5px;font-family:inherit;outline:none;color:#0b2240;}
#uai-lead-in:focus{border-color:#00a896;}
#uai-lead-go{background:#00a896;color:#fff;border:none;border-radius:18px;padding:6px 13px;font-size:11.5px;font-weight:700;cursor:pointer;white-space:nowrap;}
#uai-lead-go:hover{background:#008a7d;}

/* Input footer */
#uai-ft{padding:10px;border-top:1px solid rgba(0,30,60,.07);display:flex;gap:6px;align-items:center;}
#uai-inp{flex:1;border:1px solid #d8e6ec;border-radius:20px;padding:8px 14px;font-family:inherit;font-size:12.5px;color:#0b2240;outline:none;transition:border .12s;}
#uai-inp:focus{border-color:#00a896;}
#uai-inp::placeholder{color:#b8cdd8;}
#uai-go{width:35px;height:35px;background:#00a896;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .12s;}
#uai-go:hover{background:#008a7d;}
#uai-go:disabled{background:#d4eae7;cursor:not-allowed;}
#uai-go svg{width:14px;height:14px;fill:#fff;}

@media(max-width:400px){
  #uai-win{width:calc(100vw - 28px);right:-8px;}
  #uai{bottom:16px;right:16px;}
}
</style>

<div id="uai">

  <button id="uai-btn" aria-label="Chat with Aria, our MRI assistant">
    <svg class="ico ico-chat" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
    <svg class="ico ico-x" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    <span id="uai-badge"></span>
  </button>

  <div id="uai-win" role="dialog" aria-label="MRI Booking Assistant">

    <div id="uai-hd">
      <div id="uai-av">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
      </div>
      <div id="uai-hd-txt">
        <div id="uai-hd-name">Aria &mdash; MRI Assistant</div>
        <div id="uai-hd-sub"><span id="uai-pulse"></span>Online &middot; Glasgow</div>
      </div>
      <button id="uai-new" title="New conversation">&#8635;</button>
    </div>

    <div id="uai-msgs"></div>

    <div id="uai-typing">
      <div id="uai-typing-bub">
        <div class="uai-d"></div><div class="uai-d"></div><div class="uai-d"></div>
      </div>
    </div>

    <div id="uai-lead">
      <div id="uai-lead-p">Want us to call you about booking?</div>
      <div id="uai-lead-row">
        <input id="uai-lead-in" type="tel" placeholder="Your phone number" autocomplete="tel">
        <button id="uai-lead-go">Call me</button>
      </div>
    </div>

    <div id="uai-ft">
      <input id="uai-inp" type="text" placeholder="Ask about scans, prices, booking..." maxlength="400" autocomplete="off">
      <button id="uai-go" disabled>
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>

  </div>
</div>

<script>
(function(){
'use strict';
var AJAX  = <?php echo wp_json_encode( $ajax ); ?>;
var NONCE = <?php echo wp_json_encode( $nonce ); ?>;

var wrap   = document.getElementById('uai');
var btn    = document.getElementById('uai-btn');
var win    = document.getElementById('uai-win');
var msgs   = document.getElementById('uai-msgs');
var inp    = document.getElementById('uai-inp');
var go     = document.getElementById('uai-go');
var typing = document.getElementById('uai-typing');
var badge  = document.getElementById('uai-badge');
var lead   = document.getElementById('uai-lead');
var leadIn = document.getElementById('uai-lead-in');
var leadGo = document.getElementById('uai-lead-go');
var newBtn = document.getElementById('uai-new');

var turns    = 0;
var greeted  = false;
var busy     = false;

// ── Toggle ────────────────────────────────────────────────────────────────
btn.addEventListener('click', function(){
  wrap.classList.toggle('open');
  badge.classList.remove('on');
  if(wrap.classList.contains('open')){
    if(!greeted){ greet(); }
    setTimeout(function(){ inp.focus(); }, 220);
  }
});

// ── Greeting ─────────────────────────────────────────────────────────────
function greet(){
  greeted = true;
  addMsg('b', "Hi there! I'm Aria, Unirad's MRI assistant 👋\n\nI can help you find the right scan, explain what to expect, and get you booked in — often **same week**, from £290. No GP referral needed.\n\nWhat body area are you looking to scan?");
}

// ── Append message ────────────────────────────────────────────────────────
function addMsg(role, text){
  var row = document.createElement('div');
  row.className = 'uai-m ' + role;
  var bub = document.createElement('div');
  bub.className = 'uai-bub';
  bub.innerHTML = fmt(text);
  row.appendChild(bub);
  msgs.appendChild(row);
  msgs.scrollTop = msgs.scrollHeight;
  return row;
}

// ── Lightweight markdown + link formatter ─────────────────────────────────
function fmt(t){
  t = t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  t = t.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>');
  t = t.replace(/\*(.*?)\*/g,'<em>$1</em>');
  t = t.replace(/(https?:\/\/[^\s<\)]+)/g,'<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  t = t.replace(/\n/g,'<br>');
  return t;
}

// ── Send ──────────────────────────────────────────────────────────────────
function send(){
  var text = inp.value.trim();
  if(!text || busy) return;
  busy = true;
  inp.value = '';
  go.disabled = true;
  addMsg('u', text);
  typing.classList.add('on');
  msgs.scrollTop = msgs.scrollHeight;

  var fd = new FormData();
  fd.append('action',  'unirad_ai_chat');
  fd.append('nonce',   NONCE);
  fd.append('message', text);

  fetch(AJAX, {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      typing.classList.remove('on');
      busy = false;
      go.disabled = inp.value.trim() === '';
      if(d.success){
        addMsg('b', d.data.reply);
        turns = d.data.turn_count || (turns + 1);
        if(turns >= 3){ lead.classList.add('on'); }
      } else {
        addMsg('b', 'Sorry, I had a hiccup. Please try again or call us on 0141 846 9116.');
      }
    })
    .catch(function(){
      typing.classList.remove('on');
      busy = false;
      go.disabled = false;
      addMsg('b', 'Connection issue. Please try again.');
    });
}

go.addEventListener('click', send);
inp.addEventListener('keydown', function(e){
  if(e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); send(); }
});
inp.addEventListener('input', function(){
  go.disabled = this.value.trim() === '' || busy;
});

// ── New conversation ──────────────────────────────────────────────────────
newBtn.addEventListener('click', function(){
  var fd = new FormData();
  fd.append('action', 'unirad_ai_reset');
  fd.append('nonce',  NONCE);
  fetch(AJAX, {method:'POST', body:fd}).catch(function(){});
  msgs.innerHTML = '';
  lead.classList.remove('on');
  lead.innerHTML = '<div id="uai-lead-p">Want us to call you about booking?</div><div id="uai-lead-row"><input id="uai-lead-in" type="tel" placeholder="Your phone number" autocomplete="tel"><button id="uai-lead-go">Call me</button></div>';
  leadIn = document.getElementById('uai-lead-in');
  leadGo = document.getElementById('uai-lead-go');
  bindLead();
  turns = 0;
  greeted = false;
  greet();
});

// ── Lead capture ──────────────────────────────────────────────────────────
function bindLead(){
  leadGo.addEventListener('click', function(){
    var phone = leadIn.value.trim();
    if(!phone){ leadIn.focus(); return; }
    var fd = new FormData();
    fd.append('action', 'unirad_ai_callback');
    fd.append('nonce',  NONCE);
    fd.append('phone',  phone);
    fd.append('note',   'AI Chat Callback Request');
    fetch(AJAX, {method:'POST', body:fd}).catch(function(){});
    document.getElementById('uai-lead').innerHTML = '<div style="font-size:12px;font-weight:700;color:#07776c">✓ Thanks! We\'ll call you shortly.</div>';
    addMsg('b', "Great — we'll give you a call shortly. In the meantime you can also book directly at https://unirad.co.uk/book-your-scan/ — same-week appointments available.");
  });
}
bindLead();

// ── Attention badge (8 seconds after page load) ───────────────────────────
setTimeout(function(){
  if(!wrap.classList.contains('open')){
    badge.textContent = '1';
    badge.classList.add('on');
  }
}, 8000);

})();
</script>
<?php
}
