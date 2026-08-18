<?php
/**
 * UEM Authentication Module
 * Handles Organizer and Attendee registration/login logic.
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. ORGANIZER SIGN-UP FORM
 * Shortcode: [uem_signup_form]
 */
add_shortcode('uem_signup_form', 'uem_render_organizer_signup');

function uem_render_organizer_signup() {
    if (is_user_logged_in()) {
        return '<div style="text-align:center; padding:20px;">You are already logged in. <a href="'.site_url('/organizer-dashboard/').'">Go to Dashboard</a></div>';
    }

    $primary = UEM_PRIMARY_COLOR;
    $error = '';

    if (isset($_POST['uem_organizer_submit'])) {
        $email      = sanitize_email($_POST['u_email']);
        $password   = $_POST['u_pass'];
        
        // Câmpuri suplimentare
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $phone      = sanitize_text_field($_POST['phone']);
        $city       = sanitize_text_field($_POST['city']);
        $company    = sanitize_text_field($_POST['company']); // Required
        $country    = sanitize_text_field($_POST['country']); // Optional

        if (email_exists($email)) {
            $error = 'An account with this email already exists.';
        } elseif (empty($company)) {
            $error = 'Company or Institution is required for organizers.';
        } else {
            // Creăm utilizatorul
            $user_id = wp_create_user($email, $password, $email);
            
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('uem-organizer'); 
                
                // Salvăm metadatele
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                update_user_meta($user_id, 'uem_phone', $phone);
                update_user_meta($user_id, 'uem_city', $city);
                update_user_meta($user_id, 'uem_workplace', $company); // Salvăm sub aceeași cheie ca la subscriber pentru consistență
                update_user_meta($user_id, 'uem_country', $country);
                
                // Login automat și redirecționare
                wp_set_auth_cookie($user_id);
                wp_redirect(site_url('/organizer-dashboard/'));
                exit;
            } else {
                $error = $user_id->get_error_message();
            }
        }
    }

    ob_start(); ?>
    <div class="uem-auth-box" style="max-width:500px; margin:auto; padding:30px; border:1px solid #eee; border-radius:15px; background:#fff; box-shadow:0 10px 25px rgba(0,0,0,0.05); font-family:sans-serif;">
        <h3 style="text-align:center; color:<?php echo $primary; ?>; margin-top:0;">Organizer Sign-up</h3>
        <p style="text-align:center; font-size:14px; color:#666; margin-bottom:25px;">Create your organizer account to start managing events.</p>
        
        <?php if($error) echo "<p style='color:#d9534f; background:#f2dede; padding:10px; border-radius:5px; font-size:13px; margin-bottom:20px;'>$error</p>"; ?>
        
        <form method="POST">
            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">First Name</label>
                    <input type="text" name="first_name" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="First Name">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Last Name</label>
                    <input type="text" name="last_name" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="Last Name">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Mobile Number</label>
                    <input type="tel" name="phone" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="+00 xx xxx xxx">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">City</label>
                    <input type="text" name="city" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="City">
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Company or Institution *</label>
                <input type="text" name="company" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="Your Organization Name">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Country (Optional)</label>
                <input type="text" name="country" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="Country">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Email Address</label>
                <input type="email" name="u_email" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="name@example.com">
            </div>

            <div style="margin-bottom:25px;">
                <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px; text-transform:uppercase; color:#888;">Password</label>
                <input type="password" name="u_pass" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;" placeholder="••••••••">
            </div>

            <button type="submit" name="uem_organizer_submit" style="width:100%; background:<?php echo $primary; ?>; color:#fff; border:0; padding:15px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:16px;">Create Organizer Account</button>
        </form>
        
        <p style="text-align:center; font-size:13px; margin-top:20px; color:#888;">Already have an account? <a href="<?php echo site_url('/login'); ?>" style="color:<?php echo $primary; ?>; text-decoration:none; font-size:13px; font-weight:bold;">Login here</a></p>
    </div>
    <?php return ob_get_clean();
}



/**
 * 2. ATTENDEE SIGN-UP FORM
 * Shortcode: [uem_attendee_signup]
 */
add_shortcode('uem_attendee_signup', 'uem_render_attendee_signup');

