<?php
/**
 * UNISFERA EVENT MANAGER - Template Engine
 */

if (!defined('ABSPATH')) exit;

function uem_render_event_page() {
    global $post;
    if (!$post) return '';

    $event_id = $post->ID;
    $user_id  = get_current_user_id();
    $primary  = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';

    // --- REGISTRATION PROCESSING LOGIC ---
    if (isset($_POST['uem_register_now']) && get_post_meta($event_id, '_uem_pricing_type', true) !== 'paid') {
        $attendees = get_post_meta($event_id, '_uem_attendees', true) ?: [];
        if (!is_array($attendees)) $attendees = [];
        
        $final_user_id = 0;
        $email_to_send = '';
        $name_to_send  = '';

        if (is_user_logged_in()) {
            $final_user_id = $user_id;
            $current_user = wp_get_current_user();
            $email_to_send = $current_user->user_email;
            $fname = get_user_meta($user_id, 'first_name', true);
            $lname = get_user_meta($user_id, 'last_name', true);
            $name_to_send = trim($fname . ' ' . $lname) ?: $current_user->display_name;
        } else {
            $first_name = sanitize_text_field($_POST['guest_fname']);
            $last_name  = sanitize_text_field($_POST['guest_lname']);
            $email      = sanitize_email($_POST['guest_email']);
            $email_to_send = $email;
            $name_to_send  = $first_name . ' ' . $last_name;

            // Handle Account Creation
            if (isset($_POST['create_account']) && !empty($_POST['guest_pass'])) {
                $username = $email; 
                $password = $_POST['guest_pass'];

                if (!email_exists($email) && !username_exists($username)) {
                    $new_user_id = wp_create_user($username, $password, $email);
                    if (!is_wp_error($new_user_id)) {
                        wp_update_user([
                            
                            'ID'         => $new_user_id,
                            'first_name' => $first_name,
                            'last_name'  => $last_name,
                            'role'       => 'subscriber'
                        ]);
                        if (class_exists('UEM_Email_Handler')) {

    UEM_Email_Handler::send_account_confirmation_dynamic($email, $name_to_send);

}
                        $final_user_id = $new_user_id;
                        // Log them in automatically
                        wp_set_current_user($new_user_id);
                        wp_set_auth_cookie($new_user_id);
                    }
                }
            }

            // Store as guest object if no account was created
            if ($final_user_id === 0) {
                $guest_data = [
                    'name'    => $first_name . ' ' . $last_name,
                    'email'   => $email,
                    'phone'   => sanitize_text_field($_POST['guest_phone']),
                    'company' => sanitize_text_field($_POST['guest_company']),
                    'type'    => 'guest',
                    'date'    => current_time('mysql')
                ];
                $attendees[] = $guest_data;
            }
        }

        // Add User ID to list if applicable
        if ($final_user_id > 0 && !in_array($final_user_id, $attendees)) {
            $attendees[] = $final_user_id;
        }

        update_post_meta($event_id, '_uem_attendees', $attendees);
        $e_start = get_post_meta($event_id, '_uem_event_start_date', true) ?: 'TBA';
        $e_hour  = get_post_meta($event_id, '_uem_event_start_hour', true) ?: '';
        $e_end   = get_post_meta($event_id, '_uem_event_end_date', true);

        $full_date_string = $e_start;
        if (!empty($e_end))  { $full_date_string .= ' -  ' . $e_end; }
        if (class_exists('UEM_Email_Handler') && !empty($email_to_send)) {

    UEM_Email_Handler::send_registration_confirmation($email_to_send, $event_id, $name_to_send, $full_date_string);
        }


        echo '<div style="position:fixed; top:20px; left:50%; transform:translateX(-50%); background:#eaffea; color:#1a5928; padding:15px 30px; border-radius:50px; box-shadow:0 10px 30px rgba(0,0,0,0.1); z-index:9999; font-weight:bold;">✅ Registration successful!</div>';
        echo '<meta http-equiv="refresh" content="2">'; 
    }

    // --- EVENT DATA RETRIEVAL ---
    $start_date = get_post_meta($event_id, '_uem_event_start_date', true) ?: 'TBA';
    $end_date   = get_post_meta($event_id, '_uem_event_end_date', true);
    $start_hour = get_post_meta($event_id, '_uem_event_start_hour', true) ?: '';
    $location   = get_post_meta($event_id, '_uem_event_location', true) ?: 'To be announced';
    $organizer  = get_post_meta($event_id, '_uem_display_organizer', true);
    $reg_type   = get_post_meta($event_id, '_uem_registration_type', true);
    $ext_link   = get_post_meta($event_id, '_uem_external_reg_link', true);
    $agenda_url = get_post_meta($event_id, '_uem_event_agenda', true);
    $speakers   = get_post_meta($event_id, '_uem_event_speakers', true);
    $is_live_enabled = get_post_meta($event_id, '_uem_live_enabled', true); // Noua verificare
    $img = get_the_post_thumbnail_url($event_id, 'full');
    $attendees_list = get_post_meta($event_id, '_uem_attendees', true) ?: [];
    
    $is_registered = false;
    $current_user_email = is_user_logged_in() ? wp_get_current_user()->user_email : (isset($_GET['access_email']) ? sanitize_email($_GET['access_email']) : '');
    
    foreach ((array)$attendees_list as $attendee) {
        // Verifică dacă e ID de utilizator logat
        if (is_user_logged_in() && !is_array($attendee) && (int)$attendee === $user_id) {
            $is_registered = true;
            break;
        }
        // Verifică dacă e Guest (care este un array în baza de date)
        if (is_array($attendee) && isset($attendee['email']) && $attendee['email'] === $current_user_email) {
            $is_registered = true;
            break;
        }
    }

    ob_start(); ?>

    <style>
        .uem-event-container { max-width: 1140px; margin: 40px auto; padding: 0 20px; font-family: -apple-system, system-ui, sans-serif; color: #333; }
        .uem-banner { width: 100%; height: 450px; background: #eee url('<?php echo esc_url($img); ?>') center/cover no-repeat; border-radius: 20px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .uem-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
        .uem-card { background: #fff; padding: 35px; border-radius: 18px; border: 1px solid #f0f0f0; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
        .uem-sidebar { position: sticky; top: 120px; border-top: 6px solid <?php echo $primary; ?>; }
        .uem-meta-box { margin-bottom: 20px; }
        .uem-meta-label { color:#999; font-weight:bold; text-transform:uppercase; font-size:11px; letter-spacing: 1px; display:block; margin-bottom:5px; }
        .uem-meta-value { font-size:17px; font-weight:500; color: #1a1a1a; }
        .uem-btn { background: <?php echo $primary; ?>; color: #fff !important; padding: 16px; width: 100%; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; text-align: center; text-decoration: none; display: block; transition: 0.3s ease; font-size: 16px; }
        .uem-btn:hover { filter: brightness(1.1); transform: translateY(-2px); }
        .uem-input-guest { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 12px; font-size: 14px; transition: border 0.3s; }
        .uem-input-guest:focus { border-color: <?php echo $primary; ?>; outline: none; }
        .pass-field-wrapper { display: none; margin-top: 5px; }
        @media (max-width: 850px) { .uem-grid { grid-template-columns: 1fr; } .uem-banner { height: 280px; } }
    </style>

    <div class="uem-event-container">
        <?php if (function_exists('uem_get_navigation') && is_user_logged_in()) echo uem_get_navigation(); ?>

        <?php if ($img) : ?><div class="uem-banner"></div><?php endif; ?>

        <div class="uem-grid">
            <div class="uem-main-content">
                <div class="uem-card">
                    <h1 style="margin:0 0 20px 0; font-size: 32px; color: #1a1a1a;"><?php echo get_the_title($event_id); ?></h1>
                    <div class="uem-description" style="line-height: 1.7; font-size: 16px; color: #555;">
                        <?php echo wpautop($post->post_content); ?>
                    </div>

                    <?php if (!empty($speakers)) : ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <h3 style="text-align: center; margin-bottom: 5px; font-size: 20px;">Speakers</h3>
                            <div style="color: #555; line-height: 1.6; white-space: pre-line; text-align: center;">
                                <?php echo esc_html($speakers); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                
                <?php if ($reg_type !== 'none') : ?>
                    <div class="uem-card" style="margin-top: 25px; background: #fafafa;">
                        <h3 style="text-align:center; margin-top:0; color:<?php echo $primary; ?>;">Register for this Event</h3>
                        
                        <?php if ($reg_type === 'external') : ?>
                            <p style="text-align:center;">This event uses an external registration system.</p>
                            <a href="<?php echo esc_url($ext_link); ?>" target="_blank" class="uem-btn">CONTINUE TO REGISTRATION</a>

                        <?php elseif ($is_registered) : ?>
                            <div style="background:#e6fffa; color:#234e52; padding:20px; border-radius:12px; text-align:center; border: 1px solid #b2f5ea;">
                                <span style="font-size: 24px;">✅</span><br>
                                <strong>You are registered!</strong> We have sent the details to your email.
                            </div>

                        <?php elseif (!is_user_logged_in() && get_post_meta($event_id, '_uem_pricing_type', true) === 'paid') : ?>
                            <p style="text-align:center">Please log in to choose a ticket and pay securely.</p><a class="uem-btn" href="<?php echo esc_url(site_url('/login/?redirect_to='.rawurlencode(get_permalink($event_id)))); ?>">Log in to continue</a>
                        <?php elseif (!is_user_logged_in()) : ?>
                            <form method="post" style="max-width: 500px; margin: 20px auto 0 auto;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <input type="text" name="guest_fname" class="uem-input-guest" placeholder="First Name *" required>
                                    <input type="text" name="guest_lname" class="uem-input-guest" placeholder="Last Name *" required>
                                </div>
                                <input type="email" name="guest_email" class="uem-input-guest" placeholder="Email Address *" required>
                                <input type="text" name="guest_phone" class="uem-input-guest" placeholder="Phone Number">
                                <input type="text" name="guest_company" class="uem-input-guest" placeholder="Company / Organization">
                                
                                <div style="margin: 5px 0 15px 0; font-size: 14px; color: #555;">
                                    <label style="cursor:pointer; display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="create_account" id="uem_create_acc" onclick="toggleUemPass()"> 
                                        Also create an account?
                                    </label>
                                </div>

                                <div id="uem_pass_wrapper" class="pass-field-wrapper">
                                    <input type="password" name="guest_pass" id="uem_guest_pass" class="uem-input-guest" placeholder="Choose a Password *">
                                </div>

                                <button type="submit" name="uem_register_now" class="uem-btn">REGISTER</button>
                                
                                <p style="text-align:center; font-size: 13px; margin-top: 15px; color: #777;">
                                    Have an account? <a href="<?php echo site_url('/login'); ?>" style="color:<?php echo $primary; ?>; font-weight:bold;">Log in</a>.
                                </p>
                            </form>

                        <?php else : ?>
                            <div style="text-align:center; padding: 10px;">
                                <p>Hi <strong><?php echo wp_get_current_user()->display_name; ?></strong>, do you want to register?</p>
                                <?php if (get_post_meta($event_id, '_uem_pricing_type', true) === 'paid') : ?>
                                    <a class="uem-btn" href="<?php echo esc_url(site_url('/payment-checkout/?ev_id='.$event_id)); ?>">Choose ticket &amp; pay</a>
                                <?php else : ?><form method="post"><button type="submit" name="uem_register_now" class="uem-btn">Register for this event</button></form><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="uem-sidebar-col">
                <div class="uem-card uem-sidebar">
                    <div class="uem-meta-box">
                        <span class="uem-meta-label">When</span>
                        <div class="uem-meta-value">📅 <?php echo esc_html($start_date); ?> 
                            <?php echo !empty($start_hour) ? ' at ' . esc_html($start_hour) : ''; ?>
                            <?php if (!empty($end_date)) echo '<br><small style="color:#888; font-weight:normal;">Until: ' . esc_html($end_date) . '</small>'; ?>
                        </div>
                    </div>

                    <div class="uem-meta-box">
                        <span class="uem-meta-label">Where</span>
                        <div class="uem-meta-value">📍 <?php echo esc_html($location); ?></div>
                    </div>

                    <?php if (!empty($organizer)) : ?>
                        <div class="uem-meta-box">
                            <span class="uem-meta-label">Organizer</span>
                            <div class="uem-meta-value" style="color: #666; margin-bottom: 10px;"><?php echo esc_html($organizer); ?></div>
                            
                            <?php if (!empty($agenda_url)) : ?>
                                <a href="<?php echo esc_url($agenda_url); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: <?php echo $primary; ?>; text-decoration: none; font-weight: 600; background: #fff1f0; padding: 6px 12px; border-radius: 6px; border: 1px solid <?php echo $primary; ?>20;">
                                    📂 View Agenda
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_registered && $is_live_enabled === '1') : ?>
                        <hr style="border:0; border-top:1px solid #eee; margin: 25px 0;">
                        <a href="<?php echo site_url('/live-page/?ev_id=' . $event_id); ?>" class="uem-btn" style="background:#2c3e50;">ENTER LIVE EVENT</a>
                    <?php elseif ($is_registered && $is_live_enabled !== '1') : ?>
                        <p style="text-align: center; font-size: 12px; color: #888; margin-top: 20px;">
                            
                            
                        </p>
                    <?php endif; ?>

                    <?php if ($is_registered && get_post_meta($event_id, '_uem_evaluation_active', true) === '1') : ?>
                        <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">
                        <a href="<?php echo esc_url(site_url('/event-evaluation/?ev_id=' . $event_id)); ?>" class="uem-btn" style="background:#16a34a;">COMPLETE EVALUATION</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleUemPass() {
        const check = document.getElementById('uem_create_acc');
        const wrapper = document.getElementById('uem_pass_wrapper');
        const passInput = document.getElementById('uem_guest_pass');
        if (check.checked) {
            wrapper.style.display = 'block';
            passInput.setAttribute('required', 'required');
        } else {
            wrapper.style.display = 'none';
            passInput.removeAttribute('required');
        }
    }
    </script>

    <?php
    return ob_get_clean();
}
