<?php
$id = isset($id) ? (int) $id : 0;
$sections = isset($sections) && is_array($sections) ? $sections : [];
$content = isset($content) ? (string) $content : '';

if ($id <= 0) {
    return;
}

$has = static function ($key) use ($sections) {
    return !empty($sections[$key]);
};

$component = static function ($key, $args = []) use ($id, $content) {
    if ($key === 'description' && !isset($args['content'])) {
        $args['content'] = $content;
    }
    return \WpStore\Domain\Product\ProductRenderer::render_component($key, $id, $args);
};
?>
<div class="wps-p-4 single-product">
    <?php if ($has('gallery') || $has('title') || $has('breadcrumb') || $has('price') || $has('rating') || $has('info') || $has('after_summary') || $has('actions') || $has('share')) : ?>
        <div class="wps-flex wps-gap-4 wps-items-start wps-mb-4 product-detail">
            <?php if ($has('gallery')) : ?>
                <div class="wps-w-full" style="flex: 1;">
                    <?php echo $component('gallery'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
            <div style="flex: 1;">
                <?php foreach (['title', 'breadcrumb', 'price', 'rating', 'info', 'after_summary', 'actions', 'share'] as $section) : ?>
                    <?php if ($has($section)) : ?>
                        <?php echo $component($section); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach (['description', 'reviews', 'related'] as $section) : ?>
        <?php if ($has($section)) : ?>
            <?php echo $component($section); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
