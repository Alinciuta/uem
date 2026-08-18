<?php
/**
 * Custom Template for uem_event CPT
 * Forces Header, Footer, and unique render
 */

get_header(); // This pulls your Unisfera Website Menu

echo '<main id="primary" class="site-main uem-single-event-wrapper">';

while ( have_posts() ) : the_post();
    // We call the render function from event-template.php
    if (function_exists('uem_render_event_page')) {
        echo uem_render_event_page();
    } else {
        the_content();
    }
endwhile;

echo '</main>';

get_footer(); // This pulls your Unisfera Website Footer