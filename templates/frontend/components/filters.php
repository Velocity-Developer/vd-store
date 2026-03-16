<?php
$categories = isset($categories) && is_array($categories) ? $categories : [];
$current = isset($current) && is_array($current) ? $current : ['sort' => '', 'min_price' => '', 'max_price' => '', 'cats' => [], 'labels' => []];
$reset_url = isset($reset_url) ? (string) $reset_url : '';
$show_labels = isset($show_labels) ? (bool) $show_labels : true;
?>
<form x-data="typeof wpStoreFilters === 'function' ? wpStoreFilters() : {}" x-init="init && typeof init === 'function' ? init() : null" @submit.prevent="update && typeof update === 'function' ? update() : null" method="get" action="" class="wps-card wps-p-4" style="margin-bottom:12px;">
  <div class="wps-text-lg wps-font-medium wps-mb-3 wps-text-bold">Filter & Urutkan</div>
  <div class="wps-mt-3">
    <label class="wps-label">Urutkan</label>
    <select class="wps-select" name="sort" x-model="sort" @change="update">
      <option value="">Default</option>
      <option value="az" <?php echo $current['sort'] === 'az' ? 'selected' : ''; ?>>A → Z</option>
      <option value="za" <?php echo $current['sort'] === 'za' ? 'selected' : ''; ?>>Z → A</option>
      <option value="cheap" <?php echo $current['sort'] === 'cheap' ? 'selected' : ''; ?>>Termurah</option>
      <option value="expensive" <?php echo $current['sort'] === 'expensive' ? 'selected' : ''; ?>>Termahal</option>
    </select>
  </div>
  <div class="wps-mt-3">
    <label class="wps-label">Rentang Harga</label>
    <div class="wps-price-range">
      <div class="wps-slider">
        <div class="wps-progress" :style="rangeFillStyle"></div>
      </div>
      <div class="wps-range-input">
        <input type="range"
          :min="price_min_bound"
          :max="price_max_bound"
          step="1"
          x-model.number="min_price"
          @input="clampPrices(); update()"
          class="wps-range min">
        <input type="range"
          :min="price_min_bound"
          :max="price_max_bound"
          step="1"
          x-model.number="max_price"
          @input="clampPrices(); update()"
          class="wps-range max">
      </div>
    </div>
    <div class="wps-price-input wps-mt-2">
      <div class="wps-form-group wps-mb-0">
        <input class="wps-input" type="number" min="0" step="1" x-model.number="min_price" @input="clampPrices(); update()">
      </div>
      <div class="wps-form-group wps-mb-0">
        <input class="wps-input" type="number" min="0" step="1" x-model.number="max_price" @input="clampPrices(); update()">
      </div>
    </div>
    <div class="wps-flex wps-justify-between wps-items-center wps-mt-2">
      <div class="wps-flex wps-justify-between wps-items-center wps-mt-2">
        <span class="wps-text-sm wps-text-gray-700" x-text="formatCurrency(price_min_bound)"></span>
        <span class="wps-text-sm wps-text-gray-700" x-text="formatCurrency(price_max_bound)"></span>
      </div>
    </div>
    <div class="wps-mt-3">
      <div class="wps-label">Kategori</div>
      <div class="" style="gap:8px;">
        <?php foreach ($categories as $cat): ?>
          <label class="wps-checkbox-label wps-display-block">
            <input type="checkbox" class="wps-checkbox" name="cats[]" :value="<?php echo esc_attr($cat['id']); ?>" x-model="cats" @change="update" :disabled="isCatLocked(<?php echo esc_attr($cat['id']); ?>)" <?php echo in_array($cat['id'], $current['cats'], true) ? 'checked' : ''; ?>>
            <span class="wps-text-sm wps-text-gray-900"><?php echo esc_html($cat['name']); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($show_labels): ?>
      <div class="wps-mt-3">
        <div class="wps-label">Label</div>
        <div class="" style="gap:8px;">
          <label class="wps-checkbox-label wps-display-block">
            <input type="checkbox" class="wps-checkbox" name="labels[]" value="best" x-model="labels" @change="update" <?php echo in_array('best', $current['labels'], true) ? 'checked' : ''; ?>>
            <span class="wps-text-sm wps-text-gray-900">Best Seller</span>
          </label>
          <label class="wps-checkbox-label wps-display-block">
            <input type="checkbox" class="wps-checkbox" name="labels[]" value="limited" x-model="labels" @change="update" <?php echo in_array('limited', $current['labels'], true) ? 'checked' : ''; ?>>
            <span class="wps-text-sm wps-text-gray-900">Limited</span>
          </label>
          <label class="wps-checkbox-label wps-display-block">
            <input type="checkbox" class="wps-checkbox" name="labels[]" value="new" x-model="labels" @change="update" <?php echo in_array('new', $current['labels'], true) ? 'checked' : ''; ?>>
            <span class="wps-text-sm wps-text-gray-900">New</span>
          </label>
        </div>
      </div>
    <?php endif; ?>
    <div class="wps-mt-4 wps-flex wps-justify-between wps-items-center">
      <a href="<?php echo esc_url($reset_url); ?>" class="wps-btn wps-btn-secondary"><?php echo wps_icon(['name' => 'trash', 'size' => 16, 'class' => 'wps-mr-2']); ?>Reset</a>
      <button type="submit" class="wps-btn wps-btn-primary"><?php echo wps_icon(['name' => 'sliders2', 'size' => 16, 'class' => 'wps-mr-2']); ?>Terapkan</button>
    </div>
  </div>
