<?php
/**
 * UEM Edit Event - Aligned with Submission Logic 3.1
 */

add_shortcode('uem_edit_event', 'uem_render_edit_event');

function uem_render_edit_event() {
    $ev_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

    if (!$ev_id) {
        return '<p style="color:red; text-align:center; padding:20px;">Error: Event ID missing.</p>';
    }

    $p = get_post($ev_id);
    if (!$p || $p->post_type !== 'uem_event') {
        return '<p style="color:red; text-align:center; padding:20px;">Error: Event not found.</p>';
    }

    // Permisiuni
    $current_user_id = get_current_user_id();
    if ($p->post_author != $current_user_id && !current_user_can('manage_options')) {
        return '<p style="color:red; text-align:center; padding:20px;">Denied. Nu ai voie să editezi acest eveniment.</p>';
    }

    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    $message = '';

    // --- LOGICA DE SALVARE ---
    if (isset($_POST['uem_update_event'])) {
        if (!isset($_POST['uem_edit_nonce']) || !wp_verify_nonce($_POST['uem_edit_nonce'], 'uem_edit_action')) {
            $message = '<p style="color:red;">Error. Security Nonce invalid.</p>';
        } else {
            $ev_start_date = sanitize_text_field($_POST['ev_start_date']);
            $ev_end_date = sanitize_text_field($_POST['ev_end_date']);
            $date_error = uem_get_event_date_range_error($ev_start_date, $ev_end_date);

            if ($date_error) {
                $message = '<div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold; text-align:center;">' . esc_html($date_error) . '</div>';
            } else {
            $updated_post = array(
                'ID'           => $ev_id,
                'post_title'   => sanitize_text_field($_POST['ev_title']),
                'post_content' => wp_kses_post($_POST['ev_desc']),
            );

            $result = wp_update_post($updated_post);

            if (!is_wp_error($result)) {
                // Forțăm activarea comentariilor pentru sistemul de Chat
                wp_set_comment_status($ev_id, 'open');

                // Actualizăm Meta existent
                update_post_meta($ev_id, '_uem_event_start_date', $ev_start_date);
                update_post_meta($ev_id, '_uem_event_start_hour', sanitize_text_field($_POST['ev_start_hour']));
                update_post_meta($ev_id, '_uem_event_end_date',   $ev_end_date);
                update_post_meta($ev_id, '_uem_event_type',       sanitize_text_field($_POST['ev_type']));
                update_post_meta($ev_id, '_uem_display_organizer', sanitize_text_field($_POST['ev_display_org']));
                update_post_meta($ev_id, '_uem_event_location',    sanitize_text_field($_POST['ev_loc']));
                
                // Salvare Câmpuri Noi (Agenda & Speakers)
                update_post_meta($ev_id, '_uem_event_agenda', sanitize_text_field($_POST['uem_event_agenda']));
                update_post_meta($ev_id, '_uem_event_speakers', sanitize_textarea_field($_POST['uem_event_speakers']));
                
                $reg_type = sanitize_text_field($_POST['ev_reg_type']);
                update_post_meta($ev_id, '_uem_registration_type', $reg_type);
                if ($reg_type === 'external') {
                    update_post_meta($ev_id, '_uem_external_reg_link', esc_url_raw($_POST['ev_reg_link']));
                }

                // Banner
                if (!empty($_POST['uem_img_id'])) {
                    set_post_thumbnail($ev_id, intval($_POST['uem_img_id']));
                }

                $message = '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold; text-align:center;">✓ Success! Event updated. <a href="'.get_permalink($ev_id).'">View Event</a></div>';
                $p = get_post($ev_id); // Refresh data
            }
            }
        }
    }

    // Preluăm metadatele actuale
    $ev_start_date = get_post_meta($ev_id, '_uem_event_start_date', true);
    $ev_start_hour = get_post_meta($ev_id, '_uem_event_start_hour', true);
    $ev_end_date   = get_post_meta($ev_id, '_uem_event_end_date', true);
    $ev_type       = get_post_meta($ev_id, '_uem_event_type', true);
    $ev_display_org= get_post_meta($ev_id, '_uem_display_organizer', true);
    $ev_loc        = get_post_meta($ev_id, '_uem_event_location', true);
    $reg_type      = get_post_meta($ev_id, '_uem_registration_type', true);
    $reg_link      = get_post_meta($ev_id, '_uem_external_reg_link', true);
    $ev_agenda      = get_post_meta($ev_id, '_uem_event_agenda', true);
    $ev_speakers   = get_post_meta($ev_id, '_uem_event_speakers', true);
    $img_id        = get_post_thumbnail_id($ev_id);

    ob_start(); ?>
    <div class="uem-page-wrapper">
        <div class="uem-card" style="max-width: 800px; margin: auto; background:#fff; padding:30px; border-radius:12px; border:1px solid #eee;">
            
            <?php echo $message; ?>

            <form method="POST">
                <?php wp_nonce_field('uem_edit_action', 'uem_edit_nonce'); ?>
                
                <label class="uem-edit-label">Event Title *</label>
                <input type="text" name="ev_title" class="uem-input" value="<?php echo esc_attr($p->post_title); ?>" required>
                
                <label class="uem-edit-label">Display Organizer (Optional)</label>
                <input type="text" name="ev_display_org" class="uem-input" value="<?php echo esc_attr($ev_display_org); ?>" placeholder="Name of person or company">

                <label class="uem-edit-label">Event Banner</label>
                <div style="margin-bottom:20px; display:flex; align-items:center; gap:15px;">
                    <div id="uem_img_preview">
                        <?php if($img_id) echo wp_get_attachment_image($img_id, 'thumbnail', false, ["style"=>"border-radius:8px;"]); ?>
                    </div>
                    <input type="hidden" name="uem_img_id" id="uem_img_id" value="<?php echo $img_id; ?>">
                    <button type="button" id="uem_upload_btn" style="padding:10px 15px; border-radius:5px; border:1px solid #ddd; cursor:pointer; background:#f5f5f5;">Change Image</button>
                </div>

                <div style="display: flex; gap: 15px; margin-top:15px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 140px;">
                        <label class="uem-edit-label">Start Date *</label>
                        <input type="date" name="ev_start_date" id="uem_start_date" class="uem-input" value="<?php echo esc_attr($ev_start_date); ?>" required>
                    </div>
                    <div style="flex: 1; min-width: 100px;">
                        <label class="uem-edit-label">Start Time *</label>
                        <input type="time" name="ev_start_hour" class="uem-input" value="<?php echo esc_attr($ev_start_hour); ?>" required>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <label class="uem-edit-label">End Date</label>
                        <input type="date" name="ev_end_date" id="uem_end_date" class="uem-input" value="<?php echo esc_attr($ev_end_date); ?>" min="<?php echo esc_attr($ev_start_date); ?>">
                    </div>
                </div>

                <div style="margin-top:20px; background:#f9f9f9; padding:15px; border-radius:8px;">
                    <label class="uem-edit-label">Event Format:</label>
                    <label><input type="radio" name="ev_type" value="physical" onclick="handleLocChange('physical')" <?php checked($ev_type, 'physical'); ?>> Physical</label> &nbsp;
                    <label><input type="radio" name="ev_type" value="online" onclick="handleLocChange('online')" <?php checked($ev_type, 'online'); ?>> Online</label> &nbsp;
                    <label><input type="radio" name="ev_type" value="hybrid" onclick="handleLocChange('hybrid')" <?php checked($ev_type, 'hybrid'); ?>> Hybrid</label>

                    <div id="loc-container" style="margin-top:10px;">
                        <label class="uem-edit-label">Location / Address</label>
                        <input type="text" name="ev_loc" id="ev_loc_input" class="uem-input" value="<?php echo esc_attr($ev_loc); ?>" placeholder="City, Venue name or 'Online'...">
                    </div>
                </div>

                <div style="margin-top:20px; background:#f0f7ff; padding:15px; border-radius:8px;">
                    <label class="uem-edit-label">Attendee Registration Method:</label>
                    <div style="margin-bottom:10px;">
                        <label><input type="radio" name="ev_reg_type" value="internal" onclick="toggleReg('internal')" <?php checked($reg_type, 'internal'); ?>> Internal Form</label><br>
                        <label><input type="radio" name="ev_reg_type" value="external" onclick="toggleReg('external')" <?php checked($reg_type, 'external'); ?>> External Link</label><br>
                        <label><input type="radio" name="ev_reg_type" value="none" onclick="toggleReg('none')" <?php checked($reg_type, 'none'); ?>> Not applicable</label>
                    </div>

                    <div id="ext-link-container" style="<?php echo ($reg_type === 'external') ? 'display:block;' : 'display:none;'; ?> margin-top:10px;">
                        <label class="uem-edit-label">Registration Link</label>
                        <input type="url" name="ev_reg_link" class="uem-input" value="<?php echo esc_url($reg_link); ?>" placeholder="https://...">
                    </div>
                </div>

                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #f5f5f5;">
                    <strong><h3 style="font-size: 16px; margin-bottom: 15px; color: <?php echo $primary; ?>;">Event Details</h3></strong>

                    <label class="uem-edit-label">Event Agenda (PDF File)</label>
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom:15px;">
                        <input type="text" name="uem_event_agenda" id="uem_event_agenda" class="uem-input" style="margin-bottom:0;" value="<?php echo esc_attr($ev_agenda); ?>" readonly placeholder="No file selected">
                        <button type="button" id="uem_upload_pdf_btn" style="padding:10px 15px; border-radius:5px; border:1px solid #ddd; cursor:pointer; background:#f5f5f5; white-space:nowrap;">Select PDF</button>
                    </div>

                    <label class="uem-edit-label">Speakers</label>
                    <textarea name="uem_event_speakers" class="uem-input" rows="3" placeholder="Enter speakers"><?php echo esc_textarea($ev_speakers); ?></textarea>
                </div>

                <label class="uem-edit-label" style="margin-top:20px;">Event Description</label>
                <div style="margin-bottom:20px; border:1px solid #ddd; border-radius:5px;">
                    <?php wp_editor($p->post_content, 'ev_desc', array('textarea_rows' => 10, 'media_buttons' => false)); ?>
                </div>

                <button type="submit" name="uem_update_event" class="uem-btn-primary" style="width:100%; padding:15px; font-weight:bold; font-size:16px;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
    function handleLocChange(type) {
        const input = document.getElementById('ev_loc_input');
        if (type === 'online') { input.value = 'Online'; } 
        else if (input.value === 'Online') { input.value = ''; }
    }

    function toggleReg(type) {
        document.getElementById('ext-link-container').style.display = (type === 'external') ? 'block' : 'none';
    }

    const startDateInput = document.getElementById('uem_start_date');
    const endDateInput = document.getElementById('uem_end_date');
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            endDateInput.min = startDateInput.value;
        });
    }

    jQuery(document).ready(function($){
        var frame;
        // Handler pentru Banner (Imagine)
        $('#uem_upload_btn').on('click', function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title: 'Select Image', button: { text: 'Use Image' }, multiple: false });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#uem_img_id').val(attachment.id);
                $('#uem_img_preview').html('<img src="'+attachment.url+'" style="width:150px; border-radius:8px; margin-bottom:10px;">');
            });
            frame.open();
        });

        // Handler pentru Agenda (PDF)
        var pdf_frame;
        $('#uem_upload_pdf_btn').on('click', function(e){
            e.preventDefault();
            if (pdf_frame) { pdf_frame.open(); return; }
            pdf_frame = wp.media({ 
                title: 'Select Event Agenda (PDF)', 
                button: { text: 'Use this PDF' }, 
                multiple: false,
                library: { type: 'application/pdf' } 
            });
            pdf_frame.on('select', function() {
                var attachment = pdf_frame.state().get('selection').first().toJSON();
                $('#uem_event_agenda').val(attachment.url);
            });
            pdf_frame.open();
        });
    });
    </script>

    <style>
        .uem-edit-label { display:block; margin-bottom:5px; font-weight:bold; font-size:13px; color:#333; }
        .uem-input { width:100%; padding:10px; border:1px solid #ccc; border-radius:5px; margin-bottom:15px; box-sizing: border-box; }
        .uem-btn-primary { background: <?php echo $primary; ?>; color:#fff; border:none; border-radius:8px; cursor:pointer; }
    </style>

    <?php 
    wp_enqueue_media();
    return ob_get_clean();
}
