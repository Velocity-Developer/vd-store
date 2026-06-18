<?php
$id = isset($id) ? (int) $id : 0;
$title = isset($title) ? (string) $title : '';
$image_src = isset($image_src) ? (string) $image_src : '';
$items = isset($items) && is_array($items) ? $items : [];

$ptype_single = get_post_meta((int) $id, '_store_product_type', true);
$is_digital_single = ($ptype_single === 'digital') || (bool) get_post_meta((int) $id, '_store_is_digital', true);
?>
<div class="wps-product-gallery" data-wps-product-gallery data-gallery-id="<?php echo esc_attr((string) $id); ?>">
<?php if (count($items) > 1) : ?>
    <div class="wps-position-relative wps-w-full wps-products-carousel wps-product-gallery-main" data-wps-carousel data-cell-align="center" data-contain="true" data-wrap-around="true" data-page-dots="true" data-prev-next-buttons="true" data-draggable="true">
        <div class="main-carousel carousel-main" id="wps-main-carousel-<?php echo esc_attr((string) $id); ?>">
            <?php foreach ($items as $index => $gi) : ?>
                <div class="carousel-cell wps-mx-0">
                    <button type="button" class="wps-product-gallery-open" data-gallery-open data-gallery-index="<?php echo esc_attr((string) $index); ?>" aria-label="<?php echo esc_attr(sprintf(__('Lihat gambar %d', 'wp-store'), $index + 1)); ?>">
                        <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url((string) ($gi['full'] ?? '')); ?>" alt="<?php echo esc_attr($title); ?>">
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php echo wps_label_badge_html((int) $id); ?>
        <?php if ($is_digital_single) : ?>
            <span class="wps-digital-badge wps-text-xs wps-text-white">
                <?php echo wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff']); ?>
                <span class="txt wps-text-white wps-text-xs">Digital</span>
            </span>
        <?php endif; ?>
    </div>
    <div class="wps-product-gallery-thumbs wps-mt-3" role="tablist" aria-label="<?php echo esc_attr__('Thumbnail galeri produk', 'wp-store'); ?>">
        <?php foreach ($items as $index => $gi) : ?>
            <button type="button" class="wps-product-gallery-thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" data-gallery-thumb data-gallery-index="<?php echo esc_attr((string) $index); ?>" aria-label="<?php echo esc_attr(sprintf(__('Pilih gambar %d', 'wp-store'), $index + 1)); ?>">
                <img class="wps-img-60 wps-rounded" src="<?php echo esc_url((string) ($gi['thumb'] ?? '')); ?>" alt="">
            </button>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="wps-product-gallery-main-single" style="position:relative;display:block;">
        <button type="button" class="wps-product-gallery-open" data-gallery-open data-gallery-index="0" aria-label="<?php echo esc_attr__('Lihat gambar produk', 'wp-store'); ?>">
            <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($title); ?>">
        </button>
        <?php echo wps_label_badge_html((int) $id); ?>
        <?php if ($is_digital_single) : ?>
            <span class="wps-text-xs wps-text-white" style="position:absolute;top:8px;left:8px;display:flex;align-items:center;background:#111827cc;color:#fff;border-radius:9999px;padding:2px 6px;backdrop-filter:saturate(180%) blur(4px);">
                <?php echo wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff']); ?>
                <span style="color:#fff;font-size:10px;margin-left:4px;">Digital</span>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>
    <div class="wps-product-gallery-viewer" data-gallery-viewer hidden>
        <div class="wps-product-gallery-viewer__backdrop" data-gallery-close></div>
        <div class="wps-product-gallery-viewer__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($title); ?>">
            <button type="button" class="wps-product-gallery-viewer__close" data-gallery-close aria-label="<?php echo esc_attr__('Tutup gambar', 'wp-store'); ?>">&times;</button>
            <?php if (count($items) > 1) : ?>
                <button type="button" class="wps-product-gallery-viewer__nav is-prev" data-gallery-prev aria-label="<?php echo esc_attr__('Gambar sebelumnya', 'wp-store'); ?>">&lsaquo;</button>
                <button type="button" class="wps-product-gallery-viewer__nav is-next" data-gallery-next aria-label="<?php echo esc_attr__('Gambar berikutnya', 'wp-store'); ?>">&rsaquo;</button>
            <?php endif; ?>
            <div class="wps-product-gallery-viewer__content">
                <img src="" alt="<?php echo esc_attr($title); ?>" data-gallery-viewer-image>
            </div>
        </div>
    </div>
    <div class="wps-display-none" data-gallery-images>
        <?php foreach ($items as $index => $gi) : ?>
            <span data-gallery-image="<?php echo esc_attr((string) $index); ?>" data-full="<?php echo esc_url((string) ($gi['full'] ?? '')); ?>"></span>
        <?php endforeach; ?>
    </div>
</div>
