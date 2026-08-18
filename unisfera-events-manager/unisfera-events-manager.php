<?php
/**
 * Plugin Name: Unisfera Events Manager
 * Description: Independent Event Management & Live Streaming System.
 * Version: 3.1
 * Author: Unisfera Events SRL
 */

if (!defined('ABSPATH')) exit;

// 1. GLOBAL CONSTANTS
define('UEM_VERSION', '3.1');
define('UEM_PATH', plugin_dir_path(__FILE__));
define('UEM_URL', plugin_dir_url(__FILE__));
define('UEM_PRIMARY_COLOR', get_option('uem_primary_color', '#E74C3C'));

function uem_get_event_date_range_error($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) {
        return '';
    }

    if ($end_date < $start_date) {
        return 'End date must be equal to or later than the start date.';
    }

    return '';
}

// 2. CONSOLIDATED MODULE INCLUDES
// Audit: All files verified to exist in the /inc/ directory
$modules = [
    'inc/event-template.php',     // Theme bridge / Triple-render fix
    'inc/live-room.php',           // Viewer shortcode [uem_live_event]
    'inc/my-events.php',           // [uem_my_events]
    'inc/organizer-dashboard.php', // [uem_organizer_dashboard]
    'inc/admin-live-logic.php',    // [uem_admin_live_logic]
    'inc/auth-forms.php',          // [uem_login_form]
    'inc/auth-extensions.php',     // [uem_subscriber_signup] & [uem_lost_password]
    'inc/edit-event.php',          // Event editing logic
    'inc/event-submission.php',    // New event submission
    'inc/edit-profile.php',        // [uem_edit_profile]
    'inc/navigation.php',          // Menu navigation logic
    'inc/class-uem-chat.php',          // Chat
    'inc/events-list.php',          // [uem_events_list]
    'inc/event-participants.php',   // [uem_event_participants]   
    'inc/evaluation.php',           // Post-event evaluation and certificates
    'inc/email-handler.php',
    'payments/payments.php',       // Optional payments add-on
    'inc/admin-dashboard.php',
];

foreach ($modules as $module) {
    if (file_exists(UEM_PATH . $module)) {
        require_once UEM_PATH . $module;
    }
}


add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('uem-chat-style', plugins_url('assets/chat-style.css', __FILE__));
});


// 3. CUSTOM POST TYPE: uem_event
add_action('init', 'uem_register_post_type');
function uem_register_post_type() {
    register_post_type('uem_event', [
        'labels' => [
            'name' => 'Events',
            'singular_name' => 'Event',
            'add_new' => 'Add New Event',
            'edit_item' => 'Edit Event',
            'all_items' => 'All Events'
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'event'], 
        'supports' => ['title', 'editor', 'thumbnail'],
        'menu_icon' => 'dashicons-calendar-alt',
        'show_in_rest' => true,
        'show_in_menu' => 'uem-main-menu', // Integrated into Unisfera Manager
    ]);
}

// 4. TEMPLATE BRIDGE (The "Independent" Plugin Fix)
add_filter('template_include', 'uem_force_event_template', 999);
function uem_force_event_template($template) {
    if (is_singular('uem_event')) {
        $plugin_template = UEM_PATH . 'inc/single-uem_event.php';
        if (file_exists($plugin_template)) return $plugin_template;
    }
    return $template;
}

