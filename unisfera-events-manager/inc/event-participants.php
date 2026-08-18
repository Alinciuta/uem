<?php

add_shortcode('uem_event_participants', 'uem_render_participants_page');

function uem_render_participants_page() {
    $ev_id = isset($_GET['ev_id']) ? intval($_GET['ev_id']) : 0;
    if (!$ev_id || !current_user_can('edit_post', $ev_id)) {
        return 'Access denied or event not found.';
    }

    // --- FORCE ENQUEUE STYLE ---
    wp_enqueue_style('uem-style', plugins_url('assets/css/uem-style.css', __FILE__));

    $all_attendees = get_post_meta($ev_id, '_uem_attendees', true) ?: [];
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';

    // --- DELETE PARTICIPANT LOGIC ---
    if (isset($_POST['uem_delete_participant'])) {
        $index_to_delete = intval($_POST['participant_index']);
        if (isset($all_attendees[$index_to_delete])) {
            unset($all_attendees[$index_to_delete]);
            $all_attendees = array_values($all_attendees); // Reindex
            update_post_meta($ev_id, '_uem_attendees', $all_attendees);
            echo '<div style="color:green; margin-bottom:10px;">Participant removed.</div>';
        }
    }

    // --- IMPORT CSV LOGIC ---
    if (isset($_POST['uem_submit_import']) && isset($_FILES['uem_import_file'])) {
        $file = $_FILES['uem_import_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            fgetcsv($handle); // Skip header
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $email = sanitize_email($data[1]);
                if ($email) {
                    $exists = false;
                    foreach($all_attendees as $a) {
                        if (is_array($a) && $a['email'] === $email) { $exists = true; break; }
                    }
                    if (!$exists) {
                        $all_attendees[] = [
                            'name'  => sanitize_text_field($data[0]),
                            'email' => $email,
                            'type'  => 'guest'
                        ];
                    }
                }
            }
            fclose($handle);
            update_post_meta($ev_id, '_uem_attendees', $all_attendees);
            echo '<div style="color:green; margin-bottom:10px;">Import successful!</div>';
        }
    }

    // --- PAGINATION LOGIC ---
    $items_per_page = 50;
    $total_items = count($all_attendees);
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Slice the array to show only current page items
    $attendees = array_slice($all_attendees, $offset, $items_per_page, true);
    
    $csv_export_url = add_query_arg(['uem_export_csv' => $ev_id, '_wpnonce' => wp_create_nonce('uem_export_' . $ev_id)], home_url());
    $csv_template_url = add_query_arg('uem_download_template', '1', home_url());

    ob_start(); ?>
    <div class="uem-participants-wrapper" style="max-width: 900px; margin: auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin:0; color: #3b3b3b">Participants: <?php echo get_the_title($ev_id); ?> (<?php echo $total_items; ?>)</h3>
        </div>

        <!-- TOOLBAR: IMPORT & EXPORT -->
        <div style="margin-bottom: 50px; padding: 20px; background: #f8fafc; border-radius: 10px;">
            <form method="post" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <a href="<?php echo $csv_export_url; ?>" style="border: 2px solid <?php echo $primary; ?>; border-radius: 8px; background: #fff; color: <?php echo $primary; ?>; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 500; height: 32px; display: inline-flex; align-items: center; box-sizing: border-box;">
                    Export participants
                </a>
                <div style="border-left: 2px solid #ddd; height: 25px; margin: 0 5px;"></div>
                <input type="file" name="uem_import_file" accept=".csv" required style="font-size: 12px; max-width: 200px;">
                <button type="submit" name="uem_submit_import" style="border: 2px solid <?php echo $primary; ?>; border-radius: 8px; background: #fff; color: <?php echo $primary; ?>; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 600; height: 32px; padding: 0 15px; box-sizing: border-box;">
                    Upload CSV
                </button>
                <a href="<?php echo $csv_template_url; ?>" style="color: #64748b; text-decoration: underline; font-size: 11px; font-style: italic; margin-right: 10px;">
                    Import template
                </a>
            </form>
        </div>

        <!-- PARTICIPANTS LIST WRAPPER -->
        <div class="uem-participants-table-wrapper">
            <table class="uem-participants-table" style="width:100%; border-collapse: collapse; font-size: 14px; margin-bottom: 15px;">
                <thead>
                    <tr style="background:#eee; text-align:left;">
                        <th style="padding:10px;">Name</th>
                        <th style="padding:10px;">Email</th>
                        <th style="padding:10px;">Type</th>
                        <th style="padding:10px; text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendees)) : ?>
                        <tr><td colspan="4" style="padding:20px; text-align:center; color:#888;">No participants found on this page.</td></tr>
                    <?php else : 
                        foreach ($attendees as $index => $attendee) : 
                            $is_array = is_array($attendee);
                            $name = $is_array ? ($attendee['name'] ?? 'N/A') : get_userdata($attendee)->display_name;
                            $email = $is_array ? ($attendee['email'] ?? 'N/A') : get_userdata($attendee)->user_email;
                            $type = $is_array ? 'Guest' : 'User';
                    ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;" data-label="Name"><?php echo esc_html($name); ?></td>
                            <td style="padding:10px;" data-label="Email"><?php echo esc_html($email); ?></td>
                            <td style="padding:10px;" data-label="Type"><span style="font-size:10px; background:#e2e8f0; padding:2px 5px; border-radius:3px;"><?php echo $type; ?></span></td>
                            <td style="padding:10px; text-align:center;" data-label="Action">
                                <form method="post" onsubmit="return confirm('Remove this participant?');" style="margin:0;">
                                    <input type="hidden" name="participant_index" value="<?php echo $index; ?>">
                                    <button type="submit" name="uem_delete_participant" style="background:none; border:none; color:<?php echo $primary; ?>; cursor:pointer; font-size:12px; font-weight:bold;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- PAGINATION NAVIGATION -->
            <?php if ($total_pages > 1) : ?>
                <div style="display: flex; justify-content: center; gap: 8px; margin: 20px 0;">
                    <?php for ($i = 1; $i <= $total_pages; $i++) : 
                        $page_url = add_query_arg('paged', $i);
                        $is_active = ($i == $current_page);
                    ?>
                        <a href="<?php echo esc_url($page_url); ?>" style="text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; <?php echo $is_active ? "background:$primary; color:#fff;" : "background:#f1f5f9; color:#475569; border: 1px solid #e2e8f0;"; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="text-align: center; margin-top: 10px; margin-bottom: 20px;">
        <br>
        <a href="<?php echo site_url('/organizer-dashboard/'); ?>" style="color: #888; text-decoration: none; font-weight: 600;">← Back to dashboard</a>
        <br>
    </div>
    
    <?php
    return ob_get_clean();
}