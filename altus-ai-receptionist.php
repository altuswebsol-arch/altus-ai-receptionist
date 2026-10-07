<?php
/**
 * Plugin Name: Altus AI Receptionist
 * Description: Smith.ai-style AI receptionist for small business websites. Chat widget, lead capture, owner alerts, credit ledger, full customisation.
 * Version: 0.2.5
 * Author: Altus Web Solutions
 * Text Domain: altus-ai
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ALTUS_AI_VER', '0.2.5' );
define( 'ALTUS_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALTUS_AI_URL', plugin_dir_url( __FILE__ ) );

require_once ALTUS_AI_DIR . 'includes/brain.php';

/* ---------- Activation ---------- */
function altus_ai_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();
    $p = $wpdb->prefix . 'altus_ai_';
    dbDelta( "CREATE TABLE {$p}conversations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id VARCHAR(64) NOT NULL, started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY session_id (session_id)) $c;" );
    dbDelta( "CREATE TABLE {$p}messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, convo_id BIGINT UNSIGNED NOT NULL, role VARCHAR(10) NOT NULL, message TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY convo_id (convo_id)) $c;" );
    dbDelta( "CREATE TABLE {$p}leads (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL DEFAULT '', contact VARCHAR(160) NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) $c;" );
    dbDelta( "CREATE TABLE {$p}ledger (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, kind VARCHAR(20) NOT NULL, amount INT NOT NULL, balance_after INT NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) $c;" );
    if ( get_option( 'altus_ai_credits', null ) === null ) {
        update_option( 'altus_ai_credits', 100 );
        $wpdb->insert( $p . 'ledger', array( 'kind' => 'grant', 'amount' => 100, 'balance_after' => 100, 'note' => 'Starter demo credits' ) );
    }
    if ( get_option( 'altus_ai_settings', null ) === null ) {
        update_option( 'altus_ai_settings', altus_ai_default_settings() );
    }
}
register_activation_hook( __FILE__, 'altus_ai_activate' );

function altus_ai_default_settings() {
    return array(
        'business_name'   => 'Altus Web Solutions',
        'owner_email'     => 'support@altuswebsolutions.com',
        'enabled'         => 1,
        'welcome'         => 'Hi there! I\'m the Altus AI receptionist. I can answer questions about our services and pricing, or arrange a callback — what can I do for you?',
        'faqs'            => '',
        // Customisation (v0.2.0)
        'primary_color'   => '#2563eb',
        'accent_color'    => '#38bdf8',
        'logo_url'        => '',
        'position'        => 'right',
        'tone'            => 'friendly',
        'hours_days'      => '1,2,3,4,5',
        'hours_start'     => '09:00',
        'hours_end'       => '18:00',
        'after_hours_msg' => 'We\'re closed right now, but leave your name and number and we\'ll call you back first thing.',
        'knowledge'       => '',
    );
}
function altus_ai_settings() {
    return wp_parse_args( (array) get_option( 'altus_ai_settings', array() ), altus_ai_default_settings() );
}

/* Optimisation: defer the widget script so it never blocks page rendering */
add_filter( 'script_loader_tag', function( $tag, $handle ) {
    if ( $handle === 'altus-ai-widget' && strpos( $tag, 'defer' ) === false ) {
        $tag = str_replace( ' src', ' defer src', $tag );
    }
    return $tag;
}, 10, 2 );

/* ---------- Admin ---------- */
add_action( 'admin_menu', function() {
    add_menu_page( 'Altus AI', 'Altus AI', 'manage_options', 'altus-ai', 'altus_ai_admin_page', 'dashicons-format-chat', 30 );
});
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'altus-ai' ) === false ) return;
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_media();
    wp_enqueue_script( 'wp-color-picker' );
});

