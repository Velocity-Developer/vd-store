<?php
/**
 * The template for displaying all single store products
 */

get_header();

?>
<div class="wps-container wps-mx-auto wps-my-8 wps-pt-4">
    <?php
    while (have_posts()) :
        the_post();
        // The content is filtered by WpStore\Frontend\Shortcode::filter_single_content
        // which renders templates/frontend/pages/single-flex.php
        the_content();
    endwhile;
    ?>
</div>
<?php
get_footer();
