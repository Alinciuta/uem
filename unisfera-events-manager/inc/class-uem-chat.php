<?php
/**
 * Unisfera Event Manager - Chat Module
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Securitate

class UEM_Chat {

    public function __construct() {
        // Înregistrăm acțiunea AJAX pentru utilizatori logați
        add_action('wp_ajax_uem_send_message', array($this, 'handle_send_message'));
    }

    /**
     * Randarea mesajelor sub formă de bule de chat
     */
    public static function render_messages($ev_id) {
        $comments = get_comments(array(
            'post_id' => $ev_id,
            'status'  => 'approve',
            'order'   => 'ASC',
            'number'  => 50
        ));

        if (!$comments) {
            echo '<div style="text-align:center;color:#999;padding:40px 20px;font-size:13px;">No messages yet.</div>';
            return;
        }

        $current_user_id = get_current_user_id();

        foreach ($comments as $comment) {
            $is_me = ($comment->user_id == $current_user_id);
            $class = $is_me ? 'me' : 'other';
            
            echo '
            <div class="uem-chat-row '.$class.'">
                <div class="uem-msg-content">
                    <span class="uem-msg-info">' . esc_html($comment->comment_author) . ' • ' . get_comment_date('H:i', $comment) . '</span>
                    <div class="uem-msg-bubble">
                        ' . esc_html($comment->comment_content) . '
                    </div>
                </div>
            </div>';
        }
    }

    /**
     * Procesarea mesajului trimis prin AJAX
     */
    public function handle_send_message() {
        // Verificăm securitatea
        check_ajax_referer('uem_chat_secure', 'nonce');

        $ev_id   = isset($_POST['ev_id']) ? intval($_POST['ev_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $user    = wp_get_current_user();

        if ($ev_id > 0 && !empty($message) && is_user_logged_in()) {
            $comment_id = wp_insert_comment(array(
                'comment_post_ID'      => $ev_id,
                'comment_author'       => $user->display_name,
                'comment_author_email' => $user->user_email,
                'comment_content'      => $message,
                'user_id'              => $user->ID,
                'comment_type'         => 'comment',
                'comment_approved'     => 1,
            ));

            if ($comment_id) {
                wp_send_json_success();
            }
        }
        
        wp_send_json_error();
    }
}

// Inițializăm clasa imediat ce fișierul este încărcat
new UEM_Chat();