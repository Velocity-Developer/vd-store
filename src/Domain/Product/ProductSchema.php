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
                        'desc' => 'Harga utama produk.',
                        'required' => true,
                        'min' => 0,
                        'step' => 0.01,
                    ],
                    [
                        'name' => 'Harga Promo',
                        'id' => '_store_sale_price',
                        'type' => 'number',
                        'placeholder' => '0',
                        'desc' => 'Kosongkan jika tidak ada promo.',
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
                        'desc' => 'Wajib diisi untuk perhitungan ongkir. Gunakan angka lebih dari 0.',
                        'min' => 0.001,
                        'step' => 0.001,
                        'required' => true,
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
                        'name' => 'Nama Opsi Harga',
                        'id' => '_store_option2_name',
                        'type' => 'text',
                        'placeholder' => 'Ukuran',
                        'desc' => 'Nama pilihan yang dapat menambah harga dari harga dasar produk.',
                        'default' => 'Pilihan Harga',
                    ],
                    [
                        'name' => 'Pilihan Harga',
                        'id' => '_store_advanced_options',
                        'type' => 'textarea',
                        'placeholder' => "Small=0\nMedium=10000\nLarge=20000",
                        'desc' => '1 baris = label=tambahan_harga. Gunakan 0 jika tidak ada tambahan harga.',
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
