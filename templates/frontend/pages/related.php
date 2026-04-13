<div class="wps-related-products">
<?php if (!empty($items)) : ?>
        <div class="wps-grid wps-related-grid wps-gap-4<?php echo count($items) === 1 ? ' is-single' : ''; ?>">
            <?php foreach ($items as $item) : ?>
                <?php echo \WpStore\Frontend\Template::render('components/product-card', ['item' => $item, 'currency' => $currency, 'view_label' => 'Lihat']); ?>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="wps-text-sm wps-text-gray-500">Tidak ada produk terkait.</div>
    <?php endif; ?>
</div>
