<?php

namespace WpStore\Domain\Review;

class ProductReviewRepository
{
    private $table_exists = null;

    public function table()
    {
        global $wpdb;

        $table = apply_filters('wp_store_product_review_table', $wpdb->prefix . 'vmp_reviews', $wpdb->prefix);
        return is_string($table) && $table !== '' ? $table : ($wpdb->prefix . 'vmp_reviews');
    }

    public function product_reviews($product_id, $limit = 20)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        $limit = max(1, min(100, (int) $limit));
        if ($product_id <= 0 || !$this->table_exists()) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE product_id = %d AND is_approved = 1 ORDER BY created_at DESC LIMIT %d",
                $product_id,
                $limit
            ),
            ARRAY_A
        );

        $reviews = [];
        foreach ((array) $rows as $row) {
            $normalized = $this->normalize_row($row);
            $user = get_userdata((int) $normalized['user_id']);
            $normalized['user_name'] = $user && $user->display_name !== '' ? $user->display_name : ($user ? $user->user_login : 'Member');
            $normalized['image_urls'] = $this->image_urls((array) ($normalized['image_ids'] ?? []));
            $reviews[] = $normalized;
        }

        return $reviews;
    }

    public function product_summary($product_id)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return ['review_count' => 0, 'rating_average' => 0.0];
        }

        if (!metadata_exists('post', $product_id, '_store_review_count') || !metadata_exists('post', $product_id, '_store_rating_average')) {
            return $this->recalculate_product_meta($product_id);
        }

        return [
            'review_count' => (int) get_post_meta($product_id, '_store_review_count', true),
            'rating_average' => (float) get_post_meta($product_id, '_store_rating_average', true),
        ];
    }

    public function find_by_keys($product_id, $order_id, $user_id)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        if ($product_id <= 0 || $order_id <= 0 || $user_id <= 0 || !$this->table_exists()) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE product_id = %d AND order_id = %d AND user_id = %d LIMIT 1",
                $product_id,
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->normalize_row($row) : null;
    }

    public function reviews_for_order_user($order_id, $user_id)
    {
        global $wpdb;

        $order_id = (int) $order_id;
        $user_id = (int) $user_id;
        if ($order_id <= 0 || $user_id <= 0 || !$this->table_exists()) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE order_id = %d AND user_id = %d ORDER BY created_at DESC",
                $order_id,
                $user_id
            ),
            ARRAY_A
        );

        $mapped = [];
        foreach ((array) $rows as $row) {
            $normalized = $this->normalize_row($row);
            $mapped[(int) $normalized['product_id']] = $normalized;
        }

        return $mapped;
    }

    public function can_review($order_id, $product_id, $user_id)
    {
        $order_id = (int) $order_id;
        $product_id = (int) $product_id;
        $user_id = (int) $user_id;

        if ($order_id <= 0 || $product_id <= 0 || $user_id <= 0) {
            return false;
        }

        if (get_post_type($order_id) !== 'store_order') {
            return false;
        }

        $owner_id = (int) get_post_meta($order_id, '_store_order_user_id', true);
        if ($owner_id > 0 && $owner_id !== $user_id) {
            return false;
        }

        if ($owner_id <= 0) {
            $order_email = (string) get_post_meta($order_id, '_store_order_email', true);
            $user = get_userdata($user_id);
            if (!$user || $order_email === '' || !hash_equals(strtolower($order_email), strtolower((string) $user->user_email))) {
                return false;
            }
        }

        $status = (string) get_post_meta($order_id, '_store_order_status', true);
        if ($status !== 'completed') {
            return false;
        }

        $items = (array) get_post_meta($order_id, '_store_order_items', true);
        foreach ($items as $item) {
            if ((int) ($item['product_id'] ?? 0) === $product_id) {
                return true;
            }
        }

        return false;
    }

    public function save($data)
    {
        global $wpdb;

        $product_id = isset($data['product_id']) ? (int) $data['product_id'] : 0;
        $order_id = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $rating = isset($data['rating']) ? max(1, min(5, (int) $data['rating'])) : 0;
        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        $content = sanitize_textarea_field((string) ($data['content'] ?? ''));
        $image_ids = isset($data['image_ids']) && is_array($data['image_ids'])
            ? array_values(array_filter(array_map('intval', $data['image_ids'])))
            : [];

        if ($product_id <= 0 || $order_id <= 0 || $user_id <= 0 || $rating <= 0 || $content === '' || !$this->table_exists()) {
            return 0;
        }

        if (!$this->can_review($order_id, $product_id, $user_id)) {
            return 0;
        }

        $seller_id = (int) get_post_field('post_author', $product_id);
        $existing = $this->find_by_keys($product_id, $order_id, $user_id);
        $now = current_time('mysql');

        if ($existing) {
            $updated = $wpdb->update(
                $this->table(),
                [
                    'seller_id' => $seller_id,
                    'rating' => $rating,
                    'title' => $title,
                    'content' => $content,
                    'image_ids' => $this->image_ids_to_string($image_ids),
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                return 0;
            }

            $review_id = (int) $existing['id'];
        } else {
            $inserted = $wpdb->insert(
                $this->table(),
                [
                    'product_id' => $product_id,
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'seller_id' => $seller_id,
                    'rating' => $rating,
                    'title' => $title,
                    'content' => $content,
                    'image_ids' => $this->image_ids_to_string($image_ids),
                    'is_approved' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
            );

            if (!$inserted) {
                return 0;
            }

            $review_id = (int) $wpdb->insert_id;
        }

        $this->recalculate_product_meta($product_id);

        return $review_id;
    }

    public function recalculate_product_meta($product_id)
    {
        global $wpdb;

        $product_id = (int) $product_id;
        if ($product_id <= 0 || !$this->table_exists()) {
            return ['review_count' => 0, 'rating_average' => 0.0];
        }

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS review_count, AVG(rating) AS rating_average
                FROM {$this->table()}
                WHERE product_id = %d AND is_approved = 1",
                $product_id
            ),
            ARRAY_A
        );

        $review_count = isset($stats['review_count']) ? (int) $stats['review_count'] : 0;
        $rating_average = isset($stats['rating_average']) ? round((float) $stats['rating_average'], 2) : 0.0;

        update_post_meta($product_id, '_store_review_count', $review_count);
        update_post_meta($product_id, '_store_rating_average', $rating_average);

        return [
            'review_count' => $review_count,
            'rating_average' => $rating_average,
        ];
    }

    private function normalize_row($row)
    {
        $row = is_array($row) ? $row : [];

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : 0,
            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'seller_id' => isset($row['seller_id']) ? (int) $row['seller_id'] : 0,
            'rating' => isset($row['rating']) ? max(0, min(5, (int) $row['rating'])) : 0,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'content' => isset($row['content']) ? (string) $row['content'] : '',
            'image_ids' => $this->parse_image_ids(isset($row['image_ids']) ? $row['image_ids'] : ''),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
        ];
    }

    private function parse_image_ids($raw)
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }

        $raw = (string) $raw;
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded)));
        }

        $parts = preg_split('/[\s,]+/', $raw);
        return array_values(array_filter(array_map('intval', (array) $parts)));
    }

    private function image_urls(array $image_ids)
    {
        $urls = [];
        foreach ($image_ids as $image_id) {
            $image_id = (int) $image_id;
            if ($image_id <= 0) {
                continue;
            }

            $url = wp_get_attachment_image_url($image_id, 'medium');
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function image_ids_to_string(array $image_ids)
    {
        return wp_json_encode(array_values(array_filter(array_map('intval', $image_ids))));
    }

    private function table_exists()
    {
        global $wpdb;

        if ($this->table_exists !== null) {
            return $this->table_exists;
        }

        $table = $this->table();
        $this->table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;

        return $this->table_exists;
    }
}