// 5. ACTIVATION: ROLES, PAGES & DATABASE
register_activation_hook(__FILE__, 'uem_manager_activate');
function uem_manager_activate() {
    update_option('users_can_register', 1);

    if (!get_role('uem-organizer')) {
        add_role('uem-organizer', 'Event Organizer', [
            'read' => true, 'edit_posts' => true, 'publish_posts' => true, 'upload_files' => true, 'delete_posts' => true
        ]);
    }
    
    uem_register_post_type();

    // --- CREARE TABEL (Mutat aici pentru siguranță) ---
    global $wpdb;
    $table_name = $wpdb->prefix . 'uem_attendance_logs';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        last_ping datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    if (function_exists('uem_payments_install_tables')) uem_payments_install_tables();

    // --- AUTO-PROVISION PAGES ---
    $pages = [
        'login'                => ['title' => 'Login', 'content' => '[uem_login_form]'],
        'signup'               => ['title' => 'Sign Up', 'content' => '[uem_subscriber_signup]'],
        'organizer-signup'     => ['title' => 'Organizer Sign Up', 'content' => '[uem_signup_form]'],
        'lost-password'        => ['title' => 'Lost Password', 'content' => '[uem_lost_password]'],
        'events-archive'       => ['title' => 'Events List', 'content' => '[uem_events_list]'],
        'organizer-dashboard'  => ['title' => 'Dashboard', 'content' => '[uem_organizer_dashboard]'],
        'live-page'            => ['title' => 'Live Room', 'content' => '[uem_live_event]'],
        'admin-live-page'      => ['title' => 'Admin Live Control', 'content' => '[uem_admin_live_logic]'],
        'my-registered-events' => ['title' => 'My Registered Events', 'content' => '[uem_my_events]'],
        'submit-event'         => ['title' => 'Submit Event', 'content' => '[uem_submit_event]'],
        'edit-event'           => ['title' => 'Edit Event', 'content' => '[uem_edit_event]'],
        'edit-profile'         => ['title' => 'Edit Profile', 'content' => '[uem_edit_profile]'],
        'event-participants'   => ['title' => 'Event Participants', 'content' => '[uem_event_participants]'],
        'post-event'           => ['title' => 'Post event', 'content' => '[uem_post_event]'],
        'event-evaluation'     => ['title' => 'Event evaluation', 'content' => '[uem_event_evaluation]'],
        'my-certificates'      => ['title' => 'My certificates', 'content' => '[uem_my_certificates]'],
        'payment-checkout'     => ['title' => 'Payment checkout', 'content' => '[uem_payment_checkout]'],
        'payment-result'       => ['title' => 'Payment result', 'content' => '[uem_payment_result]'],
    ];

    foreach ($pages as $slug => $page) {
        if (!get_page_by_path($slug)) {
            wp_insert_post([
                'post_title'   => $page['title'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $slug
            ]);
        }
    }
    flush_rewrite_rules();
}
        


// 10. EMAIL TRIGGER
add_action('user_register', 'uem_trigger_registration_email');
function uem_trigger_registration_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    // Încercăm să luăm numele din formularul POST (dacă există câmpurile)
    // Altfel luăm din user_meta sau display_name
    $fname = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : get_user_meta($user_id, 'first_name', true);
    $lname = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : get_user_meta($user_id, 'last_name', true);
    
    $full_name = trim($fname . ' ' . $lname);
    if (empty($full_name)) {
        $full_name = $user->display_name;
    }

    if (class_exists('UEM_Email_Handler')) {
        UEM_Email_Handler::send_account_confirmation_dynamic($user->user_email, $full_name);
    }
}


// 9. GLOBAL ASSETS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('uem-style', UEM_URL . 'assets/uem-style.css', [], UEM_VERSION);
    wp_add_inline_style('uem-style', ":root { --uem-primary: " . UEM_PRIMARY_COLOR . "; }");
});

// 10. FORCE LOGOUT REDIRECT - METODA DEFINITIVĂ
add_action('wp_logout', 'uem_absolute_logout_redirect', 1);

function uem_absolute_logout_redirect() {
    wp_destroy_current_session();
    wp_clear_auth_cookie();
    
    // Luăm slug-ul din setări, dacă nu există folosim 'login'
    $slug = get_option('uem_logout_redirect', 'login');
    $login_url = home_url('/' . $slug);

    if (!headers_sent()) {
        header("Cache-Control: no-cache, must-revalidate");
        header("Location: " . $login_url);
        exit;
    } else {
        echo '<script type="text/javascript">window.location.href="' . $login_url . '";</script>';
        exit;
    }
}


// 11. CSV Export Handling (RĂMÂNE CU ACEST NUME)
add_action('init', 'uem_handle_csv_export');
function uem_handle_csv_export() {
    if (isset($_GET['uem_export_csv']) && is_user_logged_in()) {
        $event_id = intval($_GET['uem_export_csv']);
        
        // Security: Check Nonce and Permissions
        if (!wp_verify_nonce($_GET['_wpnonce'], 'uem_export_' . $event_id)) return;
        
        $post = get_post($event_id);
        if (!$post || ($post->post_author != get_current_user_id() && !current_user_can('manage_options'))) return;

        $attendees = get_post_meta($event_id, '_uem_attendees', true) ?: [];
        if (empty($attendees)) return;

        // Set Headers for Download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=participants-event-' . $event_id . '.csv');

        $output = fopen('php://output', 'w');
        // Column Headers (Adjust based on your registration fields)
        fputcsv($output, ['Name', 'Email', 'Phone', 'Company', 'Type', 'Registration Date']);

        foreach ($attendees as $attendee) {
            if (is_numeric($attendee)) {
                // It's a User ID (Registered User)
                $user_info = get_userdata($attendee);
                fputcsv($output, [
                    $user_info->display_name,
                    $user_info->user_email,
                    get_user_meta($attendee, 'billing_phone', true), // Example meta
                    'Member',
                    'Registered User',
                    ''
                ]);
            } else {
                // It's an array (Guest User)
                fputcsv($output, [
                    $attendee['name'] ?? '',
                    $attendee['email'] ?? '',
                    $attendee['phone'] ?? '',
                    $attendee['company'] ?? '',
                    'Guest',
                    $attendee['date'] ?? ''
                ]);
            }
        }
        fclose($output);
        exit;
    }
}


