<?php
/**
 * Post-event evaluations, timed attempts and participation certificates.
 */
if (!defined('ABSPATH')) exit;

function uem_evaluation_can_manage($event_id) {
    $event = get_post($event_id);
    return $event && ($event->post_author == get_current_user_id() || current_user_can('manage_options'));
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
}

function uem_evaluation_has_completed_event($event_id, $user_id) {
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    global $wpdb;
    $table = $wpdb->prefix . 'uem_attendance_logs';
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$table} WHERE event_id = %d AND user_id = %d LIMIT 1", $event_id, $user_id));
}

function uem_evaluation_attempts($event_id) {
    $attempts = get_post_meta($event_id, '_uem_evaluation_attempts', true);
    return is_array($attempts) ? $attempts : [];
}

function uem_evaluation_save_attempts($event_id, $attempts) {
    update_post_meta($event_id, '_uem_evaluation_attempts', $attempts);
}

function uem_evaluation_format_questions($raw) {
    $questions = [];
    foreach ((array) $raw as $item) {
        $question = sanitize_text_field($item['question'] ?? '');
        $options = [];
        $valid_correct = [];
        $correct = array_map('absint', (array) ($item['correct'] ?? []));
        foreach ((array) ($item['options'] ?? []) as $index => $option) {
            $option = sanitize_text_field($option);
            if ($option !== '') {
                $new_index = count($options);
                $options[] = $option;
                if (in_array((int) $index, $correct, true)) $valid_correct[] = $new_index;
            }
        }
        $valid_correct = array_values(array_unique($valid_correct));
        if ($question !== '' && count($options) >= 2 && !empty($valid_correct)) {
            $questions[] = ['question' => $question, 'options' => $options, 'correct' => $valid_correct];
        }
    }
    return $questions;
}

