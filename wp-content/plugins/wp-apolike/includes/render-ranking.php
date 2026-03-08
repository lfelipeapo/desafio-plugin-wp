<?php
if (!defined('ABSPATH')) {
    exit;
}

$postsToShow = isset($attributes['postsToShow']) ? (int) $attributes['postsToShow'] : 5;
$showCounts = !empty($attributes['showCounts']);
$showExcerpt = !empty($attributes['showExcerpt']);

$postsToShow = max(1, min(50, $postsToShow));

$q = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => $postsToShow,
    'meta_key' => 'wpld_score',
    'orderby' => 'meta_value_num',
    'order' => 'DESC',
    'meta_query' => [
        ['key' => 'wpld_score', 'compare' => 'EXISTS'],
    ],
]);

if (!$q->have_posts()) {
    echo '<div class="wpld-ranking"><p>' . esc_html__('Sem dados ainda.', 'wp-apolike') . '</p></div>';
    return;
}

echo '<div class="wpld-ranking"><ol class="wpld-ranking-list">';
while ($q->have_posts()) {
    $q->the_post();
    $post_id = get_the_ID();
    $score = (int) get_post_meta($post_id, 'wpld_score', true);
    $likes = (int) get_post_meta($post_id, 'wpld_likes', true);
    $dislikes = (int) get_post_meta($post_id, 'wpld_dislikes', true);

    echo '<li class="wpld-ranking-item">';
    echo '<a href="' . esc_url(get_permalink()) . '" class="wpld-ranking-title">' . esc_html(get_the_title()) . '</a>';

    if ($showCounts) {
        echo ' <span class="wpld-ranking-counts">— <strong>' . $score . '</strong> (👍 ' . $likes . ' / 👎 ' . $dislikes . ')</span>';
    }

    if ($showExcerpt) {
        echo '<p class="wpld-ranking-excerpt">' . wp_trim_words(get_the_excerpt(), 20) . '</p>';
    }

    echo '</li>';
}
wp_reset_postdata();
echo '</ol></div>';
