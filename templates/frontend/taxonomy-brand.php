<?php
get_header();
$term = get_queried_object();
$title = ($term && isset($term->name)) ? (string) $term->name : 'Brand';
?>
<div class="wps-container wps-mx-auto wps-my-8">
    <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-4 wps-pt-4"><?php echo esc_html($title); ?></div>
    <?php echo do_shortcode('[wp_store_shop_with_filters per_page="12"]'); ?>
</div>
<?php
get_footer();
