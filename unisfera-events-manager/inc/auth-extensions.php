<?php
/**
 * UNISFERA AUTH EXTENSIONS
 * Handles Lost Password and Subscriber Registration logic.
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// LOGICĂ TIMPURIE: INTERCEPTARE ȘI PROCESARE FORMULAR LOST PASSWORD (SECURIZAT)
// =========================================================================
add_action('init', 'uem_process_lost_password_logic');

function uem_process_lost_password_logic() {
    if (is_user_logged_in()) return;

    // Faza 1: Solicitare inițială link resetare
    if (isset($_POST['uem_isolated_forgot_submit'])) {
        $user_email = sanitize_email($_POST['uem_forgot_email']);
        $user_data = get_user_by('email', $user_email);

        if ($user_data) {
            // Generăm un token unic, imun la bug-urile native WordPress
            $token = wp_generate_password(40, false);
            
            // Salvăm token-ul și timpul de expirare (valabil 2 ore) direct în usermeta
            update_user_meta($user_data->ID, 'uem_password_reset_token', $token);
            update_user_meta($user_data->ID, 'uem_password_reset_expiry', time() + (2 * HOUR_IN_SECONDS));

            // URL cu noul parametru securizat uem_token
            $rp_link = site_url('/lost-password/?uem_action=rp&uem_token=' . $token . '&uem_login=' . rawurlencode($user_data->user_login));

            $subject = get_option('uem_email_password_reset_subject', 'Resetare parola cont Unisfera');
            $body_template = get_option('uem_email_password_reset_body', "Buna {name},\n\nAm primit o solicitare de resetare a parolei pentru contul tau.\n\nPentru a alege o noua parola, da click pe link-ul de mai jos:\n{reset_url}\n\nDaca nu ai cerut acest lucru, poti ignora acest email.");

            $fname = get_user_meta($user_data->ID, 'first_name', true);
            $lname = get_user_meta($user_data->ID, 'last_name', true);
            $name = trim($fname . ' ' . $lname) ?: $user_data->display_name;

            $message = str_replace(['{name}', '{reset_url}'], [$name, $rp_link], $body_template);

            $email_sent = false;
            if (class_exists('UEM_Email_Handler')) {
                $email_sent = UEM_Email_Handler::send_password_reset_email_direct($user_data->user_email, $subject, $message);
            } 
            
            if (!$email_sent) {
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $email_sent = wp_mail($user_data->user_email, $subject, nl2br($message), $headers);
            }

            if ($email_sent) {
                wp_safe_redirect(add_query_arg('uem_status', 'email_sent', site_url('/lost-password/')));
                exit;
            } else {
                wp_safe_redirect(add_query_arg('uem_status', 'email_failed', site_url('/lost-password/')));
                exit;
            }
        } else {
            wp_safe_redirect(add_query_arg('uem_status', 'user_not_found', site_url('/lost-password/')));
            exit;
        }
    }

    // Faza 2: Salvarea parolei noi
    if (isset($_POST['uem_reset_password_submit']) && isset($_GET['uem_token']) && isset($_GET['uem_login'])) {
        $token = sanitize_text_field($_GET['uem_token']);
        $rp_login = sanitize_text_field($_GET['uem_login']);
        
        $user_data = get_user_by('login', $rp_login);
        
        if ($user_data) {
            $saved_token = get_user_meta($user_data->ID, 'uem_password_reset_token', true);
            $expiry = get_user_meta($user_data->ID, 'uem_password_reset_expiry', true);
            
            // Verificăm validitatea token-ului personalizat
            if (!empty($saved_token) && $saved_token === $token && time() < $expiry) {
                $pass1 = $_POST['uem_new_pass1'];
                $pass2 = $_POST['uem_new_pass2'];
                
                if (empty($pass1) || empty($pass2)) {
                    wp_safe_redirect(add_query_arg(['uem_action' => 'rp', 'uem_token' => $token, 'uem_login' => $rp_login, 'uem_status' => 'empty_fields'], site_url('/lost-password/')));
                    exit;
                } elseif ($pass1 !== $pass2) {
                    wp_safe_redirect(add_query_arg(['uem_action' => 'rp', 'uem_token' => $token, 'uem_login' => $rp_login, 'uem_status' => 'mismatch'], site_url('/lost-password/')));
                    exit;
                } elseif (strlen($pass1) < 6) {
                    wp_safe_redirect(add_query_arg(['uem_action' => 'rp', 'uem_token' => $token, 'uem_login' => $rp_login, 'uem_status' => 'too_short'], site_url('/lost-password/')));
                    exit;
                } else {
                    // Actualizăm parola cu succes
                    wp_set_password($pass1, $user_data->ID);
                    
                    // Curățăm token-ul pentru a nu mai putea fi refolosit
                    delete_user_meta($user_data->ID, 'uem_password_reset_token');
                    delete_user_meta($user_data->ID, 'uem_password_reset_expiry');
                    
                    wp_safe_redirect(add_query_arg('uem_status', 'success', site_url('/lost-password/')));
                    exit;
                }
            }
        }
    }
}

// =========================================================================
// 1. LOST PASSWORD SHORTCODE: [uem_lost_password]
// =========================================================================
add_shortcode('uem_lost_password', 'uem_render_lost_password');
function uem_render_lost_password() {
    if (is_user_logged_in()) return '<p>You are already logged in.</p>';

    $primary = 'var(--uem-primary)';
    $status = isset($_GET['uem_status']) ? sanitize_text_field($_GET['uem_status']) : '';
    $output = '<div class="uem-card" style="max-width:400px; margin:auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; font-family: sans-serif;">';
    
    // Afișare mesaje în funcție de status-ul redirecționării
    if ($status === 'email_sent') {
        $output .= '<div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">Check your email for the reset link.</div></div>';
        return $output;
    }
    if ($status === 'email_failed') $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">E-mailul nu a putut fi trimis de server.</div>';
    if ($status === 'user_not_found') $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Nu există niciun cont asociat cu această adresă de e-mail.</div>';
    if ($status === 'empty_fields') $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Te rugăm să completezi ambele câmpuri.</div>';
    if ($status === 'mismatch') $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Parolele introduse nu coincid.</div>';
    if ($status === 'too_short') $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">Parola trebuie să aibă cel puțin 6 caractere.</div>';
    
    if ($status === 'success') {
        $output .= '<div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">✅ Parola ta a fost salvată cu succes!</div>';
        $output .= '<p style="text-align:center; margin-top:15px;"><a href="'.site_url('/login').'" style="display:block; text-align:center; background:'.$primary.'; color:#fff; text-decoration:none; padding:10px; border-radius:4px; font-weight:bold;">Mergi la Login</a></p></div>';
        return $output;
    }

    // INTERFAȚĂ FAZA 2: FORMULAR INTRODUCERE PAROLĂ NOUĂ
    if (isset($_GET['uem_action']) && $_GET['uem_action'] === 'rp' && isset($_GET['uem_token']) && isset($_GET['uem_login'])) {
        $token = sanitize_text_field($_GET['uem_token']);
        $rp_login = sanitize_text_field($_GET['uem_login']);
        
        $user_data = get_user_by('login', $rp_login);
        $is_link_valid = false;

        if ($user_data) {
            $saved_token = get_user_meta($user_data->ID, 'uem_password_reset_token', true);
            $expiry = get_user_meta($user_data->ID, 'uem_password_reset_expiry', true);
            if (!empty($saved_token) && $saved_token === $token && time() < $expiry) {
                $is_link_valid = true;
            }
        }
        
        if (!$is_link_valid) {
            $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">The link has expired or is invalid.</div>';
            $output .= '<p style="text-align:center;"><a href="'.site_url('/lost-password').'" style="color:'.$primary.'; text-decoration:none; font-weight:bold;">Request new link</a></p></div>';
            return $output;
        }
        
        $output .= '
        <form method="post">
            <h4 style="text-align:center; margin-top:0; color:'.$primary.';">Choose a new password</h4>
            
            <input type="password" name="uem_new_pass1" placeholder="New password" required style="width:100%; margin-bottom:15px; padding:10px; border:1px solid #ccc; border-radius:4px;">
            <input type="password" name="uem_new_pass2" placeholder="Confirm new password" required style="width:100%; margin-bottom:15px; padding:10px; border:1px solid #ccc; border-radius:4px;">
            
            <button type="submit" name="uem_reset_password_submit" class="uem-btn" style="width:100%; background:'.$primary.'; color:#fff; border:none; padding:10px; cursor:pointer; font-weight:bold; border-radius:4px;">Reset password</button>
        </form>
        </div>';
        
        return $output;
    }

    // INTERFAȚĂ FAZA 1: FORMULAR INTRODUCERE E-MAIL
    $output .= '
    <form method="post">
        <h3 style="text-align:center; margin-top:0; color:'.$primary.';">Reset Password</h3>
        <p style="text-align:center; font-size:14px; color:#666;">Enter your email address.</p>
        <input type="email" name="uem_forgot_email" placeholder="Email Address" required style="width:100%; margin-bottom:15px; padding:10px; border:1px solid #ccc; border-radius:4px;">
        <button type="submit" name="uem_isolated_forgot_submit" class="uem-btn" style="width:100%; background:'.$primary.'; color:#fff; border:none; padding:10px; cursor:pointer; font-weight:bold; border-radius:4px;">Send Reset Link</button>
    </form>
    <div style="text-align:center; margin-top:15px; border-top:1px solid #eee; padding-top:15px;">
        <a href="'.site_url('/login').'" style="text-decoration:none; color:#666; font-size:13px;">Back to Login</a>
    </div>
    </div>';

    return $output;
}


// =========================================================================
// 2. SUBSCRIBER SIGNUP SHORTCODE: [uem_subscriber_signup] (NESCHIMBAT)
// =========================================================================
add_shortcode('uem_subscriber_signup', 'uem_render_subscriber_signup');
function uem_render_subscriber_signup() {
    if (is_user_logged_in()) return '<p style="text-align:center;">You already have an account.</p>';

    $primary = 'var(--uem-primary)';
    $output = '<div class="uem-card" style="max-width:500px; margin:auto; padding: 30px; border: 1px solid #eee; border-radius: 12px; background:#fff; box-shadow:0 5px 15px rgba(0,0,0,0.05); font-family: sans-serif;">';

    if (isset($_POST['uem_signup_submit'])) {
        $email      = sanitize_email($_POST['email']);
        $password   = $_POST['pass'];
        
        // Câmpuri noi
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $phone      = sanitize_text_field($_POST['phone']);
        $city       = sanitize_text_field($_POST['city']);
        $country    = sanitize_text_field($_POST['country']);
        $workplace  = sanitize_text_field($_POST['workplace']);

        if (email_exists($email)) {
            $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px; font-size:14px;">An account with this email already exists.</div>';
        } else {
            // Creăm utilizatorul
            $user_id = wp_create_user($email, $password, $email);
            
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('subscriber');

                // Salvăm detaliile suplimentare în usermeta
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                update_user_meta($user_id, 'uem_phone', $phone);
                update_user_meta($user_id, 'uem_city', $city);
                update_user_meta($user_id, 'uem_country', $country);
                update_user_meta($user_id, 'uem_workplace', $workplace);

                $output .= '<div style="background:#d4edda; color:#155724; padding:10px; margin-bottom:15px; border-radius:4px;">Registration successful! <a href="'.site_url('/login').'">Log in here</a>.</div>';
            } else {
                $output .= '<div style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:15px; border-radius:4px;">' . $user_id->get_error_message() . '</div>';
            }
        }
    }

    $output .= '
    <form method="post">
        <h3 style="text-align:center; margin-top:0; color:'.$primary.';">Create Account</h3>
                <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">First Name</label>
                <input type="text" name="first_name" placeholder="First Name" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Last Name</label>
                <input type="text" name="last_name" placeholder="Last Name" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
        </div>
        
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Email Address</label>
        <input type="email" name="email" placeholder="name@example.com" required style="width:100%; margin-bottom:15px; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
        
        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Mobile Number</label>
                <input type="tel" name="phone" placeholder="+00 xx xxx xxx" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">City</label>
                <input type="text" name="city" placeholder="City" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            </div>
        </div>

        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Country (Optional)</label>
        <input type="text" name="country" placeholder="Country" style="width:100%; margin-bottom:15px; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
        
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Company / Workplace </label>
        <input type="text" name="workplace" placeholder="Company or Institution" style="width:100%; margin-bottom:15px; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">

        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Password</label>
        <input type="password" name="pass" placeholder="••••••••" required style="width:100%; margin-bottom:25px; padding:12px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
        
        <button type="submit" name="uem_signup_submit" style="width:100%; background:'.$primary.'; color:#fff; border:none; padding:15px; cursor:pointer; font-weight:bold; border-radius:6px; font-size:16px; transition:0.3s;">Create My Account</button>
    </form>
    
    <div style="text-align:center; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
        <p style="font-size:14px; color:#777; margin-bottom:10px;">Already have an account?</p>
        <a href="'.site_url('/login').'" style="display:block; width:100%; padding:10px; border:1px solid '.$primary.'; color:'.$primary.'; text-decoration:none; border-radius:6px; font-weight:bold; box-sizing:border-box;">Login here</a>
    </div>
    </div>';

    return $output;
}