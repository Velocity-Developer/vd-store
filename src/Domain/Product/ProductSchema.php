<?php

namespace WpStore\Domain\Product;

class ProductSchema
{
    private static function has_marketplace()
    {
        return defined('VMP_VERSION')
            || defined('VMP_PATH')
            || class_exists('\VelocityMarketplace\Core\Plugin');
    }

    public static function sections($context = 'frontend')
    {
        $sections = [
            [
                'id' => 'media',
                'title' => 'Media Produk',
                'fields' => [
                    [
                        'name' => 'Galeri Produk',
                        'id' => '_store_gallery_ids',
                        'type' => 'image',
                        'desc' => 'Pilih beberapa gambar dari media library untuk galeri produk.',
                        'contexts' => ['frontend', 'admin'],
                        'full_width' => true,
                        'multiple' => true,
                        'media_library' => true,
                    ],
                ],
            ],
            [
                'id' => 'pricing',
                'title' => 'Harga & Inventory',
                'fields' => [
                    [
                        'name' => 'Tipe Produk',
                        'id' => '_store_product_type',
                        'type' => 'select',
                        'desc' => 'Produk fisik atau digital.',
                        'options' => [
                            'physical' => 'Produk Fisik',
                            'digital' => 'Produk Digital',
                        ],
                        'default' => 'physical',
                    ],
                    [
                        'name' => 'Label Produk',
                        'id' => '_store_label',
                        'type' => 'select',
                        'options' => ProductQuery::label_options(),
                        'default' => '',
                        'contexts' => ['frontend', 'admin'],
                    ],
                    [
                        'name' => 'SKU',
                        'id' => '_store_sku',
                        'type' => 'text',
                        'placeholder' => 'SKU produk',
                        'desc' => 'Kode unik produk.',
                    ],
                    [
                        'name' => 'Harga Regular',
                        'id' => '_store_price',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Harga utama yang tampil di daftar produk. Jika memakai harga akhir setiap pilihan, isi dengan harga pilihan yang paling murah.',
                        'min' => 0,
                        'step' => 0.01,
                    ],
                    [
                        'name' => 'Harga Promo',
                        'id' => '_store_sale_price',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika tidak ada promo. Harga promo tidak mengubah angka pada pilihan yang memakai harga akhir.',
                        'min' => 0,
                        'step' => 0.01,
                    ],
                    [
                        'name' => 'Promo Sampai',
                        'id' => '_store_flashsale_until',
                        'type' => 'date',
                        'placeholder' => '',
                        'desc' => 'Tanggal akhir harga promo.',
                    ],
                    [
                        'name' => 'File Produk Digital',
                        'id' => '_store_digital_file',
                        'type' => 'file_url',
                        'desc' => 'Bisa pilih file dari media library atau isi URL file eksternal.',
                        'required' => true,
                        'contexts' => ['frontend', 'admin'],
                        'full_width' => true,
                        'media_library' => true,
                        'show_if_product_type' => 'digital',
                    ],
                    [
                        'name' => 'Stok',
                        'id' => '_store_stock',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika stok tidak dibatasi.',
                        'min' => 0,
                        'step' => 1,
                    ],
                    [
                        'name' => 'Berat (kg)',
                        'id' => '_store_weight_kg',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika produk hanya untuk katalog atau inquiry. Produk fisik tanpa berat tidak bisa dibeli.',
                        'min' => 0,
                        'step' => 0.001,
                        'show_if_product_type' => 'physical',
                    ],
                    [
                        'name' => 'Minimal Order',
                        'id' => '_store_min_order',
                        'type' => 'number',
                        'placeholder' => '1',
                        'desc' => 'Jumlah minimum pembelian.',
                        'min' => 1,
                        'step' => 1,
                    ],
                ],
            ],
            [
                'id' => 'options',
                'title' => 'Opsi Produk',
                'fields' => [
                    [
                        'name' => 'Nama Opsi Varian',
                        'id' => '_store_option_name',
                        'type' => 'text',
                        'placeholder' => 'Warna',
                        'desc' => 'Nama pilihan yang tidak mengubah harga, misalnya Warna atau Motif.',
                        'default' => 'Pilihan Varian',
                    ],
                    [
                        'name' => 'Pilihan Varian',
                        'id' => '_store_options',
                        'type' => 'textarea',
                        'placeholder' => 'Merah, Biru, Hijau',
                        'desc' => 'Pisahkan dengan koma. Pilihan ini tidak mengubah harga produk.',
                        'rows' => 2,
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Nama Pilihan Harga',
                        'id' => '_store_option2_name',
                        'type' => 'text',
                        'placeholder' => 'Ukuran',
                        'desc' => 'Contoh: Ukuran, Kapasitas, Paket, atau jenis pilihan lain yang harganya berbeda.',
                        'default' => 'Pilihan Harga',
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Jenis Harga',
                        'id' => '_store_option_price_mode',
                        'type' => 'select',
                        'options' => [
                            'adjustment' => 'Tambahan Harga',
                            'absolute' => 'Harga Tetap',
                        ],
                        'desc' => 'Tambahan Harga dijumlahkan dengan harga produk. Harga Tetap menjadi harga akhir pilihan.',
                        'default' => 'adjustment',
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Daftar Pilihan dan Harga',
                        'id' => '_store_advanced_options',
                        'type' => 'textarea',
                        'placeholder' => "Small=0\nMedium=10000\nLarge=20000",
                        'desc' => 'Satu baris untuk satu pilihan. Format: Nama=Angka, contoh: Small=100000.',
                        'rows' => 4,
                        'full_width' => true,
                    ],
                    [
                        'name' => 'Ajukan Iklan Premium',
                        'id' => 'premium_request',
                        'type' => 'checkbox',
                        'placeholder' => '',
                        'desc' => 'Produk akan masuk antrian review premium.',
                        'contexts' => ['frontend', 'admin'],
                        'requires_marketplace' => true,
                    ],
                    [
                        'name' => 'Produk Premium',
                        'id' => 'is_premium',
                        'type' => 'checkbox',
                        'placeholder' => '',
                        'desc' => 'Tampilkan produk di urutan atas.',
                        'contexts' => ['admin'],
                        'requires_marketplace' => true,
                    ],
                ],
            ],
        ];

        $sections = apply_filters('wp_store_product_schema_sections', $sections, $context);
        if (!is_array($sections)) {
            $sections = [];
        }

        $filtered = [];
        foreach ($sections as $section) {
            $fields = [];
            foreach ((array) $section['fields'] as $field) {
                if (!empty($field['requires_marketplace']) && !self::has_marketplace()) {
                    continue;
                }
                $contexts = isset($field['contexts']) && is_array($field['contexts']) ? $field['contexts'] : ['frontend', 'admin'];
                if (!in_array($context, $contexts, true)) {
                    continue;
                }
                $fields[] = $field;
            }
            if (!empty($fields)) {
                $section['fields'] = $fields;
                $filtered[] = $section;
            }
        }

        return $filtered;
    }

