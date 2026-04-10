<?php
use WpStore\Domain\Review\RatingRenderer;

$product_id = isset($product_id) ? (int) $product_id : 0;
$limit = isset($limit) ? max(1, min(100, (int) $limit)) : 20;
$review_repo = isset($review_repo) && $review_repo instanceof \WpStore\Domain\Review\ProductReviewRepository
    ? $review_repo
    : new \WpStore\Domain\Review\ProductReviewRepository();

if ($product_id <= 0 || get_post_type($product_id) !== 'store_product') {
    return;
}

$product_reviews = $review_repo->product_reviews($product_id, $limit);
$review_summary = $review_repo->product_summary($product_id);
$rating_average = isset($review_summary['rating_average']) ? (float) $review_summary['rating_average'] : 0.0;
$review_count = isset($review_summary['review_count']) ? (int) $review_summary['review_count'] : 0;
?>
<div class="wps-card wps-p-4">
    <div class="wps-flex wps-justify-between wps-items-start wps-gap-3 wps-flex-wrap wps-mb-4">
        <div>
            <h2 class="wps-text-lg wps-font-bold wps-text-gray-900 wps-mb-1"><?php echo esc_html__('Ulasan Produk', 'vd-store'); ?></h2>
            <div class="wps-text-sm wps-text-gray-500"><?php echo esc_html__('Ulasan hanya dapat dikirim dari pesanan yang sudah selesai.', 'vd-store'); ?></div>
        </div>
        <div class="wps-text-right">
            <?php echo RatingRenderer::summary_html($rating_average, $review_count, ['show_count' => false, 'class' => 'wps-justify-end']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="wps-text-sm wps-text-gray-500 wps-mt-1">
                <?php echo esc_html(number_format_i18n($rating_average, 1) . '/5 ' . sprintf(_n('dari %d ulasan', 'dari %d ulasan', $review_count, 'vd-store'), $review_count)); ?>
            </div>
        </div>
    </div>
    <?php if (empty($product_reviews)) : ?>
        <div class="wps-text-sm wps-text-gray-500"><?php echo esc_html__('Belum ada ulasan untuk produk ini.', 'vd-store'); ?></div>
    <?php else : ?>
        <div class="wps-flex wps-flex-col wps-gap-4">
            <?php foreach ($product_reviews as $review) : ?>
                <?php $item_rating = max(0, min(5, (int) ($review['rating'] ?? 0))); ?>
                <div style="border-top:1px solid #e5e7eb;padding-top:16px;">
                    <div class="wps-flex wps-justify-between wps-items-start wps-gap-3 wps-flex-wrap">
                        <div>
                            <div class="wps-font-medium wps-text-gray-900"><?php echo esc_html((string) ($review['user_name'] ?? __('Member', 'vd-store'))); ?></div>
                            <div class="wps-mt-1">
                                <?php echo RatingRenderer::summary_html($item_rating, null, ['show_count' => false, 'class' => 'wps-text-sm']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                        <div class="wps-text-sm wps-text-gray-500"><?php echo esc_html(mysql2date('d-m-Y H:i', (string) ($review['created_at'] ?? ''))); ?></div>
                    </div>
                    <?php if (!empty($review['title'])) : ?>
                        <div class="wps-font-medium wps-text-gray-900 wps-mt-2"><?php echo esc_html((string) $review['title']); ?></div>
                    <?php endif; ?>
                    <div class="wps-text-sm wps-text-gray-500 wps-mt-1"><?php echo nl2br(esc_html((string) ($review['content'] ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php if (!empty($review['image_urls']) && is_array($review['image_urls'])) : ?>
                        <div class="wps-flex wps-flex-wrap wps-gap-2 wps-mt-3">
                            <?php foreach ($review['image_urls'] as $review_image_url) : ?>
                                <a href="<?php echo esc_url((string) $review_image_url); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo esc_url((string) $review_image_url); ?>" alt="<?php echo esc_attr__('Foto ulasan', 'vd-store'); ?>" style="width:88px;height:88px;object-fit:cover;border:1px solid #e5e7eb;border-radius:8px;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