// 12. PROTECȚIE ACCES ȘI REDIRECT SMART
add_action('admin_init', 'uem_prevent_admin_lockout');
function uem_prevent_admin_lockout() {
    if (current_user_can('manage_options')) {
        // Administratorul are voie peste tot, oprim orice redirectare forțată aici
        return;
    }
}


// 13. LOGIN REDIRECT FOR LOGGED IN USERS
add_action('template_redirect', 'uem_redirect_logged_in_users');

function uem_redirect_logged_in_users() {
    if ( is_user_logged_in() && is_page('login') ) {
        
        // Verificăm dacă există un redirect_to setat în URL (de ex. către Live Room)
        if ( isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to']) ) {
            $redirect_url = esc_url_raw($_REQUEST['redirect_to']);
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        // Dacă nu avem o destinație specifică, mergem la dashboard ca de obicei
        wp_redirect( site_url('/organizer-dashboard') );
        exit;
    }
}

// 14. AJAX HANDLERS (Pentru monitorizarea prezenței)
// Folosim wp_ajax_ deoarece monitorizăm doar utilizatorii logați
add_action('wp_ajax_uem_track_attendance', 'uem_handle_attendance_ping');

function uem_handle_attendance_ping() {
    // Verificare Nonce pentru securitate
    check_ajax_referer('uem_track_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }

    global $wpdb;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $user_id  = get_current_user_id();
    $table    = $wpdb->prefix . 'uem_attendance_logs';

    if ($event_id > 0) {
        $wpdb->insert($table, array(
            'event_id'  => $event_id,
            'user_id'   => $user_id,
            'last_ping' => current_time('mysql')
        ));
        wp_send_json_success();
    }
    
    wp_die();
}


// 15. Attendance report download
add_action('init', 'uem_handle_frontend_attendance_export');

function uem_handle_frontend_attendance_export() {
    // Verificăm dacă parametrul de export este prezent și utilizatorul este logat
    if (isset($_GET['uem_export_attendance']) && is_user_logged_in()) {
        
        // Curățăm ID-ul evenimentului primit prin URL
        $event_id = intval($_GET['uem_export_attendance']);
        if ($event_id <= 0) return;

        // Verificăm securitatea cu Nonce pentru a preveni descărcările neautorizate
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'uem_download_' . $event_id)) {
            wp_die('Security check failed.');
        }

        // Validăm permisiunile: doar autorul evenimentului sau un administrator pot exporta
        $event = get_post($event_id);
        if (!$event || ($event->post_author != get_current_user_id() && !current_user_can('manage_options'))) {
            wp_die('Nu ai permisiunea de a descărca acest raport.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'uem_attendance_logs';

        // Preluăm datele agregate: prima apariție, ultima și numărul total de minute (ping-uri)
        // Folosim {$table} pentru a asigura interpretarea corectă a numelui tabelului
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, 
                   MIN(last_ping) as first_seen, 
                   MAX(last_ping) as last_seen, 
                   COUNT(*) as minutes_active
            FROM {$table} 
            WHERE event_id = %d 
            GROUP BY user_id
        ", $event_id));

        // Dacă rezultatele sunt goale, verificăm dacă există date în tabel pentru acest ID
        if (empty($results)) {
            $check_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $event_id));
            
            if ($check_exists > 0) {
                wp_die('Datele există, dar nu au putut fi procesate. Contactați suportul.');
            } else {
                wp_die('No online presence detected for this event.');
            }
        }

        // Curățăm buffer-ul de ieșire pentru a evita caracterele parazite în CSV
        if (ob_get_length()) ob_end_clean();

        // Setăm headerele pentru descărcarea fișierului
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance-event-'.$event_id.'-'.date('Y-m-d').'.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // Adăugăm Byte Order Mark (BOM) pentru ca Excel să recunoască caracterele UTF-8 (diacritice)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Capul de tabel pentru CSV
        fputcsv($output, array('User ID', 'Name', 'Email', 'Joined At', 'Last Active', 'Total Minutes (Presence)'));

        foreach ($results as $row) {
            $user_info = get_userdata($row->user_id);
            fputcsv($output, array(
                $row->user_id,
                $user_info ? $user_info->display_name : 'Unknown User',
                $user_info ? $user_info->user_email : 'N/A',
                $row->first_seen,
                $row->last_seen,
                $row->minutes_active
            ));
        }
        
        fclose($output);
        exit;
    }
}