function uem_render_post_event_page() {
    
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';
    if (!is_user_logged_in()) return '<p>Please log in.</p>';
    $event_id = isset($_GET['ev_id']) ? absint($_GET['ev_id']) : 0;
    if (!uem_evaluation_can_manage($event_id)) return '<p>Access denied.</p>';

    $notice = '';
    if (isset($_POST['uem_save_evaluation']) && wp_verify_nonce($_POST['uem_evaluation_nonce'] ?? '', 'uem_save_evaluation_' . $event_id)) {
        $questions = uem_evaluation_format_questions($_POST['questions'] ?? []);
        $duration = max(1, min(240, absint($_POST['duration'] ?? 30)));
        $pass_score = max(0, min(100, absint($_POST['pass_score'] ?? 70)));
        $template_id = absint($_POST['existing_template_id'] ?? 0);
        if (!empty($_FILES['certificate_template']['name'])) {
            $filetype = wp_check_filetype($_FILES['certificate_template']['name']);
            if (($filetype['ext'] ?? '') !== 'pdf') {
                $notice = 'The certificate template must be a PDF file.';
            } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $template_id = media_handle_upload('certificate_template', $event_id);
            if (is_wp_error($template_id)) $template_id = 0;
            }
        }
        update_post_meta($event_id, '_uem_evaluation_questions', $questions);
        update_post_meta($event_id, '_uem_evaluation_duration', $duration);
        update_post_meta($event_id, '_uem_evaluation_pass_score', $pass_score);
        update_post_meta($event_id, '_uem_evaluation_template_id', $template_id);
        update_post_meta($event_id, '_uem_evaluation_active', (isset($_POST['evaluation_active']) && !empty($questions)) ? '1' : '0');
        if (empty($questions)) $notice = 'Add at least one valid multiple-choice question (with two answers and a correct option).';
        elseif ($notice === '') $notice = 'Post-event settings saved.';
    }

    $questions = get_post_meta($event_id, '_uem_evaluation_questions', true); $questions = is_array($questions) ? $questions : [];
    $active = get_post_meta($event_id, '_uem_evaluation_active', true) === '1';
    $duration = absint(get_post_meta($event_id, '_uem_evaluation_duration', true)) ?: 30;
    $pass_score = get_post_meta($event_id, '_uem_evaluation_pass_score', true); $pass_score = ($pass_score === '') ? 70 : absint($pass_score);
    $template_id = absint(get_post_meta($event_id, '_uem_evaluation_template_id', true));
    $attendance_url = add_query_arg(['uem_export_attendance'=>$event_id, '_wpnonce'=>wp_create_nonce('uem_download_'.$event_id)], home_url());
    ob_start(); ?>
    <style>.uem-post{max-width:960px;margin:35px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1f2937}.uem-post-card{background:#fff;border:1px solid #e7eaf0;border-radius:18px;padding:28px;margin-top:18px;box-shadow:0 10px 30px rgba(15,23,42,.05)}.uem-post-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}.uem-post label{font-weight:600;font-size:14px}.uem-post input[type=number],.uem-post input[type=text],.uem-post input:not([type]){box-sizing:border-box;width:100%;margin-top:7px;padding:12px;border:1px solid #d8dee8;border-radius:9px}.uem-post-btn{border:0;border-radius:9px;padding:12px 18px;background:<?php echo esc_attr(UEM_PRIMARY_COLOR); ?>;color:#fff;font-weight:700;text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer}.uem-post-btn--outline{background:#f8fafc;color:#1f2937;border:1px solid #d8dee8}.uem-question{padding:18px;margin:14px 0;border:1px solid #e5e7eb;border-radius:12px;background:#fbfcfe}.uem-option{display:flex;gap:8px;align-items:center;margin-top:9px}.uem-option input[type=text]{margin:0}.uem-helper{color:#64748b;font-size:13px;margin:6px 0 0}</style>
    <div class="uem-post">
        <p><a href="<?php echo esc_url(site_url('/organizer-dashboard/')); ?>">&larr; Back to dashboard</a></p>
        <div class="uem-post-card" style="background:linear-gradient(135deg,#182235,#34435e);color:#fff"><span style="opacity:.7;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">Event management</span><h2 style="margin:8px 0 0">Post event: <?php echo esc_html(get_the_title($event_id)); ?></h2></div>
        <?php if ($notice) : ?><div style="padding:12px;background:#eefbf1;color:#176b30;border-radius:7px"><?php echo esc_html($notice); ?></div><?php endif; ?>
        <section class="uem-post-card"><h3 style="margin-top:0">Attendance report</h3><p class="uem-helper">Export the attendance activity for this live event as an Excel-ready CSV file.</p><a href="<?php echo esc_url($attendance_url); ?> " style="border: 2px solid <?php echo $primary; ?>; border-radius: 8px; background: #fff; color: <?php echo $primary; ?>; text-decoration: none; padding: 0 15px; font-size: 12px; font-weight: 500; height: 32px; display: inline-flex; align-items: center; box-sizing: border-box;">↓ Export attendance report </a></section>
        <form method="post" enctype="multipart/form-data" class="uem-post-card">
            <?php wp_nonce_field('uem_save_evaluation_'.$event_id, 'uem_evaluation_nonce'); ?>
            <h3 style="margin-top:0">Evaluation form</h3><p class="uem-helper">Participants see the timer and required passing score before they start. Once started, the timer cannot be restarted.</p>
            <p><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="evaluation_active" value="1" <?php checked($active); ?>> Activate evaluation form</label></p>
            <div class="uem-post-grid"><label>Time allowed (minutes)<input type="number" name="duration" min="1" max="240" value="<?php echo esc_attr($duration); ?>" required></label><label>Minimum passing score (%)<input type="number" name="pass_score" min="0" max="100" value="<?php echo esc_attr($pass_score); ?>" required></label></div>
            <p><label>Certificate PDF template<br><input style="margin-top:7px" type="file" name="certificate_template" accept="application/pdf"></label><?php if ($template_id) echo ' <span class="uem-helper">Current: <a target="_blank" href="'.esc_url(wp_get_attachment_url($template_id)).'">view PDF</a></span>'; ?></p>
            <input type="hidden" name="existing_template_id" value="<?php echo esc_attr($template_id); ?>">
            <div id="uem-questions">
                <?php foreach ($questions as $i => $q) : uem_evaluation_question_markup($i, $q); endforeach; ?>
            </div>
            <button type="button" class="uem-post-btn uem-post-btn--outline" id="uem-add-question">+ Add question</button>
            <br>
            <div style="display: flex; align-items: center; gap: 20px;">
                <br><br><br>
                <button type="submit" name="uem_save_evaluation" class="uem-btn-primary" style="background:<?php echo $primary; ?>; color:#fff; border:none; padding:12px 25px; border-radius:8px; font-weight:bold; cursor:pointer; transition:0.3s;">Save evaluation settings</button></p>
            <a href="<?php echo site_url('/organizer-dashboard/'); ?>" style="color:#888; text-decoration:none; font-size:13px; font-weight: 600;">
                        Back to Dashboard
                    </a>
            </div>

        </form>
    </div>
    <template id="uem-question-template"><?php uem_evaluation_question_markup('__INDEX__', ['question'=>'','options'=>['',''],'correct'=>[]]); ?></template>
    <script>(function(){const c=document.getElementById('uem-questions'),t=document.getElementById('uem-question-template');document.getElementById('uem-add-question').addEventListener('click',()=>c.insertAdjacentHTML('beforeend',t.innerHTML.replaceAll('__INDEX__',c.children.length)));document.addEventListener('click',e=>{if(!e.target.matches('.uem-add-option'))return;const q=e.target.closest('.uem-question'),i=q.dataset.index,n=q.querySelectorAll('.uem-option').length;q.querySelector('.uem-options').insertAdjacentHTML('beforeend','<label class="uem-option"><input type="checkbox" name="questions['+i+'][correct][]" value="'+n+'"><input type="text" name="questions['+i+'][options][]" placeholder="Response option" required></label>')})})();</script>
    <?php return ob_get_clean();
}
add_shortcode('uem_post_event', 'uem_render_post_event_page');

