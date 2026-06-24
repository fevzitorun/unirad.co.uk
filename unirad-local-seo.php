<?php
/*
Plugin Name: Unirad Local SEO Pages
Plugin URI:  https://unirad.co.uk
Description: Glasgow neighbourhood MRI landing pages. 28 locations, schema markup, Yoast-compatible.
Version:     1.0.0
Author:      Unirad
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Location Data ─────────────────────────────────────────────────────────────
// Clinic: 22 Loanbank Quadrant, Glasgow G51 3HZ (Govan / Ibrox area)

function unirad_seo_locations() {
    return [
        'govan' => [
            'name' => 'Govan', 'county' => 'Glasgow',
            'miles' => 0, 'mins' => 2,
            'transport' => 'Govan Subway station is a 5-minute walk (Subway Circle Line). Bus 23 and Bus 26 both stop on Govan Road, 3 minutes\' walk. Free on-site parking available at the clinic.',
            'blurb' => 'Our MRI clinic is based right here in Govan — you couldn\'t be closer. Walk-in distance for many Govan residents.',
            'nearby' => ['Ibrox', 'Cessnock', 'Kinning Park', 'Drumoyne'],
        ],
        'ibrox' => [
            'name' => 'Ibrox', 'county' => 'Glasgow',
            'miles' => 1, 'mins' => 4,
            'transport' => 'Ibrox Subway station is under 5 minutes\' walk. Ample free parking directly outside the clinic.',
            'blurb' => 'Ibrox residents are minutes away from our private MRI clinic — no need to travel across the city.',
            'nearby' => ['Govan', 'Cessnock', 'Kinning Park', 'Pollokshields'],
        ],
        'paisley' => [
            'name' => 'Paisley', 'county' => 'Renfrewshire',
            'miles' => 4, 'mins' => 12,
            'transport' => 'A short drive via the M8. Train: Paisley Canal or Paisley Gilmour Street to Glasgow Central, then Subway to Govan. Free parking at the clinic.',
            'blurb' => 'Paisley patients regularly travel to our Glasgow clinic for private MRI — just a 12-minute drive through Renfrewshire.',
            'nearby' => ['Foxbar', 'Gallowhill', 'Ralston', 'Johnstone'],
        ],
        'glasgow-city-centre' => [
            'name' => 'Glasgow City Centre', 'county' => 'Glasgow',
            'miles' => 2, 'mins' => 8,
            'transport' => 'Subway from Buchanan Street or St Enoch to Govan (10 min). By car via M8, free parking at clinic.',
            'blurb' => 'Just minutes from Glasgow city centre, our Govan clinic is easily reached by Subway or car from the heart of the city.',
            'nearby' => ['Merchant City', 'Finnieston', 'Garnethill', 'Anderston'],
        ],
        'partick' => [
            'name' => 'Partick & Finnieston', 'county' => 'Glasgow West',
            'miles' => 2, 'mins' => 8,
            'transport' => 'Partick Subway station connects directly to Govan in 2 stops (under 6 minutes). Bus 9 and Bus 9A run from Dumbarton Road through Partick and on to Govan Road, stopping near the clinic. Free parking at the clinic.',
            'blurb' => 'West End residents in Partick and Finnieston have direct Subway access to our Govan MRI clinic — two stops, no transfers.',
            'nearby' => ['Byres Road', 'Hyndland', 'Broomhill', 'Kelvinside'],
        ],
        'hillhead' => [
            'name' => 'Hillhead & Byres Road', 'county' => 'Glasgow West',
            'miles' => 3, 'mins' => 12,
            'transport' => 'Hillhead Subway station (Byres Road) to Govan is 4 stops on the Subway Circle Line — approximately 10 minutes. Bus 44 runs from Great Western Road via Dumbarton Road to Govan Road, stopping within 5 minutes\' walk of the clinic. Free parking at the clinic.',
            'blurb' => 'Hillhead and Byres Road residents are just a short Subway ride from our Govan MRI clinic — 4 stops on the Circle Line and you\'re with us.',
            'nearby' => ['Partick', 'Kelvinside', 'Hyndland', 'Dowanhill'],
        ],
        'shawlands' => [
            'name' => 'Shawlands & Southside', 'county' => 'Glasgow South',
            'miles' => 3, 'mins' => 10,
            'transport' => 'Via Pollokshaws Road or Paisley Road West. Subway: Pollokshields East to Cessnock (4 stops). Free parking at clinic.',
            'blurb' => 'Glasgow Southside residents in Shawlands, Pollokshields and surrounds are well-connected to our clinic via Subway or a short drive.',
            'nearby' => ['Pollokshields', 'Queens Park', 'Battlefield', 'Langside'],
        ],
        'rutherglen' => [
            'name' => 'Rutherglen', 'county' => 'South Lanarkshire',
            'miles' => 4, 'mins' => 12,
            'transport' => 'A quick drive via Rutherglen Road and Glasgow Road. Train: Rutherglen station to Glasgow Central then Subway. Free parking.',
            'blurb' => 'Rutherglen residents can reach our private MRI clinic in Govan in under 15 minutes — either by car or public transport.',
            'nearby' => ['Cambuslang', 'Burnside', 'Toryglen', 'Bridgeton'],
        ],
        'cambuslang' => [
            'name' => 'Cambuslang', 'county' => 'South Lanarkshire',
            'miles' => 6, 'mins' => 16,
            'transport' => 'Via A724 and A8. Train: Cambuslang station to Glasgow Central then Subway to Govan. Free parking at clinic.',
            'blurb' => 'Cambuslang patients find our Govan clinic convenient — a straight drive in along the Clyde and no NHS waiting list to contend with.',
            'nearby' => ['Rutherglen', 'Newton', 'Hallside', 'Westburn'],
        ],
        'east-kilbride' => [
            'name' => 'East Kilbride', 'county' => 'South Lanarkshire',
            'miles' => 12, 'mins' => 25,
            'transport' => 'Via M77 or A726. Train: East Kilbride to Glasgow Central (25 min), then Subway to Govan. Free parking at clinic.',
            'blurb' => 'East Kilbride is Scotland\'s largest new town and home to thousands of families who choose Unirad for fast, private MRI scanning.',
            'nearby' => ['Westwood', 'Nerston', 'Calderwood', 'Hairmyres'],
        ],
        'hamilton' => [
            'name' => 'Hamilton', 'county' => 'South Lanarkshire',
            'miles' => 13, 'mins' => 26,
            'transport' => 'Via M74 northbound — straightforward motorway drive. Train: Hamilton Central to Glasgow Central, then Subway. Free parking.',
            'blurb' => 'Hamilton residents regularly use Unirad for private MRI — the M74 makes us the closest private imaging centre for much of South Lanarkshire.',
            'nearby' => ['Motherwell', 'Larkhall', 'Blantyre', 'Bothwell'],
        ],
        'motherwell' => [
            'name' => 'Motherwell', 'county' => 'North Lanarkshire',
            'miles' => 15, 'mins' => 28,
            'transport' => 'Via M74/M8 — direct motorway link. Train: Motherwell to Glasgow Central (20 min), then Subway to Govan. Free parking.',
            'blurb' => 'Motherwell and Wishaw residents choose Unirad for private MRI scanning — same-week appointments and no referral needed makes it the practical choice.',
            'nearby' => ['Wishaw', 'Bellshill', 'Shotts', 'Newarthill'],
        ],
        'coatbridge' => [
            'name' => 'Coatbridge', 'county' => 'North Lanarkshire',
            'miles' => 14, 'mins' => 28,
            'transport' => 'Via M8 westbound. Train: Coatbridge Sunnyside to Glasgow Queen Street, then Subway. Free parking at clinic.',
            'blurb' => 'Coatbridge and Airdrie patients have quick motorway access to our Glasgow MRI clinic — ideal for bypassing NHS waiting lists.',
            'nearby' => ['Airdrie', 'Glenboig', 'Bargeddie', 'Shottstown'],
        ],
        'airdrie' => [
            'name' => 'Airdrie', 'county' => 'North Lanarkshire',
            'miles' => 16, 'mins' => 30,
            'transport' => 'Via M8 westbound. Train: Airdrie to Glasgow Queen Street (30 min), then Subway. Free parking.',
            'blurb' => 'Airdrie residents can access private MRI scanning in Glasgow with no GP referral required — a straightforward drive or train journey.',
            'nearby' => ['Coatbridge', 'Caldercruix', 'Plains', 'Greengairs'],
        ],
        'bishopbriggs' => [
            'name' => 'Bishopbriggs', 'county' => 'East Dunbartonshire',
            'miles' => 8, 'mins' => 20,
            'transport' => 'Via A803 and city roads. Train: Bishopbriggs to Glasgow Queen Street, then Subway to Govan. Free parking at clinic.',
            'blurb' => 'Bishopbriggs residents are well placed to access our Govan MRI clinic — a 20-minute drive through the north of Glasgow.',
            'nearby' => ['Springburn', 'Auchinairn', 'Kirkintilloch', 'Lenzie'],
        ],
        'kirkintilloch' => [
            'name' => 'Kirkintilloch', 'county' => 'East Dunbartonshire',
            'miles' => 12, 'mins' => 25,
            'transport' => 'Via A803 into Glasgow. Bus or train to Glasgow Queen Street, then Subway. Free parking at clinic.',
            'blurb' => 'Kirkintilloch and East Dunbartonshire residents choose Unirad for fast, private MRI — no waiting list, results within 5 days.',
            'nearby' => ['Lenzie', 'Bishopbriggs', 'Lennoxtown', 'Milton of Campsie'],
        ],
        'bearsden' => [
            'name' => 'Bearsden', 'county' => 'East Dunbartonshire',
            'miles' => 8, 'mins' => 18,
            'transport' => 'Via Anniesland and Crow Road. Train: Bearsden to Glasgow Queen Street, then Subway. Free parking.',
            'blurb' => 'Bearsden is one of Glasgow\'s most affluent suburbs — residents regularly choose Unirad for prompt, professional private MRI imaging.',
            'nearby' => ['Milngavie', 'Westerton', 'Anniesland', 'Knightswood'],
        ],
        'milngavie' => [
            'name' => 'Milngavie', 'county' => 'East Dunbartonshire',
            'miles' => 11, 'mins' => 25,
            'transport' => 'Via Anniesland Road and M8. Train: Milngavie to Glasgow Queen Street, then Subway. Free parking.',
            'blurb' => 'Milngavie sits at the start of the West Highland Way — and Unirad is at the start of getting your MRI done quickly, with results in 5 working days.',
            'nearby' => ['Bearsden', 'Strathblane', 'Baldernock', 'Blanefield'],
        ],
        'clydebank' => [
            'name' => 'Clydebank', 'county' => 'West Dunbartonshire',
            'miles' => 7, 'mins' => 16,
            'transport' => 'Via Great Western Road and the Clydeside expressway. Train: Clydebank to Glasgow Queen Street, then Subway. Free parking.',
            'blurb' => 'Clydebank residents are just a 16-minute drive from our Govan MRI clinic — the fastest way to private imaging in the West of Scotland.',
            'nearby' => ['Dalmuir', 'Radnor Park', 'Drumry', 'Hardgate'],
        ],
        'dumbarton' => [
            'name' => 'Dumbarton', 'county' => 'West Dunbartonshire',
            'miles' => 16, 'mins' => 28,
            'transport' => 'Via A82 and Clydeside Expressway. Train: Dumbarton Central to Glasgow Queen Street (30 min), then Subway. Free parking.',
            'blurb' => 'Dumbarton patients regularly make the short A82 journey to Unirad — same-week appointments and a professional radiologist report make it worthwhile.',
            'nearby' => ['Alexandria', 'Balloch', 'Helensburgh', 'Bonhill'],
        ],
        'giffnock' => [
            'name' => 'Giffnock & Clarkston', 'county' => 'East Renfrewshire',
            'miles' => 7, 'mins' => 16,
            'transport' => 'Via M77 and M8, or through Pollokshaws. Train: Giffnock to Glasgow Central, then Subway. Free parking.',
            'blurb' => 'East Renfrewshire residents in Giffnock and Clarkston are well-connected to our clinic via the M77 — a straightforward 16-minute drive.',
            'nearby' => ['Newton Mearns', 'Busby', 'Eaglesham', 'Waterfoot'],
        ],
        'newton-mearns' => [
            'name' => 'Newton Mearns', 'county' => 'East Renfrewshire',
            'miles' => 9, 'mins' => 20,
            'transport' => 'Via M77 northbound — direct motorway link to Glasgow. Train to Glasgow Central then Subway. Free parking.',
            'blurb' => 'Newton Mearns has one of the fastest-growing populations in Scotland. Private MRI at Unirad means no waiting list and expert results within 5 days.',
            'nearby' => ['Giffnock', 'Barrhead', 'Waterfoot', 'Mearnskirk'],
        ],
        'barrhead' => [
            'name' => 'Barrhead', 'county' => 'East Renfrewshire',
            'miles' => 7, 'mins' => 18,
            'transport' => 'Via M77 and B773. Train: Barrhead to Glasgow Central, then Subway to Govan. Free parking at clinic.',
            'blurb' => 'Barrhead residents have easy M77 access to our Govan MRI clinic — get your scan booked for this week, not this year.',
            'nearby' => ['Neilston', 'Uplawmoor', 'Busby', 'Thornliebank'],
        ],
        'renfrew' => [
            'name' => 'Renfrew', 'county' => 'Renfrewshire',
            'miles' => 4, 'mins' => 10,
            'transport' => 'Via King\'s Inch Road and Clyde Tunnel or M8. A short, direct drive from Renfrew. Free parking at clinic.',
            'blurb' => 'Renfrew is right on our doorstep — just 10 minutes through the Clyde Tunnel brings you to our private MRI clinic in Govan.',
            'nearby' => ['Paisley', 'Braehead', 'Penilee', 'Hillington'],
        ],
        'johnstone' => [
            'name' => 'Johnstone', 'county' => 'Renfrewshire',
            'miles' => 10, 'mins' => 22,
            'transport' => 'Via A737 and M8. Train: Johnstone to Glasgow Central, then Subway. Free parking at clinic.',
            'blurb' => 'Johnstone and Linwood residents access Unirad via the M8 — private MRI with no waiting list and a full radiologist report within 5 working days.',
            'nearby' => ['Linwood', 'Elderslie', 'Bridge of Weir', 'Kilbarchan'],
        ],
        'greenock' => [
            'name' => 'Greenock & Inverclyde', 'county' => 'Inverclyde',
            'miles' => 24, 'mins' => 35,
            'transport' => 'Via A8/M8. Train: Greenock Central to Glasgow Central (50 min), then Subway. Free parking at clinic.',
            'blurb' => 'For Inverclyde residents, Unirad in Glasgow is the nearest private MRI option — a 35-minute drive or a direct train to Glasgow, then a short Subway hop.',
            'nearby' => ['Port Glasgow', 'Gourock', 'Kilmacolm', 'Inverkip'],
        ],
        'bellshill' => [
            'name' => 'Bellshill & Uddingston', 'county' => 'North Lanarkshire',
            'miles' => 11, 'mins' => 22,
            'transport' => 'Via M74/M8. Train: Uddingston to Glasgow Central, then Subway to Govan. Free parking.',
            'blurb' => 'Bellshill and Uddingston patients have excellent motorway access to our Govan clinic — 22 minutes on the M74 and M8.',
            'nearby' => ['Bothwell', 'Viewpark', 'Mossend', 'Holytown'],
        ],
        'wishaw' => [
            'name' => 'Wishaw', 'county' => 'North Lanarkshire',
            'miles' => 17, 'mins' => 30,
            'transport' => 'Via M74/M8. Train: Wishaw to Glasgow Central (30 min), then Subway. Free parking at clinic.',
            'blurb' => 'Wishaw patients choose Unirad to avoid NHS waiting lists — a 30-minute drive up the M74 and you\'re with us in Govan.',
            'nearby' => ['Motherwell', 'Carluke', 'Newmains', 'Cleland'],
        ],
        'stirling' => [
            'name' => 'Stirling', 'county' => 'Stirling',
            'miles' => 28, 'mins' => 40,
            'transport' => 'Via M80 and M8 — straightforward motorway drive. Train: Stirling to Glasgow Queen Street (40 min), then Subway. Free parking.',
            'blurb' => 'Stirling residents looking for private MRI find Unirad the most convenient option in central Scotland — 40 minutes by train or motorway.',
            'nearby' => ['Bridge of Allan', 'Dunblane', 'Alloa', 'Falkirk'],
        ],
    ];
}

// ── Yoast Sitemap Ping (once, on activation) ──────────────────────────────────

function unirad_seo_sitemap_ping() {
    if ( get_option( 'unirad_seo_sitemap_pinged' ) ) return;
    $sitemap = home_url( '/sitemap_index.xml' ); // Yoast default
    $ping    = 'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap );
    wp_remote_get( $ping, [ 'timeout' => 5, 'blocking' => false ] );
    update_option( 'unirad_seo_sitemap_pinged', 1 );
}

// ── Register CPT ─────────────────────────────────────────────────────────────

add_action( 'init',     'unirad_seo_register_cpt' );
add_filter( 'wpseo_sitemap_entry', '__return_true' ); // ensure Yoast doesn't skip unirad_loc entries
add_action( 'init',     'unirad_seo_sitemap_ping' );

function unirad_seo_register_cpt() {
    register_post_type( 'unirad_loc', [
        'labels'        => [ 'name' => 'MRI Locations', 'singular_name' => 'MRI Location' ],
        'public'        => true,
        'show_in_menu'  => false,
        'rewrite'       => [ 'slug' => 'private-mri-scan', 'with_front' => false ],
        'supports'      => [ 'title', 'custom-fields' ],
        'has_archive'   => false,
    ] );
}

// ── Create Pages on Activation ────────────────────────────────────────────────

register_activation_hook( __FILE__, 'unirad_seo_create_pages' );

function unirad_seo_create_pages() {
    unirad_seo_register_cpt();

    foreach ( unirad_seo_locations() as $slug => $loc ) {
        $existing = get_page_by_path( $slug, OBJECT, 'unirad_loc' );
        if ( $existing ) continue;

        $post_id = wp_insert_post( [
            'post_title'  => 'Private MRI Scan in ' . $loc['name'],
            'post_name'   => $slug,
            'post_type'   => 'unirad_loc',
            'post_status' => 'publish',
            'post_content' => '',
        ] );

        if ( is_wp_error( $post_id ) ) continue;

        // Location meta
        update_post_meta( $post_id, '_unirad_loc_name',      $loc['name'] );
        update_post_meta( $post_id, '_unirad_loc_county',    $loc['county'] );
        update_post_meta( $post_id, '_unirad_loc_miles',     $loc['miles'] );
        update_post_meta( $post_id, '_unirad_loc_mins',      $loc['mins'] );
        update_post_meta( $post_id, '_unirad_loc_transport', $loc['transport'] );
        update_post_meta( $post_id, '_unirad_loc_blurb',     $loc['blurb'] );
        update_post_meta( $post_id, '_unirad_loc_nearby',    implode( ', ', $loc['nearby'] ) );

        // Yoast SEO meta
        $yoast_title = 'Private MRI Scan Serving ' . $loc['name'] . ' | From £290 | Unirad Glasgow';
        $yoast_desc  = 'Private MRI scan for ' . $loc['name'] . ' patients from £290. Same-week appointments, self-referral available for selected scans. Expert radiologist report in 5 days. Just ' . $loc['mins'] . ' minutes from ' . $loc['name'] . '.';
        update_post_meta( $post_id, '_yoast_wpseo_title',    $yoast_title );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $yoast_desc );
        update_post_meta( $post_id, '_yoast_wpseo_focuskw',  'private MRI scan ' . strtolower( $loc['name'] ) );
    }

    flush_rewrite_rules();
}

// Delete pages on deactivation (optional — leave pages by default)
// register_deactivation_hook( __FILE__, 'unirad_seo_delete_pages' );

// ── Custom Template ───────────────────────────────────────────────────────────

add_action( 'template_redirect', 'unirad_seo_template_redirect' );

function unirad_seo_template_redirect() {
    if ( ! is_singular( 'unirad_loc' ) ) return;

    $post_id = get_the_ID();
    $name      = get_post_meta( $post_id, '_unirad_loc_name',      true );
    $county    = get_post_meta( $post_id, '_unirad_loc_county',    true );
    $miles     = get_post_meta( $post_id, '_unirad_loc_miles',     true );
    $mins      = get_post_meta( $post_id, '_unirad_loc_mins',      true );
    $transport = get_post_meta( $post_id, '_unirad_loc_transport', true );
    $blurb     = get_post_meta( $post_id, '_unirad_loc_blurb',     true );
    $nearby    = get_post_meta( $post_id, '_unirad_loc_nearby',    true );

    $yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title',    true );
    $yoast_desc  = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
    $canon_url   = get_permalink( $post_id );

    $dist_text = $miles > 0 ? "{$miles} miles · {$mins} min drive" : "On-site — we\'re right here in Govan";

    // JSON-LD Schema
    $schema = wp_json_encode( [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type'       => 'MedicalClinic',
                '@id'         => 'https://unirad.co.uk/#clinic',
                'name'        => 'Unirad Diagnostic Imaging',
                'description' => 'Private MRI scanning centre in Glasgow',
                'url'         => 'https://unirad.co.uk',
                'telephone'   => '',
                'address'     => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '22 Loanbank Quadrant',
                    'addressLocality' => 'Glasgow',
                    'postalCode'      => 'G51 3HZ',
                    'addressCountry'  => 'GB',
                ],
                'geo' => [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => 55.858,
                    'longitude' => -4.312,
                ],
                'openingHoursSpecification' => [
                    [ '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'], 'opens' => '08:00', 'closes' => '18:00' ],
                ],
                'priceRange'    => '££',
                'areaServed'    => $name . ', ' . $county,
                'medicalSpecialty' => 'Radiology',
            ],
            [
                '@type'      => 'FAQPage',
                'mainEntity' => [
                    [ '@type' => 'Question', 'name' => 'Do I need to see my GP before booking an MRI scan?', 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Self-referral is available for selected scans — you do not need to visit your own GP first. Our clinical team assesses your suitability and advises on the most appropriate scan before confirming your booking.' ] ],
                    [ '@type' => 'Question', 'name' => 'How far is Unirad from ' . $name . '?', 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Unirad is approximately ' . $miles . ' miles from ' . $name . ', around ' . $mins . ' minutes by car. ' . $transport ] ],
                    [ '@type' => 'Question', 'name' => 'How quickly can I get an MRI?', 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Same-week appointments are usually available. Book online and you can often be seen within 2-3 days.' ] ],
                    [ '@type' => 'Question', 'name' => 'How long does it take to get my results?', 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Your written radiologist report is delivered within 5 working days. Urgent 24–48 hour turnaround is available on request.' ] ],
                    [ '@type' => 'Question', 'name' => 'How much does a private MRI scan cost?', 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => 'Scans start from £290, including the expert radiologist report. Specialist scans such as Cardiac (£575) and Prostate (£495) are priced higher.' ] ],
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

    // Other locations for internal linking
    $all_locs  = unirad_seo_locations();
    $link_html = '';
    $count     = 0;
    foreach ( $all_locs as $s => $l ) {
        if ( $s === get_post_field( 'post_name', $post_id ) ) continue;
        $url        = home_url( '/private-mri-scan/' . $s . '/' );
        $link_html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $l['name'] ) . '</a>';
        if ( ++$count >= 8 ) break;
    }

    $book_url = 'https://unirad.co.uk/book-your-scan/';

    ?><!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $yoast_title ?: 'Private MRI Scan in ' . $name . ' | Unirad Glasgow' ); ?></title>
<meta name="description" content="<?php echo esc_attr( $yoast_desc ); ?>">
<link rel="canonical" href="<?php echo esc_url( $canon_url ); ?>">
<script type="application/ld+json"><?php echo $schema; ?></script>
<?php wp_head(); ?>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:-apple-system,BlinkMacSystemFont,"DM Sans","Segoe UI",sans-serif;color:#1e1e1e;background:#fff;}
a{color:#00a896;text-decoration:none;}
a:hover{text-decoration:underline;}

/* Nav */
.un-nav{background:#08192a;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.un-logo{color:#fff;font-size:18px;font-weight:800;letter-spacing:-.3px;}
.un-logo span{color:#00a896;}
.un-nav-book{background:#00a896;color:#fff!important;padding:8px 18px;border-radius:20px;font-size:13px;font-weight:700;}
.un-nav-book:hover{background:#008a7d;text-decoration:none!important;}

/* Hero */
.un-hero{background:linear-gradient(135deg,#08192a 0%,#0f2c45 60%,#0d3340 100%);color:#fff;padding:60px 24px 52px;}
.un-hero-inner{max-width:860px;margin:0 auto;}
.un-breadcrumb{font-size:12px;color:rgba(255,255,255,.5);margin-bottom:14px;}
.un-breadcrumb a{color:rgba(255,255,255,.5);}
.un-hero h1{font-size:clamp(26px,4vw,42px);font-weight:800;line-height:1.15;margin-bottom:16px;}
.un-hero h1 em{color:#00a896;font-style:normal;}
.un-hero-sub{font-size:16px;color:rgba(255,255,255,.75);margin-bottom:28px;line-height:1.6;max-width:620px;}
.un-hero-pills{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:32px;}
.un-pill{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:5px 14px;border-radius:20px;font-size:12.5px;display:flex;align-items:center;gap:5px;}
.un-pill b{color:#00d4b8;}
.un-hero-cta{display:inline-block;background:#00a896;color:#fff!important;padding:14px 32px;border-radius:8px;font-size:16px;font-weight:700;box-shadow:0 4px 18px rgba(0,168,150,.35);transition:background .15s,transform .15s;}
.un-hero-cta:hover{background:#008a7d;transform:translateY(-1px);text-decoration:none!important;}
.un-hero-note{font-size:12px;color:rgba(255,255,255,.4);margin-top:10px;}

/* Stats bar */
.un-stats{background:#00a896;padding:14px 24px;display:flex;flex-wrap:wrap;justify-content:center;gap:24px;}
.un-stat{color:#fff;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;}
.un-stat b{font-size:18px;}

/* Section */
.un-section{padding:52px 24px;max-width:860px;margin:0 auto;}
.un-section-wide{padding:52px 24px;background:#f7fafc;}
.un-section-wide-inner{max-width:860px;margin:0 auto;}
.un-h2{font-size:26px;font-weight:800;color:#08192a;margin-bottom:8px;}
.un-lead{font-size:16px;color:#444;line-height:1.7;margin-bottom:24px;}

/* Distance card */
.un-dist{background:#08192a;color:#fff;border-radius:12px;padding:22px 24px;margin-bottom:30px;display:flex;gap:20px;align-items:flex-start;}
.un-dist-icon{font-size:28px;margin-top:2px;}
.un-dist h3{font-size:15px;font-weight:700;margin-bottom:6px;}
.un-dist p{font-size:13px;color:rgba(255,255,255,.7);line-height:1.6;}
.un-dist strong{color:#00d4b8;}

/* Scan grid */
.un-scans{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:30px;}
.un-scan-card{border:1px solid #e4edf2;border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;}
.un-scan-name{font-size:13px;font-weight:600;color:#08192a;}
.un-scan-price{font-size:15px;font-weight:800;color:#00a896;}

/* Benefits */
.un-benefits{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:8px;}
.un-benefit{display:flex;gap:12px;align-items:flex-start;}
.un-benefit-icon{width:36px;height:36px;background:rgba(0,168,150,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.un-benefit-text b{display:block;font-size:13px;font-weight:700;color:#08192a;}
.un-benefit-text span{font-size:12px;color:#666;}

/* FAQ */
.un-faq{border-top:1px solid #e8edf2;}
.un-faq-item{border-bottom:1px solid #e8edf2;padding:16px 0;}
.un-faq-q{font-size:14px;font-weight:700;color:#08192a;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:10px;}
.un-faq-q::after{content:'+';font-size:18px;color:#00a896;flex-shrink:0;}
.un-faq-item.open .un-faq-q::after{content:'−';}
.un-faq-a{display:none;font-size:13.5px;color:#444;line-height:1.7;padding-top:10px;}
.un-faq-item.open .un-faq-a{display:block;}

/* CTA Banner */
.un-cta-banner{background:linear-gradient(135deg,#00a896,#008a7d);padding:52px 24px;text-align:center;}
.un-cta-banner h2{font-size:28px;font-weight:800;color:#fff;margin-bottom:12px;}
.un-cta-banner p{color:rgba(255,255,255,.85);font-size:15px;margin-bottom:28px;}
.un-cta-banner a{display:inline-block;background:#fff;color:#00a896!important;padding:14px 36px;border-radius:8px;font-size:16px;font-weight:800;text-decoration:none!important;box-shadow:0 4px 18px rgba(0,0,0,.15);}
.un-cta-banner a:hover{transform:translateY(-1px);}
.un-cta-sub{font-size:12px;color:rgba(255,255,255,.6);margin-top:12px;}

/* Internal links */
.un-locations{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;}
.un-locations a{background:#f0f6f8;border:1px solid #d8e8ee;color:#08192a!important;padding:5px 12px;border-radius:14px;font-size:12px;font-weight:600;}
.un-locations a:hover{background:#e0f0f4;text-decoration:none!important;}

/* Footer */
.un-foot{background:#08192a;color:rgba(255,255,255,.5);font-size:12px;padding:20px 24px;text-align:center;}
.un-foot a{color:rgba(255,255,255,.5);}

@media(max-width:600px){
  .un-hero{padding:40px 18px 36px;}
  .un-section{padding:36px 18px;}
  .un-dist{flex-direction:column;gap:10px;}
}
</style>
</head>
<body>

<nav class="un-nav">
  <a href="https://unirad.co.uk" class="un-logo-link">
    <?php
    $logo_id  = get_theme_mod( 'custom_logo' );
    $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
    if ( $logo_url ) :
    ?>
      <img src="<?php echo esc_url( $logo_url ); ?>" alt="Unirad Diagnostic Imaging" style="height:36px;width:auto;display:block;">
    <?php else : ?>
      <span class="un-logo">Uni<span>rad</span></span>
    <?php endif; ?>
  </a>
  <a href="<?php echo esc_url( $book_url ); ?>" class="un-nav-book">Book a Scan</a>
</nav>

<section class="un-hero">
  <div class="un-hero-inner">
    <div class="un-breadcrumb"><a href="https://unirad.co.uk">Unirad</a> › <a href="https://unirad.co.uk">Private MRI Glasgow</a> › <?php echo esc_html( $name ); ?></div>
    <h1>Private MRI Scan<br>Serving <em><?php echo esc_html( $name ); ?></em></h1>
    <p class="un-hero-sub"><?php echo esc_html( $blurb ); ?> Book your scan for this week — self-referral available for selected scans, and your radiologist report is ready in 5 working days.</p>
    <div class="un-hero-pills">
      <span class="un-pill"><b>£290</b> from</span>
      <span class="un-pill">&#9733; 5-star rated</span>
      <span class="un-pill">&#10003; Self-Referral Available</span>
      <span class="un-pill">&#128338; Report in 5 days</span>
      <?php if ( $miles > 0 ) : ?>
      <span class="un-pill"><b><?php echo esc_html( $mins ); ?> min</b> from <?php echo esc_html( $name ); ?></span>
      <?php endif; ?>
    </div>
    <a href="<?php echo esc_url( $book_url ); ?>" class="un-hero-cta">Book Your Scan Online &rarr;</a>
    <p class="un-hero-note">Same-week availability &middot; Secure online payment &middot; Free parking</p>
  </div>
</section>

<div class="un-stats">
  <div class="un-stat"><b>175+</b> scans this month</div>
  <div class="un-stat"><b>98.9%</b> delivery rate</div>
  <div class="un-stat"><b>5</b> day report turnaround</div>
  <div class="un-stat"><b>&#10003;</b> Self-referral available</div>
</div>

<div class="un-section">

  <h2 class="un-h2">Getting Here from <?php echo esc_html( $name ); ?></h2>
  <p class="un-lead">Our clinic is at <strong>22 Loanbank Quadrant, Govan, Glasgow G51 3HZ</strong>.<?php echo $miles > 0 ? 'Approximately ' . esc_html( $dist_text ) . '.' : ''; ?></p>

  <div class="un-dist">
    <div class="un-dist-icon">&#128663;</div>
    <div>
      <h3>Travel from <?php echo esc_html( $name ); ?> &mdash; <strong><?php echo esc_html( $dist_text ); ?></strong></h3>
      <p><?php echo esc_html( $transport ); ?></p>
      <?php if ( $nearby ) : ?>
      <p style="margin-top:8px;">Also serving: <?php echo esc_html( $nearby ); ?>.</p>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="un-h2" style="margin-bottom:16px;">MRI Scans Available</h2>
  <p class="un-lead">All prices include your expert radiologist report. No hidden fees.</p>

  <div class="un-scans">
    <div class="un-scan-card"><span class="un-scan-name">Knee MRI Scan</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Brain MRI Scan</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Lumbar Spine MRI</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Cervical Spine MRI</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Shoulder MRI Scan</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Hip MRI Scan</span><span class="un-scan-price">£290</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Prostate MRI Scan</span><span class="un-scan-price">£495</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Breast MRI</span><span class="un-scan-price">£420</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Cardiac MRI Scan</span><span class="un-scan-price">£575</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Abdomen &amp; Pelvis</span><span class="un-scan-price">£455</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Full Body Silver</span><span class="un-scan-price">£590</span></div>
    <div class="un-scan-card"><span class="un-scan-name">Full Body Platinum</span><span class="un-scan-price">£1,660</span></div>
  </div>
  <p style="font-size:13px;color:#888;margin-bottom:40px;"><a href="<?php echo esc_url( $book_url ); ?>">View all 50+ scan types &rarr;</a></p>

  <h2 class="un-h2" style="margin-bottom:20px;">Why Patients from <?php echo esc_html( $name ); ?> Choose Unirad</h2>
  <div class="un-benefits" style="margin-bottom:40px;">
    <div class="un-benefit">
      <div class="un-benefit-icon">&#9889;</div>
      <div class="un-benefit-text"><b>Same-week appointments</b><span>No NHS waiting list — often available within 2–3 days</span></div>
    </div>
    <div class="un-benefit">
      <div class="un-benefit-icon">&#128196;</div>
      <div class="un-benefit-text"><b>Report included</b><span>Expert radiologist report delivered within 5 working days</span></div>
    </div>
    <div class="un-benefit">
      <div class="un-benefit-icon">&#128203;</div>
      <div class="un-benefit-text"><b>Self-referral available for selected scans</b><span>Our clinical team assesses your suitability before confirming your booking</span></div>
    </div>
    <div class="un-benefit">
      <div class="un-benefit-icon">&#128663;</div>
      <div class="un-benefit-text"><b>Free parking</b><span>On-site free parking at our Govan clinic</span></div>
    </div>
    <div class="un-benefit">
      <div class="un-benefit-icon">&#9995;</div>
      <div class="un-benefit-text"><b>Wide-bore scanner</b><span>More comfortable for claustrophobic patients</span></div>
    </div>
    <div class="un-benefit">
      <div class="un-benefit-icon">&#11088;</div>
      <div class="un-benefit-text"><b>5-star rated</b><span>Consistently excellent patient reviews on Google</span></div>
    </div>
  </div>

  <h2 class="un-h2" style="margin-bottom:20px;">What Patients Near <?php echo esc_html( $name ); ?> Say</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:40px;">
    <?php
    $testimonials = [
      [ 'q' => 'No GP letter needed — appointment same week. MRI was quick and my report arrived in 4 days. Excellent service.', 'a' => 'Sarah M.' ],
      [ 'q' => 'I was nervous about MRI but the team put me at ease immediately. The wide-bore scanner made a real difference. Would highly recommend.', 'a' => 'James K.' ],
      [ 'q' => 'Lumbar spine scan from £290 — half the price I was quoted elsewhere. Radiologist report was detailed and my physio was very impressed.', 'a' => 'C. Thomson' ],
    ];
    foreach ( $testimonials as $t ) :
    ?>
    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:18px 20px;">
      <div style="color:#f59e0b;font-size:14px;margin-bottom:8px;">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
      <p style="font-size:13px;color:#374151;line-height:1.65;margin:0 0 12px;">&ldquo;<?php echo esc_html( $t['q'] ); ?>&rdquo;</p>
      <p style="font-size:12px;color:#888;font-weight:600;margin:0;">— <?php echo esc_html( $t['a'] ); ?>, near <?php echo esc_html( $name ); ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 class="un-h2" style="margin-bottom:20px;">Frequently Asked Questions</h2>
  <div class="un-faq">
    <div class="un-faq-item">
      <div class="un-faq-q">Do I need to see my GP before booking an MRI scan?</div>
      <div class="un-faq-a">Self-referral is available for selected scans — you do not need to visit your own GP first. Our clinical team will assess your suitability and advise on the most appropriate scan before confirming your booking. This is one of the main reasons patients from <?php echo esc_html( $name ); ?> choose Unirad.</div>
    </div>
    <div class="un-faq-item">
      <div class="un-faq-q">How far is Unirad from <?php echo esc_html( $name ); ?>?</div>
      <div class="un-faq-a"><?php echo $miles > 0 ? esc_html( "Our clinic is approximately {$miles} miles from {$name} — around {$mins} minutes by car. " . $transport ) : esc_html( "We're right here in Govan. {$transport}" ); ?></div>
    </div>
    <div class="un-faq-item">
      <div class="un-faq-q">How quickly can I get an MRI appointment?</div>
      <div class="un-faq-a">Same-week appointments are usually available. Book online and you can often be seen within 2–3 days — far faster than NHS waiting times.</div>
    </div>
    <div class="un-faq-item">
      <div class="un-faq-q">When will I receive my results?</div>
      <div class="un-faq-a">Your written radiologist report is delivered within 5 working days. If you need urgent results, a 24–48 hour turnaround is available on request.</div>
    </div>
    <div class="un-faq-item">
      <div class="un-faq-q">What does a private MRI scan cost?</div>
      <div class="un-faq-a">Scans start from £290, which includes your expert radiologist report — no hidden fees. Specialist scans (Cardiac £575, Prostate £495, Breast £420) are priced higher. View all prices at unirad.co.uk.</div>
    </div>
    <div class="un-faq-item">
      <div class="un-faq-q">I'm claustrophobic — can I still have an MRI?</div>
      <div class="un-faq-a">Yes. We use a wide-bore scanner which has a much larger opening than standard MRI machines. Most claustrophobic patients find it comfortable. Mention this when booking so we can prepare.</div>
    </div>
  </div>

</div><!-- .un-section -->

<div class="un-section">
  <h2 class="un-h2" style="margin-bottom:16px;">How to Find Us from <?php echo esc_html( $name ); ?></h2>
  <p style="font-size:14px;color:#555;margin-bottom:16px;">Unirad Private MRI &middot; 22 Loanbank Quadrant, Govan, Glasgow G51 3HZ &middot; Free parking on site</p>
  <div style="border-radius:12px;overflow:hidden;border:1px solid #e0e0e0;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <iframe
      src="https://maps.google.com/maps?q=22+Loanbank+Quadrant,+Govan,+Glasgow+G51+3HZ&output=embed&z=15"
      width="100%"
      height="320"
      style="border:0;display:block;"
      allowfullscreen=""
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade"
      title="Unirad Private MRI — 22 Loanbank Quadrant, Glasgow G51 3HZ"
    ></iframe>
  </div>
</div>

<div class="un-cta-banner">
  <h2>Book Your MRI Scan Today</h2>
  <p>Serving <?php echo esc_html( $name ); ?> and surrounding areas &middot; Same-week availability &middot; From £290</p>
  <a href="<?php echo esc_url( $book_url ); ?>">Book Online Now &rarr;</a>
  <p class="un-cta-sub">Self-referral available for selected scans &middot; Radiologist report included &middot; Free parking</p>
</div>

<div class="un-section-wide">
  <div class="un-section-wide-inner">
    <h2 class="un-h2">Also Serving These Areas</h2>
    <p style="font-size:13px;color:#666;margin-bottom:14px;">Unirad serves patients from across Glasgow, Lanarkshire, Renfrewshire and Dunbartonshire.</p>
    <div class="un-locations"><?php echo $link_html; ?></div>
  </div>
</div>

<footer class="un-foot">
  <p>&copy; <?php echo gmdate('Y'); ?> Unirad Diagnostic Imaging &middot; 22 Loanbank Quadrant, Govan, Glasgow G51 3HZ &middot; <a href="https://unirad.co.uk">unirad.co.uk</a></p>
</footer>

<script>
document.querySelectorAll('.un-faq-q').forEach(function(q){
  q.addEventListener('click',function(){
    this.closest('.un-faq-item').classList.toggle('open');
  });
});
</script>
<?php wp_footer(); ?>
</body>
</html>
<?php
    exit;
}

// ── Admin Page (list all location pages) ─────────────────────────────────────

add_action( 'admin_menu', 'unirad_seo_admin_menu' );

function unirad_seo_admin_menu() {
    add_submenu_page(
        'unirad-email-dashboard',
        'Local SEO Pages',
        '&#127758; Local SEO',
        'manage_options',
        'unirad-local-seo',
        'unirad_seo_admin_page'
    );
}

function unirad_seo_admin_page() {
    $posts = get_posts( [ 'post_type' => 'unirad_loc', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
    $all   = unirad_seo_locations();
    ?>
    <div class="wrap">
    <h1>&#127758; Local SEO Pages <span style="font-size:13px;font-weight:normal;color:#666;">(<?php echo count( $posts ); ?> pages published)</span></h1>
    <p style="margin:10px 0 20px;color:#555;">Each page targets "<em>private MRI scan [location]</em>" searches. Pages are live at <code>/private-mri-scan/[slug]/</code>.</p>

    <?php if ( empty( $posts ) ) : ?>
      <div class="notice notice-warning"><p>No location pages found. <strong>Deactivate and reactivate this plugin</strong> to create all <?php echo count( $all ); ?> pages automatically.</p></div>
    <?php else : ?>
    <table class="widefat striped" style="max-width:900px;">
      <thead><tr><th>Location</th><th>County</th><th>Distance</th><th>URL</th><th>Edit</th></tr></thead>
      <tbody>
        <?php foreach ( $posts as $p ) :
          $slug  = $p->post_name;
          $loc   = $all[ $slug ] ?? [];
          $url   = get_permalink( $p->ID );
          $miles = get_post_meta( $p->ID, '_unirad_loc_miles', true );
          $mins  = get_post_meta( $p->ID, '_unirad_loc_mins',  true );
          $county = get_post_meta( $p->ID, '_unirad_loc_county', true );
        ?>
        <tr>
          <td><strong><?php echo esc_html( $p->post_title ); ?></strong></td>
          <td><?php echo esc_html( $county ); ?></td>
          <td><?php echo $miles > 0 ? esc_html( "{$miles} mi · {$mins} min" ) : 'On-site'; ?></td>
          <td><a href="<?php echo esc_url( $url ); ?>" target="_blank" style="font-size:11px;"><?php echo esc_html( str_replace( home_url(), '', $url ) ); ?> &#8599;</a></td>
          <td><a href="<?php echo get_edit_post_link( $p->ID ); ?>">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </div>
    <?php
}
