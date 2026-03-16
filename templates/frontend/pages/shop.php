<?php if (!empty($items)) : ?>
    <div id="wps-shop" class="">
        <div class="wps-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));<?php echo (count($items) === 1) ? ' max-width: 300px;' : ''; ?>">
            <?php foreach ($items as $item) : ?>
                <?php echo \WpStore\Frontend\Template::render('components/product-card', ['item' => $item, 'currency' => $currency, 'view_label' => 'Detail']); ?>
            <?php endforeach; ?>
        </div>
        <?php if (isset($pages) && (int) $pages > 1) : ?>
            <div class="wps-flex wps-items-center wps-gap-2 wps-mt-4" style="justify-content: center;">
                <?php
                $is_archive = (is_post_type_archive('store_product') || (get_query_var('post_type') === 'store_product' && !is_singular()));
                if ($is_archive) {
                    $prev_link = (int) $page > 1 ? add_query_arg($_GET, get_pagenum_link((int) $page - 1)) : '';
                    $next_link = (int) $page < (int) $pages ? add_query_arg($_GET, get_pagenum_link((int) $page + 1)) : '';
                } else {
                    $base = get_permalink();
                    $prev_link = (int) $page > 1 ? add_query_arg('shop_page', (int) $page - 1, $base) : '';
                    $next_link = (int) $page < (int) $pages ? add_query_arg('shop_page', (int) $page + 1, $base) : '';
                }
                $pages = (int) $pages;
                $page = (int) $page;
                $show = [];
                for ($i = 1; $i <= 2 && $i <= $pages; $i++) {
                    $show[$i] = true;
                }
                $start_mid = max(1, $page - 1);
                $end_mid = min($pages, $page + 1);
                for ($i = $start_mid; $i <= $end_mid; $i++) {
                    $show[$i] = true;
                }
                for ($i = $pages - 1; $i <= $pages; $i++) {
                    if ($i >= 1 && $i <= $pages) {
                        $show[$i] = true;
                    }
                }
                $numbers = array_keys($show);
                sort($numbers);
                $prev_num = 0;
                foreach ($numbers as $i) :
                    if ($prev_num && $i > $prev_num + 1) :
                ?>
                        <span class="wps-text-sm wps-text-gray-500">…</span>
                    <?php
                    endif;
                    $prev_num = $i;
                    $page_link = $is_archive ? add_query_arg($_GET, get_pagenum_link($i)) : add_query_arg('shop_page', $i, get_permalink());
                    ?>
                    <a href="<?php echo esc_url($page_link); ?>" class="wps-btn <?php echo ($i === $page) ? 'wps-btn-primary' : 'wps-btn-secondary'; ?> wps-btn-sm"><?php echo esc_html($i); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div id="wps-shop" class="">
        <div class="wps-text-sm wps-text-gray-500">Belum ada produk.</div>
    </div>
<?php endif; ?>