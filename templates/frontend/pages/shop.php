<?php if (!empty($items)) : ?>
    <div id="wps-shop" class="">
        <div class="wps-grid wps-shop-grid wps-gap-4<?php echo count($items) === 1 ? ' is-single' : ''; ?>">
            <?php foreach ($items as $item) : ?>
                <?php echo \WpStore\Domain\Product\ProductRenderer::render_card((int) $item['id'], ['context' => 'shop', 'currency' => $currency]); ?>
            <?php endforeach; ?>
        </div>
        <?php if (isset($pages) && (int) $pages > 1) : ?>
            <div class="wps-flex wps-items-center wps-gap-2 wps-mt-4" style="justify-content: center;">
                <?php
                $is_archive = (is_post_type_archive('store_product') || (get_query_var('post_type') === 'store_product' && !is_singular()));
                $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
                $request_path = $request_uri !== '' ? (string) parse_url($request_uri, PHP_URL_PATH) : '';
                if ($request_path === '') {
                    $request_path = $is_archive ? (string) parse_url(get_post_type_archive_link('store_product'), PHP_URL_PATH) : (string) parse_url(get_permalink(), PHP_URL_PATH);
                }
                $request_path = preg_replace('#/page/\d+/?$#', '/', $request_path);
                $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
                if ($home_path !== '' && $home_path !== '/' && strpos($request_path, $home_path) === 0) {
                    $request_path = substr($request_path, strlen($home_path));
                }
                if (!is_string($request_path) || $request_path === '') {
                    $request_path = '/';
                }
                if ($request_path[0] !== '/') {
                    $request_path = '/' . $request_path;
                }
                $base_url = home_url($request_path);
                $query_args = is_array($_GET) ? $_GET : [];
                unset($query_args['shop_page']);
                $prev_link = (int) $page > 1 ? add_query_arg(array_merge($query_args, ['shop_page' => (int) $page - 1]), $base_url) : '';
                $next_link = (int) $page < (int) $pages ? add_query_arg(array_merge($query_args, ['shop_page' => (int) $page + 1]), $base_url) : '';
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
                    $page_link = add_query_arg(array_merge($query_args, ['shop_page' => $i]), $base_url);
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