function altus_ai_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $p = $wpdb->prefix . 'altus_ai_';
    $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'chats';

    if ( isset( $_POST['altus_ai_save'] ) && check_admin_referer( 'altus_ai_settings' ) ) {
        $s = altus_ai_settings();
        foreach ( array( 'business_name', 'owner_email', 'welcome', 'faqs', 'after_hours_msg', 'knowledge' ) as $k ) {
            $raw = wp_unslash( $_POST[ $k ] ?? '' );
            $s[ $k ] = ( $k === 'owner_email' ) ? sanitize_email( $raw ) : sanitize_textarea_field( $raw );
        }
        $s['business_name'] = sanitize_text_field( $s['business_name'] );
        // The Customise tab has no "enabled" checkbox — don't let saving it switch the widget off
        if ( isset( $_POST['enabled'] ) ) { $s['enabled'] = 1; }
        elseif ( ! isset( $_POST['keep_enabled'] ) ) { $s['enabled'] = 0; }
        $s['primary_color'] = sanitize_hex_color( $_POST['primary_color'] ?? '' ) ?: '#2563eb';
        $s['accent_color']  = sanitize_hex_color( $_POST['accent_color'] ?? '' ) ?: '#38bdf8';
        $s['logo_url']      = esc_url_raw( $_POST['logo_url'] ?? '' );
        $s['position']      = in_array( $_POST['position'] ?? '', array( 'right', 'left' ), true ) ? $_POST['position'] : 'right';
        $s['tone']          = in_array( $_POST['tone'] ?? '', array( 'friendly', 'professional' ), true ) ? $_POST['tone'] : 'friendly';
        $days = array_filter( array_map( 'intval', (array) ( $_POST['hours_days'] ?? array() ) ) );
        $s['hours_days']    = $days ? implode( ',', $days ) : '1,2,3,4,5';
        $s['hours_start']   = preg_match( '/^\d{2}:\d{2}$/', $_POST['hours_start'] ?? '' ) ? $_POST['hours_start'] : '09:00';
        $s['hours_end']     = preg_match( '/^\d{2}:\d{2}$/', $_POST['hours_end'] ?? '' ) ? $_POST['hours_end'] : '18:00';
        update_option( 'altus_ai_settings', $s );
        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }
    $s = altus_ai_settings();
    $credits = (int) get_option( 'altus_ai_credits', 0 );
    $leads_n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}leads" );
    $chats_n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}conversations" );

    echo '<div class="wrap"><h1>Altus AI Receptionist</h1>';
    echo '<p><strong>' . esc_html( $credits ) . '</strong> credits left &nbsp;|&nbsp; <strong>' . esc_html( $chats_n ) . '</strong> conversations &nbsp;|&nbsp; <strong>' . esc_html( $leads_n ) . '</strong> leads captured</p>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ( array( 'chats' => 'Conversations', 'leads' => 'Leads', 'credits' => 'Credits', 'customize' => 'Customise', 'settings' => 'Settings' ) as $k => $label ) {
        echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=altus-ai&tab=' . $k ) ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</h2>';

    if ( $tab === 'chats' ) {
        $convos = $wpdb->get_results( "SELECT * FROM {$p}conversations ORDER BY started_at DESC LIMIT 50" );
        if ( ! $convos ) { echo '<p>No conversations yet.</p>'; }
        foreach ( $convos as $cv ) {
            $msgs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$p}messages WHERE convo_id = %d ORDER BY id ASC", $cv->id ) );
            echo '<div style="border:1px solid #ccd0d4;background:#fff;padding:12px;margin:12px 0;max-width:760px;border-radius:8px;">';
            echo '<strong>Chat #' . (int) $cv->id . '</strong> <span style="color:#666">' . esc_html( $cv->started_at ) . '</span>';
            foreach ( $msgs as $m ) {
                $who = $m->role === 'bot' ? 'Altus AI' : 'Visitor';
                echo '<p style="margin:6px 0"><strong>' . esc_html( $who ) . ':</strong> ' . esc_html( $m->message ) . '</p>';
            }
            echo '</div>';
        }
    } elseif ( $tab === 'leads' ) {
        $leads = $wpdb->get_results( "SELECT * FROM {$p}leads ORDER BY created_at DESC LIMIT 100" );
        echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Name</th><th>Contact</th><th>Notes</th><th>When</th></tr></thead><tbody>';
        if ( ! $leads ) echo '<tr><td colspan="4">No leads yet.</td></tr>';
        foreach ( $leads as $l ) {
            echo '<tr><td>' . esc_html( $l->name ) . '</td><td>' . esc_html( $l->contact ) . '</td><td>' . esc_html( $l->notes ) . '</td><td>' . esc_html( $l->created_at ) . '</td></tr>';
        }
        echo '</tbody></table>';
    } elseif ( $tab === 'credits' ) {
        $rows = $wpdb->get_results( "SELECT * FROM {$p}ledger ORDER BY id DESC LIMIT 100" );
        echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>When</th><th>Type</th><th>Amount</th><th>Balance</th><th>Note</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td>' . esc_html( $r->created_at ) . '</td><td>' . esc_html( $r->kind ) . '</td><td>' . esc_html( $r->amount ) . '</td><td>' . esc_html( $r->balance_after ) . '</td><td>' . esc_html( $r->note ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><em>Demo build: each AI reply costs 1 credit. Stripe billing plugs into this ledger next.</em></p>';
    } elseif ( $tab === 'customize' ) {
        echo '<form method="post" style="max-width:760px" id="altus-ai-customize">';
        wp_nonce_field( 'altus_ai_settings' );
        echo '<input type="hidden" name="keep_enabled" value="1">';
        echo '<h3>Brand</h3><table class="form-table">';
        echo '<tr><th>Primary colour</th><td><input type="text" name="primary_color" value="' . esc_attr( $s['primary_color'] ) . '" class="altus-ai-color"></td></tr>';
        echo '<tr><th>Accent colour</th><td><input type="text" name="accent_color" value="' . esc_attr( $s['accent_color'] ) . '" class="altus-ai-color"></td></tr>';
        echo '<tr><th>Logo</th><td><input type="text" name="logo_url" id="altus-ai-logo" value="' . esc_attr( $s['logo_url'] ) . '" class="regular-text" placeholder="https://…"> <button type="button" class="button" id="altus-ai-logo-btn">Choose image</button><p class="description">Shown on the chat button and header. Leave empty for the glowing orb.</p></td></tr>';
        echo '<tr><th>Widget position</th><td><select name="position"><option value="right"' . selected( 'right', $s['position'], false ) . '>Bottom right</option><option value="left"' . selected( 'left', $s['position'], false ) . '>Bottom left</option></select></td></tr>';
        echo '</table><h3>Personality</h3><table class="form-table">';
        echo '<tr><th>Tone of voice</th><td><select name="tone"><option value="friendly"' . selected( 'friendly', $s['tone'], false ) . '>Friendly</option><option value="professional"' . selected( 'professional', $s['tone'], false ) . '>Professional</option></select></td></tr>';
        echo '</table><h3>Working hours</h3><table class="form-table">';
        $daynames = array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' );
        $on = array_map( 'intval', explode( ',', $s['hours_days'] ) );
        echo '<tr><th>Open days</th><td>';
        foreach ( $daynames as $n => $d ) { echo '<label style="margin-right:10px"><input type="checkbox" name="hours_days[]" value="' . $n . '"' . checked( in_array( $n, $on, true ), true, false ) . '> ' . $d . '</label>'; }
        echo '</td></tr>';
        echo '<tr><th>Open hours</th><td><input type="time" name="hours_start" value="' . esc_attr( $s['hours_start'] ) . '"> to <input type="time" name="hours_end" value="' . esc_attr( $s['hours_end'] ) . '"></td></tr>';
        echo '<tr><th>After-hours message</th><td><textarea name="after_hours_msg" rows="2" class="large-text">' . esc_textarea( $s['after_hours_msg'] ) . '</textarea></td></tr>';
        echo '</table><h3>Knowledge</h3>';
        echo '<p><button type="button" class="button" id="altus-ai-scan">Scan my website</button> <span id="altus-ai-scan-msg" style="color:#666"></span></p>';
        echo '<p class="description">Pulls in your page titles and summaries so the AI can answer from your own content. You can also paste extra facts below.</p>';
        echo '<textarea name="knowledge" rows="10" class="large-text" placeholder="Paste facts about your business here, one per line…">' . esc_textarea( $s['knowledge'] ) . '</textarea>';
        echo '<p><input type="submit" name="altus_ai_save" class="button button-primary" value="Save customisation"></p></form>';
        ?>
        <script>
        jQuery(function($){
            $('.altus-ai-color').wpColorPicker();
            var frame;
            $('#altus-ai-logo-btn').on('click', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Choose logo', multiple: false, library: { type: 'image' } });
                frame.on('select', function(){ $('#altus-ai-logo').val(frame.state().get('selection').first().toJSON().url); });
                frame.open();
            });
            $('#altus-ai-scan').on('click', function(){
                var btn = $(this); btn.prop('disabled', true);
                $('#altus-ai-scan-msg').text('Scanning…');
                $.post(ajaxurl, { action: 'altus_ai_scan' }, function(r){
                    btn.prop('disabled', false);
                    if (r.success) {
                        $('#altus-ai-scan-msg').text('Found ' + r.data.pages + ' pages — saved. Hit "Save customisation".');
                        $('textarea[name=knowledge]').val(r.data.knowledge);
                    } else { $('#altus-ai-scan-msg').text('Scan failed — try again.'); }
                });
            });
        });
        </script>
        <?php
    } else {
        echo '<form method="post" style="max-width:760px">';
        wp_nonce_field( 'altus_ai_settings' );
        // keep customise values on plain settings save
        foreach ( array( 'primary_color','accent_color','logo_url','position','tone','hours_start','hours_end','after_hours_msg','knowledge' ) as $k ) {
            echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[$k] ) . '">';
        }
        foreach ( array_map( 'intval', explode( ',', $s['hours_days'] ) ) as $d ) {
            echo '<input type="hidden" name="hours_days[]" value="' . (int) $d . '">';
        }
        echo '<table class="form-table">';
        echo '<tr><th>Business name</th><td><input type="text" name="business_name" value="' . esc_attr( $s['business_name'] ) . '" class="regular-text"></td></tr>';
        echo '<tr><th>Owner email (lead alerts)</th><td><input type="email" name="owner_email" value="' . esc_attr( $s['owner_email'] ) . '" class="regular-text"></td></tr>';
        echo '<tr><th>Widget enabled</th><td><input type="checkbox" name="enabled" value="1"' . checked( 1, $s['enabled'], false ) . '> Show the chat widget on the site</td></tr>';
        echo '<tr><th>Welcome message</th><td><textarea name="welcome" rows="3" class="large-text">' . esc_textarea( $s['welcome'] ) . '</textarea></td></tr>';
        echo '<tr><th>Custom FAQs</th><td><textarea name="faqs" rows="8" class="large-text" placeholder="One per line: question | answer">' . esc_textarea( $s['faqs'] ) . '</textarea><p class="description">Format: <code>Do you offer VAT receipts? | Yes, every invoice includes VAT.</code></p></td></tr>';
        echo '</table><p><input type="submit" name="altus_ai_save" class="button button-primary" value="Save settings"></p></form>';
        echo '<h3>Embed on any website</h3><p>Paste this one line into any non-WordPress site to load the same receptionist:</p>';
        echo '<code style="display:block;padding:10px;background:#fff;border:1px solid #ccd0d4;max-width:760px">&lt;script src="' . esc_url( home_url( '/?altus_ai_embed=1' ) ) . '" defer&gt;&lt;/script&gt;</code>';
    }
    echo '</div>';
}

