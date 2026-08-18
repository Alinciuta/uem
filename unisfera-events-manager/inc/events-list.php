<?php
add_shortcode('uem_events_list', 'uem_render_events_list_page');

function uem_render_events_list_page() {
    $today     = date('Y-m-d');
    $sort_time = isset($_GET['uem_sort']) ? sanitize_text_field($_GET['uem_sort']) : 'upcoming';
    $orderby   = isset($_GET['uem_orderby']) ? sanitize_text_field($_GET['uem_orderby']) : 'date';
    $month     = isset($_GET['uem_month']) ? sanitize_text_field($_GET['uem_month']) : '';
    $search    = isset($_GET['uem_search']) ? sanitize_text_field($_GET['uem_search']) : '';
    
    // Determinăm pagina curentă
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
        'post_type'      => 'uem_event',
        'posts_per_page' => 24, // Limita de 24 evenimente per pagina
        'paged'          => $paged,
        'post_status'    => 'publish',
        's'              => $search,
    );

    // Sortarea vizuală
    if ($orderby === 'title') {
        $args['orderby'] = 'title';
        $args['order']   = 'ASC';
    } else {
        $args['meta_key'] = '_uem_event_start_date';
        $args['orderby']  = 'meta_value';
        $args['order']    = ($sort_time === 'past') ? 'DESC' : 'ASC';
    }

    $meta_query = array('relation' => 'AND');

    if ($sort_time === 'upcoming') {
        $meta_query[] = array(
            'key'     => '_uem_event_end_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE'
        );
    } elseif ($sort_time === 'past') {
        $meta_query[] = array(
            'key'     => '_uem_event_end_date',
            'value'   => $today,
            'compare' => '<',
            'type'    => 'DATE'
        );
    }

    if (!empty($month)) {
        $meta_query[] = array('key' => '_uem_event_start_date', 'value' => '-' . $month . '-', 'compare' => 'LIKE');
    }

    $args['meta_query'] = $meta_query;

    $query = new WP_Query($args);
    $primary = defined('UEM_PRIMARY_COLOR') ? UEM_PRIMARY_COLOR : '#E74C3C';

    ob_start(); ?>
    
    <style>
        .uem-search-container { max-width: 800px; margin: 0 auto 20px; position: relative; }
        .uem-search-input { width: 100%; padding: 15px 20px; border: 2px solid #eee; border-radius: 50px; font-size: 16px; transition: all 0.3s; }
        .uem-search-input:focus { border-color: <?php echo $primary; ?>; outline: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        .uem-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .uem-actions-left { display: flex; gap: 10px; }
        
        .uem-btn-filter { background: #fff; border: 1px solid #ddd; padding: 10px 20px; border-radius: 25px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .uem-btn-filter:hover { background: #f9f9f9; }
        
        .uem-filter-panel { display: none; background: #fff; border: 1px solid #eee; padding: 10px; border-radius: 25px; margin-bottom: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .uem-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; }
        .uem-filter-label { display: block; font-size: 12px; font-weight: 600; color: #888; margin-bottom: 8px; text-transform: uppercase; }
        .uem-filter-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 15px; background: #fff; }

        .uem-pagination { text-align: center; margin-top: 40px; display: flex; justify-content: center; gap: 8px; }
        .uem-pagination a, .uem-pagination span { padding: 8px 16px; border-radius: 8px; border: 1px solid #eee; text-decoration: none; color: #555; font-weight: 600; }
        .uem-pagination .current { background: <?php echo $primary; ?>; color: #fff; border-color: <?php echo $primary; ?>; }
    </style>

    <div class="uem-page-wrapper">
        <form method="GET" action="">
            <div class="uem-search-container">
                <input type="text" name="uem_search" class="uem-search-input" placeholder="Search for an event" value="<?php echo esc_attr($search); ?>">
                <button type="submit" style="position:absolute; right:15px; top:15px; background:none; border:none; cursor:pointer;">🔍︎</button>
            </div>

            <div class="uem-toolbar">
                <div class="uem-actions-left">
                    <button type="button" class="uem-btn-filter" id="btn-toggle-filters" style="font-size: 12px;">
                        Filters <?php echo (!empty($month)) ? '<b style="color:'.$primary.'">(1)</b>' : ''; ?>
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

            <div class="uem-filter-panel" id="filters-panel" style="<?php echo (!empty($month) || $sort_time !== 'upcoming') ? 'display:block;' : ''; ?>">
                <div class="uem-filter-grid">
                    <div>
                        <label class="uem-filter-label">Event status</label>
                        <select name="uem_sort" onchange="this.form.submit()" class="uem-filter-select">
                            <option value="upcoming" <?php selected($sort_time, 'upcoming'); ?>>Upcoming Events</option>
                            <option value="past" <?php selected($sort_time, 'past'); ?>>Past Events</option>
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

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px;">
            <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); 
                $start_date = get_post_meta(get_the_ID(), '_uem_event_start_date', true);
                $location = get_post_meta(get_the_ID(), '_uem_event_location', true);
                if (has_post_thumbnail()) {
            $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
        } else {
            // Preluăm imaginea direct din folderul assets al plugin-ului
            $thumb_url = plugins_url('assets/media/no-preview-event-image.png', dirname(__FILE__));
        }
            ?>
                
                <div class="uem-card" style="background:#fff; border-radius:16px; padding:15px; border:1px solid #eee; box-shadow:0 4px 5px rgba(0,0,0,0.05);">
                    <a href="<?php the_permalink(); ?>" style="display:block;">
                <div style="width:100%; height:180px; border-top-left-radius:8px; border-top-right-radius:8px; overflow:hidden; margin-bottom: 10px; background: url('<?php echo esc_url($thumb_url); ?>') center/cover no-repeat;"></div>
            </a>
                    <h3 style="margin:0 0 5px 0; font-size:18px; color: #111;"><?php the_title(); ?></h3>
                    <p style="color:#888; font-size:13px;">📅 <?php echo esc_html($start_date); ?></p>
                    <p style="color:#888; font-size:13px; margin-bottom:5px;">📍 <?php echo esc_html($location); ?></p>
                    <a href="<?php the_permalink(); ?>" style="background:<?php echo $primary; ?>; color:#fff; font-size: 14px; display:block; text-align:center; padding:10px; border-radius:20px; text-decoration:none; font-weight:600;">View event</a>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- PAGINARE -->
        <div class="uem-pagination">
            <?php 
                echo paginate_links(array(
                    'total'   => $query->max_num_pages,
                    'current' => $paged,
                    'format'  => '?paged=%#%',
                ));
            ?>
        </div>

        <?php wp_reset_postdata(); else : ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #bbb;">No events found.</div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('btn-toggle-filters').addEventListener('click', function() {
            var panel = document.getElementById('filters-panel');
            panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        });
    </script>

    <?php return ob_get_clean();
}