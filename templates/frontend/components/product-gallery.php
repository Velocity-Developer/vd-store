<?php
$id = isset($id) ? (int) $id : 0;
$title = isset($title) ? (string) $title : '';
$image_src = isset($image_src) ? (string) $image_src : '';
$items = isset($items) && is_array($items) ? $items : [];

$ptype_single = get_post_meta((int) $id, '_store_product_type', true);
$is_digital_single = ($ptype_single === 'digital') || (bool) get_post_meta((int) $id, '_store_is_digital', true);
?>
<?php if (count($items) > 1) : ?>
    <div class="wps-position-relative wps-w-full wps-products-carousel" data-wps-carousel data-cell-align="center" data-contain="true" data-wrap-around="true" data-page-dots="true" data-prev-next-buttons="true" data-draggable="true">
        <div class="main-carousel carousel-main" id="wps-main-carousel-<?php echo esc_attr((string) $id); ?>">
            <?php foreach ($items as $gi) : ?>
                <div class="carousel-cell wps-mx-0">
                    <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url((string) ($gi['full'] ?? '')); ?>" alt="<?php echo esc_attr($title); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($is_digital_single) : ?>
            <span class="wps-digital-badge wps-text-xs wps-text-white">
                <?php echo wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff']); ?>
                <span class="txt wps-text-white wps-text-xs">Digital</span>
            </span>
        <?php endif; ?>
    </div>
    <div class="wps-mt-2 wps-products-carousel" data-wps-carousel data-as-nav-for="#wps-main-carousel-<?php echo esc_attr((string) $id); ?>" data-cell-align="left" data-contain="true" data-wrap-around="false" data-page-dots="false" data-prev-next-buttons="false" data-draggable="true">
        <div class="main-carousel carousel-nav">
            <?php foreach ($items as $gi) : ?>
                <div class="carousel-cell wps-mr-2" style="width:64px;">
                    <img class="wps-img-60 wps-rounded" src="<?php echo esc_url((string) ($gi['thumb'] ?? '')); ?>" alt="">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else : ?>
    <div style="position:relative;display:block;">
        <img class="wps-w-full wps-rounded wps-img-320" src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($title); ?>">
        <?php if ($is_digital_single) : ?>
            <span class="wps-text-xs wps-text-white" style="position:absolute;top:8px;left:8px;display:flex;align-items:center;background:#111827cc;color:#fff;border-radius:9999px;padding:2px 6px;backdrop-filter:saturate(180%) blur(4px);">
                <?php echo wps_icon(['name' => 'cloud-download', 'size' => 12, 'stroke_color' => '#ffffff']); ?>
                <span style="color:#fff;font-size:10px;margin-left:4px;">Digital</span>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>
