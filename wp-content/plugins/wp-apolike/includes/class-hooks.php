<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_Hooks {
    /**
     * Registra os hooks do plugin
     */
    public function register() {
        add_filter('the_content', [$this, 'inject_ui']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_shortcode('wpld-ranking', [$this, 'render_ranking_shortcode']);
    }

    /**
     * Enfileira os assets (CSS e JS)
     */
    public function enqueue() {
        if (!is_singular('post')) {
            return;
        }

        wp_enqueue_style('wpld-vote', WPLD_URL . 'assets/vote.css', [], WPLD_VERSION);
        wp_enqueue_script('wpld-vote', WPLD_URL . 'assets/vote.js', ['wp-api-fetch'], WPLD_VERSION, true);

        wp_localize_script('wpld-vote', 'WPLD', [
            // C) get_queried_object_id() é mais seguro fora do loop principal
            'postId' => get_queried_object_id(),
            'restBase' => esc_url_raw(rest_url('wpld/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Injeta a UI de votação no conteúdo do post
     */
    public function inject_ui($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();

        $ui = '<div class="wpld" data-post-id="' . esc_attr($post_id) . '">
            <div class="wpld-container">
                <button class="wpld-btn wpld-like" data-vote="1" type="button" title="Gostei">
                    <span class="wpld-icon">👍</span>
                    <span class="wpld-like-count">0</span>
                </button>
                <button class="wpld-btn wpld-dislike" data-vote="-1" type="button" title="Não gostei">
                    <span class="wpld-icon">👎</span>
                    <span class="wpld-dislike-count">0</span>
                </button>
                <span class="wpld-score">
                    <strong class="wpld-score-value">0</strong>
                    <span class="wpld-score-label">pontos</span>
                </span>
            </div>
        </div>';

        return $content . $ui;
    }

    /**
     * Shortcode para exibir o ranking de posts
     */
    public function render_ranking_shortcode($atts) {
        $atts = shortcode_atts([
            'posts_to_show' => 5,
            'show_counts'   => 'true',
            'show_excerpt'  => 'false',
        ], $atts, 'wpld-ranking');

        // D) filter_var evita que string 'false' seja avaliada como true
        return $this->get_ranking_html(
            (int) $atts['posts_to_show'],
            filter_var($atts['show_counts'],  FILTER_VALIDATE_BOOLEAN),
            filter_var($atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN)
        );
    }

    /**
     * Gera o HTML do ranking (com cache transient)
     */
    private function get_ranking_html($posts_to_show = 5, $show_counts = true, $show_excerpt = false) {
        $posts_to_show = max(1, min(50, $posts_to_show));

        // Tenta servir do cache antes de executar a WP_Query
        $cache_key = WPLD_Cache::make_key($posts_to_show, (bool) $show_counts, (bool) $show_excerpt);
        $cached    = WPLD_Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $q = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => $posts_to_show,
            'meta_key' => 'wpld_score',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => [
                ['key' => 'wpld_score', 'compare' => 'EXISTS'],
            ],
        ]);

        if (!$q->have_posts()) {
            return '<div class="wpld-ranking"><p>' . esc_html__('Sem dados ainda.', 'wp-apolike') . '</p></div>';
        }

        $out = '<div class="wpld-ranking"><ol class="wpld-ranking-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();
            $score = (int) get_post_meta($post_id, 'wpld_score', true);
            $likes = (int) get_post_meta($post_id, 'wpld_likes', true);
            $dislikes = (int) get_post_meta($post_id, 'wpld_dislikes', true);

            $out .= '<li class="wpld-ranking-item">';
            $out .= '<a href="' . esc_url(get_permalink()) . '" class="wpld-ranking-title">' . esc_html(get_the_title()) . '</a>';

            if ($show_counts) {
                $out .= ' <span class="wpld-ranking-counts">— <strong>' . $score . '</strong> (👍 ' . $likes . ' / 👎 ' . $dislikes . ')</span>';
            }

            if ($show_excerpt) {
                $out .= '<p class="wpld-ranking-excerpt">' . wp_trim_words(get_the_excerpt(), 20) . '</p>';
            }

            $out .= '</li>';
        }
        wp_reset_postdata();
        $out .= '</ol></div>';

        // Armazena no cache para as próximas requisições
        WPLD_Cache::set($cache_key, $out);

        return $out;
    }
}
