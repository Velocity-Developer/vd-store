<?php
$currency = isset($currency) ? (string) $currency : 'Rp';
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Katalog Produk</title>
  <style>
    @page {
      margin: 24px;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-size: 12px;
      color: #111;
      margin: 0;
      padding: 24px;
    }

    h1 {
      font-size: 18px;
      margin: 0 0 12px 0;
    }

    table.catalog {
      width: 100%;
      border-collapse: separate;
      border-spacing: 8px;
    }

    td.catalog-cell {
      width: 33.33%;
      vertical-align: top;
    }

    .card {
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 8px;
      box-sizing: border-box;
      page-break-inside: avoid;
    }

    .title {
      font-size: 12px;
      font-weight: 600;
      margin: 6px 0;
    }

    .price {
      font-size: 12px;
      color: #2563eb;
      font-weight: 700;
    }

    .frame-img {
      position: relative;
      width: 100%;
      height: 220px;
      overflow: hidden;
    }

    .img {
      display: block;
      width: 100%;
      height: auto;
      object-fit: cover;
      border-radius: 4px;
    }

    .badge {
      position: absolute;
      top: 8px;
      left: 8px;
      display: inline-block;
      padding: 2px 6px;
      border-radius: 9999px;
      font-size: 10px;
      line-height: 12px;
      color: #ffffff;
      background: #6b7280;
    }

    .badge-discount {
      position: absolute;
      bottom: 8px;
      right: 8px;
      display: inline-block;
      padding: 2px 6px;
      border-radius: 9999px;
      font-size: 10px;
      line-height: 12px;
      color: #ffffff;
      background: #ef4444;
    }

    .print-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-bottom: 16px;
    }

    .print-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 14px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      background: #fff;
      color: #111827;
      font-size: 13px;
      cursor: pointer;
    }

    .print-btn-primary {
      border-color: #2563eb;
      background: #2563eb;
      color: #fff;
    }

    @media print {
      .print-actions {
        display: none;
      }

      body {
        padding: 0;
      }
    }
  </style>
</head>

<body>
  <div class="print-actions">
    <button type="button" class="print-btn print-btn-primary" onclick="window.print()">Print</button>
    <button type="button" class="print-btn" onclick="window.close()">Tutup</button>
  </div>
  <h1>Katalog Produk</h1>
  <?php if (!empty($items)) : ?>
    <table class="catalog">
      <?php
      $cols = 3;
      $index = 0;
      foreach ($items as $item) {
        if ($index % $cols === 0) {
          echo '<tr>';
        }
        $src = is_string($item['image']) && $item['image'] !== '' ? $item['image'] : (WP_STORE_URL . 'assets/frontend/img/noimg.webp');
        $alt = is_string($item['title']) ? $item['title'] : 'Produk';
        echo '<td class="catalog-cell">';
        echo '<div class="card">';
        echo '<div class="frame-img">';
        echo '<img class="img" src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '">';
        if (!empty($item['label'])) {
          $lbl = (string) $item['label'];
          $txt = $lbl === 'label-best' ? 'Best Seller' : ($lbl === 'label-limited' ? 'Limited' : ($lbl === 'label-new' ? 'New' : ''));
          if ($txt !== '') {
            echo '<span class="badge">' . esc_html($txt) . '</span>';
          }
        }
        if (!empty($item['sale_active']) && !empty($item['discount_percent']) && (int) $item['discount_percent'] > 0) {
          echo '<span class="badge-discount">' . esc_html((int) $item['discount_percent']) . '%</span>';
        }
        echo '</div>';
        echo '<div class="title">' . esc_html($item['title']) . '</div>';
        $hasPrice = isset($item['price']) && $item['price'] !== null;
        $hasSale = !empty($item['sale_active']) && isset($item['sale_price']) && $item['sale_price'] !== null;
        if ($hasSale) {
          $sale_val = (float) $item['sale_price'];
          $sale_fmt = number_format($sale_val, 0, ',', '.');
          echo '<div class="price">' . esc_html(($currency ?? 'Rp') . ' ' . $sale_fmt) . '</div>';
          if ($hasPrice) {
            $price_val = (float) $item['price'];
            $price_fmt = number_format($price_val, 0, ',', '.');
            echo '<div style="font-size:11px; color:#6b7280; text-decoration: line-through;">' . esc_html(($currency ?? 'Rp') . ' ' . $price_fmt) . '</div>';
          }
        } elseif ($hasPrice) {
          $price_val = (float) ($item['price']);
          $price_fmt = number_format($price_val, 0, ',', '.');
          echo '<div class="price">' . esc_html(($currency ?? 'Rp') . ' ' . $price_fmt) . '</div>';
        } else {
          echo '<div style="font-size:11px; color:#6b7280;">' . esc_html(apply_filters('wp_store_empty_price_text', 'Hubungi Admin', (int) ($item['id'] ?? 0), [])) . '</div>';
        }
        echo '</div>';
        echo '</td>';
        $index++;
        if ($index % $cols === 0) {
          echo '</tr>';
        }
      }
      if ($index % $cols !== 0) {
        while ($index % $cols !== 0) {
          echo '<td class="catalog-cell"></td>';
          $index++;
        }
        echo '</tr>';
      }
      ?>
    </table>
  <?php else : ?>
    <div>Tidak ada produk.</div>
  <?php endif; ?>
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.print();
      }, 150);
    });
  </script>
</body>

</html>