function uem_evaluation_question_markup($i, $q) { $correct = is_array($q['correct'] ?? null) ? $q['correct'] : [(int)($q['correct'] ?? -1)]; ?>
    <fieldset class="uem-question" data-index="<?php echo esc_attr($i); ?>"><legend style="font-weight:700">Question</legend><input type="text" name="questions[<?php echo esc_attr($i); ?>][question]" value="<?php echo esc_attr($q['question']); ?>" placeholder="Write the question" required><p class="uem-helper">Select every correct response. Participants must select the exact correct combination.</p><div class="uem-options">
        <?php foreach ((array)$q['options'] as $option_index => $option) : ?><label class="uem-option"><input type="checkbox" name="questions[<?php echo esc_attr($i); ?>][correct][]" value="<?php echo $option_index; ?>" <?php checked(in_array($option_index, $correct)); ?>><input type="text" name="questions[<?php echo esc_attr($i); ?>][options][]" value="<?php echo esc_attr($option); ?>" placeholder="Response option" required></label><?php endforeach; ?></div><button type="button" class="uem-post-btn uem-post-btn--outline uem-add-option" style="margin-top:12px;padding:8px 12px">+ Response option</button>
    </fieldset>
<?php }

function uem_render_event_evaluation() {
    if (!is_user_logged_in()) return '<p>Please log in to complete the evaluation.</p>';
    $event_id = isset($_GET['ev_id']) ? absint($_GET['ev_id']) : 0; $user_id = get_current_user_id();
    if (get_post_meta($event_id, '_uem_evaluation_active', true) !== '1') return '<p>The evaluation form is not available.</p>';
    if (!uem_evaluation_has_completed_event($event_id, $user_id)) return '<p>You can complete this evaluation only after attending the live event.</p>';
    $questions = get_post_meta($event_id, '_uem_evaluation_questions', true); if (!is_array($questions) || !$questions) return '<p>The organizer has not added any questions yet.</p>';
    $attempts = uem_evaluation_attempts($event_id); $key = (string)$user_id; $duration = absint(get_post_meta($event_id, '_uem_evaluation_duration', true)) ?: 30;
    if (isset($_POST['uem_start_evaluation']) && wp_verify_nonce($_POST['uem_start_evaluation_nonce'] ?? '', 'uem_start_evaluation_'.$event_id)) {
        $existing = $attempts[$key] ?? [];
        if (empty($existing['passed'])) {
            $attempts[$key] = array_merge($existing, ['status'=>'started', 'started_at'=>time()]);
            uem_evaluation_save_attempts($event_id, $attempts);
        }
    }
    if (isset($_POST['uem_submit_evaluation']) && wp_verify_nonce($_POST['uem_submit_evaluation_nonce'] ?? '', 'uem_submit_evaluation_'.$event_id)) {
        $attempt = $attempts[$key] ?? []; $started = absint($attempt['started_at'] ?? 0);
        if (!$started || time() > $started + ($duration * MINUTE_IN_SECONDS)) { $attempts[$key] = array_merge($attempt, ['status'=>'retry', 'started_at'=>0, 'attempts'=>absint($attempt['attempts'] ?? 0) + 1]); uem_evaluation_save_attempts($event_id, $attempts); return '<div style="max-width:700px;margin:40px auto;padding:36px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08)"><h2 style="font-size:28px;margin:0 0 10px">Time expired</h2><p style="color:#64748b">This attempt is locked because the time limit was reached. You can start a new attempt.</p><a style="display:inline-block;text-decoration:none;background:'.esc_attr(UEM_PRIMARY_COLOR).';color:#fff;padding:14px 20px;border-radius:10px;font-weight:700" href="'.esc_url(site_url('/event-evaluation/?ev_id='.$event_id)).'">Start a new attempt</a></div>'; }
        $correct = 0;
        foreach ($questions as $i => $question) {
            $expected = is_array($question['correct']) ? $question['correct'] : [(int)$question['correct']];
            $answers = array_values(array_unique(array_map('absint', (array) ($_POST['answer'][$i] ?? []))));
            sort($expected); sort($answers);
            if ($answers === $expected) $correct++;
        }
        $pass_score = get_post_meta($event_id, '_uem_evaluation_pass_score', true); $pass_score = ($pass_score === '') ? 70 : absint($pass_score);
        $score = (int) round(($correct / count($questions)) * 100); $passed = $score >= $pass_score;
        $attempt_number = absint($attempt['attempts'] ?? 0) + 1;
        $attempts[$key] = ['status'=>$passed ? 'submitted' : 'retry','started_at'=>$started,'submitted_at'=>time(),'score'=>$score,'passed'=>$passed,'attempts'=>$attempt_number]; uem_evaluation_save_attempts($event_id, $attempts);
        $certificate = $passed ? add_query_arg(['uem_download_certificate'=>$event_id, '_wpnonce'=>wp_create_nonce('uem_certificate_'.$event_id)], home_url()) : '';
        $result_button = 'display:inline-block;text-decoration:none;background:'.esc_attr(UEM_PRIMARY_COLOR).';color:#fff;padding:14px 20px;border-radius:10px;font-weight:700';
        if ($passed) return '<div style="max-width:700px;margin:40px auto;padding:36px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08)"><div style="font-size:34px">✓</div><h2 style="font-size:28px;margin:10px 0">Evaluation completed</h2><p style="font-size:18px;color:#475569">Your score: <strong>'.$score.'%</strong></p><p><a style="'.$result_button.'" href="'.esc_url($certificate).'">Download your certificate</a></p></div>';
        return '<div style="max-width:700px;margin:40px auto;padding:36px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08)"><h2 style="font-size:28px;margin:0 0 10px">Evaluation completed</h2><p style="font-size:18px;color:#475569">Your score: <strong>'.$score.'%</strong></p><p style="color:#64748b">You did not reach the minimum score yet. You can try again.</p><a style="'.$result_button.'" href="'.esc_url(site_url('/event-evaluation/?ev_id='.$event_id)).'">Try again</a></div>';
    }
    $attempt = $attempts[$key] ?? [];
    if (($attempt['status'] ?? '') === 'submitted' && !empty($attempt['passed'])) {
        return '<div style="max-width:700px;margin:40px auto;padding:36px;text-align:center;background:#fff;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.08)"><div style="font-size:34px">✓</div><h2 style="font-size:28px;margin:10px 0">Evaluation completed</h2><p style="font-size:18px;color:#475569">You have already completed this evaluation.</p><p style="font-size:20px;margin:0">Your score: <strong>'.esc_html($attempt['score']).'%</strong></p></div>';
    }
    if (($attempt['status'] ?? '') === 'submitted' && empty($attempt['passed'])) $attempt = array_merge($attempt, ['status'=>'retry', 'attempts'=>max(1, absint($attempt['attempts'] ?? 0))]);
    if (($attempt['status'] ?? '') === 'expired') $attempt = array_merge($attempt, ['status'=>'retry', 'started_at'=>0]);
    if (($attempt['status'] ?? '') === 'retry') unset($attempt['started_at']);
    if (empty($attempt['started_at'])) return uem_render_evaluation_start_prompt($event_id, $duration);
    $remaining = max(0, ($attempt['started_at'] + $duration * MINUTE_IN_SECONDS) - time());
    if (!$remaining) {
        $attempts[$key] = array_merge($attempt, ['status'=>'retry', 'started_at'=>0, 'attempts'=>absint($attempt['attempts'] ?? 0) + 1]);
        uem_evaluation_save_attempts($event_id, $attempts);
        return uem_render_evaluation_start_prompt($event_id, $duration, 'Your previous attempt expired. You can start a new attempt.');
    }
    $pass_score = get_post_meta($event_id, '_uem_evaluation_pass_score', true); $pass_score = ($pass_score === '') ? 70 : absint($pass_score);
    ob_start(); ?><style>.uem-eval{max-width:790px;margin:38px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#182235}.uem-eval-hero{padding:30px;border-radius:20px;background:linear-gradient(135deg,#f8fafc,#e5e7eb);border:1px solid #dfe3e8;color:#111827}.uem-eval-meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:22px}.uem-eval-stat{padding:14px;border-radius:12px;background:#fff;border:1px solid #dfe3e8;color:#111827}.uem-eval-stat b{display:block;font-size:22px;color:#000}.uem-eval-form{margin-top:18px;padding:28px;background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.06)}.uem-eval-question{border:0;border-bottom:1px solid #e8ecf1;padding:0 0 22px;margin:0 0 22px}.uem-eval-choice{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;margin-top:9px;border:1px solid #e1e7ef;border-radius:10px;cursor:pointer}.uem-eval-choice:hover{border-color:<?php echo esc_attr(UEM_PRIMARY_COLOR); ?>;background:#fff8f7}.uem-eval-submit{background:<?php echo esc_attr(UEM_PRIMARY_COLOR); ?>;border:0;border-radius:10px;color:#fff;padding:14px 20px;font-size:15px;font-weight:700;cursor:pointer}</style><div class="uem-eval"><div class="uem-eval-hero"><span style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#475569">Timed evaluation</span><h2 style="margin:8px 0"><?php echo esc_html(get_the_title($event_id)); ?></h2><p style="margin:0;color:#334155">Submit before the timer expires. Your answers are scored automatically.</p><div class="uem-eval-meta"><div class="uem-eval-stat"><span style="color:#475569">Time remaining</span><b id="uem-timer"></b></div><div class="uem-eval-stat"><span style="color:#475569">Minimum score</span><b><?php echo esc_html($pass_score); ?>%</b></div></div></div><form method="post" id="uem-evaluation-form" class="uem-eval-form"><?php wp_nonce_field('uem_submit_evaluation_'.$event_id, 'uem_submit_evaluation_nonce'); foreach ($questions as $i=>$q): ?><fieldset class="uem-eval-question"><legend style="font-weight:700;font-size:17px"><?php echo esc_html(($i+1).'. '.$q['question']); ?></legend><p style="color:#64748b;font-size:13px">Select all answers you consider correct.</p><?php foreach ($q['options'] as $o=>$option): ?><label class="uem-eval-choice"><input type="checkbox" name="answer[<?php echo $i; ?>][]" value="<?php echo $o; ?>"> <span><?php echo esc_html($option); ?></span></label><?php endforeach; ?></fieldset><?php endforeach; ?><button class="uem-eval-submit" name="uem_submit_evaluation">Submit evaluation</button></form></div><script>(function(){let s=<?php echo (int)$remaining; ?>,e=document.getElementById('uem-timer'),f=document.getElementById('uem-evaluation-form');function t(){e.textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0');if(s--<=0){f.querySelectorAll('input,button').forEach(x=>x.disabled=true);f.insertAdjacentHTML('beforeend','<p style="color:#b91c1c;font-weight:700">Time expired. The form is locked.</p>');return}setTimeout(t,1000)}t()})();</script><?php return ob_get_clean();
}
add_shortcode('uem_event_evaluation', 'uem_render_event_evaluation');

function uem_render_evaluation_start_prompt($event_id, $duration, $notice = '') {
    $pass_score = get_post_meta($event_id, '_uem_evaluation_pass_score', true); $pass_score = ($pass_score === '') ? 70 : absint($pass_score);
    ob_start(); ?><style>.uem-start-overlay{max-width:620px;margin:52px auto;padding:34px;text-align:center;background:#fff;border:1px solid #e1e6ec;border-radius:20px;box-shadow:0 18px 40px rgba(15,23,42,.1);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#111827}.uem-start-rules{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:25px 0}.uem-start-rule{padding:18px;background:#f5f6f8;border-radius:12px}.uem-start-rule b{display:block;font-size:24px;margin-top:5px}.uem-start-button{border:0;border-radius:10px;background:<?php echo esc_attr(UEM_PRIMARY_COLOR); ?>;color:#fff;padding:14px 26px;font-weight:700;font-size:16px;cursor:pointer}</style><div class="uem-start-overlay"><div style="font-size:34px">⏱</div><h2 style="font-size:28px;margin:10px 0">Ready to start?</h2><?php if ($notice) : ?><p style="background:#fff7ed;color:#9a3412;padding:10px;border-radius:8px"><?php echo esc_html($notice); ?></p><?php endif; ?><p style="color:#52606d;line-height:1.6">Once you start, the countdown begins immediately. Make sure you can complete the evaluation without interruption.</p><div class="uem-start-rules"><div class="uem-start-rule"><span style="color:#52606d">Time limit</span><b><?php echo esc_html($duration); ?> min</b></div><div class="uem-start-rule"><span style="color:#52606d">Minimum score</span><b><?php echo esc_html($pass_score); ?>%</b></div></div><p style="font-size:13px;color:#64748b">The evaluation locks automatically when the time expires.</p><form method="post"><?php wp_nonce_field('uem_start_evaluation_'.$event_id, 'uem_start_evaluation_nonce'); ?><button class="uem-start-button" name="uem_start_evaluation">Start evaluation</button></form></div><?php return ob_get_clean();
}

function uem_render_my_certificates() {
    if (!is_user_logged_in()) return '<p>Please log in to see your certificates.</p>';
    $user_id = get_current_user_id();
    $events = get_posts(['post_type'=>'uem_event', 'post_status'=>'any', 'posts_per_page'=>-1, 'meta_key'=>'_uem_evaluation_attempts']);
    $certificates = [];
    foreach ($events as $event) {
        $attempt = uem_evaluation_attempts($event->ID)[$user_id] ?? [];
        if (!empty($attempt['passed'])) $certificates[] = ['event'=>$event, 'attempt'=>$attempt];
    }
    ob_start(); ?><style>.uem-certs{max-width:980px;margin:35px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.uem-cert-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(265px,1fr));gap:18px}.uem-cert-card{background:#fff;border:1px solid #e6eaf0;border-radius:16px;padding:22px;box-shadow:0 8px 22px rgba(15,23,42,.05)}.uem-cert-button{display:inline-block;margin-top:15px;padding:11px 15px;border-radius:9px;background:<?php echo esc_attr(UEM_PRIMARY_COLOR); ?>;color:#fff;text-decoration:none;font-weight:700}</style><div class="uem-certs"><h2>My certificates</h2><p style="color:#64748b">Certificates earned by successfully completing an event evaluation.</p><div class="uem-cert-grid"><?php if ($certificates) foreach ($certificates as $item) { $id=$item['event']->ID; $url=add_query_arg(['uem_download_certificate'=>$id,'_wpnonce'=>wp_create_nonce('uem_certificate_'.$id)],home_url()); ?><article class="uem-cert-card"><span style="color:#16a34a;font-weight:700;font-size:13px">✓ CERTIFICATE EARNED</span><h3><?php echo esc_html(get_the_title($id)); ?></h3><p style="color:#64748b;font-size:14px">Score: <?php echo esc_html($item['attempt']['score']); ?>%</p><a href="<?php echo esc_url($url); ?>" style="text-decoration:none; border:1px solid <?php echo $primary; ?>; color:<?php echo $primary; ?>; font-weight:600; font-size:14px; padding:10px; border-radius:20px; text-align:center; display:block; margin-top:10px; box-sizing:border-box;">Download certificate</a></article><?php } else { ?><div class="uem-cert-card" style="grid-column:1/-1;text-align:center;padding:42px"><h3>No certificates yet</h3><p style="color:#64748b">Pass an event evaluation to find your certificate here.</p></div><?php } ?></div></div><?php return ob_get_clean();
}
add_shortcode('uem_my_certificates', 'uem_render_my_certificates');

add_action('init', function() {
    if (!isset($_GET['uem_download_certificate']) || !is_user_logged_in()) return;
    $event_id = absint($_GET['uem_download_certificate']); $user_id = get_current_user_id();
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'uem_certificate_'.$event_id)) wp_die('Security check failed.');
    $attempt = uem_evaluation_attempts($event_id)[$user_id] ?? []; if (empty($attempt['passed'])) wp_die('Certificate unavailable.');
    $name = wp_get_current_user()->display_name; $event_name = get_the_title($event_id);
    $pdf = uem_simple_certificate_pdf($name, $event_name);
    $pdf = apply_filters('uem_certificate_pdf_content', $pdf, $event_id, $user_id, absint(get_post_meta($event_id, '_uem_evaluation_template_id', true)));
    nocache_headers(); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="certificate-'.sanitize_title($event_name).'.pdf"'); echo $pdf; exit;
});

function uem_simple_certificate_pdf($name, $event_name) {
    $text = function($value) { return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(', '\\)', '', ' '], remove_accents(wp_strip_all_tags($value))); };
    $name = $text($name); $x = max(72, (842 - (strlen($name) * 16)) / 2);
    $stream = "BT /F1 30 Tf ".round($x, 2)." 297 Td (".$name.") Tj ET";
    $objects = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>", "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>", "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"];
    $pdf="%PDF-1.4\n"; $offsets=[0]; foreach($objects as $i=>$object){$offsets[] = strlen($pdf); $pdf.=($i+1)." 0 obj\n$object\nendobj\n";} $xref=strlen($pdf); $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n"; for($i=1;$i<=count($objects);$i++) $pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n"; return $pdf."trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
