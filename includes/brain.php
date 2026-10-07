<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Altus AI brain v0.2.4 — natural, conversational, sales-oriented replies.
 * Covers the full Altus offering: custom web development (React, .NET, Ruby on
 * Rails, JavaScript), AI-powered builds, e-commerce, mobile apps, open-source
 * CMS, school websites (one specialism among many), maintenance, hosting, SEO,
 * digital marketing. Demo build: no external API calls.
 */

function altus_ai_in_hours( $s ) {
    $tz  = wp_timezone();
    $now = new DateTime( 'now', $tz );
    $days = array_map( 'intval', explode( ',', $s['hours_days'] ) );
    if ( ! in_array( (int) $now->format( 'N' ), $days, true ) ) return false;
    $hm = $now->format( 'H:i' );
    return $hm >= $s['hours_start'] && $hm < $s['hours_end'];
}

/* Score a chunk of knowledge against the visitor's words */
function altus_ai_knowledge_search( $t, $knowledge ) {
    $best = array( 'score' => 0, 'text' => '' );
    foreach ( preg_split( '/\r?\n/', $knowledge ) as $line ) {
        $line = trim( $line );
        if ( strlen( $line ) < 10 ) continue;
        $score = 0;
        foreach ( preg_split( '/\s+/', strtolower( $line ) ) as $w ) {
            $w = trim( $w, ".,:;!?()\"'" );
            if ( strlen( $w ) > 3 && strpos( $t, $w ) !== false ) $score++;
        }
        if ( $score > $best['score'] ) $best = array( 'score' => $score, 'text' => $line );
    }
    return $best['score'] >= 2 ? $best['text'] : '';
}

