<?php
$categories = isset($categories) ? $categories : [];
?>
<div class="wps-categories-list">
    <?php foreach ($categories as $index => $category): ?>
        <div class="wps-category-item" style="<?php echo $index > 0 ? 'border-top: 1px solid #e5e7eb;' : ''; ?> padding: 12px 0;">
            <a href="<?php echo esc_url(get_term_link($category)); ?>" class="wps-text-gray-900 wps-font-normal wps-text-xs" style="text-decoration: none; display: block; transition: color 0.2s;">
                <?php echo esc_html($category->name); ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<style>
    .wps-categories-list .wps-category-item a:hover {
        color: var(--blue-600) !important;
    }
</style>