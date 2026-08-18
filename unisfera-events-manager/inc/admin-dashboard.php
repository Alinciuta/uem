<?php
if (!defined('ABSPATH')) exit;

// 1. UNIFIED ADMIN MENU
add_action('admin_menu', 'uem_create_admin_menu');
function uem_create_admin_menu() {
    add_menu_page('Unisfera Events', 'Unisfera Events', 'manage_options', 'uem-main-menu', 'uem_render_settings_page', 'dashicons-calendar-alt', 30);
    add_submenu_page('uem-main-menu', 'Settings', 'Settings', 'manage_options', 'uem-main-menu', 'uem_render_settings_page');
    add_submenu_page('uem-main-menu', 'Organizers', 'Organizers', 'manage_options', 'uem-organizers', 'uem_render_organizers_page');
    add_submenu_page('uem-main-menu', 'Emails', 'Email Templates', 'manage_options', 'uem-emails', 'uem_render_email_settings');
    add_submenu_page('uem-main-menu', 'Payments', 'Payments', 'manage_options', 'uem-payments', 'uem_render_payment_settings');
    add_submenu_page('uem-main-menu', 'Shortcodes', 'Shortcodes Reference', 'manage_options', 'uem-shortcodes', 'uem_render_shortcodes_page');
}

// 2. GLOBAL SETTINGS PAGE (Reparată pentru a include noile câmpuri de email)
function uem_render_settings_page() {
    if (isset($_POST['save_uem_settings'])) {
        // Salvare culori și redirect
        $color_input = sanitize_text_field($_POST['uem_color']);
        if ( !empty($color_input) && $color_input[0] !== '#' ) {
            $color_input = '#' . $color_input;
        }
        update_option('uem_primary_color', sanitize_hex_color($color_input));
        update_option('uem_logout_redirect_slug', sanitize_text_field($_POST['uem_logout_slug']));
        
        // Salvare Sender Email & Name
        update_option('uem_sender_email', sanitize_email($_POST['uem_sender_email']));
        update_option('uem_sender_name', sanitize_text_field($_POST['uem_sender_name']));
        
        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    $current_color = get_option('uem_primary_color', '#6366F1');
    $logout_slug = get_option('uem_logout_redirect_slug', 'login');
    ?>
    <div class="wrap">
        <h1>Global Configuration</h1>
        <form method="post">
            <table class="form-table">
                <!-- Secțiunea Brand -->
                <tr>
                    <th scope="row">Brand Color</th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="color" id="uem_color_picker" value="<?php echo $current_color; ?>" style="height: 30px; width: 50px; padding: 0; border: 1px solid #ccc; cursor: pointer;">
                            <input type="text" name="uem_color" id="uem_color_text" value="<?php echo $current_color; ?>" placeholder="#6366F1" class="regular-text" style="width: 100px; font-family: monospace;">
                        </div>
                    </td>
                </tr>
                
                <!-- Secțiunea EMAIL SENDER (Adăugată aici pentru vizibilitate) -->
                <tr style="border-top: 1px solid #ccc;"><td colspan="2"><h3>Mail Server Settings</h3></td></tr>
                <?php uem_render_mail_settings_fields(); ?>

                <!-- Secțiunea Redirect -->
                <tr style="border-top: 1px solid #ccc;"><td colspan="2"><h3>Other Settings</h3></td></tr>
                <tr>
                    <th scope="row">Logout Redirect Page</th>
                    <td>
                        <code><?php echo home_url('/'); ?></code>
                        <input type="text" name="uem_logout_slug" value="<?php echo esc_attr($logout_slug); ?>" placeholder="login" style="width: 150px;">
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Changes', 'primary', 'save_uem_settings'); ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const picker = document.getElementById('uem_color_picker');
            const textInput = document.getElementById('uem_color_text');
            if(picker && textInput) {
                picker.addEventListener('input', () => textInput.value = picker.value.toUpperCase());
                textInput.addEventListener('input', () => {
                    let val = textInput.value.trim();
                    if (val.length === 6 && val[0] !== '#') val = '#' + val;
                    if (/^#[0-9A-F]{6}$/i.test(val)) picker.value = val;
                });
            }
        });
    </script>
    <?php
}

