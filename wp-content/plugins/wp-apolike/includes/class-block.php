<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_Block {
    /**
     * Registra os hooks do bloco
     */
    public function register() {
        add_action('init', [$this, 'register_block']);
    }

    /**
     * Registra o script do editor e o bloco dinâmico via PHP puro.
     * Não depende de block.json nem de pipeline de build.
     *
     * Ordem deliberada:
     *   1. wp_register_style  — CSS registrado ANTES do bloco (evita handle não encontrado)
     *   2. wp_register_script — script do editor com deps completas + wp-editor como fallback
     *   3. register_block_type — bloco referencia handles já registrados
     */
    public function register_block() {
        // A) Registra o CSS do bloco ANTES de registrar o bloco
        wp_register_style(
            'wpld-ranking-style',
            WPLD_URL . 'blocks/ranking/style.css',
            [],
            WPLD_VERSION
        );

        // B) Deps do editor: inclui wp-block-editor (WP moderno) + wp-editor (fallback WP antigo)
        wp_register_script(
            'wpld-block',
            WPLD_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-i18n',
                'wp-block-editor', // WP 5.2+
                'wp-editor',       // fallback para instâncias mais antigas
            ],
            WPLD_VERSION,
            true // carrega no footer
        );

        // Registra o bloco dinâmico inteiramente via PHP (sem block.json)
        register_block_type('wpld/ranking', [
            'editor_script'   => 'wpld-block',
            'style'           => 'wpld-ranking-style',
            'attributes'      => [
                'postsToShow' => [
                    'type'    => 'number',
                    'default' => 5,
                ],
                'showCounts'  => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
                'showExcerpt' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
            'render_callback' => [$this, 'render_ranking'],
        ]);
    }

    /**
     * Render callback do bloco dinâmico.
     * Sempre reflete a pontuação persistida no banco.
     *
     * @param array $attributes Atributos do bloco.
     * @return string HTML renderizado.
     */
    public function render_ranking(array $attributes): string {
        $posts_to_show = isset($attributes['postsToShow']) ? (int) $attributes['postsToShow'] : 5;
        $show_counts   = !empty($attributes['showCounts']);
        $show_excerpt  = !empty($attributes['showExcerpt']);

        $posts_to_show = max(1, min(50, $posts_to_show));

        // Tenta servir do cache antes de executar a WP_Query
        $cache_key = WPLD_Cache::make_key($posts_to_show, $show_counts, $show_excerpt);
        $cached    = WPLD_Cache::get($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $q = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => $posts_to_show,
            'meta_key'       => 'wpld_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => [
                ['key' => 'wpld_score', 'compare' => 'EXISTS'],
            ],
        ]);

        if (!$q->have_posts()) {
            return '<div class="wpld-ranking"><p>' . esc_html__('Sem dados ainda.', 'wp-apolike') . '</p></div>';
        }

        $out = '<div class="wpld-ranking"><ol class="wpld-ranking-list">';

        while ($q->have_posts()) {
            $q->the_post();
            $post_id  = get_the_ID();
            $score    = (int) get_post_meta($post_id, 'wpld_score', true);
            $likes    = (int) get_post_meta($post_id, 'wpld_likes', true);
            $dislikes = (int) get_post_meta($post_id, 'wpld_dislikes', true);

            $out .= '<li class="wpld-ranking-item">';
            $out .= '<a href="' . esc_url(get_permalink()) . '" class="wpld-ranking-title">'
                  . esc_html(get_the_title()) . '</a>';

            if ($show_counts) {
                $out .= ' <span class="wpld-ranking-counts">— <strong>' . $score
                      . '</strong> (👍 ' . $likes . ' / 👎 ' . $dislikes . ')</span>';
            }

            if ($show_excerpt) {
                $out .= '<p class="wpld-ranking-excerpt">'
                      . wp_trim_words(get_the_excerpt(), 20) . '</p>';
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
