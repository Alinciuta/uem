<?php
add_shortcode('uem_submit_event', 'uem_render_event_submission');

function uem_render_event_submission() {
    $user = wp_get_current_user();
    $is_organizer = in_array('uem-organizer', (array) $user->roles);
    $is_admin = current_user_can('administrator');
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C'; 

    if (!$is_organizer && !$is_admin) {
        return '<p style="text-align:center; color:red; padding:20px;">Access Denied. Only organizers can create events.</p>';
    }

    $output = '';

    if (isset($_POST['uem_new_event'])) {
        check_admin_referer('uem_submit_action');

        $ev_start_date = sanitize_text_field($_POST['ev_start_date']);
        $ev_end_date = sanitize_text_field($_POST['ev_end_date']);
        $date_error = uem_get_event_date_range_error($ev_start_date, $ev_end_date);

        if ($date_error) {
            $output .= '<p style="color:#721c24; text-align:center; font-weight:bold; background:#f8d7da; padding:15px; border-radius:10px;">' . esc_html($date_error) . '</p>';
        } else {
            $new_post = array(
                'post_title'   => sanitize_text_field($_POST['ev_title']),
                'post_content' => wp_kses_post($_POST['ev_desc']),
                'post_status'  => 'publish',
                'post_type'    => 'uem_event',
                'post_author'  => get_current_user_id()
            );

            $post_id = wp_insert_post($new_post);

            if ($post_id) {
                // Core Metadata
                update_post_meta($post_id, '_uem_event_start_date', $ev_start_date);
                update_post_meta($post_id, '_uem_event_start_hour', sanitize_text_field($_POST['ev_start_hour']));
                update_post_meta($post_id, '_uem_event_end_date', $ev_end_date);
                update_post_meta($post_id, '_uem_event_type', sanitize_text_field($_POST['ev_type']));
                
                // New: Display Organizer Meta
                update_post_meta($post_id, '_uem_display_organizer', sanitize_text_field($_POST['ev_display_org']));
                
                // Updated Location Logic: Always save ev_loc to ensure "Online" or Address is stored
                update_post_meta($post_id, '_uem_event_location', sanitize_text_field($_POST['ev_loc']));

                // Registration Logic
                $pricing_type = (isset($_POST['ev_pricing_type']) && $_POST['ev_pricing_type'] === 'paid') ? 'paid' : 'free';
                $reg_type = ($pricing_type === 'paid') ? 'internal' : sanitize_text_field($_POST['ev_reg_type']);
                update_post_meta($post_id, '_uem_pricing_type', $pricing_type);
                update_post_meta($post_id, '_uem_registration_type', $reg_type);
                update_post_meta($post_id, '_uem_external_reg_link', ($pricing_type === 'free' && $reg_type === 'external') ? esc_url_raw($_POST['ev_reg_link']) : '');
                update_post_meta($post_id, '_uem_ticket_price', ($pricing_type === 'paid') ? sanitize_text_field($_POST['ev_ticket_price']) : '');
                update_post_meta($post_id, '_uem_ticket_currency', ($pricing_type === 'paid') ? sanitize_text_field($_POST['ev_ticket_currency']) : '');
                update_post_meta($post_id, '_uem_payment_provider', ($pricing_type === 'paid') ? sanitize_text_field($_POST['ev_payment_provider']) : '');
                update_post_meta($post_id, '_uem_auto_invoicing', ($pricing_type === 'paid' && isset($_POST['ev_auto_invoicing'])) ? '1' : '0');
                update_post_meta($post_id, '_uem_qr_scanner', ($pricing_type === 'paid' && isset($_POST['ev_qr_scanner'])) ? '1' : '0');
                $tiers = [];
                foreach ((array) ($_POST['uem_ticket'] ?? []) as $row) {
                    $name = sanitize_text_field($row['name'] ?? ''); $price = str_replace(',', '.', sanitize_text_field($row['price'] ?? '0'));
                    if ($name !== '' && is_numeric($price) && (float)$price >= 0) $tiers[] = ['code'=>sanitize_key(wp_generate_uuid4()),'name'=>$name,'price'=>number_format((float)$price,2,'.',''),'start'=>sanitize_text_field($row['start'] ?? ''),'end'=>sanitize_text_field($row['end'] ?? '')];
                }
                update_post_meta($post_id, '_uem_ticket_tiers', $pricing_type === 'paid' ? $tiers : []);

                // Banner Upload Handling
                if (!empty($_FILES['ev_banner']['name'])) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attachment_id = media_handle_upload('ev_banner', $post_id);
                    if (!is_wp_error($attachment_id)) set_post_thumbnail($post_id, $attachment_id);
                }

                $output .= '<p style="color:green; text-align:center; font-weight:bold; background:#eaffea; padding:15px; border-radius:10px;">Event Created Successfully! <a href="'.get_permalink($post_id).'">View Event</a></p>';
            }
        }
    }
    
    ob_start(); ?>
    
    <div class="uem-page-wrapper">
        <div class="uem-card" style="max-width: 800px; margin: auto;">
            <h3 style="text-align:center; color:<?php echo $primary; ?>;">Create new event</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('uem_submit_action'); ?>
                
                <label class="uem-form-label">Name *</label>
                <input type="text" name="ev_title" class="uem-input" required>
                
                <label class="uem-form-label">Organizer display name(Optional)</label>
                <input type="text" name="ev_display_org" class="uem-input" placeholder="Name of the person or company organizing">

                <label class="uem-form-label">Event Banner (max 10MB)</label>
                <input type="file" name="ev_banner" accept="image/*" class="uem-input">

                <div style="display: flex; gap: 15px; margin-top:15px;">
                    <div style="flex: 2;"><label class="uem-form-label">Start Date *</label><input type="date" name="ev_start_date" id="uem_start_date" class="uem-input" required></div>
                    <div style="flex: 1;"><label class="uem-form-label">Start Time *</label><input type="time" name="ev_start_hour" class="uem-input" required></div>
                    <div style="flex: 2;"><label class="uem-form-label">End Date</label><input type="date" name="ev_end_date" id="uem_end_date" class="uem-input"></div>
                </div>

                <div style="margin-top:20px; background:#f9f9f9; padding:15px; border-radius:8px;">
                    <label class="uem-form-label">Format</label>
                    <label class="uem-radioform-label"><input type="radio" name="ev_type" value="physical" onclick="handleLocChange('physical')" checked> Physical</label> &nbsp;
                    <label class="uem-radioform-label"><input type="radio" name="ev_type" value="online" onclick="handleLocChange('online')"> Online</label> &nbsp;
                    <label class="uem-radioform-label"><input type="radio" name="ev_type" value="hybrid" onclick="handleLocChange('hybrid')"> Hybrid</label>

                    <div id="loc-container" style="margin-top:10px;">
                        <label class="uem-form-label">Location / Address</label>
                        <input type="text" name="ev_loc" id="ev_loc_input" class="uem-input" placeholder="City, Venue name or Online...">
                    </div>
                </div>

                <div style="margin-top:20px; background:#f6f8fb; padding:15px; border-radius:8px;">
                    <label class="uem-form-label">Registration price</label>
                    <label class="uem-radioform-label"><input type="radio" name="ev_pricing_type" value="free" checked onclick="togglePricing()"> Free</label>
                    <label class="uem-radioform-label"><input type="radio" name="ev_pricing_type" value="paid" onclick="togglePricing()"> Paid - card via NETOPIA</label>
                    <div id="uem-ticket-tiers" style="display:none;margin-top:14px"><p style="font-size:12px;color:#64748b">Add ticket types and optional sale windows (for example Early bird / Standard). Currency is RON.</p><div id="uem-tier-list"><div class="uem-tier-row"><input class="uem-input" name="uem_ticket[0][name]" placeholder="Ticket name, e.g. Early bird"><input class="uem-input" name="uem_ticket[0][price]" type="number" min="0" step="0.01" placeholder="Price (RON)"><input class="uem-input" name="uem_ticket[0][start]" type="date"><input class="uem-input" name="uem_ticket[0][end]" type="date"></div></div><button type="button" onclick="addTicketTier()" class="uem-btn-primary" style="padding:8px 12px">+ Add ticket type</button></div>
                </div>

                <div style="margin-top:20px; background:#f9f9f9; padding:15px; border-radius:8px;">
                    <label class="uem-form-label">Attendee Registration</label>
                    <div style="margin-bottom:10px;">
                        <label class="uem-radioform-label"><input type="radio" name="ev_reg_type" value="internal" onclick="toggleReg('internal')" checked> 
                            Yes, automatically create a form
                        </label><br>
                        <label class="uem-radioform-label"><input type="radio" name="ev_reg_type" value="external" onclick="toggleReg('external')"> External Link</label><br>
                        <label class="uem-radioform-label"><input type="radio" name="ev_reg_type" value="none" onclick="toggleReg('none')"> Not applicable</label><br>
                    </div>

                    <div id="ext-link-container" style="display:none; margin-top:10px;">
                        <label class="uem-label">Enter the registration Link</label>
                        <input type="url" name="ev_reg_link" class="uem-input" placeholder="https://your-site.com/tickets">
                    </div>

                    <div id="int-info-box" style="margin-top:10px; font-size:12px; color:#0056b3; background:#e1f0ff; padding:10px; border-radius:5px;">
                        ℹ️ <strong>Smart System Active:</strong> Logged-in users will register with one click. Guests will see a registration form.
                    </div>
                </div>

                <label class="uem-form-label" style="margin-top:20px;">Event Description</label>
                <div style="margin-bottom:20px;">
                    <?php wp_editor('', 'ev_desc', array('textarea_rows' => 8)); ?>
                </div>

                <button type="submit" name="uem_new_event" class="uem-btn-primary" style="width:100%; padding:15px; font-weight:bold;">Create event</button>
            </form>
        </div>
    </div>

    <script>
    function handleLocChange(type) {
        const input = document.getElementById('ev_loc_input');
        if (type === 'online') {
            input.value = 'Online';
        } else if (input.value === 'Online') {
            input.value = '';
        }
    }

    function toggleReg(type) {
        document.getElementById('ext-link-container').style.display = (type === 'external') ? 'block' : 'none';
        document.getElementById('int-info-box').style.display = (type === 'internal') ? 'block' : 'none';
    }

    window.onload = function() {
        toggleReg('internal');
    };

    const startDateInput = document.getElementById('uem_start_date');
    const endDateInput = document.getElementById('uem_end_date');
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            endDateInput.min = startDateInput.value;
        });
    }
    function togglePricing(){document.getElementById('uem-ticket-tiers').style.display=document.querySelector('input[name="ev_pricing_type"]:checked').value==='paid'?'block':'none';}
    function addTicketTier(){const list=document.getElementById('uem-tier-list'),i=list.children.length;list.insertAdjacentHTML('beforeend','<div class="uem-tier-row"><input class="uem-input" name="uem_ticket['+i+'][name]" placeholder="Ticket name"><input class="uem-input" name="uem_ticket['+i+'][price]" type="number" min="0" step="0.01" placeholder="Price (RON)"><input class="uem-input" name="uem_ticket['+i+'][start]" type="date"><input class="uem-input" name="uem_ticket['+i+'][end]" type="date"></div>');}
    </script>
    <style>.uem-tier-row{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:8px}@media(max-width:700px){.uem-tier-row{grid-template-columns:1fr}}</style>

    

    <?php 
    return $output . ob_get_clean();
}
