<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_DB {
    /**
     * Inicializa o banco de dados
     */
    public static function init() {
        // Placeholder para inicialização futura se necessário
    }

    /**
     * Retorna o nome da tabela de votos
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wpld_votes';
    }

    /**
     * Cria a tabela de votos durante a ativação do plugin
     */
    public static function activate() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // vote: -1 dislike, 0 sem voto, 1 like
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            visitor_hash CHAR(64) NOT NULL,
            vote TINYINT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_vote (post_id, visitor_hash),
            KEY idx_post (post_id),
            KEY idx_updated (updated_at)
        ) $charset;";

        dbDelta($sql);
    }

    /**
     * Obtém as contagens de likes, dislikes e score de um post
     */
    public static function get_counts(int $post_id): array {
        $likes = (int) get_post_meta($post_id, 'wpld_likes', true);
        $dislikes = (int) get_post_meta($post_id, 'wpld_dislikes', true);
        $score = (int) get_post_meta($post_id, 'wpld_score', true);
        return compact('likes', 'dislikes', 'score');
    }

    /**
     * Garante que os meta fields estejam inicializados para um post
     */
    public static function ensure_meta_initialized(int $post_id): void {
        foreach (['wpld_likes', 'wpld_dislikes', 'wpld_score'] as $k) {
            if (get_post_meta($post_id, $k, true) === '') {
                update_post_meta($post_id, $k, 0);
            }
        }
    }

    /**
     * Aplica um voto (like/dislike) de um visitante a um post
     * Retorna o novo estado do post
     */
    public static function apply_vote(int $post_id, string $visitor_hash, int $new_vote): array {
        global $wpdb;
        $table = self::table();

        self::ensure_meta_initialized($post_id);

        // Verifica rate limit
        if (!WPLD_RateLimit::check($post_id, $visitor_hash)) {
            return [
                'error' => 'Muitas tentativas. Aguarde alguns segundos.',
                'likes' => (int) get_post_meta($post_id, 'wpld_likes', true),
                'dislikes' => (int) get_post_meta($post_id, 'wpld_dislikes', true),
                'score' => (int) get_post_meta($post_id, 'wpld_score', true),
                'myVote' => self::get_my_vote($post_id, $visitor_hash),
            ];
        }

        // Descobre voto anterior (se houver)
        $old_vote = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT vote FROM $table WHERE post_id=%d AND visitor_hash=%s",
            $post_id,
            $visitor_hash
        ));

        // Toggle: se clicar no mesmo, remove (vira 0)
        if ($old_vote === $new_vote) {
            $new_vote = 0;
        }

        // Atualiza tabela
        if ($new_vote === 0) {
            $wpdb->delete($table, ['post_id' => $post_id, 'visitor_hash' => $visitor_hash], ['%d', '%s']);
        } else {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (post_id, visitor_hash, vote, updated_at)
                 VALUES (%d, %s, %d, %s)
                 ON DUPLICATE KEY UPDATE vote=VALUES(vote), updated_at=VALUES(updated_at)",
                $post_id,
                $visitor_hash,
                $new_vote,
                current_time('mysql')
            ));
        }

        // Ajusta agregados de forma consistente (delta)
        $likes = (int) get_post_meta($post_id, 'wpld_likes', true);
        $dislikes = (int) get_post_meta($post_id, 'wpld_dislikes', true);

        // Remove efeito do voto antigo
        if ($old_vote === 1) {
            $likes--;
        }
        if ($old_vote === -1) {
            $dislikes--;
        }

        // Aplica efeito do novo
        if ($new_vote === 1) {
            $likes++;
        }
        if ($new_vote === -1) {
            $dislikes++;
        }

        $likes = max(0, $likes);
        $dislikes = max(0, $dislikes);
        $score = $likes - $dislikes;

        update_post_meta($post_id, 'wpld_likes', $likes);
        update_post_meta($post_id, 'wpld_dislikes', $dislikes);
        update_post_meta($post_id, 'wpld_score', $score);

        // Limpa cache do ranking
        WPLD_Cache::invalidate_ranking();

        return [
            'likes' => $likes,
            'dislikes' => $dislikes,
            'score' => $score,
            'myVote' => $new_vote,
        ];
    }

    /**
     * Obtém o voto atual de um visitante em um post
     */
    public static function get_my_vote(int $post_id, string $visitor_hash): int {
        global $wpdb;
        $table = self::table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT vote FROM $table WHERE post_id=%d AND visitor_hash=%s",
            $post_id,
            $visitor_hash
        ));
    }
}
