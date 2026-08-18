<?php
if (!defined('ABSPATH')) exit;

class UEM_Email_Handler {

    // Sursă unică pentru trimitere - centralizăm totul aici
    private static function send($to, $subject, $body) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        // nl2br se asigură că rândurile noi din Admin sunt respectate în HTML
        return wp_mail($to, $subject, nl2br($body), $headers);
    }


    // 1. ACCOUNT CREATION
    public static function send_account_confirmation_dynamic($email, $name) {
    $subject = get_option('uem_email_acc_confirm_subject', 'Welcome to Unisfera!');
    $body    = get_option('uem_email_acc_confirm_body', "Hello {name},\n\nYour account is ready. Log in to start managing events.");
    $user = get_user_by('email', $email);
    if ($user) {
        $fname = get_user_meta($user->ID, 'first_name', true);
        $lname = get_user_meta($user->ID, 'last_name', true);
        $full_name = trim($fname . ' ' . $lname);
        if (!empty($full_name)) {
            $name = $full_name;
        } elseif (empty($name)) {
            $name = $user->display_name;
        }
    }
    if (empty($name)) {
        $name = 'New Member';
    }

    $body = str_replace(
        ['{name}', '{login_url}'], 
        [$name, site_url('/login/')], 
        $body
    );

    return self::send($email, $subject, $body);
}



// 2. REGISTRATION CONFIRMATION
public static function send_registration_confirmation($to, $event_id, $name, $passed_date = '') {
    // A. Preluăm template-ul din setările Admin
    $subject = get_option('uem_email_reg_success_subject', 'Registration Confirmed: {event_title}');
    $body    = get_option('uem_email_reg_success_body', "Hello {name},\n\nYou have successfully joined {event_title}.");

    // B. Extragem detaliile evenimentului
    $event_title = get_the_title($event_id);
    
    // PRIORITATE: Dacă am primit data formatată din template, o folosim. 
    // Dacă nu, o căutăm în DB cu cheia corectĂ (_uem_event_start_date)
    $event_date = !empty($passed_date) ? $passed_date : get_post_meta($event_id, '_uem_event_start_date', true);
    if (empty($event_date)) { $event_date = 'TBA'; }

    $event_loc = get_post_meta($event_id, '_uem_event_location', true) ?: 'To be announced';

    // C. Logica de actualizare a numelui (Sincronizare cu Profilul)
    // Folosim $to pentru că așa se numește variabila de email în argumentele funcției
    $user = get_user_by('email', $to);
    if ($user) {
        $fname = get_user_meta($user->ID, 'first_name', true);
        $lname = get_user_meta($user->ID, 'last_name', true);
        $db_name = trim($fname . ' ' . $lname);
        
        if (!empty($db_name)) {
            $name = $db_name;
        } elseif (empty($name)) {
            $name = $user->display_name;
        }
    }

    if (empty($name)) {
        $name = 'Guest';
    }

    // D. Înlocuim placeholders în Body și Subject
    // ATENȚIE: Asigură-te că aceste tag-uri sunt cele folosite în setările plugin-ului
    $placeholders = ['{name}', '{event_title}', '{event_date}', '{event_location}', '{date}', '{location}'];
    $replacements = [$name, $event_title, $event_date, $event_loc, $event_date, $event_loc];

    $body = str_replace($placeholders, $replacements, $body);
    $subject = str_replace('{event_title}', $event_title, $subject);

    // E. Trimitem (Folosim $to, nu $email)
    return self::send($to, $subject, $body);
}


    // 3. REMINDER
    public static function send_bulk_reminder($event_id, $custom_text) {
        $attendees = get_post_meta($event_id, '_uem_attendees', true) ?: [];
        $event_title = get_the_title($event_id);
        
        foreach ($attendees as $attendee) {
            $email = is_array($attendee) ? $attendee['email'] : get_userdata($attendee)->user_email;
            $name  = is_array($attendee) ? $attendee['name'] : get_userdata($attendee)->display_name;

            $subject = "Reminder: " . $event_title;
            // Aici nu avem template în admin, organizatorul scrie mesajul în dashboard
            self::send($email, $subject, $custom_text);
        }
    }
}