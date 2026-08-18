<?php
/**
 * Unisfera Navigation Hub - Single Instance & Desktop Labels
 */

function uem_get_navigation() {
    if (!is_user_logged_in()) return '';

    $primary = UEM_PRIMARY_COLOR;
    $current_user = wp_get_current_user();
    
    // VerificÄƒm rolurile
    $is_organizer = in_array('uem-organizer', (array) $current_user->roles);
    $is_subscriber = in_array('subscriber', (array) $current_user->roles);
    $is_admin = current_user_can('manage_options');

    ob_start(); ?>
    <style>
        /* DESKTOP Style */
        .uem-nav-container { padding: 0 10px; margin: 20px 0; width: 100%; clear: both; }
        .uem-nav-hub {
            background: #ffffff; 
            max-width: 850px; 
            margin: 0 auto; 
            padding: 10px 25px; 
            border-radius: 50px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border: 1px solid #f0f0f0;
            font-family: -apple-system, system-ui, sans-serif;
            z-index: 9999;
        }
        .uem-nav-left, .uem-nav-right { display: flex; align-items: center; gap: 20px; }
        
        .uem-nav-hub a { 
            text-decoration: none !important; 
            color: #555; 
            font-weight: 600; 
            font-size: 13px; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            transition: 0.2s;
        }
        .uem-nav-hub a:hover { color: <?php echo $primary; ?>; }
        .uem-icon-min { font-size: 16px; color: #999; }

        .uem-label { display: inline-block; }
        .uem-logout-label { display: inline-block; color: <?php echo $primary; ?>; }

        /* MOBILE Style */
        @media (max-width: 768px) {
            .uem-nav-hub {
                position: fixed;
                bottom: 20px;
                left: 15px;
                right: 15px;
                width: auto;
                border-radius: 25px;
                padding: 12px 10px;
                background: rgba(255, 255, 255, 0.98);
                box-shadow: 0 -5px 30px rgba(0,0,0,0.12);
                
            }
            .uem-nav-left, .uem-nav-right { flex: 1; justify-content: space-around; gap: 0; }
            .uem-nav-hub a { flex-direction: column; font-size: 18px; gap: 4px; }
            .uem-label { font-size: 10px; font-weight: 400; text-transform: uppercase; }
            body { padding-bottom: 100px !important; }
            .uem-logout-label { font-size: 10px; font-weight: 400; text-transform: uppercase; }
        }
    </style>

    <div class="uem-nav-container">
        <nav class="uem-nav-hub">
            <div class="uem-nav-left">
                
                <a href="<?php echo site_url('/organizer-dashboard/'); ?>">
                    <span class="uem-icon-min">♟</span>
                    <span class="uem-label">Home</span>
                </a>
                
                <?php if ($is_organizer || $is_admin) : ?>
                    <a href="<?php echo site_url('/submit-event/'); ?>">
                        <span class="uem-icon-min">＋</span>
                        <span class="uem-label">Create</span>
                    </a>
                <?php endif; ?>

                <?php if ($is_subscriber || $is_admin) : ?>
                    <a href="<?php echo site_url('/my-registered-events/'); ?>">
                        <span class="uem-icon-min">✓</span>
                        <span class="uem-label">Joined</span>
                    </a>
                    <a href="<?php echo site_url('/my-certificates/'); ?>">
                        <span class="uem-icon-min">★</span>
                        <span class="uem-label">Certificates</span>
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo site_url('/events/'); ?>">
                    <span class="uem-icon-min">➲</span>
                    <span class="uem-label">All events</span>
                </a>

                <a href="<?php echo site_url('/edit-profile/'); ?>">
                    <span class="uem-icon-min">⌨</span>
                    <span class="uem-label">Settings</span>
                </a>
                <a href="<?php echo wp_logout_url(); ?>">
                    <span class="uem-icon-min">></span>
                    <span class="uem-logout-label">Logout</span>
                </a>
            </div>
        </nav>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * LogicÄƒ de injectare (PÄƒstratÄƒ neschimbatÄƒ)
 */
add_filter('the_content', function($content) {
    if (!is_user_logged_in() || !is_main_query() || !in_the_loop()) {
        return $content;
    }

    global $post;
    if (!$post) return $content;

    $uem_pages = [
        'events', 
        'organizer-dashboard', 
        'submit-event', 
        'edit-event', 
        'my-registered-events', 
        'edit-profile',
        'admin-live-page',
        'live-page',
        'post-event',
        'event-evaluation',
        'my-certificates',
        'event'
    ];

    $should_show = false;

    // 1. Verificare prin slug-ul paginilor fixe
    if (in_array($post->post_name, $uem_pages)) {
        $should_show = true;
    }

    // 2. NOU: Verificare dacă suntem pe pagina individuală a unui eveniment (CPT uem_event)
    if (!$should_show && is_singular('uem_event')) {
        $should_show = true;
    }

    // 3. Verificare prin URL (ca rezervă pentru link-uri custom)
    if (!$should_show) {
        $current_url = $_SERVER['REQUEST_URI'];
        foreach ($uem_pages as $page_slug) {
            if (strpos($current_url, $page_slug) !== false) {
                $should_show = true;
                break;
            }
        }
    }

    if ($should_show) {
        static $already_done = false;
        if (!$already_done) {
            $already_done = true;
            return uem_get_navigation() . $content;
        }
    }

    return $content;
}, 1);
