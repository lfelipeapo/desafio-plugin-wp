<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_Cache {
    const RANKING_CACHE_TTL    = 3600; // 1 hora
    const RANKING_CACHE_PREFIX = 'wpld_ranking_';

    /**
     * Gera uma chave de cache única para cada combinação de atributos do ranking.
     * Isso permite que blocos/shortcodes com configurações diferentes tenham
     * caches independentes.
     *
     * @param int  $posts_to_show Número de posts.
     * @param bool $show_counts   Exibir contagens.
     * @param bool $show_excerpt  Exibir resumo.
     * @return string
     */
    public static function make_key(int $posts_to_show, bool $show_counts, bool $show_excerpt): string {
        return self::RANKING_CACHE_PREFIX
            . $posts_to_show
            . '_' . ($show_counts  ? '1' : '0')
            . '_' . ($show_excerpt ? '1' : '0');
    }

    /**
     * Obtém o HTML do ranking do cache.
     * Retorna false se não houver cache.
     *
     * @param string $key Chave gerada por make_key().
     * @return string|false
     */
    public static function get(string $key) {
        return get_transient($key);
    }

    /**
     * Armazena o HTML do ranking no cache.
     *
     * @param string $key  Chave gerada por make_key().
     * @param string $html HTML a ser cacheado.
     */
    public static function set(string $key, string $html): void {
        set_transient($key, $html, self::RANKING_CACHE_TTL);
    }

    /**
     * Invalida todos os caches de ranking do plugin.
     * Chamado automaticamente após cada voto em WPLD_DB::apply_vote().
     */
    public static function invalidate_ranking(): void {
        global $wpdb;
        // Remove todos os transients com o prefixo do plugin
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                    OR option_name LIKE %s",
                '_transient_'         . self::RANKING_CACHE_PREFIX . '%',
                '_transient_timeout_' . self::RANKING_CACHE_PREFIX . '%'
            )
        );
    }

    /**
     * Limpa todos os caches do plugin (alias para invalidate_ranking).
     */
    public static function clear_all(): void {
        self::invalidate_ranking();
    }
}
