<?php
/**
 * UEM Organizer Dashboard - Dynamic View
 * Shortcode: [uem_organizer_dashboard]
 */

add_shortcode('uem_organizer_dashboard', 'uem_render_modern_dashboard');

function uem_render_modern_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="uem-page-wrapper"><p style="font-size:13px;">Access denied.</p></div>';
    }

    $user = wp_get_current_user();
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    $is_organizer = in_array('uem-organizer', (array) $user->roles);
    $is_admin     = current_user_can('manage_options');
    $dashboard_notice = '';

    // --- HANDLE DASHBOARD ACTIONS (Status Change & Delete) ---
    if (isset($_POST['uem_dashboard_action']) && wp_verify_nonce($_POST['uem_dashboard_nonce'], 'uem_dashboard_action_nonce')) {
        $event_id = intval($_POST['event_id']);
        $post = get_post($event_id);

        // Security check: Only author or admin can modify
        if ($post && ($post->post_author == $user->ID || $is_admin)) {
            $action = $_POST['uem_dashboard_action'];

            if (in_array($action, ['set_draft', 'set_cancelled', 'set_publish'])) {
                $status = 'publish';
                if ($action === 'set_draft') $status = 'draft';
                if ($action === 'set_cancelled') $status = 'cancelled';
                wp_update_post(['ID' => $event_id, 'post_status' => $status, 'post_type' => 'uem_event']);
            }
            
            elseif ($action === 'delete_event') {
                if ($post->post_status === 'publish') {
                    $dashboard_notice = '<div style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; padding:12px; border-radius:8px; margin-bottom:15px; font-size:13px; text-align:center;">Please set this event as draft before deleting it.</div>';
                } else {
                    wp_delete_post($event_id, true);
                    echo '<meta http-equiv="refresh" content="0">';
                    exit;
                }
            }
            if (!$dashboard_notice) {
                echo '<meta http-equiv="refresh" content="0">';
                exit;
            }
        }
        if (!$dashboard_notice) {
            echo '<meta http-equiv="refresh" content="0">';
            exit;
        }
    }

    ob_start(); ?>
    <div class="uem-page-wrapper" style="max-width: 900px; margin: auto; padding: 5px 5px;">
        
        <div style="margin-bottom: 5px;">
            <h2 style="margin: 0 0 5px 0; font-weight: 600; text-align: center; font-size: 1.4rem;">Welcome, <?php echo esc_html($user->display_name); ?></h2>
            <br>
        </div>

        <?php echo $dashboard_notice; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, auto)); gap: 15px; margin-bottom: 10px;">
            <?php if ($is_organizer || $is_admin) : ?>
                <a href="<?php echo site_url('/submit-event/'); ?>" style="background: <?php echo $primary; ?>; color: #fff; text-decoration: none; text-align: center; font-size: 14px; padding: 10px 20px; border-radius: 40px; font-weight: bold;">
                Create New Event
                </a>
            <?php endif; ?>

            <a href="<?php echo site_url('/edit-profile/'); ?>" style="background: #fff; color: <?php echo $primary; ?>; border: 1.5px solid <?php echo $primary; ?>; text-decoration: none; text-align: center; font-size: 14px; padding: 10px 20px; border-radius: 40px; font-weight: bold;">
                Edit Profile
            </a>

            <a href="<?php echo site_url('/events/'); ?>" style="background: #fff; color: <?php echo $primary; ?>; border: 1.5px solid <?php echo $primary; ?>; text-decoration: none; text-align: center; font-size: 14px; padding: 10px 20px; border-radius: 40px; font-weight: bold;">
                Browse Events
            </a>
        </div>

        <div class="uem-card" style="background: #fff; border: 1px solid #eee; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            
            <?php if ($is_organizer || $is_admin) : ?>
                <h3 style="margin-top: 0; margin-bottom: 15px; font-weight: 600; color: #555; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px;">Your Events</h3>
                <?php echo uem_render_events_table_helper($user->ID); ?>
            <?php else : ?>
                <p style="text-align: center; margin-top: 0; margin-bottom: 15px; font-weight: 600; color: #555; font-size: 20px; text-transform: uppercase; letter-spacing: 0.5px;">My registrations</p>
                <?php 
                    if (function_exists('uem_render_my_events')) {
                        echo uem_render_my_events(true); 
                    }
                ?>
            <?php endif; ?>

        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 2. TABLE WITH EVENTS ORGANIZER CREATED