// 3. ORGANIZERS MANAGEMENT
function uem_render_organizers_page() {
    if (isset($_POST['uem_add_org'])) {
        $u = sanitize_user($_POST['u']); 
        $e = sanitize_email($_POST['e']); 
        $p = $_POST['p'];
        
        if (!email_exists($e) && !username_exists($u)) {
            $user_id = wp_create_user($u, $p, $e);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id); 
                $user->set_role('uem-organizer');
                echo '<div class="updated"><p>Organizer Created.</p></div>';
            }
        } else {
            echo '<div class="error"><p>User or Email already exists.</p></div>';
        }
    }
    $organizers = get_users(['role' => 'uem-organizer']);
    ?>
    <div class="wrap">
        <h1>Organizers</h1>
        <form method="post" style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px;">
            <input type="text" name="u" placeholder="Username" required>
            <input type="email" name="e" placeholder="Email" required>
            <input type="password" name="p" placeholder="Password" required>
            <input type="submit" name="uem_add_org" class="button button-primary" value="Add Organizer">
        </form>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>User</th><th>Email</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($organizers as $org) : ?>
                    <tr><td><strong><?php echo $org->display_name; ?></strong></td><td><?php echo $org->user_email; ?></td><td><a href="<?php echo get_edit_user_link($org->ID); ?>">Edit</a></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// 4. EMAIL TEMPLATES (Rămâne neschimbat, e ok)