/* Website scan: train the AI on the site's own pages (fast local query, cached input) */
add_action( 'wp_ajax_altus_ai_scan', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'forbidden' );
    $q = new WP_Query( array( 'post_type' => 'page', 'posts_per_page' => 15, 'orderby' => 'menu_order', 'order' => 'ASC', 'post_status' => 'publish' ) );
    $lines = array();
    $lines[] = 'Business: ' . get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
    foreach ( $q->posts as $post ) {
        $excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
        if ( trim( $excerpt ) !== '' ) $lines[] = $post->post_title . ': ' . $excerpt;
    }
    $knowledge = implode( "\n", $lines );
    $s = altus_ai_settings();
    $s['knowledge'] = $knowledge;
    update_option( 'altus_ai_settings', $s );
    wp_send_json_success( array( 'pages' => count( $q->posts ), 'knowledge' => $knowledge ) );
});

/* ---------- Frontend (optimised: vanilla JS, deferred, single small CSS/JS) ---------- */
add_action( 'wp_enqueue_scripts', function() {
    $s = altus_ai_settings();
    if ( empty( $s['enabled'] ) ) return;
    wp_enqueue_style( 'altus-ai-widget', ALTUS_AI_URL . 'assets/altus-ai-widget.css', array(), ALTUS_AI_VER );
    wp_enqueue_script( 'altus-ai-widget', ALTUS_AI_URL . 'assets/altus-ai-widget.js', array(), ALTUS_AI_VER, true );
    wp_localize_script( 'altus-ai-widget', 'AltusAI', array(
        'ajax'    => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'altus_ai_chat' ),
        'welcome' => $s['welcome'],
        'biz'     => $s['business_name'],
        'credits' => (int) get_option( 'altus_ai_credits', 0 ),
    ) );
});

