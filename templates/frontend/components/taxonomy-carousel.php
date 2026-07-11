<?php if (!empty($pages)) : ?>
    <section class="wps-taxonomy-carousel" data-wps-carousel="1"
        data-cell-align="left"
        data-contain="true"
        data-wrap-around="false"
        data-page-dots="false"
        data-prev-next-buttons="<?php echo !empty($has_multiple_pages) ? 'true' : 'false'; ?>"
        data-lazy-load="0"
        data-autoplay="0"
        data-pause-on-hover="true"
        data-draggable="<?php echo !empty($has_multiple_pages) ? 'true' : 'false'; ?>"
        data-group-cells="0"
        style="--wps-taxonomy-columns: <?php echo (int) $columns; ?>;">
        <div class="main-carousel">
            <?php foreach ($pages as $page) : ?>
                <div class="wps-taxonomy-carousel__page carousel-cell">
                    <div class="wps-taxonomy-carousel__grid">
                        <?php foreach ($page as $item) : ?>
                            <a class="wps-taxonomy-carousel__item" href="<?php echo esc_url($item['url']); ?>">
                                <span class="wps-taxonomy-carousel__image">
                                    <?php if (!empty($item['image_id'])) : ?>
                                        <?php echo wp_get_attachment_image((int) $item['image_id'], $image_size, false, [
                                            'class' => 'wps-taxonomy-carousel__image-file',
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                            'alt' => $item['name'],
                                        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php else : ?>
                                        <span class="wps-taxonomy-carousel__placeholder" aria-hidden="true"><?php echo esc_html($item['initial']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="wps-taxonomy-carousel__name"><?php echo esc_html($item['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
