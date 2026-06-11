<?php
/*
Plugin Name: Unirad Scan Button
Description: Servis sayfaları için direkt booking butonu. Shortcode: [unirad_book] veya [unirad_book scan_id="knee"]
Version: 1.1
Author: Unirad
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────
// URL slug → scan data mapping
// Each entry: array( scan_id, scan_mode, label, price_single, price_bilateral )
// price_bilateral = null means no laterality
// ─────────────────────────────────────────────────────────────
function usbb_map() {
    return array(
        // ── URL slug keys (auto-detect) ──────────────────────
        'head-mri-scan'          => array( 'brain_head',  'mri',  'Brain / Head MRI Scan',    290, null ),
        'brain-mri-scan'         => array( 'brain_head',  'mri',  'Brain / Head MRI Scan',    290, null ),
        'neck-mri-scan'          => array( 'soft_tissue', 'mri',  'Soft Tissue Neck MRI',     290, null ),
        'shoulder-mri-scan'      => array( 'shoulder',    'mri',  'Shoulder MRI Scan',        290,  455 ),
        'chest-mri-scan'         => array( 'chest',       'mri',  'Chest (Thorax) MRI',       290, null ),
        'abdomen-mri-scan'       => array( 'abdomen_mri', 'mri',  'Abdomen MRI Scan',         290, null ),
        'spine-mri-scan'         => array( 'lumbar',      'mri',  'Lumbar Spine MRI',         290, null ),
        'hip-mri-scan'           => array( 'hip',         'mri',  'Hip MRI Scan',             290,  455 ),
        'knee-mri-scan'          => array( 'knee',        'mri',  'Knee MRI Scan',            290,  455 ),
        'foot-mri-scan'          => array( 'foot',        'mri',  'Foot MRI',                 290,  455 ),
        'ankle-mri-scan'         => array( 'ankle',       'mri',  'Ankle MRI Scan',           290,  455 ),
        'elbow-mri-scan'         => array( 'elbow',       'mri',  'Elbow MRI Scan',           290,  455 ),
        'hand-mri-scan'          => array( 'hand',        'mri',  'Hand MRI',                 290,  455 ),
        'wrist-mri-scan'         => array( 'wrist',       'mri',  'Wrist MRI Scan',           290,  455 ),
        'cardiac-mri-scan'       => array( 'cardiac',     'mri',  'Cardiac MRI Scan',         575, null ),
        'prostate-mri-scan'      => array( 'prostate',    'mri',  'Prostate MRI Scan',        495, null ),
        'pelvis-mri-scan'        => array( 'pelvis_scan', 'mri',  'Pelvis MRI Scan',          290, null ),
        'full-body-mri'          => array( 'full_body',   'full', 'Full Body MRI',              0, null ), // special: 3 packages
        'full-body-mr'           => array( 'full_body',   'full', 'Full Body MRI',              0, null ),
        // ── Shortcode scan_id aliases ────────────────────────
        'brain_head'             => array( 'brain_head',  'mri',  'Brain / Head MRI Scan',    290, null ),
        'neck'                   => array( 'soft_tissue', 'mri',  'Soft Tissue Neck MRI',     290, null ),
        'shoulder'               => array( 'shoulder',    'mri',  'Shoulder MRI Scan',        290,  455 ),
        'chest'                  => array( 'chest',       'mri',  'Chest (Thorax) MRI',       290, null ),
        'abdomen'                => array( 'abdomen_mri', 'mri',  'Abdomen MRI Scan',         290, null ),
        'spine'                  => array( 'lumbar',      'mri',  'Lumbar Spine MRI',         290, null ),
        'hip'                    => array( 'hip',         'mri',  'Hip MRI Scan',             290,  455 ),
        'knee'                   => array( 'knee',        'mri',  'Knee MRI Scan',            290,  455 ),
        'foot'                   => array( 'foot',        'mri',  'Foot MRI',                 290,  455 ),
        'ankle'                  => array( 'ankle',       'mri',  'Ankle MRI Scan',           290,  455 ),
        'elbow'                  => array( 'elbow',       'mri',  'Elbow MRI Scan',           290,  455 ),
        'hand'                   => array( 'hand',        'mri',  'Hand MRI',                 290,  455 ),
        'wrist'                  => array( 'wrist',       'mri',  'Wrist MRI Scan',           290,  455 ),
        'cardiac'                => array( 'cardiac',     'mri',  'Cardiac MRI Scan',         575, null ),
        'prostate'               => array( 'prostate',    'mri',  'Prostate MRI Scan',        495, null ),
        'pelvis'                 => array( 'pelvis_scan', 'mri',  'Pelvis MRI Scan',          290, null ),
        'full_body'              => array( 'full_body',   'full', 'Full Body MRI',              0, null ), // special: 3 packages
    );
}

// Full body packages
function usbb_full_body_packages() {
    return array(
        array( 'id' => '1332', 'name' => 'Silver',   'desc' => 'Brain, Abdomen & Pelvis',                            'price' => 590  ),
        array( 'id' => '1464', 'name' => 'Gold',     'desc' => 'Brain, Spine (whole), Abdomen & Pelvis',             'price' => 1210 ),
        array( 'id' => '1465', 'name' => 'Platinum', 'desc' => 'Heart, Brain, Spine (whole), Abdomen & Pelvis',      'price' => 1660 ),
    );
}

// Auto-detect from page URL
function usbb_detect_from_url() {
    $map  = usbb_map();
    $uri  = $_SERVER['REQUEST_URI'];
    $slug = basename( parse_url( $uri, PHP_URL_PATH ), '/' );
    if ( isset( $map[ $slug ] ) ) return $map[ $slug ];
    foreach ( $map as $key => $val ) {
        if ( strpos( $uri, $key ) !== false ) return $val;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────
// Styles
// ─────────────────────────────────────────────────────────────
add_action( 'wp_head', 'usbb_styles' );
function usbb_styles() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    echo '<style>
.usbb-wrap{display:inline-flex;align-items:stretch;background:#fff;border:1.5px solid rgba(0,168,150,.22);border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,60,80,.09);font-family:"DM Sans",sans-serif;max-width:100%;}
.usbb-info{padding:16px 20px;flex:1;}
.usbb-scan-name{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:#0b2240;margin-bottom:10px;}
.usbb-chk{width:22px;height:22px;background:#00a896;border-radius:5px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.usbb-chk svg{width:12px;height:12px;}
.usbb-divider{height:1px;background:rgba(0,30,60,.07);margin:0 0 12px;}
.usbb-price-row{display:flex;align-items:baseline;gap:6px;}
.usbb-price-lbl{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#a0b8c8;}
.usbb-price-val{font-size:22px;font-weight:800;color:#00a896;transition:opacity .15s;}
/* Laterality buttons */
.usbb-lat{display:flex;gap:5px;margin-top:10px;}
.usbb-lat-btn{flex:1;padding:5px 0;background:#fff;border:1px solid #d0dde6;border-radius:20px;color:#5a7080;font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;transition:all .1s;text-align:center;}
.usbb-lat-btn:hover{border-color:#00a896;color:#00a896;}
.usbb-lat-btn.on{background:#00a896;border-color:#00a896;color:#fff;}
/* Full body package selector */
.usbb-pkgs{display:flex;flex-direction:column;gap:5px;margin-top:10px;}
.usbb-pkg{display:flex;align-items:center;gap:8px;padding:7px 10px;border:1.5px solid #dce4ef;border-radius:8px;cursor:pointer;transition:all .1s;}
.usbb-pkg:hover{border-color:#00a896;}
.usbb-pkg.on{border-color:#00a896;background:rgba(0,168,150,.06);}
.usbb-pkg-radio{width:14px;height:14px;border:1.5px solid #c8d8e4;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .1s;}
.usbb-pkg.on .usbb-pkg-radio{border-color:#00a896;background:#00a896;}
.usbb-pkg.on .usbb-pkg-radio::after{content:"";width:5px;height:5px;background:#fff;border-radius:50%;display:block;}
.usbb-pkg-name{font-size:12.5px;font-weight:700;color:#0b2240;flex-shrink:0;}
.usbb-pkg.on .usbb-pkg-name{color:#00877a;}
.usbb-pkg-desc{font-size:11px;color:#9ab8c8;flex:1;line-height:1.3;}
.usbb-pkg-price{font-size:12.5px;font-weight:700;color:#00a896;flex-shrink:0;}
/* Book button */
.usbb-btn{background:#00a896;color:#fff;border:none;padding:0 28px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;align-self:stretch;white-space:nowrap;display:flex;align-items:center;gap:6px;transition:background .14s;text-decoration:none;flex-shrink:0;}
.usbb-btn:hover{background:#008a7d;color:#fff;}
.usbb-btn svg{width:13px;height:13px;}
@media(max-width:600px){
  .usbb-wrap{flex-direction:column;}
  .usbb-btn{padding:14px;justify-content:center;}
}
</style>';
}

// ─────────────────────────────────────────────────────────────
// Shortcode
// ─────────────────────────────────────────────────────────────
add_shortcode( 'unirad_book', 'usbb_render' );
function usbb_render( $atts ) {
    $atts = shortcode_atts( array(
        'scan_id'   => '',
        'scan_mode' => '',
        'label'     => '',
        'price'     => '',
    ), $atts, 'unirad_book' );

    $map  = usbb_map();
    $data = null;

    if ( ! empty( $atts['scan_id'] ) && isset( $map[ $atts['scan_id'] ] ) ) {
        $data = $map[ $atts['scan_id'] ];
    }
    if ( ! $data ) $data = usbb_detect_from_url();
    if ( ! $data ) {
        return '<a href="/book-your-scan/" class="usbb-btn" style="border-radius:10px;padding:12px 24px;display:inline-flex;align-items:center;gap:6px;">Book Your Scan &rarr;</a>';
    }

    list( $scan_id, $scan_mode, $label, $price, $price_bilateral ) = $data;

    if ( ! empty( $atts['label'] ) )     $label     = esc_attr( $atts['label'] );
    if ( ! empty( $atts['price'] ) )     $price     = (int) $atts['price'];
    if ( ! empty( $atts['scan_mode'] ) ) $scan_mode = esc_attr( $atts['scan_mode'] );

    $is_lat      = ( $price_bilateral !== null );
    $is_fullbody = ( $scan_id === 'full_body' );

    static $usbb_count = 0;
    $usbb_count++;
    $uid = 'usbb-' . $usbb_count;

    if ( $is_fullbody ) {
        return usbb_render_fullbody( $uid );
    }

    $book_url = '/book-your-scan/?scan_id=' . urlencode( $scan_id ) . '&scan_mode=' . urlencode( $scan_mode );
    $init_side = $is_lat ? 'Left' : '';
    $init_href = $book_url . ( $is_lat ? '&scan_side=Left' : '' );
    $init_price = $is_lat ? $price : $price; // Left/Right = £290, Bilateral = £455

    ob_start(); ?>
<div class="usbb-wrap" id="<?php echo esc_attr($uid); ?>">
  <div class="usbb-info">
    <div class="usbb-scan-name">
      <div class="usbb-chk"><svg viewBox="0 0 12 12" fill="none"><path d="M2 6L5 9L10 3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <?php echo esc_html( $label ); ?>
    </div>
    <div class="usbb-divider"></div>
    <div class="usbb-price-row">
      <span class="usbb-price-lbl">Price</span>
      <span class="usbb-price-val" id="<?php echo esc_attr($uid); ?>-price">&pound;<?php echo number_format( $init_price, 2 ); ?></span>
    </div>
    <?php if ( $is_lat ) : ?>
    <div class="usbb-lat" id="<?php echo esc_attr($uid); ?>-lat">
      <button class="usbb-lat-btn on" data-side="Left"  data-price="<?php echo $price; ?>">Left</button>
      <button class="usbb-lat-btn"    data-side="Right" data-price="<?php echo $price; ?>">Right</button>
      <button class="usbb-lat-btn"    data-side="Bilateral" data-price="<?php echo $price_bilateral; ?>">Bilateral</button>
    </div>
    <?php endif; ?>
  </div>
  <a class="usbb-btn" id="<?php echo esc_attr($uid); ?>-btn" href="<?php echo esc_url( $init_href ); ?>">
    BOOK NOW
    <svg viewBox="0 0 14 14" fill="none"><path d="M2 7H12M8 3L12 7L8 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
</div>
<?php if ( $is_lat ) : ?>
<script>
(function(){
  var uid   = <?php echo json_encode($uid); ?>;
  var wrap  = document.getElementById(uid);
  if (!wrap) return;
  var btn   = document.getElementById(uid+'-btn');
  var pval  = document.getElementById(uid+'-price');
  var base  = <?php echo json_encode( $book_url ); ?>;
  wrap.querySelectorAll('.usbb-lat-btn').forEach(function(b){
    b.onclick = function(){
      wrap.querySelectorAll('.usbb-lat-btn').forEach(function(x){x.classList.remove('on');});
      b.classList.add('on');
      var side  = b.dataset.side;
      var price = b.dataset.price;
      pval.textContent = '\u00a3' + parseFloat(price).toFixed(2);
      btn.href = base + '&scan_side=' + encodeURIComponent(side);
    };
  });
})();
</script>
<?php endif; ?>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// Full body — 3-package selector
// ─────────────────────────────────────────────────────────────
function usbb_render_fullbody( $uid ) {
    $packages = usbb_full_body_packages();
    $first    = $packages[0];
    ob_start(); ?>
<div class="usbb-wrap" id="<?php echo esc_attr($uid); ?>" style="flex-direction:column;max-width:520px;">
  <div class="usbb-info" style="width:100%;">
    <div class="usbb-scan-name">
      <div class="usbb-chk"><svg viewBox="0 0 12 12" fill="none"><path d="M2 6L5 9L10 3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      Full Body MRI
    </div>
    <div class="usbb-divider"></div>
    <div class="usbb-pkgs">
      <?php foreach ( $packages as $i => $pkg ) : ?>
      <div class="usbb-pkg<?php echo $i === 0 ? ' on' : ''; ?>"
           data-id="<?php echo esc_attr($pkg['id']); ?>"
           data-price="<?php echo $pkg['price']; ?>"
           data-name="<?php echo esc_attr($pkg['name']); ?>">
        <div class="usbb-pkg-radio"></div>
        <span class="usbb-pkg-name"><?php echo esc_html($pkg['name']); ?></span>
        <span class="usbb-pkg-desc"><?php echo esc_html($pkg['desc']); ?></span>
        <span class="usbb-pkg-price">&pound;<?php echo number_format($pkg['price'], 0); ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="usbb-price-row" style="margin-top:12px;">
      <span class="usbb-price-lbl">Price</span>
      <span class="usbb-price-val" id="<?php echo esc_attr($uid); ?>-price">&pound;<?php echo number_format($first['price'], 2); ?></span>
    </div>
  </div>
  <a class="usbb-btn" id="<?php echo esc_attr($uid); ?>-btn"
     href="/book-your-scan/?scan_id=<?php echo esc_attr($first['id']); ?>&scan_mode=full"
     style="border-radius:0 0 10px 10px;padding:14px;justify-content:center;">
    BOOK NOW
    <svg viewBox="0 0 14 14" fill="none"><path d="M2 7H12M8 3L12 7L8 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
</div>
<script>
(function(){
  var uid  = <?php echo json_encode($uid); ?>;
  var wrap = document.getElementById(uid);
  if (!wrap) return;
  var btn  = document.getElementById(uid+'-btn');
  var pval = document.getElementById(uid+'-price');
  wrap.querySelectorAll('.usbb-pkg').forEach(function(pkg){
    pkg.onclick = function(){
      wrap.querySelectorAll('.usbb-pkg').forEach(function(x){x.classList.remove('on');});
      pkg.classList.add('on');
      var id    = pkg.dataset.id;
      var price = parseFloat(pkg.dataset.price);
      pval.textContent = '\u00a3' + price.toFixed(2);
      btn.href = '/book-your-scan/?scan_id=' + encodeURIComponent(id) + '&scan_mode=full';
    };
  });
})();
</script>
    <?php
    return ob_get_clean();
}
