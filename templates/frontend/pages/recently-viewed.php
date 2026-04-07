<?php
$title = isset($title) && is_string($title) && $title !== '' ? $title : __('Produk yang Baru Dilihat', 'wp-store');
$items = isset($items) && is_array($items) ? $items : [];
$currency = isset($currency) ? (string) $currency : 'Rp';

if (empty($items)) {
    return;
}
?>
<div class="wps-p-4">
    <div class="wps-flex wps-items-center wps-justify-between wps-mb-4">
        <div class="wps-text-sm wps-text-gray-900"><?php echo esc_html($title); ?></div>
        <div class="wps-text-xs wps-text-gray-500"><?php echo esc_html(sprintf(_n('%d produk', '%d produk', count($items), 'wp-store'), count($items))); ?></div>
    </div>
    <div class="wps-grid wps-grid-cols-2 wps-md-grid-cols-4">
        <?php foreach ($items as $item) : ?>
            <?php echo \WpStore\Frontend\Template::render('components/product-card', ['item' => $item, 'currency' => $currency, 'view_label' => 'Detail']); ?>
        <?php endforeach; ?>
    </div>
</div>