add_shortcode( 'altus_ai_chat', function() {
    return '<div class="altus-ai-shortcode-slot"></div>';
});

add_action( 'wp_footer', function() {
    $s = altus_ai_settings();
    if ( empty( $s['enabled'] ) ) return;
    $pos_class = $s['position'] === 'left' ? ' pos-left' : '';
    $logo = esc_url( $s['logo_url'] );
    ?>
    <style>
    #altus-ai-root{ --aai-p: <?php echo esc_html( $s['primary_color'] ); ?>; --aai-a: <?php echo esc_html( $s['accent_color'] ); ?>; }
    #altus-ai-root.pos-left{ left: 22px; right: auto; }
    #altus-ai-root.pos-left #altus-ai-panel{ left: 0; right: auto; }
    </style>
    <div id="altus-ai-root" class="<?php echo esc_attr( trim( $pos_class ) ); ?>">
        <button id="altus-ai-launcher" aria-label="Chat with <?php echo esc_attr( $s['business_name'] ); ?> AI">
            <?php if ( $logo ) : ?>
                <img src="<?php echo $logo; ?>" alt="" class="altus-ai-logo">
            <?php else : ?>
                <span class="altus-ai-orb"></span>
            <?php endif; ?>
            <span class="altus-ai-ping"></span>
        </button>
        <div id="altus-ai-panel" hidden>
            <div class="altus-ai-head">
                <?php if ( $logo ) : ?>
                    <img src="<?php echo $logo; ?>" alt="" class="altus-ai-orb-sm altus-ai-logo-sm">
                <?php else : ?>
                    <span class="altus-ai-orb altus-ai-orb-sm"></span>
                <?php endif; ?>
                <div class="altus-ai-head-text">
                    <strong><?php echo esc_html( $s['business_name'] ); ?> AI</strong>
                    <span class="altus-ai-status"><i></i>Online — replies instantly</span>
                </div>
                <span class="altus-ai-credits" title="Demo credits remaining"><b>⚡</b><em id="altus-ai-credit-n"><?php echo (int) get_option( 'altus_ai_credits', 0 ); ?></em></span>
                <button id="altus-ai-close" aria-label="Close chat">×</button>
            </div>
            <div id="altus-ai-msgs"></div>
            <div class="altus-ai-chips">
                <button data-q="What services do you offer?">Services</button>
                <button data-q="How much does it cost?">Pricing</button>
                <button data-q="Can someone call me back?">Call me back</button>
            </div>
            <form id="altus-ai-form">
                <input id="altus-ai-input" type="text" placeholder="Ask anything…" autocomplete="off" maxlength="500">
                <button type="submit" aria-label="Send">➤</button>
            </form>
            <div class="altus-ai-foot">Powered by <strong>Altus AI</strong></div>
        </div>
    </div>
    <?php
});