function uem_render_attendee_signup() {
    if (is_user_logged_in()) {
        return '<div style="text-align:center; padding:15px; background:#e9ecef; border-radius:8px;">You are logged in and ready to join events!</div>';
    }

    $primary = UEM_PRIMARY_COLOR;
    $error = '';

    if (isset($_POST['uem_attendee_submit'])) {
        $email = sanitize_email($_POST['u_email']);
        $pass  = $_POST['u_pass'];
        // Am eliminat $user = sanitize_user($_POST['u_name']);

        // Verificăm doar dacă emailul există
        if (email_exists($email)) {
            $error = 'This email is already registered.';
        } else {
            // Folosim $email ca username (primul parametru) și ca email (al treilea parametru)
            $user_id = wp_create_user($email, $pass, $email);
            
            if (!is_wp_error($user_id)) {
                $new_user = new WP_User($user_id);
                $new_user->set_role('subscriber');

                wp_set_auth_cookie($user_id);
                wp_redirect(get_permalink());
                exit;
            } else {
                $error = $user_id->get_error_message();
            }
        }
    }

    ob_start(); ?>
    <div class="uem-attendee-box" style="padding:25px; border:1px solid #eee; border-radius:12px; background:#f9f9f9; font-family:sans-serif;">
        <h4 style="margin-top:0; color:<?php echo $primary; ?>;">Create Account to Participate</h4>
        <p style="font-size:13px; color:#777; margin-bottom:15px;">A password is required to keep your live stream access secure.</p>

        <?php if($error) echo "<p style='color:red; font-size:12px;'>$error</p>"; ?>

        <form method="POST">
            <input type="email" name="u_email" placeholder="Email Address" required style="width:100%; margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:5px;">
            <input type="password" name="u_pass" placeholder="Create Password" required style="width:100%; margin-bottom:15px; padding:10px; border:1px solid #ddd; border-radius:5px;">
            
            <button type="submit" name="uem_attendee_submit" style="background:<?php echo $primary; ?>; color:#fff; width:100%; border:0; padding:12px; border-radius:6px; font-weight:bold; cursor:pointer;">
                Sign Up & Register
            </button>
        </form>
        <p style="font-size:12px; margin-top:15px; text-align:center;">
            Already a member? <a href="<?php echo site_url('/login'); ?>" style="color:<?php echo $primary; ?>; font-weight:bold;">Log in</a>
        </p>
    </div>
    <?php return ob_get_clean();
}

/**
 * 3. LOGIN FORM
 * Shortcode: [uem_login_form]
 */
add_shortcode('uem_login_form', 'uem_render_login_form');

function uem_render_login_form() {
    // ... codul de verificare login existent ...

    $primary = UEM_PRIMARY_COLOR;
    
    // VERIFICARE SMART PENTRU REDIRECT
    // Dacă avem redirect_to în URL, îl folosim. Dacă nu, mergem la dashboard.
    $default_redirect = site_url('/organizer-dashboard/');
    if ( isset($_REQUEST['redirect_to']) ) {
        $default_redirect = esc_url_raw($_REQUEST['redirect_to']);
    }

    $error = isset($_GET['login']) && $_GET['login'] == 'failed' ? 'Invalid email or password.' : '';

    ob_start(); ?>
    <div class="uem-auth-box" style="max-width:400px; margin:auto; padding:30px; border:1px solid #eee; border-radius:15px; background:#fff; box-shadow:0 10px 25px rgba(0,0,0,0.05); font-family:sans-serif; font-size: 14px;">
        <h3 style="text-align:center; color:<?php echo esc_attr($primary); ?>; margin-top:0; margin-bottom:20px;">Login</h3>

        <?php if ($error) : ?>
            <div role="alert" style="color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; padding:12px; border-radius:6px; font-size:13px; margin-bottom:18px; text-align:center;">
                <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>

        <?php 
        wp_login_form(array(
            'echo'           => true,
            'redirect'       => $default_redirect, // Acum este variabil!
            'form_id'        => 'uem-login-form',
            'label_username' => 'Email',
            'label_password' => 'Password',
            'label_remember' => 'Remember Me',
            'label_log_in'   => 'Sign In',
            'remember'       => true,
            'value_remember' => true,
        )); 
        ?>
        
        <p style="text-align:center; font-size:12px; margin-top:20px; color:#888;">
            Don't have an account? <br>
            <a href="<?php echo site_url('/signup'); ?>" style="color:<?php echo $primary; ?>; text-decoration:none;">Sign up here </a>  |  
            <a href="<?php echo site_url('/lost-password'); ?>" style="color:<?php echo $primary; ?>; text-decoration:none;"> Lost password?</a>
        </p>
    </div>
    
    <style>
        #uem-login-form input[type="text"], #uem-login-form input[type="password"] {
            width: 100%; padding: 10px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;
        }
        #uem-login-form .login-submit input {
            width: 100%; background: <?php echo $primary; ?>; color: #fff; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px;
        }
    </style>
    <?php
    return ob_get_clean();
}

add_action('wp_login_failed', 'uem_redirect_login_failed');

function uem_redirect_login_failed() {
    $login_page = site_url('/login/');
    
    // Păstrăm redirect_to dacă există
    if ( isset($_REQUEST['redirect_to']) ) {
        $login_page = add_query_arg('redirect_to', urlencode($_REQUEST['redirect_to']), $login_page);
    }
    
    $login_page = add_query_arg('login', 'failed', $login_page);
    
    wp_redirect($login_page);
    exit;
}

add_filter('authenticate', 'uem_blank_login_redirect', 30, 3);
function uem_blank_login_redirect($user, $username, $password) {
    if ($username == "" || $password == "") {
        wp_redirect(site_url('/login/') . '?login=failed');
        exit;
    }
    return $user;
}
