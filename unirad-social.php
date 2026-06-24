<?php
/*
Plugin Name: Unirad Social Autoposter
Plugin URI:  https://unirad.co.uk
Description: Share WordPress blog posts to Facebook, Instagram and LinkedIn. AI-generated captions via Claude. Manual trigger from post editor.
Version:     1.0.0
Author:      Unirad
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'admin_menu',            'unirad_social_menu' );
add_action( 'add_meta_boxes',        'unirad_social_meta_box' );
add_action( 'admin_enqueue_scripts', 'unirad_social_assets' );
add_action( 'wp_ajax_unirad_social_generate', 'unirad_social_ajax_generate' );
add_action( 'wp_ajax_unirad_social_post',     'unirad_social_ajax_post' );

// ── Settings helpers ──────────────────────────────────────────────────────────

function unirad_social_setting( $key, $default = '' ) {
    $opts = get_option( 'unirad_social_settings', [] );
    return $opts[ $key ] ?? $default;
}

// ── Admin Menu ────────────────────────────────────────────────────────────────

function unirad_social_menu() {
    add_submenu_page(
        'unirad-email-dashboard',
        'Social Autoposter',
        '&#128279; Social',
        'manage_options',
        'unirad-social',
        'unirad_social_settings_page'
    );
}

// ── Settings Page ──────────────────────────────────────────────────────────────

function unirad_social_settings_page() {
    if ( isset( $_POST['unirad_social_save'] ) ) {
        check_admin_referer( 'unirad_social_save' );
        $fields = [ 'fb_page_id', 'fb_page_token', 'ig_user_id', 'li_org_id', 'li_token', 'ai_key' ];
        $data   = [];
        foreach ( $fields as $f ) {
            $data[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) );
        }
        update_option( 'unirad_social_settings', $data );
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $s = get_option( 'unirad_social_settings', [] );
    ?>
    <div class="wrap" style="max-width:700px;">
    <h1>&#128279; Social Autoposter — Settings</h1>
    <p style="color:#555;margin:8px 0 22px;">Enter your API credentials below. All tokens are stored in the WordPress database (wp_options). Never share these in chat.</p>

    <form method="post">
    <?php wp_nonce_field( 'unirad_social_save' ); ?>

    <h2 style="font-size:15px;border-bottom:1px solid #e0e0e0;padding-bottom:8px;margin-bottom:16px;">Facebook &amp; Instagram (Meta Graph API)</h2>
    <table class="form-table" style="margin-bottom:0;">
      <tr><th>Facebook Page ID</th><td><input name="fb_page_id" value="<?php echo esc_attr($s['fb_page_id']??''); ?>" class="regular-text" placeholder="123456789012345"><p class="description">Your Facebook Page numeric ID (Settings → Page Info → Page ID)</p></td></tr>
      <tr><th>Facebook Page Access Token</th><td><input name="fb_page_token" value="<?php echo esc_attr($s['fb_page_token']??''); ?>" class="large-text" type="password" placeholder="EAA..."><p class="description">Long-lived Page Access Token from <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>. Needs: pages_manage_posts, instagram_content_publish.</p></td></tr>
      <tr><th>Instagram Business User ID</th><td><input name="ig_user_id" value="<?php echo esc_attr($s['ig_user_id']??''); ?>" class="regular-text" placeholder="17841400000000000"><p class="description">Instagram Professional Account ID (linked to your Facebook Page). Leave blank to skip Instagram.</p></td></tr>
    </table>

    <h2 style="font-size:15px;border-bottom:1px solid #e0e0e0;padding-bottom:8px;margin:24px 0 16px;">LinkedIn</h2>
    <table class="form-table" style="margin-bottom:0;">
      <tr><th>LinkedIn Organization ID</th><td><input name="li_org_id" value="<?php echo esc_attr($s['li_org_id']??''); ?>" class="regular-text" placeholder="12345678"><p class="description">Your LinkedIn Company Page ID (visible in the company URL: linkedin.com/company/<strong>ID</strong>)</p></td></tr>
      <tr><th>LinkedIn Access Token</th><td><input name="li_token" value="<?php echo esc_attr($s['li_token']??''); ?>" class="large-text" type="password" placeholder="AQV..."><p class="description">OAuth 2.0 access token with <code>w_organization_social</code> permission. Generate via your LinkedIn App.</p></td></tr>
    </table>

    <h2 style="font-size:15px;border-bottom:1px solid #e0e0e0;padding-bottom:8px;margin:24px 0 16px;">AI Caption Generation</h2>
    <table class="form-table">
      <tr><th>Anthropic API Key</th><td><input name="ai_key" value="<?php echo esc_attr($s['ai_key']??''); ?>" class="large-text" type="password" placeholder="sk-ant-api03-..."><p class="description">Your Anthropic API key for Claude caption generation. Leave blank to use manual captions.</p></td></tr>
    </table>

    <p><button type="submit" name="unirad_social_save" class="button button-primary">Save Settings</button></p>
    </form>

    <hr style="margin:30px 0;">
    <h2 style="font-size:15px;margin-bottom:10px;">How to Get Tokens</h2>
    <ol style="font-size:13px;color:#555;line-height:2;">
      <li><strong>Facebook Page Token:</strong> Go to <a href="https://developers.facebook.com/tools/explorer/" target="_blank">developers.facebook.com/tools/explorer</a> → select your app → select your Page → add permissions → Generate Token → Exchange for long-lived token.</li>
      <li><strong>Instagram User ID:</strong> Use Graph API Explorer: <code>GET me/accounts</code> → find connected IG account in data → copy <code>instagram_business_account.id</code>.</li>
      <li><strong>LinkedIn Token:</strong> Create a LinkedIn App at <a href="https://developer.linkedin.com/" target="_blank">developer.linkedin.com</a> → request <code>w_organization_social</code> → use OAuth 2.0 to get a token.</li>
    </ol>
    </div>
    <?php
}

// ── Meta Box ──────────────────────────────────────────────────────────────────

function unirad_social_meta_box() {
    add_meta_box(
        'unirad_social_box',
        '&#128279; Share to Social Media',
        'unirad_social_meta_box_html',
        'post',
        'side',
        'default'
    );
}

function unirad_social_meta_box_html( $post ) {
    $shared = get_post_meta( $post->ID, '_unirad_social_shared', true ) ?: [];
    $nonce  = wp_create_nonce( 'unirad_social_nonce' );
    $s      = get_option( 'unirad_social_settings', [] );
    $has_ai = ! empty( $s['ai_key'] );
    $has_fb = ! empty( $s['fb_page_token'] ) && ! empty( $s['fb_page_id'] );
    $has_ig = ! empty( $s['ig_user_id'] );
    $has_li = ! empty( $s['li_token'] ) && ! empty( $s['li_org_id'] );

    $fb_done = ! empty( $shared['facebook'] );
    $ig_done = ! empty( $shared['instagram'] );
    $li_done = ! empty( $shared['linkedin'] );
    ?>
    <div id="unirad-social-box" data-post="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <?php if ( ! $has_fb && ! $has_ig && ! $has_li ) : ?>
      <p style="font-size:12px;color:#888;">No social credentials configured. <a href="<?php echo admin_url( 'admin.php?page=unirad-social' ); ?>">Add them in settings →</a></p>
    <?php else : ?>

      <!-- Status indicators -->
      <div style="margin-bottom:12px;font-size:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ( $has_fb ) : ?>
          <span class="us-status" id="us-fb-status" style="<?php echo $fb_done ? 'color:#00a896;font-weight:700;' : 'color:#aaa;'; ?>">
            <?php echo $fb_done ? '&#10003; Facebook' : '&#9675; Facebook'; ?>
          </span>
        <?php endif; ?>
        <?php if ( $has_ig ) : ?>
          <span class="us-status" id="us-ig-status" style="<?php echo $ig_done ? 'color:#00a896;font-weight:700;' : 'color:#aaa;'; ?>">
            <?php echo $ig_done ? '&#10003; Instagram' : '&#9675; Instagram'; ?>
          </span>
        <?php endif; ?>
        <?php if ( $has_li ) : ?>
          <span class="us-status" id="us-li-status" style="<?php echo $li_done ? 'color:#00a896;font-weight:700;' : 'color:#aaa;'; ?>">
            <?php echo $li_done ? '&#10003; LinkedIn' : '&#9675; LinkedIn'; ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Captions -->
      <div id="us-captions" style="display:none;">
        <?php if ( $has_fb ) : ?>
        <p style="font-size:11px;font-weight:700;color:#444;margin:0 0 4px;">Facebook caption:</p>
        <textarea id="us-cap-fb" rows="4" style="width:100%;font-size:12px;border:1px solid #d0d0d0;border-radius:4px;padding:6px;margin-bottom:10px;resize:vertical;"></textarea>
        <?php endif; ?>
        <?php if ( $has_ig ) : ?>
        <p style="font-size:11px;font-weight:700;color:#444;margin:0 0 4px;">Instagram caption:</p>
        <textarea id="us-cap-ig" rows="4" style="width:100%;font-size:12px;border:1px solid #d0d0d0;border-radius:4px;padding:6px;margin-bottom:10px;resize:vertical;"></textarea>
        <?php endif; ?>
        <?php if ( $has_li ) : ?>
        <p style="font-size:11px;font-weight:700;color:#444;margin:0 0 4px;">LinkedIn caption:</p>
        <textarea id="us-cap-li" rows="4" style="width:100%;font-size:12px;border:1px solid #d0d0d0;border-radius:4px;padding:6px;margin-bottom:10px;resize:vertical;"></textarea>
        <?php endif; ?>
      </div>

      <div id="us-msg" style="font-size:12px;color:#666;min-height:18px;margin-bottom:8px;"></div>

      <!-- Buttons -->
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <?php if ( $has_ai ) : ?>
        <button id="us-btn-generate" type="button" class="button" style="font-size:12px;">
          &#10024; Generate Captions
        </button>
        <?php endif; ?>
        <button id="us-btn-post" type="button" class="button button-primary" style="font-size:12px;display:none;">
          &#128279; Post to All
        </button>
      </div>

      <?php if ( ! $has_ai ) : ?>
      <!-- Manual caption entry (no AI key) -->
      <script>document.getElementById('us-captions').style.display='block';</script>
      <div style="margin-top:10px;">
        <button id="us-btn-post-manual" type="button" class="button button-primary" style="font-size:12px;">
          &#128279; Post to All
        </button>
      </div>
      <?php endif; ?>

    <?php endif; ?>
    </div><!-- #unirad-social-box -->
    <?php
}

// ── Assets ────────────────────────────────────────────────────────────────────

function unirad_social_assets( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var box = document.getElementById('unirad-social-box');
      if(!box) return;

      var POST_ID = box.dataset.post;
      var NONCE   = box.dataset.nonce;
      var AJAX    = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';

      var btnGen    = document.getElementById('us-btn-generate');
      var btnPost   = document.getElementById('us-btn-post');
      var btnManual = document.getElementById('us-btn-post-manual');
      var captions  = document.getElementById('us-captions');
      var msg       = document.getElementById('us-msg');

      function setMsg(text, color){ msg.style.color = color || '#666'; msg.textContent = text; }

      // Generate captions
      if(btnGen){
        btnGen.addEventListener('click', function(){
          setMsg('Generating captions with AI…', '#888');
          btnGen.disabled = true;
          var fd = new FormData();
          fd.append('action',  'unirad_social_generate');
          fd.append('nonce',   NONCE);
          fd.append('post_id', POST_ID);
          fetch(AJAX, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d){
              btnGen.disabled = false;
              if(!d.success){ setMsg('Error: '+(d.data||'unknown'), '#c0392b'); return; }
              var caps = d.data;
              if(caps.facebook && document.getElementById('us-cap-fb'))
                document.getElementById('us-cap-fb').value = caps.facebook;
              if(caps.instagram && document.getElementById('us-cap-ig'))
                document.getElementById('us-cap-ig').value = caps.instagram;
              if(caps.linkedin && document.getElementById('us-cap-li'))
                document.getElementById('us-cap-li').value = caps.linkedin;
              captions.style.display = 'block';
              if(btnPost) btnPost.style.display = 'inline-block';
              setMsg('Captions generated. Review and edit, then click Post to All.', '#00a896');
            })
            .catch(function(){ btnGen.disabled=false; setMsg('Connection error.','#c0392b'); });
        });
      }

      // Post function
      function doPost(){
        setMsg('Posting to social media…', '#888');
        if(btnPost) btnPost.disabled = true;
        if(btnManual) btnManual.disabled = true;

        var fd = new FormData();
        fd.append('action',  'unirad_social_post');
        fd.append('nonce',   NONCE);
        fd.append('post_id', POST_ID);
        var capFb = document.getElementById('us-cap-fb');
        var capIg = document.getElementById('us-cap-ig');
        var capLi = document.getElementById('us-cap-li');
        if(capFb) fd.append('cap_fb', capFb.value);
        if(capIg) fd.append('cap_ig', capIg.value);
        if(capLi) fd.append('cap_li', capLi.value);

        fetch(AJAX, {method:'POST', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(d){
            if(btnPost) btnPost.disabled = false;
            if(btnManual) btnManual.disabled = false;
            if(!d.success){ setMsg('Error: '+(d.data||'unknown'),'#c0392b'); return; }
            var r = d.data;
            var lines = [];
            if(r.facebook){ lines.push('✓ Facebook'); document.getElementById('us-fb-status').textContent='✓ Facebook'; document.getElementById('us-fb-status').style.color='#00a896'; document.getElementById('us-fb-status').style.fontWeight='700'; }
            else if(r.facebook_error){ lines.push('✗ Facebook: '+r.facebook_error); }
            if(r.instagram){ lines.push('✓ Instagram'); var s=document.getElementById('us-ig-status'); if(s){s.textContent='✓ Instagram';s.style.color='#00a896';s.style.fontWeight='700';} }
            else if(r.instagram_error){ lines.push('✗ Instagram: '+r.instagram_error); }
            if(r.linkedin){ lines.push('✓ LinkedIn'); var s=document.getElementById('us-li-status'); if(s){s.textContent='✓ LinkedIn';s.style.color='#00a896';s.style.fontWeight='700';} }
            else if(r.linkedin_error){ lines.push('✗ LinkedIn: '+r.linkedin_error); }
            setMsg(lines.join(' · '), lines.some(function(l){return l.startsWith('✓');}) ? '#00a896' : '#c0392b');
          })
          .catch(function(){ if(btnPost)btnPost.disabled=false; if(btnManual)btnManual.disabled=false; setMsg('Connection error.','#c0392b'); });
      }

      if(btnPost)   btnPost.addEventListener('click', doPost);
      if(btnManual) btnManual.addEventListener('click', doPost);
    });
    </script>
    <?php
}

// ── AJAX: Generate Captions ───────────────────────────────────────────────────

function unirad_social_ajax_generate() {
    check_ajax_referer( 'unirad_social_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Forbidden' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $post    = get_post( $post_id );
    if ( ! $post ) wp_send_json_error( 'Post not found.' );

    $ai_key = unirad_social_setting( 'ai_key' );
    if ( empty( $ai_key ) ) wp_send_json_error( 'Anthropic API key not set.' );

    $title   = $post->post_title;
    $excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 );
    $url     = get_permalink( $post_id );

    $prompt = "You are a conversion-focused social media manager for Unirad Diagnostic Imaging, a private MRI clinic at 22 Loanbank Quadrant, Govan, Glasgow G51 3HZ.\n\n"
        . "Blog post title: {$title}\n"
        . "Blog post excerpt: {$excerpt}\n"
        . "Blog post URL: {$url}\n\n"
        . "Write three captions. Respond ONLY with valid JSON in this exact format:\n"
        . '{"facebook":"...","instagram":"...","linkedin":"..."}'  . "\n\n"
        . "CONVERSION RULES (mandatory for every caption):\n"
        . "1. Every caption MUST end with a clear, specific patient benefit — e.g. 'Get your radiologist report in 5 working days' or 'Same-week appointments available in Glasgow'.\n"
        . "2. Every caption MUST include a direct link or call-to-action pointing patients back to our Glasgow clinic at unirad.co.uk.\n"
        . "3. Lead with the problem the patient has (e.g. waiting, uncertainty, pain) before the solution.\n"
        . "4. Use social proof where possible: '5-star rated', 'book without a GP appointment', 'from £290'.\n\n"
        . "Platform guidelines:\n"
        . "- Facebook: 2-3 sentences, conversational, mention Glasgow, include the blog post URL at the end.\n"
        . "- Instagram: 150-220 chars, 3-5 emojis, end with 6-8 hashtags (#MRIScan #GlasgowHealth #PrivateMRI #Unirad #GlasgowMRI #DirectBooking). Say 'link in bio' — no bare URL.\n"
        . "- LinkedIn: Professional healthcare tone, 2-3 sentences, include the URL. No emojis. Target GPs, physios and HR managers who refer patients.\n"
        . "Do not include any text outside the JSON object.";

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $ai_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode( [
            'model'      => 'claude-opus-4-8',
            'max_tokens' => 800,
            'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = '';
    foreach ( (array) ( $data['content'] ?? [] ) as $block ) {
        if ( isset( $block['type'] ) && $block['type'] === 'text' ) $text .= $block['text'];
    }

    // Extract JSON from response (Claude sometimes adds markdown code fences)
    $text = trim( $text );
    if ( preg_match( '/\{.*\}/s', $text, $m ) ) $text = $m[0];

    $captions = json_decode( $text, true );
    if ( ! $captions || ! isset( $captions['facebook'] ) ) {
        wp_send_json_error( 'Could not parse AI response. Raw: ' . esc_html( substr( $text, 0, 200 ) ) );
    }

    wp_send_json_success( $captions );
}

// ── AJAX: Post to Social ──────────────────────────────────────────────────────

function unirad_social_ajax_post() {
    check_ajax_referer( 'unirad_social_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Forbidden' );

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $post    = get_post( $post_id );
    if ( ! $post ) wp_send_json_error( 'Post not found.' );

    $cap_fb = sanitize_textarea_field( wp_unslash( $_POST['cap_fb'] ?? '' ) );
    $cap_ig = sanitize_textarea_field( wp_unslash( $_POST['cap_ig'] ?? '' ) );
    $cap_li = sanitize_textarea_field( wp_unslash( $_POST['cap_li'] ?? '' ) );

    $url       = get_permalink( $post_id );
    $title     = $post->post_title;
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );

    $s       = get_option( 'unirad_social_settings', [] );
    $results = [];

    // ── Facebook ──────────────────────────────────────────────────────────────
    if ( ! empty( $s['fb_page_token'] ) && ! empty( $s['fb_page_id'] ) && $cap_fb ) {
        $fb_body = [ 'message' => $cap_fb, 'link' => $url, 'access_token' => $s['fb_page_token'] ];
        if ( $thumb_url ) $fb_body['full_picture'] = $thumb_url;

        $fb = wp_remote_post(
            'https://graph.facebook.com/v19.0/' . rawurlencode( $s['fb_page_id'] ) . '/feed',
            [ 'timeout' => 20, 'body' => $fb_body ]
        );

        if ( is_wp_error( $fb ) ) {
            $results['facebook_error'] = $fb->get_error_message();
        } else {
            $fb_data = json_decode( wp_remote_retrieve_body( $fb ), true );
            if ( isset( $fb_data['id'] ) ) {
                $results['facebook'] = true;
            } else {
                $results['facebook_error'] = $fb_data['error']['message'] ?? 'Unknown Facebook error';
            }
        }
    }

    // ── Instagram ─────────────────────────────────────────────────────────────
    if ( ! empty( $s['ig_user_id'] ) && ! empty( $s['fb_page_token'] ) && $cap_ig && $thumb_url ) {
        // Step 1: create media container
        $ig_alt_text = 'Private MRI scan at Unirad Diagnostic Imaging Glasgow — ' . wp_strip_all_tags( $title );
        $ig1 = wp_remote_post(
            'https://graph.facebook.com/v19.0/' . rawurlencode( $s['ig_user_id'] ) . '/media',
            [
                'timeout' => 20,
                'body'    => [
                    'image_url'    => $thumb_url,
                    'caption'      => $cap_ig,
                    'alt_text'     => mb_substr( $ig_alt_text, 0, 100 ),
                    'access_token' => $s['fb_page_token'],
                ],
            ]
        );

        if ( ! is_wp_error( $ig1 ) ) {
            $ig1_data = json_decode( wp_remote_retrieve_body( $ig1 ), true );
            $creation_id = $ig1_data['id'] ?? '';

            if ( $creation_id ) {
                // Step 2: publish
                $ig2 = wp_remote_post(
                    'https://graph.facebook.com/v19.0/' . rawurlencode( $s['ig_user_id'] ) . '/media_publish',
                    [
                        'timeout' => 20,
                        'body'    => [
                            'creation_id'  => $creation_id,
                            'access_token' => $s['fb_page_token'],
                        ],
                    ]
                );

                if ( ! is_wp_error( $ig2 ) ) {
                    $ig2_data = json_decode( wp_remote_retrieve_body( $ig2 ), true );
                    if ( isset( $ig2_data['id'] ) ) {
                        $results['instagram'] = true;
                    } else {
                        $results['instagram_error'] = $ig2_data['error']['message'] ?? 'Publish failed';
                    }
                } else {
                    $results['instagram_error'] = $ig2->get_error_message();
                }
            } else {
                $results['instagram_error'] = $ig1_data['error']['message'] ?? 'Container creation failed';
            }
        } else {
            $results['instagram_error'] = $ig1->get_error_message();
        }
    } elseif ( ! empty( $s['ig_user_id'] ) && ! $thumb_url ) {
        $results['instagram_error'] = 'No featured image — Instagram requires an image.';
    }

    // ── LinkedIn ──────────────────────────────────────────────────────────────
    if ( ! empty( $s['li_token'] ) && ! empty( $s['li_org_id'] ) && $cap_li ) {
        $li_body = [
            'author'          => 'urn:li:organization:' . $s['li_org_id'],
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'   => [ 'text' => $cap_li ],
                    'shareMediaCategory' => 'ARTICLE',
                    'media'             => [
                        [
                            'status'      => 'READY',
                            'originalUrl' => $url,
                            'title'       => [ 'text' => $title ],
                            'description' => [ 'text' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 20 ) ],
                        ],
                    ],
                ],
            ],
            'visibility' => [ 'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC' ],
        ];

        $li = wp_remote_post( 'https://api.linkedin.com/v2/ugcPosts', [
            'timeout' => 20,
            'headers' => [
                'Authorization'              => 'Bearer ' . $s['li_token'],
                'Content-Type'               => 'application/json',
                'X-Restli-Protocol-Version'  => '2.0.0',
            ],
            'body' => wp_json_encode( $li_body ),
        ] );

        if ( is_wp_error( $li ) ) {
            $results['linkedin_error'] = $li->get_error_message();
        } else {
            $li_code = wp_remote_retrieve_response_code( $li );
            $li_data = json_decode( wp_remote_retrieve_body( $li ), true );
            if ( $li_code === 201 || $li_code === 200 ) {
                $results['linkedin'] = true;
            } else {
                $msg = $li_data['message'] ?? $li_data['serviceErrorCode'] ?? "LinkedIn error {$li_code}";
                $results['linkedin_error'] = (string) $msg;
            }
        }
    }

    // Save share history to post meta
    $existing = get_post_meta( $post_id, '_unirad_social_shared', true ) ?: [];
    if ( ! empty( $results['facebook'] ) )  $existing['facebook']  = current_time( 'mysql' );
    if ( ! empty( $results['instagram'] ) ) $existing['instagram'] = current_time( 'mysql' );
    if ( ! empty( $results['linkedin'] ) )  $existing['linkedin']  = current_time( 'mysql' );
    update_post_meta( $post_id, '_unirad_social_shared', $existing );

    wp_send_json_success( $results );
}
