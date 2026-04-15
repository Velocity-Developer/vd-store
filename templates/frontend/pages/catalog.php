<?php
$currency = isset($currency) ? (string) $currency : (get_option('wp_store_settings', [])['currency_symbol'] ?? 'Rp');
$print_url = site_url('/wp-json/wp-store/v1/catalog/print');
if (is_user_logged_in()) {
    $print_url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $print_url);
}
?>
<style>
  @media print {
    .no-print {
      display: none !important;
    }

    .catalog-grid {
      grid-template-columns: 1fr 1fr 1fr;
    }
  }
</style>
<div class="wps-container wps-mx-auto wps-my-8">
  <div class="wps-flex wps-items-center wps-justify-between wps-mb-4">
    <div class="wps-text-lg wps-font-medium wps-text-gray-900">Katalog Produk</div>
    <a href="<?php echo esc_url($print_url); ?>" class="wps-btn wps-btn-secondary" target="_blank" rel="noopener">
      <?php echo wps_icon(['name' => 'printer', 'size' => 16, 'class' => 'wps-mr-2']); ?>
      Print Katalog
    </a>
  </div>
  <?php if (!empty($items)) : ?>
    <div class="catalog-grid wps-grid wps-gap-3" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
      <?php foreach ($items as $item) : ?>
        <div class="wps-box-gray wps-rounded wps-p-3">
          <a class="wps-block wps-mb-2" href="<?php echo esc_url($item['link']); ?>" style="position:relative; display:block;">
            <?php
            $src = is_string($item['image']) && $item['image'] !== '' ? $item['image'] : (WP_STORE_URL . 'assets/frontend/img/noimg.webp');
            $alt = is_string($item['title']) ? $item['title'] : 'Produk';
            ?>
            <img class="wps-rounded" src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr($alt); ?>" style="width:100%; aspect-ratio: 1 / 1; object-fit: cover;">
            <?php echo wps_label_badge_html((int) $item['id']); ?>
            <?php echo wps_discount_badge_html((int) $item['id']); ?>
          </a>
          <div class="wps-text-sm wps-text-gray-900 wps-font-medium"><?php echo esc_html($item['title']); ?></div>
          <div class="wps-mt-1"><?php echo do_shortcode('[wp_store_price id="' . esc_attr($item['id']) . '"]'); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="wps-text-sm wps-text-gray-500">Belum ada produk.</div>
  <?php endif; ?>
</div>
