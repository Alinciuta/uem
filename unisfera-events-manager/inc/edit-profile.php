<?php
/**
 * UEM Edit Profile Module
 * Shortcode: [uem_edit_profile]
 */

add_shortcode('uem_edit_profile', 'uem_render_edit_profile');

function uem_render_edit_profile() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="'.wp_login_url().'">log in</a> to edit your profile.</p>';
    }

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $primary = UEM_PRIMARY_COLOR;
    $message = '';

    $is_organizer = in_array('uem-organizer', (array) $current_user->roles);

    if (isset($_POST['uem_update_profile'])) {
        $email = sanitize_email($_POST['u_email']);
        $display_name = sanitize_text_field($_POST['u_display_name']);
        
        $args = [
            'ID'           => $user_id,
            'user_email'   => $email,
            'display_name' => $display_name,
        ];

        if (!empty($_POST['u_pass'])) {
            $args['user_pass'] = $_POST['u_pass'];
        }

        $update_status = wp_update_user($args);

        if (is_wp_error($update_status)) {
            $message = '<div style="background:#f2dede; color:#a94442; padding:15px; border-radius:8px; margin-bottom:20px;">Error: ' . $update_status->get_error_message() . '</div>';
        } else {
            // Salvăm metadatele suplimentare
            update_user_meta($user_id, 'first_name', sanitize_text_field($_POST['first_name']));
            update_user_meta($user_id, 'last_name', sanitize_text_field($_POST['last_name']));
            update_user_meta($user_id, 'uem_phone', sanitize_text_field($_POST['uem_phone']));
            update_user_meta($user_id, 'uem_city', sanitize_text_field($_POST['uem_city']));
            update_user_meta($user_id, 'uem_country', sanitize_text_field($_POST['uem_country']));
            update_user_meta($user_id, 'uem_workplace', sanitize_text_field($_POST['uem_workplace']));

            $message = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;">Profile updated successfully!</div>';
            $current_user = wp_get_current_user();
        }
    }

    $meta = array(
        'first_name' => get_user_meta($user_id, 'first_name', true),
        'last_name'  => get_user_meta($user_id, 'last_name', true),
        'phone'      => get_user_meta($user_id, 'uem_phone', true),
        'city'       => get_user_meta($user_id, 'uem_city', true),
        'country'    => get_user_meta($user_id, 'uem_country', true),
        'workplace'  => get_user_meta($user_id, 'uem_workplace', true),
    );

    ob_start(); ?>
    <div class="uem-card" style="max-width:800px; margin:auto; padding:30px; border:1px solid #eee; border-radius:15px; background:#fff; box-shadow:0 10px 25px rgba(0,0,0,0.05); font-family:sans-serif;">
        <h3 style="color:<?php echo $primary; ?>; text-align: center; margin-top:0;">Account update</h3>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <div style="margin-bottom:15px;">
                <label class="uem-form-label">Display name (publicly visible)</label>
                <input type="text" name="u_display_name" value="<?php echo esc_attr($current_user->display_name); ?>" class="uem-input">
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label class="uem-form-label">First Name</label>
                    <input type="text" name="first_name" value="<?php echo esc_attr($meta['first_name']); ?>" class="uem-input">
                </div>
                <div style="flex: 1;">
                    <label class="uem-form-label">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo esc_attr($meta['last_name']); ?>" class="uem-input">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label class="uem-form-label">Mobile Number</label>
                    <input type="tel" name="uem_phone" value="<?php echo esc_attr($meta['phone']); ?>" class="uem-input">
                </div>
                <div style="flex: 1;">
                    <label class="uem-form-label">City</label>
                    <input type="text" name="uem_city" value="<?php echo esc_attr($meta['city']); ?>" class="uem-input">
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label class="uem-form-label">
                    Company or Institution <?php echo $is_organizer ? '*' : '(Optional)'; ?>
                </label>
                <input type="text" name="uem_workplace" value="<?php echo esc_attr($meta['workplace']); ?>" <?php echo $is_organizer ? 'required' : ''; ?> class="uem-input">
            </div>

            <div style="margin-bottom:15px;">
                <label class="uem-form-label">Country (Optional)</label>
                <input type="text" name="uem_country" value="<?php echo esc_attr($meta['country']); ?>" class="uem-input">
            </div>

            <div style="margin-bottom:15px;">
                <label class="uem-form-label">Email Address</label>
                <input type="email" name="u_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="uem-input">
            </div>

            <div style="margin-bottom:25px;">
                <label class="uem-form-label">New Password (leave blank to keep current)</label>
                <input type="password" name="u_pass" placeholder="••••••••"  class="uem-input">
            </div>

            <button type="submit" name="uem_update_profile" style="width:100%; background:<?php echo $primary; ?>; color:#fff; border:0; padding:15px; border-radius:8px; font-weight:bold; cursor:pointer; font-size:16px;">
                Save Changes
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