function altus_ai_brain_reply( $text, $settings ) {
    $t = strtolower( trim( $text ) );
    $biz = $settings['business_name'];
    $pro = ( $settings['tone'] === 'professional' );

    // 1) Owner's custom FAQs win first: "question | answer" per line
    if ( ! empty( $settings['faqs'] ) ) {
        foreach ( preg_split( '/\r?\n/', $settings['faqs'] ) as $line ) {
            if ( strpos( $line, '|' ) === false ) continue;
            list( $q, $a ) = array_map( 'trim', explode( '|', $line, 2 ) );
            if ( $q === '' || $a === '' ) continue;
            $qw = preg_split( '/\s+/', strtolower( $q ) );
            $hits = 0;
            foreach ( $qw as $w ) { if ( strlen( $w ) > 3 && strpos( $t, $w ) !== false ) $hits++; }
            if ( $hits >= min( 2, count( $qw ) ) ) return array( 'reply' => $a, 'intent' => 'faq' );
        }
    }

    $has = function( $words ) use ( $t ) {
        foreach ( (array) $words as $w ) { if ( strpos( $t, $w ) !== false ) return true; }
        return false;
    };

    // 2) Built-in intents — specific answers first, every reply steers toward a sale
    if ( $has( array( 'hello', 'hi', 'hey', 'salam', 'aoa', 'good morning', 'good afternoon' ) ) && strlen( $t ) < 30 ) {
        return array( 'reply' => $settings['welcome'], 'intent' => 'greeting' );
    }
    if ( $has( array( 'react', '.net', 'dotnet', 'ruby', 'rails', 'wordpress', 'technology', 'technologies', 'tech stack', 'stack', 'cms', 'platform' ) ) ) {
        $r = $pro
            ? 'Our core stack is React, Ruby on Rails, .NET and modern JavaScript, alongside WordPress and open-source CMS where that fits better. We choose the technology around your project, not the other way round. Shall I have someone talk through what would suit yours?'
            : 'Good question! We mainly build with React, Ruby on Rails, .NET and modern JavaScript — plus WordPress and open-source CMS when that\'s the better fit. We pick the tech around your project, not the other way round. Want someone to talk through what\'d suit yours?';
        return array( 'reply' => $r, 'intent' => 'technology', 'handoff' => true );
    }
    if ( $has( array( 'ecommerce', 'e-commerce', 'online shop', 'online store', 'sell online', 'selling online' ) ) ) {
        $r = $pro
            ? 'Yes — we engineer custom e-commerce platforms built for speed and designed to convert visitors into customers. Shall I arrange a consultation on what your store would need?'
            : 'Yes! We build custom online shops — fast, secure, and designed to turn visitors into buyers. Want someone to talk through what your shop would need?';
        return array( 'reply' => $r, 'intent' => 'ecommerce', 'handoff' => true );
    }
    if ( $has( array( 'mobile app', 'mobile apps', 'ios', 'android', 'native app' ) ) ) {
        $r = $pro
            ? 'Yes — from high-performance native iOS apps to robust cross-platform builds. Tell me what you have in mind and I\'ll arrange proper guidance.'
            : 'Yep, we do apps too — from native iPhone apps to cross-platform builds that work everywhere. What\'ve you got in mind?';
        return array( 'reply' => $r, 'intent' => 'mobile', 'handoff' => true );
    }
    if ( $has( array( 'artificial intelligence', 'machine learning', ' ai ', ' ai-powered', 'ai powered', 'automation', 'automate', 'erp', 'ai assistant' ) ) || $t === 'ai' ) {
        $r = $pro
            ? 'We build AI into websites and business systems — AI assistants, automated workflows, even machine learning models. You\'re speaking to one of our AI receptionists right now. Shall I arrange a demonstration of what AI could do for your business?'
            : 'We\'re big on AI — we build it into websites and business systems: smart assistants, automated workflows, even machine learning. And ha — you\'re talking to one of our AI receptionists right now! Want someone to show you what AI could do for your business?';
        return array( 'reply' => $r, 'intent' => 'ai', 'handoff' => true );
    }
    if ( $has( array( 'school', 'ofsted', 'academy', 'academies' ) ) ) {
        $r = $pro
            ? 'Yes — school websites and multi-academy trust platforms are one of our specialisms, built to cover everything Ofsted and the DfE expect. We also offer a free tool that scores any UK school website. Would you like the link, or prefer a call about your project?'
            : 'Yes, we do — schools and academy trusts are one of our specialisms, though we build all sorts! Our school sites cover everything Ofsted and the DfE look for, and we\'ve got a free tool that scores any UK school website. Want the link, or would you rather someone gave you a call?';
        return array( 'reply' => $r, 'intent' => 'school', 'handoff' => true );
    }
    if ( $has( array( 'maintenance', 'maintain', 'care plan', 'support plan', 'keep my site updated' ) ) ) {
        $r = $pro
            ? 'Yes — our maintenance and support plans cover updates, security, backups and small changes, handled for you. Shall I send you the details?'
            : 'Yep! Our maintenance plans cover all the boring-but-important stuff — updates, security, backups, little changes — handled for you. Want the details?';
        return array( 'reply' => $r, 'intent' => 'maintenance', 'handoff' => true );
    }
    if ( $has( array( 'hosting', 'web host', 'host my', 'hosting cost' ) ) ) {
        $r = $pro
            ? 'Yes — we provide managed hosting alongside our builds, so your site stays fast and secure. Shall I have someone sort out the right setup for you?'
            : 'Yep, we do hosting too — managed, so your site stays fast and secure without you lifting a finger. Want someone to sort the right setup for you?';
        return array( 'reply' => $r, 'intent' => 'hosting', 'handoff' => true );
    }
    if ( $has( array( 'marketing', 'ads', 'advertising', 'social media' ) ) ) {
        $r = $pro
            ? 'Yes — alongside websites we offer digital marketing: SEO, content and campaigns that bring in enquiries. Shall I arrange a free review of your current marketing?'
            : 'Yep! Alongside websites we do digital marketing — SEO, content, the works — all aimed at bringing you enquiries. Fancy a free review of where you stand right now?';
        return array( 'reply' => $r, 'intent' => 'marketing', 'handoff' => true );
    }
    if ( $has( array( 'seo', 'google', 'rank', 'traffic' ) ) ) {
        $r = $pro
            ? 'We handle SEO properly — solid on-page work and regular content, no tricks. And we\'ll never promise a #1 ranking, because no honest company can. Would you like a free review of your site?'
            : 'We do SEO the proper way — good on-page work and regular content, no tricks. One thing we\'ll never do is promise you the #1 spot, because nobody honest can! Want us to take a free look at your site?';
        return array( 'reply' => $r, 'intent' => 'seo', 'handoff' => true );
    }
    if ( $has( array( 'service', 'what do you do', 'what can you', 'website', 'web design', 'web development' ) ) ) {
        $r = $pro
            ? 'We\'re a full-service web agency: custom website development (React, .NET, Ruby on Rails), AI-powered builds, e-commerce, mobile apps and open-source CMS — plus school websites, maintenance, hosting, SEO and digital marketing. Which of those are you interested in?'
            : 'We do a bit of everything, honestly — custom websites (React, .NET, Ruby on Rails), AI-powered builds, online shops, mobile apps, plus school websites, maintenance, hosting, SEO and marketing. What are you looking for?';
        return array( 'reply' => $r, 'intent' => 'services' );
    }
    if ( $has( array( 'price', 'pricing', 'cost', 'how much', 'quote', 'charge', 'fee' ) ) ) {
        $r = $pro
            ? 'We don\'t sell fixed packages — every project gets a firm fixed price once we understand what you need. Most small business sites sit somewhere between a simple starter site and a full online shop. Shall I arrange a proper quote for you?'
            : 'Good question! We don\'t do one-size-fits-all pricing — every project gets a firm fixed price once we know what you need. Most small business sites fall somewhere between a simple starter site and a full online shop. Want someone to put together a proper quote for you?';
        return array( 'reply' => $r, 'intent' => 'pricing', 'handoff' => true );
    }
    if ( $has( array( 'how long', 'timeline', 'take', 'fast', 'deadline', 'long does' ) ) ) {
        $r = $pro
            ? 'It really depends on the size of the project — a straightforward site moves much faster than a large e-commerce build. If you tell me what you have in mind, I can get you an accurate timeline.'
            : 'Honestly it depends on the project — a simple site is much quicker than a big online shop. Tell me what you\'re after and I\'ll get you a proper timeline, no guessing.';
        return array( 'reply' => $r, 'intent' => 'timeline', 'handoff' => true );
    }
    if ( $has( array( 'book', 'call', 'speak', 'human', 'someone', 'person', 'contact', 'phone', 'email', 'callback' ) ) ) {
        $ask = $pro
            ? 'Certainly. Please share your name and the best number or email to reach you, and someone from ' . $biz . ' will be in touch.'
            : 'Of course! Just leave your name and the best number or email to reach you, and someone from the team will get back to you. What\'s your name?';
        return array( 'reply' => $ask, 'intent' => 'handoff', 'handoff' => true, 'capture' => true );
    }
    if ( $has( array( 'thank', 'thanks', 'great', 'perfect', 'brilliant', 'awesome' ) ) ) {
        return array( 'reply' => $pro ? 'You\'re very welcome. Is there anything else I can help with?' : 'Anytime! Anything else I can help with?', 'intent' => 'thanks' );
    }
    if ( $has( array( 'bye', 'goodbye', 'see you', 'later' ) ) && strlen( $t ) < 30 ) {
        return array( 'reply' => $pro ? 'Goodbye — I\'m here around the clock if you need anything.' : 'Bye for now! I\'m here 24/7 if you need anything.', 'intent' => 'bye' );
    }

    // 3) Website knowledge ("Scan my website" / pasted facts) — only for questions
    // no specific intent covers, so page excerpts never hijack a direct answer
    if ( ! empty( $settings['knowledge'] ) && strlen( $t ) > 4 ) {
        $found = altus_ai_knowledge_search( $t, $settings['knowledge'] );
        if ( $found !== '' ) {
            $prefix = $pro ? 'Here\'s what I can tell you: ' : 'Good question — ';
            $cta = $pro
                ? ' Shall I have someone talk you through how this would work for you?'
                : ' Want me to get someone to talk you through what this could look like for you?';
            return array( 'reply' => $prefix . $found . $cta, 'intent' => 'knowledge', 'handoff' => true );
        }
    }

    $fb = $pro
        ? 'I\'m not quite sure I follow — could you phrase that another way? Or leave your name and contact details and someone will pick it up personally.'
        : 'Hmm, I\'m not sure I follow — could you say that a bit differently? Or just leave your name and number and someone will pick it up personally.';
    return array( 'reply' => $fb, 'intent' => 'fallback', 'handoff' => true );
}

/* Spend 1 credit per AI reply; returns false when empty */
function altus_ai_spend_credit( $note = 'AI reply' ) {
    global $wpdb;
    $bal = (int) get_option( 'altus_ai_credits', 0 );
    if ( $bal < 1 ) return false;
    $bal--;
    update_option( 'altus_ai_credits', $bal );
    $wpdb->insert( $wpdb->prefix . 'altus_ai_ledger', array( 'kind' => 'spend', 'amount' => -1, 'balance_after' => $bal, 'note' => $note ) );
    return true;
}