// Universal embed endpoint for non-WordPress sites
add_action( 'init', function() {
    if ( ! isset( $_GET['altus_ai_embed'] ) ) return;
    header( 'Content-Type: application/javascript; charset=utf-8' );
    echo '(function(){var s=document.createElement("script");s.src=' . wp_json_encode( ALTUS_AI_URL . 'assets/altus-ai-widget.js' ) . ';s.defer=true;document.head.appendChild(s);'
        . 'var l=document.createElement("link");l.rel="stylesheet";l.href=' . wp_json_encode( ALTUS_AI_URL . 'assets/altus-ai-widget.css' ) . ';document.head.appendChild(l);'
        . 'window.addEventListener("load",function(){if(window.AltusAIInit)window.AltusAIInit();});})();';
    exit;
});

/* ---------- AJAX: chat ---------- */
add_action( 'wp_ajax_altus_ai_chat', 'altus_ai_ajax_chat' );
add_action( 'wp_ajax_nopriv_altus_ai_chat', 'altus_ai_ajax_chat' );

function altus_ai_ajax_chat() {
    check_ajax_referer( 'altus_ai_chat', 'nonce' );
    global $wpdb;
    $p = $wpdb->prefix . 'altus_ai_';
    $text = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
    $session = sanitize_key( $_POST['session'] ?? '' );
    if ( $text === '' || $session === '' ) wp_send_json_error( 'empty' );

    if ( ! altus_ai_spend_credit( 'AI reply' ) ) {
        wp_send_json_success( array( 'reply' => 'We\'ve run out of demo credits for now — please try again later or contact us directly.', 'credits' => 0 ) );
    }

    $convo = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}conversations WHERE session_id = %s", $session ) );
    if ( ! $convo ) {
        $wpdb->insert( $p . 'conversations', array( 'session_id' => $session ) );
        $convo = $wpdb->insert_id;
    }
    $wpdb->insert( $p . 'messages', array( 'convo_id' => $convo, 'role' => 'user', 'message' => $text ) );

    $s = altus_ai_settings();
    $res = altus_ai_brain_reply( $text, $s );

    // After-hours: still answer the question first, then add the owner's
    // after-hours note (explicit callback requests get just the note)
    if ( ! empty( $res['handoff'] ) && ! altus_ai_in_hours( $s ) && ! empty( $s['after_hours_msg'] ) ) {
        if ( ( $res['intent'] ?? '' ) === 'handoff' ) {
            $res['reply'] = $s['after_hours_msg'];
        } else {
            $res['reply'] .= ' ' . $s['after_hours_msg'];
        }
        $res['capture'] = true;
    }

    $wpdb->insert( $p . 'messages', array( 'convo_id' => $convo, 'role' => 'bot', 'message' => $res['reply'] ) );

    wp_send_json_success( array(
        'reply'   => $res['reply'],
        'handoff' => ! empty( $res['handoff'] ),
        'capture' => ! empty( $res['capture'] ),
        'credits' => (int) get_option( 'altus_ai_credits', 0 ),
    ) );
}

/* ---------- AJAX: lead capture ---------- */
add_action( 'wp_ajax_altus_ai_lead', 'altus_ai_ajax_lead' );
add_action( 'wp_ajax_nopriv_altus_ai_lead', 'altus_ai_ajax_lead' );

function altus_ai_ajax_lead() {
    check_ajax_referer( 'altus_ai_chat', 'nonce' );
    global $wpdb;
    $p = $wpdb->prefix . 'altus_ai_';
    $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $contact = sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) );
    $notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
    if ( $name === '' || $contact === '' ) wp_send_json_error( 'missing' );

    $wpdb->insert( $p . 'leads', array( 'name' => $name, 'contact' => $contact, 'notes' => $notes ) );
    $s = altus_ai_settings();
    wp_mail(
        $s['owner_email'],
        'New Altus AI lead: ' . $name,
        "Name: $name\nContact: $contact\nNotes: $notes\n\nCaptured by Altus AI Receptionist on " . home_url(),
        array( 'From: Altus AI <wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>' )
    );
    wp_send_json_success( array( 'ok' => true ) );
}
