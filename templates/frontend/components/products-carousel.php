<?php if (!empty($items)) : ?>
  <?php
  $per_row = isset($per_row) ? (int) $per_row : 4;
  if ($per_row <= 0) $per_row = 4;
  $w = isset($img_width) ? (int) $img_width : 200;
  if ($w <= 0) $w = 200;
  $h = isset($img_height) ? (int) $img_height : 200;
  if ($h <= 0) $h = 200;
  $crop = isset($crop) ? (bool) $crop : true;
  $size = [$w, $h];
  $style_img = 'width:100%; height:100%; object-fit:' . ($crop ? 'cover' : 'contain') . ';';
  $aspect_ratio = (int) $w . ' / ' . (int) $h;
  $opt = isset($opts) && is_array($opts) ? $opts : [];
  $cell_align = sanitize_key($opt['cell_align'] ?? 'center');
  $contain = !empty($opt['contain']);
  $wrap = !empty($opt['wrap_around']);
  $dots = !empty($opt['page_dots']);
  $buttons = !empty($opt['prev_next_buttons']);
  $lazy = isset($opt['lazy_load']) ? (int) $opt['lazy_load'] : 0;
  $autoplay = isset($opt['autoplay']) ? (int) $opt['autoplay'] : 0;
  $pause_hover = !empty($opt['pause_on_hover']);
  $draggable = !empty($opt['draggable']);
  $group_cells = $per_row > 1 ? (int) $per_row : 0;
  ?>
  <div class="wps-products-carousel"
    data-wps-carousel="1"
    data-cell-align="<?php echo esc_attr($cell_align); ?>"
    data-contain="<?php echo $contain ? 'true' : 'false'; ?>"
    data-wrap-around="<?php echo $wrap ? 'true' : 'false'; ?>"
    data-page-dots="<?php echo $dots ? 'true' : 'false'; ?>"
    data-prev-next-buttons="<?php echo $buttons ? 'true' : 'false'; ?>"
    data-lazy-load="<?php echo (int) $lazy; ?>"
    data-autoplay="<?php echo (int) $autoplay; ?>"
    data-pause-on-hover="<?php echo $pause_hover ? 'true' : 'false'; ?>"
    data-draggable="<?php echo $draggable ? 'true' : 'false'; ?>"
    data-group-cells="<?php echo (int) $group_cells; ?>">
    <?php if (isset($label) && is_string($label) && $label !== '') : ?>
      <div class="wps-text-sm wps-text-gray-900 wps-mb-3"><?php echo esc_html($label); ?></div>
    <?php endif; ?>
    <div>
      <div class="main-carousel" style="width:100%;">
        <?php foreach ($items as $item) : ?>
          <?php
          $id = (int) ($item['id'] ?? 0);
          ?>
          <div class="carousel-cell" style="width:calc(100% / <?php echo (int) $per_row; ?>); margin-right:8px; display:block;">
            <?php echo \WpStore\Domain\Product\ProductRenderer::render_card($id, [
              'context' => 'carousel',
              'currency' => $currency ?? 'Rp',
              'image_size' => $size,
              'thumbnail_width' => $w,
              'thumbnail_height' => $h,
              'thumbnail_crop' => $crop ? 'true' : 'false',
            ]); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php else : ?>
  <div class="wps-text-sm wps-text-gray-500">Tidak ada produk.</div>
<?php endif; ?>
