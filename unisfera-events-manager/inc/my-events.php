<?php
/**
 * My Registered Events Module
 * Shortcode: [uem_my_events]
 */

add_shortcode('uem_my_events', 'uem_render_my_events');

function uem_render_my_events($is_dashboard = false) {
    if (!is_user_logged_in()) {
        return '<div style="max-width: 1000px; margin: 40px auto; padding: 0 20px; text-align:center;">
                    <p>Please <a href="'.site_url('/login').'" style="color:var(--uem-primary); font-weight:bold;">log in</a> to see your registered events.</p>
                </div>';
    }

    $user_id   = get_current_user_id();
    $today     = date('Y-m-d');
    $primary   = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';

    // GET Filter Parameters
    $sort_time = isset($_GET['uem_sort']) ? sanitize_text_field($_GET['uem_sort']) : 'upcoming';
    $orderby   = isset($_GET['uem_orderby']) ? sanitize_text_field($_GET['uem_orderby']) : 'date';
    $month     = isset($_GET['uem_month']) ? sanitize_text_field($_GET['uem_month']) : '';
    $search    = isset($_GET['uem_search']) ? sanitize_text_field($_GET['uem_search']) : '';

    $args = array(
        'post_type'      => 'uem_event',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        's'              => $search,
    );

    // Sorting Logic
    if ($orderby === 'title') {
        $args['orderby'] = 'title';
        $args['order']   = 'ASC';
    } else {
        $args['meta_key'] = '_uem_event_start_date';
        $args['orderby']  = 'meta_value';
        $args['order']    = ($sort_time === 'past') ? 'DESC' : 'ASC';
    }

    // Meta Query for User Registration AND Time/Month Filters
    $meta_query = array('relation' => 'AND');

    // Filter 1: Only show events where the current user is registered
    $meta_query[] = array(
        'relation' => 'OR',
        array('key' => '_uem_attendees', 'value' => '"' . $user_id . '"', 'compare' => 'LIKE'),
        array('key' => '_uem_attendees', 'value' => 'i:' . $user_id . ';', 'compare' => 'LIKE'),
    );

    // Filter 2: Event Status (MODIFICATĂ)
    if ($sort_time === 'upcoming') {
        // Un eveniment este "Upcoming" dacă data de FINAL este astăzi sau în viitor
        $meta_query[] = array(
            'key'     => '_uem_event_end_date', 
            'value'   => $today, 
            'compare' => '>=', 
            'type'    => 'DATE'
        );
    } elseif ($sort_time === 'past') {
        // Un eveniment este "Past" doar dacă data de FINAL a trecut deja
        $meta_query[] = array(
            'key'     => '_uem_event_end_date', 
            'value'   => $today, 
            'compare' => '<', 
            'type'    => 'DATE'
        );
    }

    // Filter 3: Specific Month
    if (!empty($month)) {
        $meta_query[] = array('key' => '_uem_event_start_date', 'value' => '-' . $month . '-', 'compare' => 'LIKE');
    }

    $args['meta_query'] = $meta_query;
    $query = new WP_Query($args);

    ob_start(); ?>
    
    <style>
        .uem-search-container { max-width: 800px; margin: 0 auto 30px; position: relative; }
        .uem-search-input { width: 100%; padding: 15px 20px; border: 2px solid #eee; border-radius: 50px; font-size: 16px; transition: all 0.3s; }
        .uem-search-input:focus { border-color: <?php echo $primary; ?>; outline: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .uem-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .uem-actions-left { display: flex; gap: 10px; }
        .uem-btn-filter { background: #fff; border: 1px solid #ddd; padding: 10px 20px; border-radius: 25px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .uem-btn-filter:hover { background: #f9f9f9; }
        .uem-filter-panel { display: none; background: #fff; border: 1px solid #eee; padding: 20px; border-radius: 25px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .uem-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; }
        .uem-filter-label { display: block; font-size: 12px; font-weight: 700; color: #888; margin-bottom: 8px; text-transform: uppercase; }
        .uem-filter-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 20px; background: #fff; }
    </style>

    <?php if (!$is_dashboard) : ?>
    <div class="uem-page-wrapper" style="max-width: 1100px; margin-left: auto; margin-right: auto; padding: 15px 15px; font-family: sans-serif; box-sizing: border-box;">
        <h3 style="color:<?php echo $primary; ?>; text-align: center; margin-top:0;">My registered events</h3>
    <?php endif; ?>

        <form method="GET" action="">

            <div class="uem-toolbar">
                <div class="uem-actions-left">
                    <button type="button" class="uem-btn-filter" id="btn-toggle-filters-my" style="font-size: 12px;">
                        Filters <?php echo (!empty($month) || $sort_time !== 'upcoming') ? '<b style="color:'.$primary.'">(active)</b>' : ''; ?>
                    </button>
                    
                    <select name="uem_orderby" onchange="this.form.submit()" class="uem-btn-filter" style="font-size: 12px;">
                        <option value="date" <?php selected($orderby, 'date'); ?>>Sort by Date</option>
                        <option value="title" <?php selected($orderby, 'title'); ?>>Sort A-Z</option>
                    </select>
                </div>

                <?php if($search || !empty($month) || $sort_time !== 'upcoming'): ?>
                    <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" style="color: #999; text-decoration: none; font-size: 14px;">✕ Reset Filters</a>
                <?php endif; ?>
            </div>

            <div class="uem-filter-panel" id="filters-panel-my" style="<?php echo (!empty($month) || $sort_time !== 'upcoming') ? 'display:block;' : ''; ?>">
                <div class="uem-filter-grid">
                    <div>
                        <label class="uem-filter-label">Event status</label>
                        <select name="uem_sort" onchange="this.form.submit()" class="uem-filter-select">
                            <option value="upcoming" <?php selected($sort_time, 'upcoming'); ?>>Upcoming</option>
                            <option value="past" <?php selected($sort_time, 'past'); ?>>Past</option>
                        </select>
                    </div>
                    <div>
                        <label class="uem-filter-label">Month</label>
                        <select name="uem_month" onchange="this.form.submit()" class="uem-filter-select">
                            <option value="">All Months</option>
                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                $m_val = str_pad($m, 2, '0', STR_PAD_LEFT);
                                echo '<option value="'.$m_val.'" '.selected($month, $m_val, false).'>'.date('F', mktime(0,0,0,$m,1)).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); 
                $ev_id = get_the_ID();
                $start_date = get_post_meta($ev_id, '_uem_event_start_date', true);
                $location = get_post_meta($ev_id, '_uem_event_location', true);
                if (has_post_thumbnail()) {
            $thumb_url = get_the_post_thumbnail_url($ev_id, 'medium');
        } else {
            // Preluăm imaginea direct din folderul assets al plugin-ului
            $thumb_url = plugins_url('assets/media/no-preview-event-image.png', dirname(__FILE__));
        }
            ?>
                <div style="background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <div style="margin-bottom: 15px;">
                        <a href="<?php the_permalink(); ?>" style="display:block;">
                <div style="width:100%; height:180px; border-top-left-radius:8px; border-top-right-radius:8px; overflow:hidden; margin-bottom: 10px; background: url('<?php echo esc_url($thumb_url); ?>') center/cover no-repeat;"></div>
            </a>
                        <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #111; line-height: 1.3;"><?php the_title(); ?></h3>
                        <div style="color: #777; font-size: 13px; display: flex; flex-direction: column; gap: 5px;">
                            <span>📅 <?php echo esc_html($start_date); ?></span>
                            <span>📍 <?php echo esc_html($location); ?></span>
                        </div>
                    </div>
                    <a href="<?php the_permalink(); ?>" style="text-decoration: none; background: <?php echo $primary; ?>; color: #fff; font-weight: 600; font-size: 14px; padding: 12px; border-radius: 20px; text-align: center; display: block; width: 100%; box-sizing: border-box;">
                        Details
                    </a>
                    <?php if (get_post_meta($ev_id, '_uem_evaluation_active', true) === '1') : ?>
                        <a href="<?php echo esc_url(site_url('/event-evaluation/?ev_id=' . $ev_id)); ?>" style="text-decoration:none; border:1px solid <?php echo $primary; ?>; color:<?php echo $primary; ?>; font-weight:600; font-size:14px; padding:10px; border-radius:20px; text-align:center; display:block; margin-top:10px; box-sizing:border-box;">Complete evaluation</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; wp_reset_postdata(); else : ?>
                <div style="grid-column: 1/-1; background: #fff; padding: 60px 20px; text-align: center; border-radius: 20px; border: 2px dashed #e0e0e0;">
                    <div style="font-size: 40px; margin-bottom: 15px;">🎟️</div>
                    <p style="color: #666; font-size: 18px;">No events found matching your filters.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php if (!$is_dashboard) : ?>
    </div>
    <?php endif; ?>

    <script>
        document.getElementById('btn-toggle-filters-my').addEventListener('click', function() {
            var panel = document.getElementById('filters-panel-my');
            panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        });
    </script>

    <?php return ob_get_clean();
}
