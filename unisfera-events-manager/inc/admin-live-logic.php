<?php
add_shortcode('uem_admin_live_logic', 'uem_render_admin_live_settings');

function uem_render_admin_live_settings() {
    $ev_id = isset($_GET['ev_id']) ? intval($_GET['ev_id']) : 0;
    
    // Security: Trebuie să fie autorul evenimentului sau admin
    if (!$ev_id || !current_user_can('edit_post', $ev_id)) {
        return '<div class="uem-page-wrapper"><p style="font-size:13px;">Unauthorized access.</p></div>';
    }

    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    $message = '';

    // --- LOGICA DE SALVARE UNIFICATĂ ---
    if (isset($_POST['uem_update_stream'])) {
        // Salvează URL-ul Video
        update_post_meta($ev_id, '_uem_event_video_url', esc_url_raw($_POST['video_url']));
        $useful_info = isset($_POST['useful_info']) ? sanitize_textarea_field($_POST['useful_info']) : '';
        update_post_meta($ev_id, '_uem_live_useful_info', $useful_info);
        
        // Salvează starea Live (Checkbox)
        $live_status = isset($_POST['live_enabled']) ? '1' : '0';
        update_post_meta($ev_id, '_uem_live_enabled', $live_status);
        
        // Salvează starea Chat (Checkbox)
        $chat_status = isset($_POST['chat_enabled']) ? '1' : '0';
        update_post_meta($ev_id, '_uem_chat_enabled', $chat_status);

        $message = '<div style="background:#d4edda; padding:12px; color:#155724; border-radius:8px; margin-bottom:15px; font-size:13px; text-align:center; border: 1px solid #c3e6cb;">Settings updated successfully!</div>';
    }

    // Preluare date actuale din baza de date
    $current_video = get_post_meta($ev_id, '_uem_event_video_url', true);
    $is_live_on    = get_post_meta($ev_id, '_uem_live_enabled', true);
    $is_chat_on    = get_post_meta($ev_id, '_uem_chat_enabled', true);
    $useful_info   = get_post_meta($ev_id, '_uem_live_useful_info', true);

    ob_start(); ?>
    
    <div class="uem-page-wrapper">
        
        <?php echo $message; ?>

        <div class="uem-card" style="background:#fff; padding:30px; border-radius:15px; border:1px solid #f0f0f0; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h3 style="margin-top:0; font-size: 1.2rem; font-weight: 700; color:#1a1a1a;">Live Session Control for <?php echo get_the_title($ev_id); ?></h3>
            
            <form method="POST">
                <div style="background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 1px;">
                    <label style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <input type="checkbox" name="live_enabled" value="1" <?php checked($is_live_on, '1'); ?>> 
                        Activate the live session
                    </label>
                    <p style="font-size: 11px; color: #999; margin: 5px 0 15px 25px;">When disabled, attendees cannot access the live room even if they are registered.</p>

                    <label style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <input type="checkbox" name="chat_enabled" value="1" <?php checked($is_chat_on, '1'); ?>> 
                        Enable Live Q&A Chat
                    </label>
                    <p style="font-size: 11px; color: #999; margin: 5px 0 0 25px;">Show or hide the interaction sidebar in the live page.</p>
                </div>

                <div style="margin-bottom:5px; padding: 20px;">
                    <label style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Live Stream / Video URL
                    </label>
                    <input type="text" 
                           name="video_url" 
                           class="uem-input" 
                           value="<?php echo esc_attr($current_video); ?>" 
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;"
                           placeholder="Ex: https://www.youtube.com/watch?v=xxxx">
                    <small style="color:#aaa;">Paste your YouTube, Vimeo link or Embed URL.</small>
                </div>

                <div style="margin-bottom:5px; padding: 20px;">
                    <label style="display:block; font-weight:bold; margin-bottom:8px; font-size:14px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Useful Information
                    </label>
                    <textarea name="useful_info"
                              class="uem-input"
                              rows="5"
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing:border-box;"
                              placeholder="Add links, instructions, contact details, or reminders for attendees."><?php echo esc_textarea($useful_info); ?></textarea>
                    <small style="color:#aaa;">Shown in the live room only when this field contains text.</small>
                </div>
                
                <hr style="border:0; border-top:1px solid #eee; margin: 25px 0;">

                <div style="display: flex; align-items: center; gap: 20px;">
                    <button type="submit" 
                            name="uem_update_stream" 
                            class="uem-btn-primary" 
                            style="background:<?php echo $primary; ?>; color:#fff; border:none; padding:12px 25px; border-radius:8px; font-weight:bold; cursor:pointer; transition:0.3s;">
                        Save settings
                    </button>
                    
                    <a href="<?php echo site_url('/organizer-dashboard/'); ?>" style="color:#888; text-decoration:none; font-size:13px; font-weight: 600;">
                        Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php 
    return ob_get_clean();
}