// 16. Import CSV (MODIFICAT NUMELE FUNCȚIEI)
add_action('admin_init', 'uem_handle_csv_import_logic'); // Nume nou aici

function uem_handle_csv_import_logic() { // Nume nou aici
    if (isset($_POST['uem_submit_import']) && isset($_FILES['uem_import_file'])) {
        $event_id = intval($_POST['uem_import_event_id']);
        
        if (!current_user_can('edit_post', $event_id)) {
            wp_die('Nu ai permisiunea necesară.');
        }

        $file = $_FILES['uem_import_file']['tmp_name'];
        if (!is_file($file)) return;

        $handle = fopen($file, 'r');
        // Preluăm lista actuală (poate conține ID-uri sau email-uri)
        $attendees = get_post_meta($event_id, '_uem_attendees', true) ?: [];
        
        $imported_count = 0;
        $guest_count = 0;

        // Sarim peste header-ul CSV
        fgetcsv($handle); 

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $email = sanitize_email($data[1]); // Presupunem Email pe coloana 2
            if (empty($email)) continue;

            $user = get_user_by('email', $email);

            if ($user) {
                // CAZ 1: Utilizatorul există în WP
                if (!in_array($user->ID, $attendees)) {
                    $attendees[] = $user->ID;
                    $imported_count++;
                }
            } else {
                // CAZ 2: Utilizatorul NU există -> Înregistrăm ca GUEST (stocăm email-ul)
                if (!in_array($email, $attendees)) {
                    $attendees[] = $email;
                    $imported_count++;
                    $guest_count++;
                }
            }
        }
        fclose($handle);

        update_post_meta($event_id, '_uem_attendees', $attendees);

        // Redirect cu detalii despre import
        wp_safe_redirect(add_query_arg([
            'uem_import_success' => $imported_count,
            'uem_guests' => $guest_count
        ], wp_get_referer()));
        exit;
    }
}


// 17. LOGICĂ GENERARE TEMPLATE CSV PENTRU IMPORT
add_action('init', 'uem_handle_csv_template_download');
function uem_handle_csv_template_download() {
    if (isset($_GET['uem_download_template'])) {
        
        // Verificăm dacă utilizatorul este logat și are permisiuni de organizator
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_die('Access denied.');
        }

        // Setăm headerele pentru descărcare fișier
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=unisfera-import-template.csv');
        
        // Creăm stream-ul CSV
        $output = fopen('php://output', 'w');
        
        // Capetele de tabel (Header)
        fputcsv($output, ['Name', 'Email']);
        
        // Un rând de exemplu (Opțional)
        fputcsv($output, ['John Doe', 'john.doe@example.com']);
        
        fclose($output);
        exit;
    }
}

// Add pages introduced in newer plugin versions without requiring a deactivate/reactivate cycle.
add_action('init', 'uem_ensure_post_event_pages', 20);
function uem_ensure_post_event_pages() {
    $pages = [
        'post-event'       => ['title' => 'Post event', 'content' => '[uem_post_event]'],
        'event-evaluation' => ['title' => 'Event evaluation', 'content' => '[uem_event_evaluation]'],
        'my-certificates'  => ['title' => 'My certificates', 'content' => '[uem_my_certificates]'],
        'payment-checkout' => ['title' => 'Payment checkout', 'content' => '[uem_payment_checkout]'],
        'payment-result'   => ['title' => 'Payment result', 'content' => '[uem_payment_result]'],
    ];
    foreach ($pages as $slug => $page) {
        if (!get_page_by_path($slug)) {
            wp_insert_post(['post_title'=>$page['title'], 'post_content'=>$page['content'], 'post_status'=>'publish', 'post_type'=>'page', 'post_name'=>$slug]);
        }
    }
}