function uem_render_events_table_helper($author_id) {
    $events = get_posts([
        'post_type' => 'uem_event',
        'author' => $author_id,
        'posts_per_page' => -1,
        'post_status' => ['publish', 'pending', 'draft', 'cancelled']
    ]);

    if (empty($events)) {
        return '<p style="color:#888; font-size: 13px; margin:0;">You haven\'t created any events yet.</p>';
    }

    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    $nonce = wp_create_nonce('uem_dashboard_action_nonce');
    
    ob_start(); ?>
    
    <style>
        .uem-dashboard-list { display: flex; flex-direction: column; width: 100%; }
        .uem-event-row {
            display: flex;
            flex-direction: column;
            padding: 15px 0;
            border-bottom: 1px solid #f9f9f9;
            gap: 12px;
        }
        .uem-event-info {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            flex-wrap: wrap;
        }
        .uem-event-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-start;
        }
        .uem-event-actions form { display: flex; gap: 8px; flex-wrap: wrap; margin: 0; }
        .uem-confirm-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 99999;
        }
        .uem-confirm-overlay.is-open { display: flex; }
        .uem-confirm-dialog {
            width: min(420px, 100%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            padding: 24px;
            font-family: sans-serif;
        }
        .uem-confirm-dialog h4 { margin: 0 0 8px; font-size: 18px; color: #1f2937; }
        .uem-confirm-dialog p { margin: 0 0 18px; font-size: 14px; line-height: 1.5; color: #6b7280; }
        .uem-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
        .uem-confirm-actions button {
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            min-height: 36px;
            padding: 0 16px;
        }
        .uem-confirm-cancel { background: #fff; border: 1px solid #d1d5db; color: #374151; }
        .uem-confirm-delete { background: #8B0000; border: 1px solid #8B0000; color: #fff; }
        @media (min-width: 768px) {
            .uem-event-row { padding: 20px 5px; }
        }
        @media (max-width: 480px) {
            .uem-event-actions a, .uem-event-actions button { 
                flex: 1; 
                justify-content: center;
                min-width: 120px;
            }
        }
    </style>

    <div class="uem-dashboard-list">
        <?php foreach ($events as $event) : 
            $attendees = get_post_meta($event->ID, '_uem_attendees', true) ?: [];
            $count = is_array($attendees) ? count($attendees) : 0;
            $event_link = get_permalink($event->ID);
            $edit_url = site_url('/edit-event/?event_id=' . $event->ID);
            $live_url = site_url('/admin-live-page/?ev_id=' . $event->ID);
            $csv_url  = add_query_arg(['uem_export_csv' => $event->ID, '_wpnonce' => wp_create_nonce('uem_export_' . $event->ID)], home_url());
            
            $end_date = get_post_meta($event->ID, '_uem_event_end_date', true);
            $today = current_time('Y-m-d');
            $is_past = !empty($end_date) && $today > $end_date;

            $status_label = ucfirst($event->post_status);
            $status_bg = '#f0f0f0';
            $text_color = '#666';

            if ($is_past) {
                $status_label = 'Past';
                $status_bg = '#e5e7eb';
                $text_color = '#4b5563';
            } elseif ($event->post_status === 'publish') {
                $status_label = 'Published';
                $status_bg = '#16a34a';
                $text_color = '#fff';
            } elseif ($event->post_status === 'draft') {
                $status_label = 'Draft';
                $status_bg = '#b45309';
                $text_color = '#fff';
            } elseif ($event->post_status === 'cancelled') {
                $status_label = 'Cancelled';
                $status_bg = '#d32f2f';
                $text_color = '#fff';
            }
            $participants_url = site_url('/event-participants/?ev_id=' . $event->ID);
            $post_event_url = site_url('/post-event/?ev_id=' . $event->ID);
        ?>
            <div class="uem-event-row">
                <div class="uem-event-info">
                    <a href="<?php echo esc_url($event_link); ?>" class="uem-event-title-link">
                        <strong style="color: #3b3b3b; font-size: 18px;"><?php echo esc_html($event->post_title); ?></strong>
                    </a>
                    <span style="font-size: 9px; padding: 2px 6px; border-radius: 4px; background: <?php echo $status_bg; ?>; color: <?php echo $text_color; ?>; font-weight: 700; text-transform: uppercase;">
                        <?php echo esc_html($status_label); ?>
                    </span>
                    
                </div>


                <div class="uem-event-actions">
                    <a href="<?php echo esc_url($participants_url); ?>" 
                       style="border: 2px solid #333; border-radius: 8px; background: #fff; color: #333; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 600; height: 30px; display: inline-flex; align-items: center; box-sizing: border-box;">Participants (<?php echo $count; ?>)
                    </a>
                    
                    <a href="<?php echo $edit_url; ?>" 
                       style="border: 2px solid #333; border-radius: 8px; background: #fff; color: #333; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 600; height: 30px; display: inline-flex; align-items: center; box-sizing: border-box;">
                        Edit
                    </a>

                    <a href="<?php echo $live_url; ?>" 
                       style="border: 2px solid #333; border-radius: 8px; background: #fff; color: #333; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 600; height: 30px; display: inline-flex; align-items: center; box-sizing: border-box;">
                        Live Setup
                    </a>
                    
                    <a href="<?php echo esc_url($post_event_url); ?>" 
                       style="border: 2px solid #333; border-radius: 8px; background: #fff; color: #333; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 600; height: 30px; display: inline-flex; align-items: center; box-sizing: border-box;">
                        Post event
                    </a>

                    <form method="post" class="uem-dashboard-action-form">
                        <input type="hidden" name="event_id" value="<?php echo $event->ID; ?>">
                        <input type="hidden" name="uem_dashboard_nonce" value="<?php echo $nonce; ?>">
                        
                        <?php if ($event->post_status === 'publish') : ?>
                            <button type="submit" name="uem_dashboard_action" value="set_draft" 
                                    style="border: 2px solid #B45309; border-radius: 8px; background: #fff; color: #B45309; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 600; height: 30px; padding: 0 15px; box-sizing: border-box;">
                                Set as draft
                            </button>
                        <?php elseif ($event->post_status === 'draft' || $event->post_status === 'cancelled') : ?>
                            <button type="submit" name="uem_dashboard_action" value="set_publish" 
                                    style="border: 2px solid #047857; border-radius: 8px; background: #fff; color: #047857; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 600; height: 30px; padding: 0 15px; box-sizing: border-box;">
                                Publish
                            </button>
                        <?php endif; ?>

                        <button type="submit" name="uem_dashboard_action" value="delete_event" class="uem-delete-event-btn"
                                style="border: 2px solid #8B0000; border-radius: 8px; background: #fff; color: #8B0000; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 600; height: 30px; padding: 0 15px; box-sizing: border-box;">
                            Delete
                        </button>
                    </form>

                </div>
            </div><br>
        <?php endforeach; ?>
    </div>

    <div class="uem-confirm-overlay" id="uem-delete-confirm" aria-hidden="true">
        <div class="uem-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="uem-delete-confirm-title">
            <h4 id="uem-delete-confirm-title">Delete this event?</h4>
            <p>This action cannot be undone. Published events must be set as draft before they can be deleted.</p>
            <div class="uem-confirm-actions">
                <button type="button" class="uem-confirm-cancel" id="uem-confirm-cancel">Cancel</button>
                <button type="button" class="uem-confirm-delete" id="uem-confirm-delete">Delete event</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const modal = document.getElementById('uem-delete-confirm');
        const cancelBtn = document.getElementById('uem-confirm-cancel');
        const confirmBtn = document.getElementById('uem-confirm-delete');
        let pendingButton = null;

        document.querySelectorAll('.uem-delete-event-btn').forEach(function(button) {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                pendingButton = button;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                cancelBtn.focus();
            });
        });

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (pendingButton) {
                pendingButton.focus();
            }
            pendingButton = null;
        }

        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
        confirmBtn.addEventListener('click', function() {
            if (!pendingButton) return;
            const form = pendingButton.closest('form');
            if (form) {
                form.querySelectorAll('input[name="uem_dashboard_action"][value="delete_event"]').forEach(function(input) {
                    input.remove();
                });
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'uem_dashboard_action';
                actionInput.value = 'delete_event';
                form.appendChild(actionInput);
                form.submit();
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