    public static function tabs($context = 'admin')
    {
        $sections = self::sections($context);
        foreach ($sections as $section_index => $section) {
            foreach ((array) $section['fields'] as $field_index => $field) {
                $sections[$section_index]['fields'][$field_index]['label'] = (string) ($field['name'] ?? ($field['label'] ?? ''));
                if (($field['type'] ?? '') === 'number') {
                    $sections[$section_index]['fields'][$field_index]['attributes'] = [
                        'min' => $field['min'] ?? '',
                        'step' => $field['step'] ?? '',
                    ];
                } elseif (($field['type'] ?? '') === 'date') {
                    $sections[$section_index]['fields'][$field_index]['type'] = 'datetime-local';
                } elseif (($field['type'] ?? '') === 'textarea' && ($field['id'] ?? '') === '_store_options') {
                    $sections[$section_index]['fields'][$field_index]['type'] = 'repeatable_text';
                } elseif (($field['type'] ?? '') === 'textarea' && ($field['id'] ?? '') === '_store_advanced_options') {
                    $sections[$section_index]['fields'][$field_index]['type'] = 'group_advanced_options';
                } elseif ((($field['type'] ?? '') === 'image' || ($field['type'] ?? '') === 'file') && !empty($field['multiple'])) {
                    $sections[$section_index]['fields'][$field_index]['type'] = 'file_list';
                }
            }
        }

        return $sections;
    }
}