</form>
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('wpStoreFilters', () => ({
      sort: <?php echo wp_json_encode((string) ($current['sort'] ?? '')); ?>,
      min_price: <?php echo is_numeric($current['min_price'] ?? '') ? (float) $current['min_price'] : '""'; ?>,
      max_price: <?php echo is_numeric($current['max_price'] ?? '') ? (float) $current['max_price'] : '""'; ?>,
      price_min_bound: <?php echo isset($price_min_global) ? (float) $price_min_global : 0; ?>,
      price_max_bound: <?php echo isset($price_max_global) ? (float) $price_max_global : 0; ?>,
      cats: <?php echo wp_json_encode(array_values($current['cats'] ?? [])); ?>,
      labels: <?php echo wp_json_encode(array_values($current['labels'] ?? [])); ?>,
      locked_cats: <?php echo wp_json_encode(isset($locked_cats) ? array_values($locked_cats) : []); ?>,
      updating: false,
      _updateTimer: null,
      initializing: true,
      init() {
        this.parseQueryIntoState();
        this.enforceLockedCats();
        if (this.min_price === '' || isNaN(this.min_price)) this.min_price = this.price_min_bound;
        if (this.max_price === '' || isNaN(this.max_price)) this.max_price = this.price_max_bound;
        this.clampPrices();
        this.$watch('sort', () => this.update());
        this.$watch('min_price', () => {
          this.clampPrices();
          this.update();
        });
        this.$watch('max_price', () => {
          this.clampPrices();
          this.update();
        });
        this.$watch('cats', () => {
          this.enforceLockedCats();
          this.update();
        });
        this.$watch('labels', () => this.update());
        this.initializing = false;
        window.addEventListener('popstate', () => {
          this.parseQueryIntoState();
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
                (url.searchParams.getAll('labels[]').length > 0);
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
        const minPct = Math.max(0, Math.min(100, ((this.min_price - this.price_min_bound) / span) * 100));
        const maxPct = Math.max(0, Math.min(100, ((this.max_price - this.price_min_bound) / span) * 100));
        const left = Math.min(minPct, maxPct);
        const right = Math.max(0, 100 - Math.max(minPct, maxPct));
        return `left:${left}%; right:${right}%;`;
      },
      clampPrices() {
        if (this.min_price < this.price_min_bound) this.min_price = this.price_min_bound;
        if (this.max_price > this.price_max_bound) this.max_price = this.price_max_bound;
        if (this.min_price > this.max_price) this.min_price = this.max_price;
      },
      enforceLockedCats() {
        const base = Array.isArray(this.cats) ? this.cats.map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n)) : [];
        const lock = Array.isArray(this.locked_cats) ? this.locked_cats.map((n) => parseInt(n, 10)).filter((n) => Number.isFinite(n)) : [];
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
        return Array.isArray(this.locked_cats) && this.locked_cats.map((m) => parseInt(m, 10)).includes(n);
      },
      formatCurrency(v) {
        const n = parseFloat(v);
        if (!Number.isFinite(n)) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
      },
      buildQuery() {
        const p = new URLSearchParams();
        if (this.sort) p.set('sort', this.sort);
        if (this.min_price !== '' && this.min_price !== null && !isNaN(this.min_price)) p.set('min_price', this.min_price);
        if (this.max_price !== '' && this.max_price !== null && !isNaN(this.max_price)) p.set('max_price', this.max_price);
        (Array.isArray(this.cats) ? this.cats : []).forEach((c) => {
          const n = parseInt(c, 10);
          if (Number.isFinite(n) && n > 0) p.append('cats[]', String(n));
        });
        (Array.isArray(this.labels) ? this.labels : []).forEach((l) => {
          const s = String(l).toLowerCase();
          if (['best', 'limited', 'new'].includes(s)) p.append('labels[]', s);
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
          if (mn !== null) this.min_price = parseFloat(mn);
          if (mx !== null) this.max_price = parseFloat(mx);
          const cats = qs.getAll('cats[]').map((v) => parseInt(v, 10)).filter((n) => Number.isFinite(n) && n > 0);
          if (cats.length) this.cats = cats;
          const labels = qs.getAll('labels[]').map((v) => String(v).toLowerCase()).filter((s) => ['best', 'limited', 'new'].includes(s));
          if (labels.length) this.labels = labels;
          this.enforceLockedCats();
          this.clampPrices();
        } catch (e) {}
      },
      refreshShop() {
        if (this.updating) return;
        this.updating = true;
        const url = new URL(window.location.href);
        fetch(url.toString(), {
            credentials: 'same-origin'
          })
          .then((r) => r.text())
          .then((html) => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newBlock = doc.querySelector('#wps-shop');
            const curBlock = document.querySelector('#wps-shop');
            if (newBlock && curBlock) {
              if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                try {
                  window.Alpine.destroyTree(curBlock);
                } catch (e) {}
              }
              curBlock.innerHTML = newBlock.innerHTML;
              if (window.Alpine) {
                const hasAlpine = curBlock.querySelector('[x-data],[x-init],[x-show],[x-model],[x-on],[x-bind],[x-if],[x-for]');
                if (hasAlpine) {
                  try {
                    if (typeof window.Alpine.initTree === 'function') {
                      requestAnimationFrame(() => window.Alpine.initTree(curBlock));
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
        if (this.initializing || this.updating) return;
        clearTimeout(this._updateTimer);
        this._updateTimer = setTimeout(() => {
          this.updating = true;
          const url = new URL(window.location.href);
          url.search = this.buildQuery();
          try {
            url.pathname = url.pathname.replace(/\/page\/\d+\/?/, '/');
          } catch (e) {}
          history.replaceState({}, '', url.toString());
          fetch(url.toString(), {
              credentials: 'same-origin'
            })
            .then((r) => r.text())
            .then((html) => {
              const doc = new DOMParser().parseFromString(html, 'text/html');
              const newBlock = doc.querySelector('#wps-shop');
              const curBlock = document.querySelector('#wps-shop');
              if (newBlock && curBlock) {
                if (window.Alpine && typeof window.Alpine.destroyTree === 'function') {
                  try {
                    window.Alpine.destroyTree(curBlock);
                  } catch (e) {}
                }
                curBlock.innerHTML = newBlock.innerHTML;
                if (window.Alpine) {
                  const hasAlpine = curBlock.querySelector('[x-data]');
                  if (hasAlpine) {
                    try {
                      if (typeof window.Alpine.initTree === 'function') {
                        requestAnimationFrame(() => window.Alpine.initTree(curBlock));
                      }
                    } catch (e) {}
                  }
                }
              }
            })
            .finally(() => {
              this.updating = false;
            });
        }, 150);
      }
    }));
  });
</script>
