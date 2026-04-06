<?php
$image_src = (!empty($item['image']) ? $item['image'] : (WP_STORE_URL . 'assets/frontend/img/noimg.webp'));
$card_class = isset($card_class) && is_string($card_class) ? trim($card_class) : '';
$extra_html = isset($extra_html) && is_string($extra_html) ? $extra_html : '';
$actions_html = isset($actions_html) && is_string($actions_html) ? $actions_html : '';
?>
<div class="wps-card wps-card-hover wps-transition<?php echo $card_class !== '' ? ' ' . esc_attr($card_class) : ''; ?>">
  <div class="wps-p-2">
    <a class="wps-text-sm wps-text-gray-900 wps-mb-4 wps-text-bold wps-d-block wps-rel" href="<?php echo esc_url($item['link']); ?>">
      <div class="wps-mb-2">
        <?php echo do_shortcode('[wp_store_thumbnail id="' . esc_attr($item['id']) . '"]'); ?>
      </div>
      <?php echo esc_html($item['title']); ?>
    </a>
    <div class="wps-text-xxs wps-text-gray-900 wps-mb-4">
      <?php if (isset($item['price']) && $item['price'] !== null) : ?>
        <?php
        $price_val = (float) ($item['price']);
        $formatted_price = ($currency ?? 'Rp') === 'Rp'
          ? number_format($price_val, 0, ',', '.')
          : number_format_i18n($price_val, 0);
        echo esc_html(($currency ?? 'Rp') . ' ' . $formatted_price);
        ?>
      <?php endif; ?>
    </div>
    <?php if ($extra_html !== '') : ?>
      <div class="wps-mb-4">
        <?php echo $extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
    <?php endif; ?>
    <?php if ($actions_html !== '') : ?>
      <?php echo $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php else : ?>
      <div class="wps-flex wps-items-center wps-justify-between">
        <div class="wps-flex wps-gap-2">
          <?php echo do_shortcode('[wp_store_add_to_cart id="' . esc_attr($item['id']) . '" size="sm"]'); ?>
          <?php echo do_shortcode('[wp_store_add_to_wishlist id="' . esc_attr($item['id']) . '" size="sm" icon_only="1" label_add="" label_remove=""]'); ?>
        </div>
        <a class="wps-btn wps-btn-secondary wps-btn-sm" href="<?php echo esc_url($item['link']); ?>"><?php echo esc_html($view_label ?? 'Detail'); ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