function uem_render_email_settings() {
    $email_templates = [
        'reg_success' => [
            'label'   => 'Successful Event Registration',
            'desc'    => 'Sent when a user successfully joins an event.',
            'default_sub' => 'Registration Confirmed: {event_title}',
            'default_body'=> '<h2>Hello {name},</h2><p>You have successfully registered for <strong>{event_title}</strong>.</p><p>Event Date: {event_date}<br>Location: {event_location}</p>',
            'placeholders'=> '{name}, {event_title}, {event_date}, {event_location}'
        ],
        'acc_confirm' => [
            'label'   => 'Account Confirmation',
            'desc'    => 'Sent when a new user account is created.',
            'default_sub' => 'Welcome to Unisfera!',
            'default_body'=> '<h2>Welcome {name},</h2><p>Your account has been created. Login URL: {login_url}</p>',
            'placeholders'=> '{name}, {login_url}'
        ]
    ];

    if (isset($_POST['uem_save_emails'])) {
        foreach ($email_templates as $key => $tpl) {
            update_option("uem_email_{$key}_subject", sanitize_text_field($_POST["sub_{$key}"]));
            update_option("uem_email_{$key}_body", wp_kses_post($_POST["body_{$key}"]));
        }
        echo '<div class="updated"><p>Saved!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Email Templates</h1>
        <form method="post">
            <?php foreach ($email_templates as $key => $tpl) : 
                $sub = get_option("uem_email_{$key}_subject", $tpl['default_sub']);
                $body = get_option("uem_email_{$key}_body", $tpl['default_body']);
            ?>
                <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-bottom:20px;">
                    <h3><?php echo $tpl['label']; ?></h3>
                    <input type="text" name="sub_<?php echo $key; ?>" value="<?php echo esc_attr($sub); ?>" style="width:100%; margin-bottom:10px;">
                    <?php wp_editor($body, "body_{$key}", ['textarea_rows' => 5]); ?>
                    <p><code><?php echo $tpl['placeholders']; ?></code></p>
                </div>
            <?php endforeach; ?>
            <?php submit_button('Save Emails', 'primary', 'uem_save_emails'); ?>
        </form>
    </div>
    <?php
}

// 5. SHORTCODES REFERENCE (Neschimbat)
function uem_render_shortcodes_page() {
    ?>
    <div class="wrap">
        <h1>Shortcode Reference</h1>
        <p>Use <code>[uem_events_list]</code> to display events.</p>
    </div>
    <?php
}

// 6. EVENT METADATA (Reparată pentru consistență și securitate)
add_action('add_meta_boxes', function() {
    add_meta_box('uem_details', 'Event Metadata', 'uem_mb_html', 'uem_event', 'side');
});

function uem_mb_html($post) {
    // Folosim nonce pentru securitate
    wp_nonce_field('uem_save_meta', 'uem_meta_nonce');
    
    // ATENȚIE: Am schimbat cheia în _uem_event_start_date pentru a se potrivi cu restul codului
    $d = get_post_meta($post->ID, '_uem_event_start_date', true);
    $l = get_post_meta($post->ID, '_uem_event_location', true);
    
    echo '<p>Date (ex: 24 May 2026):<br><input type="text" name="uem_date" value="'.esc_attr($d).'" style="width:100%"></p>';
    echo '<p>Location:<br><input type="text" name="uem_loc" value="'.esc_attr($l).'" style="width:100%"></p>';
}

add_action('save_post', function($post_id) {
    // Verificări de securitate
    if (!isset($_POST['uem_meta_nonce']) || !wp_verify_nonce($_POST['uem_meta_nonce'], 'uem_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['uem_date'])) update_post_meta($post_id, '_uem_event_start_date', sanitize_text_field($_POST['uem_date']));
    if (isset($_POST['uem_loc'])) update_post_meta($post_id, '_uem_event_location', sanitize_text_field($_POST['uem_loc']));
});

// Helper pentru randarea câmpurilor de email (folosit în Settings)
function uem_render_mail_settings_fields() {
    ?>
    <tr valign="top">
        <th scope="row">Sender Email Address</th>
        <td>
            <input type="email" name="uem_sender_email" value="<?php echo esc_attr(get_option('uem_sender_email', get_option('admin_email'))); ?>" class="regular-text" />
            <p class="description">Email-ul oficial (ex: office@unisfera.ro).</p>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row">Sender Name</th>
        <td>
            <input type="text" name="uem_sender_name" value="<?php echo esc_attr(get_option('uem_sender_name', 'Unisfera Events')); ?>" class="regular-text" />
            <p class="description">Numele care apare în inbox.</p>
        </td>
    </tr>
    <?php
}

function uem_render_payment_settings() {
    if (isset($_POST['uem_save_payment_settings']) && check_admin_referer('uem_payment_settings')) {
        update_option('uem_payment_mode', sanitize_key($_POST['uem_payment_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox');
        update_option('uem_netopia_pos_signature', sanitize_text_field($_POST['uem_netopia_pos_signature'] ?? ''));
        if (!empty($_POST['uem_netopia_api_key'])) update_option('uem_netopia_api_key', uem_payment_encrypt(trim($_POST['uem_netopia_api_key'])));
        echo '<div class="updated"><p>Payment settings saved.</p></div>';
    }
    $mode=get_option('uem_payment_mode','sandbox'); $signature=get_option('uem_netopia_pos_signature',''); $has_key=(bool)get_option('uem_netopia_api_key',''); ?>
    <div class="wrap"><h1>Unisfera Payments</h1><p>NETOPIA API v2 - card payments. Keep Sandbox enabled until NETOPIA validates your implementation.</p><form method="post"><table class="form-table"><tr><th>Environment</th><td><select name="uem_payment_mode"><option value="sandbox" <?php selected($mode,'sandbox'); ?>>Sandbox / Testing</option><option value="live" <?php selected($mode,'live'); ?>>Live</option></select></td></tr><tr><th>POS Signature</th><td><input class="regular-text" name="uem_netopia_pos_signature" value="<?php echo esc_attr($signature); ?>" required></td></tr><tr><th>NETOPIA API Key</th><td><input class="regular-text" type="password" name="uem_netopia_api_key" autocomplete="new-password" placeholder="<?php echo $has_key?'Saved - enter only to replace':'Enter API key'; ?>"><p class="description">Stored encrypted using WordPress authentication salts and never displayed again.</p></td></tr><tr><th>IPN URL</th><td><code><?php echo esc_html(rest_url('uem/v1/netopia/ipn')); ?></code></td></tr></table><?php wp_nonce_field('uem_payment_settings'); submit_button('Save payment settings','primary','uem_save_payment_settings'); ?></form></div><?php
}
