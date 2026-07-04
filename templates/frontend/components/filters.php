<?php
$categories = isset($categories) && is_array($categories) ? $categories : [];
$brands = isset($brands) && is_array($brands) ? $brands : [];
$current = isset($current) && is_array($current) ? $current : ['sort' => '', 'min_price' => '', 'max_price' => '', 'cats' => [], 'brands' => []];
$reset_url = isset($reset_url) ? (string) $reset_url : '';
$mode = isset($mode) ? sanitize_key((string) $mode) : 'auto';
$use_js = $mode !== 'off';
$locked_cats = isset($locked_cats) && is_array($locked_cats) ? array_values($locked_cats) : [];
$locked_brands = isset($locked_brands) && is_array($locked_brands) ? array_values($locked_brands) : [];
$category_tree = [];
foreach ($categories as $cat) {
    if (!is_array($cat) || empty($cat['id'])) {
        continue;
    }

    $parent_id = isset($cat['parent']) ? (int) $cat['parent'] : 0;
    if (!isset($category_tree[$parent_id])) {
        $category_tree[$parent_id] = [];
    }
    $category_tree[$parent_id][] = $cat;
}
$sort_terms = static function (&$terms) {
    if (!is_array($terms)) {
        $terms = [];
        return;
    }

    usort($terms, static function ($a, $b) {
        $a_name = is_array($a) ? (string) ($a['name'] ?? '') : '';
        $b_name = is_array($b) ? (string) ($b['name'] ?? '') : '';
        return strcasecmp($a_name, $b_name);
    });
};
$render_category_rows = static function ($parent_id = 0, $depth = 0) use (&$render_category_rows, &$category_tree, $sort_terms, $use_js, $current) {
    $html = '';
    $parent_id = (int) $parent_id;
    $depth = max(0, (int) $depth);

    if (empty($category_tree[$parent_id])) {
        return $html;
    }

    $terms = $category_tree[$parent_id];
    $sort_terms($terms);

    foreach ($terms as $cat) {
        if (!is_array($cat) || empty($cat['id'])) {
            continue;
        }

        $cat_id = (int) $cat['id'];
        $cat_name = (string) ($cat['name'] ?? '');
        $is_checked = in_array($cat_id, (array) ($current['cats'] ?? []), true);
        $label_class = 'wps-checkbox-label';
        if ($depth > 0) {
            $label_class .= ' wps-text-gray-700';
        }
        $input_attrs = $use_js
            ? ':value="' . esc_attr($cat_id) . '" x-model="cats" @change="update" :disabled="isCatLocked(' . esc_attr($cat_id) . ')"'
            : '';

        $html .= '<div style="' . ($depth > 0 ? 'padding-left:' . (20 * $depth) . 'px;' : '') . '">';
        $html .= '<label class="' . esc_attr($label_class) . '" style="display:flex;align-items:center;gap:5px;line-height:1.15;min-height:24px;">';
        $html .= '<input type="checkbox" class="wps-checkbox" name="cats[]" value="' . esc_attr($cat_id) . '" style="margin:0;flex:0 0 auto;display:block;" ' . $input_attrs . ' ' . checked($is_checked, true, false) . '>';
        $html .= '<span class="wps-text-sm' . ($depth > 0 ? ' wps-text-gray-600' : ' wps-text-gray-900') . '" style="display:block;line-height:1.15;padding-top:1px;">' . esc_html($cat_name) . '</span>';
        $html .= '</label>';
        $html .= '</div>';
        $html .= $render_category_rows($cat_id, $depth + 1);
    }

    return $html;
};
?>
<form
    <?php echo $use_js ? 'x-data="typeof wpStoreFilters === \'function\' ? wpStoreFilters() : {}" x-init="init && typeof init === \'function\' ? init() : null"' : ''; ?>
    method="get" action="" class="wps-card wps-p-4" style="margin-bottom:12px;">
    <div class="wps-text-lg wps-font-medium wps-mb-3 wps-text-bold">Filter & Urutkan</div>
    <div class="wps-mt-3">
        <label class="wps-label">Urutkan</label>
        <select class="wps-select" name="sort" <?php echo $use_js ? 'x-model="sort" @change="update"' : ''; ?>>
            <option value="">Default</option>
            <option value="name_asc" <?php echo $current['sort'] === 'name_asc' ? 'selected' : ''; ?>>A-Z</option>
            <option value="name_desc" <?php echo $current['sort'] === 'name_desc' ? 'selected' : ''; ?>>Z-A</option>
            <option value="sold_desc" <?php echo $current['sort'] === 'sold_desc' ? 'selected' : ''; ?>>Terlaris
            </option>
            <option value="rating_desc" <?php echo $current['sort'] === 'rating_desc' ? 'selected' : ''; ?>>Rating
                Tertinggi</option>
            <option value="price_asc" <?php echo $current['sort'] === 'price_asc' ? 'selected' : ''; ?>>Termurah
            </option>
            <option value="price_desc" <?php echo $current['sort'] === 'price_desc' ? 'selected' : ''; ?>>Termahal
            </option>
        </select>
    </div>
    <div class="wps-mt-3">
        <label class="wps-label">Rentang Harga</label>
        <div class="wps-price-range">
            <div class="wps-slider">
                <div class="wps-progress" :style="rangeFillStyle"></div>
            </div>
            <div class="wps-range-input">
                <input type="range" :min="price_min_bound" :max="price_max_bound" step="1"
                    x-model.number="active_min_price" @input="clampPrices(); syncInputsFromRange(); update()"
                    class="wps-range min">
                <input type="range" :min="price_min_bound" :max="price_max_bound" step="1"
                    x-model.number="active_max_price" @input="clampPrices(); syncInputsFromRange(); update()"
                    class="wps-range max">
            </div>
        </div>
        <div class="wps-price-input wps-mt-2">
            <div class="wps-form-group wps-mb-0">
                <input class="wps-input" type="number" min="0" step="1" name="min_price"
                    value="<?php echo esc_attr((string) ($current['min_price'] ?? '')); ?>"
                    <?php echo $use_js ? 'x-model="min_price_input" @input="syncFromInputs(); update()"' : ''; ?>
                    placeholder="Min">
            </div>
            <div class="wps-form-group wps-mb-0">
                <input class="wps-input" type="number" min="0" step="1" name="max_price"
                    value="<?php echo esc_attr((string) ($current['max_price'] ?? '')); ?>"
                    <?php echo $use_js ? 'x-model="max_price_input" @input="syncFromInputs(); update()"' : ''; ?>
                    placeholder="Max">
            </div>
        </div>
        <div class="wps-flex wps-justify-between wps-items-center wps-mt-2 opacity-50">
            <div class="wps-flex wps-justify-between wps-items-center">
                <span class="wps-text-sm wps-text-gray-700" x-text="formatCurrency(price_min_bound)"></span>
                <span class="mx-2 wps-text-sm wps-text-gray-500">-</span>
                <span class="wps-text-sm wps-text-gray-700" x-text="formatCurrency(price_max_bound)"></span>
            </div>
        </div>
        <div class="wps-mt-3">
            <div class="wps-label">Kategori</div>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php echo $render_category_rows(0, 0); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php if (!empty($brands)) : ?>
        <div class="wps-mt-3">
            <div class="wps-label">Brand</div>
            <div class="" style="gap:8px;">
                <?php foreach ($brands as $brand): ?>
                <label class="wps-checkbox-label" style="display:flex;align-items:center;gap:5px;line-height:1.15;min-height:24px;">
                    <input type="checkbox" class="wps-checkbox" name="brands[]"
                        style="margin:0;flex:0 0 auto;display:block;"
                        value="<?php echo esc_attr($brand['id']); ?>"
                        <?php echo $use_js ? ':value="' . esc_attr($brand['id']) . '" x-model="brands" @change="update" :disabled="isBrandLocked(' . esc_attr($brand['id']) . ')"' : ''; ?>
                        <?php echo in_array($brand['id'], $current['brands'], true) ? 'checked' : ''; ?>>
                    <span class="wps-text-sm wps-text-gray-900" style="display:block;line-height:1.15;padding-top:1px;"><?php echo esc_html($brand['name']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="wps-mt-4 wps-flex wps-justify-between wps-items-center">
            <a href="<?php echo esc_url($reset_url ?: remove_query_arg(['sort', 'min_price', 'max_price', 'cats', 'brands', 'shop_page'])); ?>"
                class="wps-btn wps-btn-secondary"
                <?php echo $use_js ? '@click.prevent="resetFilters"' : ''; ?>><?php echo wps_icon(['name' => 'trash', 'size' => 16, 'class' => 'wps-mr-2']); ?>Reset</a>
            <button type="submit"
                class="wps-btn wps-btn-primary"><?php echo wps_icon(['name' => 'sliders2', 'size' => 16, 'class' => 'wps-mr-2']); ?>Terapkan</button>
        </div>
        <div class="wps-filter-loading wps-mt-3" x-show="updating" x-cloak>
            <span class="wps-filter-loading__spinner" aria-hidden="true"></span>
            <span><?php echo esc_html__('Loading...', 'wp-store'); ?></span>
        </div>
    </div>
</form>
<?php if ($use_js) : ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('wpStoreFilters', () => ({
        sort: <?php echo wp_json_encode((string) ($current['sort'] ?? '')); ?>,
        min_price_input: <?php echo is_numeric($current['min_price'] ?? '') ? wp_json_encode((string) ((float) $current['min_price'])) : '""'; ?>,
        max_price_input: <?php echo is_numeric($current['max_price'] ?? '') ? wp_json_encode((string) ((float) $current['max_price'])) : '""'; ?>,
        active_min_price: <?php echo is_numeric($current['min_price'] ?? '') ? (float) $current['min_price'] : (isset($price_min_global) ? (float) $price_min_global : 0); ?>,
        active_max_price: <?php echo is_numeric($current['max_price'] ?? '') ? (float) $current['max_price'] : (isset($price_max_global) ? (float) $price_max_global : 0); ?>,
        price_min_bound: <?php echo isset($price_min_global) ? (float) $price_min_global : 0; ?>,
        price_max_bound: <?php echo isset($price_max_global) ? (float) $price_max_global : 0; ?>,
        reset_url: <?php echo wp_json_encode($reset_url); ?>,
        cats: <?php echo wp_json_encode(array_values($current['cats'] ?? [])); ?>,
        brands: <?php echo wp_json_encode(array_values($current['brands'] ?? [])); ?>,
        locked_cats: <?php echo wp_json_encode($locked_cats); ?>,
        locked_brands: <?php echo wp_json_encode($locked_brands); ?>,
        updating: false,
        _updateTimer: null,
        initializing: true,
        init() {
            this.parseQueryIntoState();
            this.enforceLockedCats();
            this.enforceLockedBrands();
            this.removeLockedFiltersFromUrl();
            if (this.min_price_input === '') this.active_min_price = this.price_min_bound;
            if (this.max_price_input === '') this.active_max_price = this.price_max_bound;
            this.clampPrices();
            this.$watch('sort', () => this.update());
            this.$watch('cats', () => {
                this.enforceLockedCats();
                this.update();
            });
            this.$watch('brands', () => {
                this.enforceLockedBrands();
                this.update();
            });
            this.initializing = false;
            window.addEventListener('popstate', () => {
                this.parseQueryIntoState();
                this.removeLockedFiltersFromUrl();
                this.refreshShop();
            });
            const shop = document.querySelector('#wps-shop');
            if (shop) {
                shop.addEventListener('click', (e) => {
                    const a = e.target.closest('a[href]');
                    if (!a) return;
                    const href = a.href || a.getAttribute('href');
                    if (!href) return;
                    try {
                        const url = new URL(href, window.location.origin);
                        if (url.origin !== window.location.origin) return;
                        const cur = new URL(window.location.href);
                        const norm = (p) => p.replace(/\/page\/\d+\/?/, '/');
                        const sameBase = norm(url.pathname) === norm(cur.pathname);
                        const hasFilterParams =
                            url.searchParams.has('sort') ||
                            url.searchParams.has('min_price') ||
                            url.searchParams.has('max_price') ||
                            url.searchParams.has('shop_page') ||
                            (url.searchParams.getAll('cats[]').length > 0) ||
                            (url.searchParams.getAll('brands[]').length > 0);
                        const isPaginationPath = /\/page\/\d+\/?/.test(url.pathname);
                        if (sameBase && (hasFilterParams || isPaginationPath)) {
                            e.preventDefault();
                            history.pushState({}, '', url.toString());
                            this.parseQueryIntoState();
                            this.refreshShop();
                        }
                    } catch (err) {}
                });
            }
        },
        get rangeFillStyle() {
            const span = Math.max(1, this.price_max_bound - this.price_min_bound);
            const minPct = Math.max(0, Math.min(100, ((this.active_min_price - this
                .price_min_bound) / span) * 100));
            const maxPct = Math.max(0, Math.min(100, ((this.active_max_price - this
                .price_min_bound) / span) * 100));
            const left = Math.min(minPct, maxPct);
            const right = Math.max(0, 100 - Math.max(minPct, maxPct));
            return `left:${left}%; right:${right}%;`;
        },
        clampPrices() {
            if (this.active_min_price < this.price_min_bound) this.active_min_price = this
                .price_min_bound;
            if (this.active_max_price > this.price_max_bound) this.active_max_price = this
                .price_max_bound;
            if (this.active_min_price > this.active_max_price) this.active_min_price = this
                .active_max_price;
        },
        syncInputsFromRange() {
            this.min_price_input = this.active_min_price <= this.price_min_bound ? '' : String(Math
                .round(this.active_min_price));
            this.max_price_input = this.active_max_price >= this.price_max_bound ? '' : String(Math
                .round(this.active_max_price));
        },
        syncFromInputs() {
            const minRaw = String(this.min_price_input || '').trim();
            const maxRaw = String(this.max_price_input || '').trim();
            this.active_min_price = minRaw === '' ? this.price_min_bound : parseFloat(minRaw);
            this.active_max_price = maxRaw === '' ? this.price_max_bound : parseFloat(maxRaw);
            if (!Number.isFinite(this.active_min_price)) this.active_min_price = this
                .price_min_bound;
            if (!Number.isFinite(this.active_max_price)) this.active_max_price = this
                .price_max_bound;
            this.clampPrices();
        },
        enforceLockedCats() {
            const base = Array.isArray(this.cats) ? this.cats.map((n) => parseInt(n, 10)).filter((
                n) => Number.isFinite(n)) : [];
            const lock = Array.isArray(this.locked_cats) ? this.locked_cats.map((n) => parseInt(n,
                10)).filter((n) => Number.isFinite(n)) : [];
            const set = new Set(base.concat(lock));
            const next = Array.from(set).sort((a, b) => a - b);
            const cur = base.slice().sort((a, b) => a - b);
            const equal = next.length === cur.length && next.every((v, i) => v === cur[i]);
            if (!equal) {
                this.cats = next;
            }
        },
        isCatLocked(id) {
            const n = parseInt(id, 10);
            if (!Number.isFinite(n)) return false;
            return Array.isArray(this.locked_cats) && this.locked_cats.map((m) => parseInt(m, 10))
                .includes(n);
        },
        enforceLockedBrands() {
            const base = Array.isArray(this.brands) ? this.brands.map((n) => parseInt(n, 10)).filter((
                n) => Number.isFinite(n)) : [];
            const lock = Array.isArray(this.locked_brands) ? this.locked_brands.map((n) => parseInt(n,
                10)).filter((n) => Number.isFinite(n)) : [];
            const set = new Set(base.concat(lock));
            const next = Array.from(set).sort((a, b) => a - b);
            const cur = base.slice().sort((a, b) => a - b);
            const equal = next.length === cur.length && next.every((v, i) => v === cur[i]);
            if (!equal) {
                this.brands = next;
            }
        },
        isBrandLocked(id) {
            const n = parseInt(id, 10);
            if (!Number.isFinite(n)) return false;
            return Array.isArray(this.locked_brands) && this.locked_brands.map((m) => parseInt(m, 10))
                .includes(n);
        },
        removeLockedFiltersFromUrl() {
            try {
                const url = new URL(window.location.href);
                let changed = false;
                const stripLocked = (key, locked) => {
                    const lock = new Set((Array.isArray(locked) ? locked : [])
                        .map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n)));
                    const values = url.searchParams.getAll(key);
                    const keep = values.filter((value) => !lock.has(parseInt(value, 10)));
                    if (keep.length === values.length) return;
                    url.searchParams.delete(key);
                    keep.forEach((value) => url.searchParams.append(key, value));
                    changed = true;
                };
                stripLocked('cats[]', this.locked_cats);
                stripLocked('brands[]', this.locked_brands);
                if (changed) {
                    history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
                }
            } catch (e) {}
        },
        formatCurrency(v) {
            const n = parseFloat(v);
            if (!Number.isFinite(n)) return 'Rp 0';
            return 'Rp ' + n.toLocaleString('id-ID');
        },
        buildQuery() {
            const p = new URLSearchParams();
            if (this.sort) p.set('sort', this.sort);
            const minRaw = String(this.min_price_input || '').trim();
            const maxRaw = String(this.max_price_input || '').trim();
            if (minRaw !== '') p.set('min_price', minRaw);
            if (maxRaw !== '') p.set('max_price', maxRaw);
            const lockedCats = new Set((Array.isArray(this.locked_cats) ? this.locked_cats : [])
                .map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n)));
            const lockedBrands = new Set((Array.isArray(this.locked_brands) ? this.locked_brands : [])
                .map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n)));
            (Array.isArray(this.cats) ? this.cats : []).forEach((c) => {
                const n = parseInt(c, 10);
                if (Number.isFinite(n) && n > 0 && !lockedCats.has(n)) p.append('cats[]', String(n));
            });
            (Array.isArray(this.brands) ? this.brands : []).forEach((b) => {
                const n = parseInt(b, 10);
                if (Number.isFinite(n) && n > 0 && !lockedBrands.has(n)) p.append('brands[]', String(n));
            });
            return p.toString();
        },
        parseQueryIntoState() {
            try {
                const url = new URL(window.location.href);
                const qs = url.searchParams;
                const sp = qs.get('sort') || '';
                const mn = qs.get('min_price');
                const mx = qs.get('max_price');
                if (sp !== null) this.sort = String(sp);
                this.min_price_input = mn !== null ? String(mn) : '';
                this.max_price_input = mx !== null ? String(mx) : '';
                const cats = qs.getAll('cats[]').map((v) => parseInt(v, 10)).filter((n) => Number
                    .isFinite(n) && n > 0);
                const brands = qs.getAll('brands[]').map((v) => parseInt(v, 10)).filter((n) =>
                    Number.isFinite(n) && n > 0);
                this.cats = cats;
                this.brands = brands;
                this.syncFromInputs();
                this.enforceLockedCats();
                this.enforceLockedBrands();
            } catch (e) {}
        },
        refreshShop() {
            if (this.updating) return;
            this.updating = true;
            const url = new URL(window.location.href);
            const curBlock = document.querySelector('#wps-shop');
            if (!curBlock) {
                window.location.href = url.toString();
                return;
            }
            fetch(url.toString(), {
                    credentials: 'same-origin'
                })
                .then((r) => r.text())
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newBlock = doc.querySelector('#wps-shop');
                    if (newBlock && curBlock) {
                        if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                            try {
                                window.Alpine.destroyTree(curBlock);
                            } catch (e) {}
                        }
                        curBlock.innerHTML = newBlock.innerHTML;
                        if (window.Alpine) {
                            const hasAlpine = curBlock.querySelector(
                                '[x-data],[x-init],[x-show],[x-model],[x-on],[x-bind],[x-if],[x-for]'
                            );
                            if (hasAlpine) {
                                try {
                                    if (typeof window.Alpine.initTree === 'function') {
                                        requestAnimationFrame(() => window.Alpine.initTree(
                                            curBlock));
                                    }
                                } catch (e) {}
                            }
                        }
                    }
                })
                .finally(() => {
                    this.updating = false;
                });
        },
        update() {
            if (this.initializing) return;
            clearTimeout(this._updateTimer);
            this._updateTimer = setTimeout(() => {
                const qs = this.buildQuery();
                const base = window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
                const next = qs ? `${base}?${qs}` : base;
                history.pushState({}, '', next);
                this.refreshShop();
            }, 120);
        },
        resetFilters() {
            this.sort = '';
            this.min_price_input = '';
            this.max_price_input = '';
            this.active_min_price = this.price_min_bound;
            this.active_max_price = this.price_max_bound;
            this.cats = Array.isArray(this.locked_cats) ? this.locked_cats.slice() : [];
            this.brands = Array.isArray(this.locked_brands) ? this.locked_brands.slice() : [];
            this.enforceLockedCats();
            this.enforceLockedBrands();
            const next = this.reset_url || window.location.pathname.replace(/\/page\/\d+\/?$/, '/');
            history.pushState({}, '', next);
            this.refreshShop();
        }
    }))
});
</script>
<?php endif; ?>
