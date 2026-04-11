<?php

namespace WpStore\Admin;

use WpStore\Domain\Product\ProductData;

class ProductColumns
{
    public function register()
    {
        add_filter('manage_store_product_posts_columns', [$this, 'add_columns']);
        add_action('manage_store_product_posts_custom_column', [$this, 'render_columns'], 10, 2);
    }

    public function add_columns($columns)
    {
        $new_columns = [];
        
        // Loop through existing columns to insert ours in specific positions
        foreach ($columns as $key => $title) {
            // Insert Thumbnail before Title
            if ($key === 'title') {
                $new_columns['thumbnail'] = 'Thumbnail';
            }
            
            $new_columns[$key] = $title;
            
            // Insert Price and Author after Title
            if ($key === 'title') {
                $new_columns['price'] = 'Harga';
                $new_columns['author_name'] = 'Author';
            }
        }
        
        return $new_columns;
    }

    public function render_columns($column, $post_id)
    {
        switch ($column) {
            case 'thumbnail':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [50, 50]);
                } else {
                    $fallback = WP_STORE_URL . 'assets/frontend/img/noimg.webp';
                    echo '<img src="' . esc_url($fallback) . '" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:4px;" />';
                }
                break;
                
            case 'price':
                $product = ProductData::map_post((int) $post_id);
                $price = is_array($product) ? ($product['price'] ?? null) : null;
                if ($price !== null) {
                    echo 'Rp ' . number_format((float) $price, 0, ',', '.');
                } else {
                    echo '-';
                }
                break;

            case 'author_name':
                $author_id = (int) get_post_field('post_author', $post_id);
                if ($author_id > 0) {
                    $user = get_userdata($author_id);
                    echo esc_html($user ? $user->display_name : ('User #' . $author_id));
                } else {
                    echo '-';
                }
                break;
        }
    }
}
