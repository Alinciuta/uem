<?php
add_shortcode('uem_live_event', 'uem_render_live_room');

function uem_render_live_room() {
    $ev_id = isset($_GET['ev_id']) ? intval($_GET['ev_id']) : 0;
    if (!$ev_id) return 'Event not found.';

    // 1. Preluăm lista mixtă de participanți
    $attendees = get_post_meta($ev_id, '_uem_attendees', true) ?: [];
    $is_registered = false;
    $guest_email = '';

    // FUNCȚIE INTERNĂ AJUTĂTOARE pentru verificarea email-ului în lista mixtă
    $check_email_in_list = function($email, $list) {
        foreach ($list as $entry) {
            // Dacă e ID de user (numeric)
            if (!is_array($entry)) {
                $user = get_user_by('id', $entry);
                if ($user && strtolower($user->user_email) === strtolower($email)) return true;
            } 
            // Dacă e array de Guest (verificăm cheia 'email')
            elseif (is_array($entry) && isset($entry['email'])) {
                if (strtolower($entry['email']) === strtolower($email)) return true;
            }
        }
        return false;
    };

    // 2. Verificăm dacă este utilizator logat
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        // Verificăm dacă ID-ul sau Email-ul lui se află în listă
        if (in_array($current_user->ID, $attendees) || $check_email_in_list($current_user->user_email, $attendees)) {
            $is_registered = true;
        }
    } else {
        // 3. Dacă NU este logat, verificăm cookie-ul de Guest
        $guest_email = isset($_COOKIE['uem_guest_email_' . $ev_id]) ? sanitize_email($_COOKIE['uem_guest_email_' . $ev_id]) : '';
        if ($guest_email && $check_email_in_list($guest_email, $attendees)) {
            $is_registered = true;
        }
    }

    // 4. Procesarea formularului de acces (dacă nu e deja înregistrat)
    if (!$is_registered && !current_user_can('edit_post', $ev_id)) {
        
        if (!is_user_logged_in() && isset($_POST['uem_guest_auth'])) {
            $submitted_email = sanitize_email($_POST['uem_guest_email']);
            
            // Verificăm dacă email-ul are cont pe site
            $existing_user = get_user_by('email', $submitted_email);

            if ($existing_user) {
                $current_live_url = add_query_arg('ev_id', $ev_id, get_permalink());
                $custom_login_page = home_url('/login/');
                $final_auth_url = add_query_arg('redirect_to', urlencode($current_live_url), $custom_login_page);
            
                wp_redirect($final_auth_url);
                exit;
            }

            // DACĂ NU ARE CONT, verificăm dacă este în lista de GUESTS folosind funcția nouă
            if ($check_email_in_list($submitted_email, $attendees)) {
                setcookie('uem_guest_email_' . $ev_id, $submitted_email, time() + 86400, "/");
                
                // Redirect pentru a activa accesul
                wp_redirect(add_query_arg('ev_id', $ev_id, get_permalink()));
                exit;
            } else {
                return '<div style="text-align:center; padding:80px;"><h4>Email is not found in the participants list. </h4><a href="'.get_permalink($ev_id).'">Back to event</a></div>';
            }
        }

        // Afișare Formular
        ob_start(); ?>
        <div style="text-align:center; padding:80px; max-width:800px; margin:auto;">
            <h4>Access the live session</h4>
            <p>To continue, please use the email address used for registration.</p>
            <form method="POST">
                <input type="email" name="uem_guest_email" required placeholder="email@exemplu.com" style="width:100%; padding:10px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                <button type="submit" name="uem_guest_auth" style="background:#E74C3C; color:#fff; border:none; padding:10px 20px; border-radius:5px; cursor:pointer; width:100%; font-size:14px;">Verify access</button>
            </form>
            <br><a href="<?php echo get_permalink($ev_id); ?>">Back to event page</a>
        </div>
        <?php return ob_get_clean();
    }


    $video_url = get_post_meta($ev_id, '_uem_event_video_url', true);
    $is_chat_enabled = get_post_meta($ev_id, '_uem_chat_enabled', true);
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    $start_date = get_post_meta($ev_id, '_uem_event_start_date', true);
    $start_time = get_post_meta($ev_id, '_uem_event_start_time', true);
    $agenda_url = get_post_meta($ev_id, '_uem_event_agenda', true);
    $speakers = get_post_meta($ev_id, '_uem_event_speakers', true);
    $useful_info = get_post_meta($ev_id, '_uem_live_useful_info', true);
    $event_start_timestamp = strtotime("$start_date $start_time");
    
    // Verificăm dacă evenimentul a început deja pentru a încărca tracker-ul
    if (current_time('timestamp') >= $event_start_timestamp) {
        wp_enqueue_script('uem-live-track', UEM_URL . 'assets/js/uem-live-track.js', array(), '1.0', true);
        
        wp_localize_script('uem-live-track', 'uem_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'event_id' => $ev_id,
            'nonce'    => wp_create_nonce('uem_track_nonce')
        ));
    }

    if (strpos($video_url, 'watch?v=') !== false) $video_url = str_replace('watch?v=', 'embed/', $video_url);

    ob_start(); ?>
    
    <style>
        :root { --uem-primary: <?php echo $primary; ?>; }
        .uem-live-layout { display: grid; grid-template-columns: <?php echo ($is_chat_enabled === '1') ? '1fr 350px' : '1fr'; ?>; gap: 20px; max-width: 1300px; margin: auto; padding: 20px; }
        .uem-video-section { width: 100%; }
        #uem-msg-list { height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 8px; margin-bottom: 10px; background: #f9f9f9; }
        #chat-input { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 10px; resize: none; }
        .uem-live-details { max-width: 1300px; margin: 0 auto 20px; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .uem-live-detail-box { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .uem-live-detail-box h4 { margin: 0 0 10px; font-size: 14px; color: #1a1a1a; text-transform: uppercase; letter-spacing: 0.4px; }
        .uem-live-detail-box p { margin: 0; color: #555; font-size: 14px; line-height: 1.6; }
        .uem-live-detail-box a { color: var(--uem-primary); font-weight: 700; text-decoration: none; }
        @media (max-width: 900px) { .uem-live-layout { grid-template-columns: 1fr; } }
    </style>
    
    <div style="margin-top: 5px; text-align: center; max-width: 1000px; margin: 5px auto; padding: 0 5px;">
        <h3 style="font-weight: 600; color: #1a1a1a;"><?php echo get_the_title($ev_id); ?></h3>
    </div>

    <div class="uem-live-layout">
        <div class="uem-video-section">
            <div style="background:#000; border-radius:15px; overflow:hidden; position:relative; padding-bottom:56.25%; height:0; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
                <?php if($video_url): ?>
                    <iframe src="<?php echo esc_url($video_url); ?>?autoplay=0" style="position:absolute; top:0; left:0; width:100%; height:100%; border:none;" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                <?php else: ?>
                    <div style="position:absolute; top:45%; width:100%; text-align:center; color:#fff;"><p>The stream has not started yet.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_chat_enabled === '1') : ?>
        <div id="uem-chat-container">
            <div id="uem-msg-list">
                <?php if (class_exists('UEM_Chat')) UEM_Chat::render_messages($ev_id); ?>
            </div>
            <div class="uem-chat-footer">
                <?php if (is_user_logged_in()) : ?>
                    <textarea id="chat-input" placeholder="Write a message..."></textarea>
                    <button id="chat-send" style="background:<?php echo $primary; ?>; color:#fff; width:100%; border:none; padding:10px; border-radius:8px; cursor:pointer; margin-top:8px; font-weight:bold;">Send Message</button>
                <?php else : ?>
                    <p style="text-align:center; font-size:16px;">To use the chat, please <a href="<?php echo wp_login_url(get_permalink()); ?>">login</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($agenda_url) || !empty($speakers) || !empty($useful_info)) : ?>
        <div class="uem-live-details">
            <?php if (!empty($agenda_url)) : ?>
                <div class="uem-live-detail-box">
                    <h4>Agenda / Program</h4>
                    <p><a href="<?php echo esc_url($agenda_url); ?>" target="_blank" rel="noopener">Open agenda</a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($speakers)) : ?>
                <div class="uem-live-detail-box">
                    <h4>Speakers</h4>
                    <p><?php echo nl2br(esc_html($speakers)); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($useful_info)) : ?>
                <div class="uem-live-detail-box">
                    <h4>Useful Information</h4>
                    <p><?php echo nl2br(esc_html($useful_info)); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 10px; margin-bottom: 20px;">
        <a href="<?php echo get_permalink($ev_id); ?>" style="color: #888; text-decoration: none; font-weight: 600;">← Back to Event Page</a>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const input = document.getElementById('chat-input');
        const $msgList = $('#uem-msg-list');
        const $sendBtn = $('#chat-send');
        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

        const scrollToBottom = () => { 
            if($msgList.length) $msgList.scrollTop($msgList.prop("scrollHeight")); 
        };

        const refreshChat = () => {
            $msgList.load(window.location.href + ' #uem-msg-list > *', function() {
                scrollToBottom();
            });
        };

        const sendMessage = () => {
            const msg = $(input).val();
            if(!msg.trim() || $sendBtn.prop('disabled')) return;

            $sendBtn.prop('disabled', true).text('...');
            
            $.post(ajaxurl, {
                action: 'uem_send_message',
                ev_id: <?php echo $ev_id; ?>,
                message: msg,
                nonce: '<?php echo wp_create_nonce('uem_chat_secure'); ?>'
            }, function() {
                $(input).val('').focus();
                $sendBtn.prop('disabled', false).text('Send Message');
                // Refresh instant pentru cel care trimite
                refreshChat();
            });
        };

        if (input) {
            input.addEventListener('keydown', function(e) {
                if ((e.key === 'Enter' || e.keyCode === 13) && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        $sendBtn.on('click', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Initial scroll
        scrollToBottom();

        // Refresh automat la fiecare 2 secunde pentru ceilalți participanți
        setInterval(() => { 
            if (!$(input).is(':focus') || $(input).val().length === 0) {
                refreshChat();
            }
        }, 2000);

        // TRACKING PREZENȚĂ
        var current_event_id = "<?php echo $ev_id; ?>"; 
        var track_nonce = "<?php echo wp_create_nonce('uem_track_nonce'); ?>";

        function sendAttendancePing() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'uem_track_attendance',
                    event_id: current_event_id,
                    nonce: track_nonce
                },
                success: function(response) {
                    console.log('UEM Tracker: Ping OK.');
                }
            });
        }

        sendAttendancePing();
        setInterval(sendAttendancePing, 60000);
    });
    </script>

    <?php return ob_get_clean();
}
