<?php
/*
Plugin Name: Unirad Hero v9
Description: Homepage hero. Shortcode: [unirad_hero_v9]
Version: 9.4
Author: Unirad
*/
if(!defined('ABSPATH'))exit;

add_action('init','uhr9_init');
function uhr9_init(){
    add_shortcode('unirad_hero_v9','uhr9_render');
}

add_action('wp_head','uhr9_styles');
function uhr9_styles(){
echo '<style>
.uhr9{position:relative;min-height:76vh;display:flex;align-items:center;background:#08192a;overflow:visible;z-index:1;}
.uhr9-bg{position:absolute;inset:0;overflow:hidden;background-image:url("https://unirad.co.uk/wp-content/uploads/unirad_baner.png");background-size:cover;background-position:center right;opacity:.36;will-change:transform;}
.uhr9-fade{position:absolute;inset:0;pointer-events:none;background:linear-gradient(108deg,rgba(8,25,42,.97) 0%,rgba(8,25,42,.82) 38%,rgba(8,25,42,.28) 68%,transparent 100%);}
.uhr9::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#00a896,#00d4b8 55%,transparent);z-index:10;}

.uhr9-inner{position:relative;z-index:4;max-width:1200px;margin:0 auto;width:100%;padding:54px 52px 50px;animation:uhr9-in .45s ease both;}
@keyframes uhr9-in{from{opacity:0;transform:translateY(9px)}to{opacity:1;transform:none}}

/* Badge */
.uhr9-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(0,168,150,.16);border:1px solid rgba(0,168,150,.38);border-radius:30px;padding:4px 14px;margin-bottom:16px;font-size:10px;font-weight:700;color:#4ddece;letter-spacing:.13em;text-transform:uppercase;}
.uhr9-dot{width:6px;height:6px;background:#00d4b8;border-radius:50%;animation:uhr9-pulse 2s infinite;}
@keyframes uhr9-pulse{0%,100%{opacity:1}50%{opacity:.28}}

/* Headline — extra bold, matches v7 screenshot */
.uhr9 h1{
  font-family:"Instrument Serif",Georgia,serif;
  font-size:clamp(38px,4.2vw,62px);
  font-weight:900;
  color:#fff;line-height:1.04;
  margin:0 0 14px;
  max-width:min(640px,90vw);
  letter-spacing:-.025em;
}
.uhr9 h1 em{font-style:italic;color:#00d4b8;font-weight:700;}

/* Sub */
.uhr9-sub{font-size:14.5px;color:rgba(255,255,255,.65);line-height:1.6;max-width:min(420px,90vw);margin:0 0 22px;}

/* Trust pills desktop */
.uhr9-trust{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:22px;}
.uhr9-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:4px 11px;font-size:11px;font-weight:500;color:rgba(255,255,255,.82);}
.uhr9-pill-ic{color:#00d4b8;}

/* Mobile trust strip */
.uhr9-trust-mobile{display:none;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;margin-bottom:20px;}
.uhr9-trust-mobile::-webkit-scrollbar{display:none;}
.uhr9-trust-mobile-inner{display:flex;gap:7px;white-space:nowrap;}
.uhr9-tmcard{display:inline-flex;flex-direction:column;align-items:center;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.13);border-radius:9px;padding:7px 12px;flex-shrink:0;}
.uhr9-tmcard-n{font-size:14px;font-weight:800;color:#00d4b8;line-height:1;}
.uhr9-tmcard-l{font-size:9px;font-weight:600;color:rgba(255,255,255,.42);text-transform:uppercase;letter-spacing:.05em;margin-top:2px;}

/* ── Bar ── */
.uhr9-bar{background:#fff;border:1px solid rgba(0,0,0,.07);border-radius:12px;box-shadow:0 10px 44px rgba(0,0,0,.26);max-width:840px;position:relative;z-index:9000;overflow:visible;}
.uhr9-bar-row{display:flex;align-items:stretch;min-height:64px;}

.uhr9-seg{flex:1;padding:9px 16px 10px;border-right:1px solid rgba(0,30,60,.07);cursor:pointer;position:relative;transition:background .12s;min-width:0;display:flex;flex-direction:column;justify-content:center;}
.uhr9-seg:hover{background:rgba(0,168,150,.025);}
.uhr9-seg-lbl{display:block;font-size:9px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#a8bfcc;margin-bottom:3px;}
.uhr9-seg-val{font-size:13px;font-weight:600;color:#0b2240;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.uhr9-seg-val svg{opacity:.18;flex-shrink:0;}
.uhr9-seg-val.ph{color:#b8cdd8;font-weight:400;}

.uhr9-price-cell{flex:0 0 auto;padding:9px 16px 10px;border-right:1px solid rgba(0,30,60,.07);display:flex;flex-direction:column;justify-content:center;min-width:80px;}
.uhr9-price-lbl{font-size:9px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#a8bfcc;margin-bottom:3px;}
.uhr9-price-val{font-size:19px;font-weight:800;color:#00a896;line-height:1.1;}
.uhr9-price-val.ph{font-size:12px;font-weight:400;color:#c8d8e0;}

.uhr9-book-btn{background:#00a896;color:#fff;border:none;padding:0 18px;font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer;border-radius:0 11px 11px 0;transition:background .13s;flex-shrink:0;white-space:nowrap;display:flex;align-items:center;gap:4px;}
.uhr9-book-btn:hover:not(:disabled){background:#008a7d;}
.uhr9-book-btn:disabled{background:rgba(0,168,150,.12);color:rgba(0,80,70,.28);cursor:not-allowed;}
.uhr9-book-btn svg{width:11px;height:11px;flex-shrink:0;}

/* ── Trust strip — SEPARATE element below bar, NOT inside bar ── */
.uhr9-trust-strip{
  display:flex;flex-wrap:wrap;gap:14px;
  margin-top:11px;
  max-width:840px;
  padding:0 4px;
  font-size:10.5px;font-weight:500;color:rgba(255,255,255,.5);
}
.uhr9-trust-strip span{display:flex;align-items:center;gap:4px;}
.uhr9-tfi{color:#00d4b8;}

/* ── Dropdowns ── */
.uhr9-dda{position:relative;}
.uhr9-dd{display:none;position:absolute;top:calc(100% + 6px);left:0;min-width:260px;background:#fff;border:1px solid rgba(0,30,60,.1);border-radius:12px;box-shadow:0 20px 56px rgba(0,20,60,.16);z-index:99999;overflow:hidden;animation:uhr9-drop .12s ease;}
@keyframes uhr9-drop{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
.uhr9-dd.open{display:block;}
.uhr9-dd-hd{padding:8px 14px;font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9ab8c8;border-bottom:1px solid rgba(0,0,0,.05);background:#fafcfd;}

.uhr9-type-opt{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:500;color:#0b2240;transition:background .09s;border-bottom:1px solid rgba(0,0,0,.04);gap:8px;}
.uhr9-type-opt:last-child{border-bottom:none;}
.uhr9-type-opt:hover{background:#f3f9f8;}
.uhr9-type-opt.on{background:rgba(0,168,150,.07);color:#00a896;font-weight:600;}
.uhr9-dd-badge{font-size:10.5px;font-weight:700;color:#9ab8c8;background:rgba(0,0,0,.05);border-radius:9px;padding:2px 7px;white-space:nowrap;}
.uhr9-type-opt.on .uhr9-dd-badge{color:#00a896;background:rgba(0,168,150,.1);}

.uhr9-dd-search{display:block;width:100%;padding:9px 14px;background:#fafcfd;border:none;border-bottom:1px solid rgba(0,0,0,.05);color:#0b2240;font-family:inherit;font-size:13px;outline:none;}
.uhr9-dd-search::placeholder{color:#b8cdd8;}

.uhr9-dd-list{max-height:220px;overflow-y:auto;padding:4px;}
.uhr9-dd-list::-webkit-scrollbar{width:3px;}
.uhr9-dd-list::-webkit-scrollbar-thumb{background:rgba(0,168,150,.2);border-radius:2px;}

/* Checkbox item */
.uhr9-dd-item{display:flex;align-items:center;flex-wrap:wrap;gap:4px 9px;padding:7px 10px;border-radius:6px;cursor:pointer;font-size:12.5px;font-weight:500;color:#0b2240;transition:background .09s;}
.uhr9-dd-item:hover{background:#f3f9f8;}
.uhr9-dd-item.on{background:rgba(0,168,150,.08);}
.uhr9-chk{width:14px;height:14px;flex-shrink:0;border:1.5px solid #c8d8e4;border-radius:3px;background:#fff;display:flex;align-items:center;justify-content:center;transition:all .1s;}
.uhr9-dd-item.on .uhr9-chk{background:#00a896;border-color:#00a896;}
.uhr9-chk svg{display:none;width:8px;height:8px;}
.uhr9-dd-item.on .uhr9-chk svg{display:block;}
.uhr9-dd-item-name{flex:1;color:#0b2240;line-height:1.3;}
.uhr9-dd-item.on .uhr9-dd-item-name{color:#00877a;font-weight:600;}
.uhr9-dd-item-p{font-size:10.5px;font-weight:700;color:#b8cdd8;flex-shrink:0;}
.uhr9-dd-item-desc{font-size:10px;color:#8aacbb;width:100%;display:block;margin-top:1px;}
.uhr9-dd-item.on .uhr9-dd-item-p{color:#00a896;opacity:.75;}
.uhr9-dd-item.booked{opacity:.4;cursor:not-allowed;pointer-events:none;}
.uhr9-dd-item.booked .uhr9-dd-item-p{color:#e87070;}

/* Laterality — small ghost buttons like Image 2 */
.uhr9-sides{display:none;gap:5px;padding:4px 10px 8px;}
.uhr9-sides.show{display:flex;}
.uhr9-sb{
  flex:1;padding:5px 0;
  background:#fff;
  border:1px solid #d0dde6;
  border-radius:20px;
  color:#5a7080;
  font-family:inherit;font-size:11px;font-weight:600;
  cursor:pointer;transition:all .1s;text-align:center;line-height:1.3;
}
.uhr9-sb:hover{border-color:#00a896;color:#00a896;}
.uhr9-sb.on{background:#00a896;border-color:#00a896;color:#fff;}

.uhr9-dd-ft{border-top:1px solid rgba(0,0,0,.05);padding:8px 14px;font-size:11px;color:#9ab8c8;}
.uhr9-dd-ft a{color:#00a896;text-decoration:none;font-weight:600;}

/* Floating cards desktop */
.uhr9-review{position:absolute;right:52px;top:36px;z-index:4;background:rgba(255,255,255,.96);border:1px solid rgba(0,168,150,.1);border-radius:11px;padding:12px 16px;max-width:204px;box-shadow:0 4px 22px rgba(0,0,0,.13);animation:uhr9-in .55s .2s ease both;}
.uhr9-review-stars{color:#f59e0b;font-size:12px;margin-bottom:4px;letter-spacing:1px;}
.uhr9-review-text{font-size:10.5px;color:#374151;line-height:1.45;margin-bottom:4px;font-style:italic;}
.uhr9-review-auth{font-size:8.5px;font-weight:700;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;}
.uhr9-floaters{position:absolute;right:52px;bottom:44px;z-index:4;display:flex;gap:7px;}
.uhr9-fcard{background:rgba(255,255,255,.96);border:1px solid rgba(0,168,150,.1);border-radius:9px;padding:8px 14px;text-align:center;box-shadow:0 2px 14px rgba(0,0,0,.12);}
.uhr9-fcard-n{display:block;font-size:17px;font-weight:800;color:#00a896;line-height:1;}
.uhr9-fcard-l{display:block;font-size:8.5px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:#7ea4bc;margin-top:2px;}

/* Responsive */
@media(max-width:960px){
  .uhr9-floaters,.uhr9-review{display:none;}
  .uhr9-trust{display:none;}
  .uhr9-trust-mobile{display:block;}
  .uhr9{min-height:auto;}
  .uhr9-inner{padding:32px 18px 28px;}
  .uhr9 h1,.uhr9-sub{max-width:100%;}
  .uhr9-bar{max-width:100%;}
  .uhr9-trust-strip{max-width:100%;margin-top:9px;}
  .uhr9-bar-row{flex-direction:column;min-height:auto;}
  .uhr9-seg,.uhr9-price-cell{border-right:none;border-bottom:1px solid rgba(0,30,60,.07);padding:10px 14px;}
  .uhr9-price-cell{min-width:unset;flex:1;}
  .uhr9-book-btn{border-radius:0 0 11px 11px;min-height:46px;width:100%;justify-content:center;font-size:13px;}
  .uhr9-dd{left:0;right:0;min-width:unset;}
}
@media(max-width:480px){
  .uhr9-inner{padding:26px 15px 24px;}
  .uhr9 h1{font-size:clamp(30px,9vw,42px);}
  .uhr9-trust-strip{gap:10px;font-size:10px;}
}

/* ── Dark mode: logo visibility fix ────────────────────────────────────────
   Targets common WordPress logo selectors. If your theme uses a different
   class, add it to the list below.
   - brightness(0) invert(1) turns a dark logo solid white (cleanest result
     for monochrome / dark-text logos on transparent backgrounds).
   - drop-shadow adds a subtle glow so coloured logos still read on dark bg.
   ────────────────────────────────────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
  .custom-logo-link img,
  img.custom-logo,
  .site-branding img,
  .site-branding a img,
  header .logo img,
  .header-logo img,
  #site-logo img,
  .navbar-brand img {
    filter: brightness(0) invert(1) drop-shadow(0 0 3px rgba(255,255,255,.25));
  }
  /* Wrapper: add subtle semi-transparent bg so the logo area stands out */
  .custom-logo-link,
  .site-branding .logo-link {
    display: inline-flex;
    background: rgba(255,255,255,.06);
    border-radius: 6px;
    padding: 4px 8px;
  }
}
</style>';
}

function uhr9_catalog(){
    return array(
        'mri'=>array(
            array('id'=>'abdomen_pelvis','name'=>'Abdomen & Pelvis Scan','lat'=>false,'price'=>455,'priceBoth'=>455),
            array('id'=>'abdomen_mri','name'=>'Abdomen MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'achilles','name'=>'Achilles Tendon MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'angiography','name'=>'Angiography - Brain','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'ankle','name'=>'Ankle MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'axilla','name'=>'Axilla MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'brachial','name'=>'Brachial Plexus MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'brain_head','name'=>'Brain MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'breast','name'=>'Breast MRI','lat'=>false,'price'=>420,'priceBoth'=>420),
            array('id'=>'calf','name'=>'Calf MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'cardiac','name'=>'Cardiac MRI Scan','lat'=>false,'price'=>575,'priceBoth'=>575),
            array('id'=>'cervical','name'=>'Cervical Spine MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'chest','name'=>'Chest (Thorax) MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'clavicle','name'=>'Clavicle MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'coccyx','name'=>'Coccyx Spine MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'elbow','name'=>'Elbow MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'face','name'=>'Face MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'femur','name'=>'Femur (Upper Leg) MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'fingers','name'=>'Fingers / Thumb MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'foot','name'=>'Foot MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'forearm','name'=>'Forearm MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'hand','name'=>'Hand MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'hip','name'=>'Hip MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'iam','name'=>'IAM MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'knee','name'=>'Knee MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'liver','name'=>'Liver MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'lumbar','name'=>'Lumbar Spine MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'mrcp','name'=>'MRCP Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'orbit','name'=>'Orbit MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'pancreas','name'=>'Pancreas MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'pelvis_sports','name'=>'Pelvis (Sports Groin) MRI','lat'=>false,'price'=>380,'priceBoth'=>380),
            array('id'=>'pelvis_gynae','name'=>'Pelvis Gynae MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'pelvis_scan','name'=>'Pelvis MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'perianal','name'=>'Perianal Fistula MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'pituitary','name'=>'Pituitary MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'prostate','name'=>'Prostate MRI Scan','lat'=>false,'price'=>495,'priceBoth'=>495),
            array('id'=>'sacroiliac','name'=>'Sacroiliac Joints MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'sacrum','name'=>'Sacrum MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'scapula','name'=>'Scapula MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'shoulder','name'=>'Shoulder MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'sinuses','name'=>'Sinuses MRI Scan','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'small_bowel','name'=>'Small Bowel MRI Scan','lat'=>false,'price'=>495,'priceBoth'=>495),
            array('id'=>'soft_tissue','name'=>'Soft Tissue Neck MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'sternum','name'=>'Sternum MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'thigh','name'=>'Thigh MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'thoracic','name'=>'Thoracic Spine MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'tibia','name'=>'Tibia (Lower Leg) MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'tmj','name'=>'TMJ Joint MRI','lat'=>false,'price'=>290,'priceBoth'=>290),
            array('id'=>'upper_arm','name'=>'Upper Arm MRI','lat'=>true,'price'=>290,'priceBoth'=>455),
            array('id'=>'wrist','name'=>'Wrist MRI Scan','lat'=>true,'price'=>290,'priceBoth'=>455),
        ),
        'full'=>array(
            array('id'=>'1332','name'=>'Silver Package','desc'=>'Brain, Abdomen & Pelvis','lat'=>false,'price'=>590,'priceBoth'=>590),
            array('id'=>'1464','name'=>'Gold Package','desc'=>'Brain, Spine (whole), Abdomen & Pelvis','lat'=>false,'price'=>1210,'priceBoth'=>1210),
            array('id'=>'1465','name'=>'Platinum Package','desc'=>'Heart, Brain, Spine (whole), Abdomen & Pelvis','lat'=>false,'price'=>1660,'priceBoth'=>1660),
        ),
        'gp'=>array(
            array('id'=>'6388','name'=>'GP Consultation (In-Clinic)','lat'=>false,'price'=>99,'priceBoth'=>99),
            array('id'=>'6387','name'=>'GP Consultation (Online)','lat'=>false,'price'=>45,'priceBoth'=>45),
            array('id'=>'1482','name'=>'GP Referral Letter','lat'=>false,'price'=>40,'priceBoth'=>40),
            array('id'=>'1481','name'=>'GP Consultation (After Scan)','lat'=>false,'price'=>50,'priceBoth'=>50),
        ),
    );
}

function uhr9_render(){
    $cat = uhr9_catalog();
    $js  = wp_json_encode($cat);
    ob_start();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@400;500;600;700;900&display=swap">

<section class="uhr9" id="uhr9">
  <div class="uhr9-bg" id="uhr9Bg"></div>
  <div class="uhr9-fade"></div>

  <div class="uhr9-review">
    <div class="uhr9-review-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
    <div class="uhr9-review-text">"Professional service from start to finish. Highly recommend."</div>
    <div class="uhr9-review-auth">Sarah M. &middot; Google Review</div>
  </div>
  <div class="uhr9-floaters">
    <div class="uhr9-fcard"><span class="uhr9-fcard-n">5d</span><span class="uhr9-fcard-l">Report</span></div>
    <div class="uhr9-fcard"><span class="uhr9-fcard-n">5&#9733;</span><span class="uhr9-fcard-l">Google</span></div>
    <div class="uhr9-fcard"><span class="uhr9-fcard-n">&pound;290</span><span class="uhr9-fcard-l">From</span></div>
  </div>

  <div class="uhr9-inner">

    <div class="uhr9-badge"><span class="uhr9-dot"></span>Available Same Week &middot; Glasgow</div>

    <h1>MRI Appointment <em>Within Days,</em> Not Months</h1>

    <p class="uhr9-sub">Private MRI scanning in Glasgow &mdash; no waiting lists, expert radiologist reporting, results within 5 days.</p>

    <!-- Desktop trust pills -->
    <div class="uhr9-trust">
      <div class="uhr9-pill"><span class="uhr9-pill-ic">&#10003;</span>Radiologist Report Included</div>
      <div class="uhr9-pill"><span class="uhr9-pill-ic">&#10003;</span>From &pound;290 &middot; No Hidden Fees</div>
      <div class="uhr9-pill"><span class="uhr9-pill-ic">&#10003;</span>Book Without a GP Appointment</div>
    </div>

    <!-- Mobile trust strip -->
    <div class="uhr9-trust-mobile">
      <div class="uhr9-trust-mobile-inner">
        <div class="uhr9-tmcard"><span class="uhr9-tmcard-n">5d</span><span class="uhr9-tmcard-l">Report</span></div>
        <div class="uhr9-tmcard"><span class="uhr9-tmcard-n">5&#9733;</span><span class="uhr9-tmcard-l">Google</span></div>
        <div class="uhr9-tmcard"><span class="uhr9-tmcard-n">&pound;290</span><span class="uhr9-tmcard-l">From</span></div>
        <div class="uhr9-tmcard"><span class="uhr9-tmcard-n">&#10003;</span><span class="uhr9-tmcard-l">Direct Book</span></div>
        <div class="uhr9-tmcard"><span class="uhr9-tmcard-n">&#10003;</span><span class="uhr9-tmcard-l">Instant</span></div>
      </div>
    </div>

    <!-- Booking bar — clean, no footer inside -->
    <div class="uhr9-bar">
      <div class="uhr9-bar-row">

        <!-- Scan type -->
        <div class="uhr9-seg uhr9-dda" id="uhr9TS" style="flex:0 0 auto;min-width:148px;">
          <span class="uhr9-seg-lbl">Scan Type</span>
          <div class="uhr9-seg-val" id="uhr9TV">
            <span id="uhr9TT">MRI Scan</span>
            <svg width="9" height="5" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div class="uhr9-dd" id="uhr9TD">
            <div class="uhr9-dd-hd">Select scan type</div>
            <div class="uhr9-type-opt on" data-mode="mri">MRI Scan <span class="uhr9-dd-badge">From &pound;290</span></div>
            <div class="uhr9-type-opt" data-mode="full">Full Body MRI <span class="uhr9-dd-badge">From &pound;590</span></div>
            <div class="uhr9-type-opt" data-mode="gp">GP Consultation <span class="uhr9-dd-badge">From &pound;40</span></div>
          </div>
        </div>

        <!-- Body part -->
        <div class="uhr9-seg uhr9-dda" id="uhr9PS" style="flex:2">
          <span class="uhr9-seg-lbl">Body Part / Service</span>
          <div class="uhr9-seg-val ph" id="uhr9PV">
            <span id="uhr9PT">Select body part...</span>
            <svg width="9" height="5" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          </div>
          <div class="uhr9-dd" id="uhr9PD" style="min-width:320px">
            <input class="uhr9-dd-search" id="uhr9SR" type="text" placeholder="Search e.g. knee, spine, brain...">
            <div class="uhr9-dd-list" id="uhr9LI"></div>
            <div class="uhr9-sides" id="uhr9SI">
              <button class="uhr9-sb on" data-side="Left">Left</button>
              <button class="uhr9-sb" data-side="Right">Right</button>
              <button class="uhr9-sb" data-side="Bilateral">Bilateral</button>
            </div>
            <div class="uhr9-dd-ft">Not sure? <a href="/appointment/">Talk to us &rarr;</a></div>
          </div>
        </div>

        <!-- Price -->
        <div class="uhr9-price-cell">
          <div class="uhr9-price-lbl">Price</div>
          <div class="uhr9-price-val ph" id="uhr9PX">&mdash;</div>
        </div>

        <!-- Book button -->
        <button class="uhr9-book-btn" id="uhr9BN" disabled>
          Book Now
          <svg viewBox="0 0 14 14" fill="none"><path d="M2 7H12M8 3L12 7L8 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
    </div>

    <!-- Trust strip — OUTSIDE & BELOW bar, separate element -->
    <div class="uhr9-trust-strip">
      <span><span class="uhr9-tfi">&#10003;</span> Same week appointments</span>
      <span><span class="uhr9-tfi">&#10003;</span> Secure payment</span>
      <span><span class="uhr9-tfi">&#10003;</span> Instant confirmation</span>
    </div>

  </div>
</section>

<script>
(function(){
var C=<?php echo $js;?>;
var SPECIAL={breast:420,cardiac:575,prostate:495,small_bowel:495,abdomen_pelvis:455,pelvis_sports:380};
var BUNDLE={1:290,2:455,3:600,4:700};
var S={mode:'mri',picks:{},pendingId:null};
var ML={mri:'MRI Scan',full:'Full Body MRI',gp:'GP Consultation'};
function g(id){return document.getElementById(id);}
var tS=g('uhr9TS'),tD=g('uhr9TD'),tT=g('uhr9TT');
var pS=g('uhr9PS'),pD=g('uhr9PD'),pT=g('uhr9PT'),pV=g('uhr9PV');
var sr=g('uhr9SR'),li=g('uhr9LI'),si=g('uhr9SI');
var bn=g('uhr9BN'),px=g('uhr9PX');
var sb=si.querySelectorAll('.uhr9-sb');

function f(p){return '£'+p;}
function its(){return C[S.mode]||[];}
function keys(){return Object.keys(S.picks);}

function calcTotal(){
  if(S.mode!=='mri'){var t=0;keys().forEach(function(k){t+=S.picks[k].price||0;});return t;}
  var sp=0,bp=0;
  keys().forEach(function(k){
    var p=S.picks[k];
    if(SPECIAL.hasOwnProperty(p.id)){sp+=SPECIAL[p.id];}
    else{bp+=(p.side==='Both')?2:1;}
  });
  bp=Math.min(bp,4);
  return sp+(bp>0?(BUNDLE[bp]||0):0);
}

function countStdParts(){
  var bp=0;
  keys().forEach(function(k){
    var p=S.picks[k];
    if(!SPECIAL.hasOwnProperty(p.id)){bp+=(p.side==='Both')?2:1;}
  });
  return bp;
}

function selLabel(){
  var n=keys().length;
  if(n===0)return 'Select body part...';
  if(n===1){
    var k=keys()[0],p=S.picks[k];
    return p.name+(p.side?' — '+p.side:'');
  }
  return n+' scans selected';
}

function ui(){
  tT.textContent=ML[S.mode];
  var n=keys().length;
  var ready=n>0;
  if(ready){
    pT.textContent=selLabel();
    pV.classList.remove('ph');
    px.textContent=f(calcTotal());
    px.classList.remove('ph');
  } else {
    pT.textContent='Select body part...';
    pV.classList.add('ph');
    px.textContent='—';
    px.classList.add('ph');
  }
  bn.disabled=!ready;
}

function mkTick(){
  var ns='http://www.w3.org/2000/svg';
  var s=document.createElementNS(ns,'svg');s.setAttribute('viewBox','0 0 9 9');s.setAttribute('fill','none');s.setAttribute('width','8');s.setAttribute('height','8');
  var p=document.createElementNS(ns,'path');p.setAttribute('d','M1.5 4.5L3.5 6.5L7.5 2.5');p.setAttribute('stroke','#fff');p.setAttribute('stroke-width','1.6');p.setAttribute('stroke-linecap','round');p.setAttribute('stroke-linejoin','round');
  s.appendChild(p);return s;
}

function rl(q){
  q=(q||'').toLowerCase().trim();li.innerHTML='';
  its().forEach(function(x){
    if(q&&x.name.toLowerCase().indexOf(q)===-1)return;
    var sel=!!(S.picks[x.id]);
    var d=document.createElement('div');d.className='uhr9-dd-item'+(sel?' on':'');
    var chk=document.createElement('div');chk.className='uhr9-chk';chk.appendChild(mkTick());d.appendChild(chk);
    var nm=document.createElement('span');nm.className='uhr9-dd-item-name';nm.textContent=x.name;d.appendChild(nm);
    if(x.desc){var ds=document.createElement('span');ds.className='uhr9-dd-item-desc';ds.textContent=x.desc;d.appendChild(ds);}
    var pr=document.createElement('span');pr.className='uhr9-dd-item-p';pr.textContent=f(x.price);d.appendChild(pr);
    d.onclick=function(e){
      e.stopPropagation();
      if(S.picks[x.id]){
        // Deselect
        delete S.picks[x.id];
        S.pendingId=null;
        si.classList.remove('show');
        rl(sr.value);ui();
        return;
      }
      // Callback items: only 1 selection allowed
      if(x.cb){
        if(keys().length>0){
          alert('Callback scans can only be selected individually. Please book them separately.');
          return;
        }
      } else {
        // If a callback item already selected, cannot add non-callback
        var hasCb=keys().some(function(k){return S.picks[k].cb;});
        if(hasCb){
          alert('Please remove the callback scan before adding additional scans.');
          return;
        }
      }
      // Check limit for standard items
      if(!SPECIAL.hasOwnProperty(x.id)){
        var newPts=x.lat?2:1;
        if(countStdParts()+newPts>4){
          alert('Maximum 4 standard scan areas (bilateral counts as 2). Special scans like Breast and Cardiac are added separately.');
          return;
        }
      }
      // If laterality, show side picker
      if(x.lat){
        S.pendingId=x.id;
        si.classList.add('show');
        sb.forEach(function(b){b.classList.remove('on');});
        sb[0].classList.add('on');
        // Add with Left as default
        S.picks[x.id]=Object.assign({},x,{side:'Left'});
      } else {
        S.picks[x.id]=Object.assign({},x,{side:null});
        S.pendingId=null;
        si.classList.remove('show');
      }
      rl(sr.value);ui();
    };
    li.appendChild(d);
  });
}

function ca(){tD.classList.remove('open');pD.classList.remove('open');}
tS.onclick=function(e){e.stopPropagation();var w=tD.classList.contains('open');ca();if(!w)tD.classList.add('open');};
document.querySelectorAll('.uhr9-type-opt').forEach(function(o){
  o.onclick=function(e){
    e.stopPropagation();S.mode=o.dataset.mode;S.picks={};S.pendingId=null;si.classList.remove('show');
    document.querySelectorAll('.uhr9-type-opt').forEach(function(x){x.classList.remove('on');});
    o.classList.add('on');ca();ui();
  };
});
pS.onclick=function(e){e.stopPropagation();var w=pD.classList.contains('open');ca();if(!w){pD.classList.add('open');rl(sr.value);sr.focus();}};
sr.oninput=function(){rl(this.value);};
sr.onclick=function(e){e.stopPropagation();};
sb.forEach(function(b){
  b.onclick=function(e){
    e.stopPropagation();
    sb.forEach(function(x){x.classList.remove('on');});
    b.classList.add('on');
    var side=b.dataset.side;
    if(side==='Bilateral')side='Both'; // normalize
    if(S.pendingId&&S.picks[S.pendingId]){
      // Check bilateral limit
      if(side==='Both'){
        var oldPts=S.picks[S.pendingId].side==='Both'?2:1;
        var curPts=countStdParts()-oldPts;
        if(curPts+2>4){alert('Bilateral counts as 2 areas. Maximum 4 standard areas total.');b.classList.remove('on');sb[0].classList.add('on');return;}
      }
      S.picks[S.pendingId].side=side;
    }
    ui();
  };
});
document.addEventListener('click',ca);

bn.onclick=function(){
  if(bn.disabled||keys().length===0)return;
  if(S.mode!=='mri'){
    var k=keys()[0],it=S.picks[k];
    var url='/book-your-scan/?scan_id='+encodeURIComponent(it.id)+'&scan_mode='+encodeURIComponent(S.mode);
    window.location.href=url;
    return;
  }
  // Build scan_ids URL
  var ids=[],params=[];
  keys().forEach(function(k){
    var p=S.picks[k];
    ids.push(p.id);
    if(p.side)params.push('side_'+p.id+'='+encodeURIComponent(p.side));
  });
  var url='/book-your-scan/?scan_ids='+encodeURIComponent(ids.join(','))+'&scan_mode=mri';
  if(params.length)url+='&'+params.join('&');
  window.location.href=url;
};

var bgEl=g('uhr9Bg');
if(bgEl&&window.innerWidth>960){window.addEventListener('scroll',function(){bgEl.style.transform='translateY('+Math.round(window.scrollY*.15)+'px)';},{passive:true});}
rl();ui();
})();
</script>
<?php
    return ob_get_clean();
}
