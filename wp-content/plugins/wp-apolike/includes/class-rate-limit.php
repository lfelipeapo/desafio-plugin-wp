<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_RateLimit {
    const RATE_LIMIT_TTL = 2; // 2 segundos
    const RATE_LIMIT_PREFIX = 'wpld_rate_limit_';

    /**
     * Verifica se o visitante pode fazer um voto (rate limit)
     * Usa IP + post_id como chave
     */
    public static function check(int $post_id, string $visitor_hash): bool {
        $ip = self::get_client_ip();
        $key = self::RATE_LIMIT_PREFIX . $post_id . '_' . $ip;

        // Verifica se existe transient (significa que foi votado recentemente)
        if (get_transient($key)) {
            return false;
        }

        // Define transient para rate limit
        set_transient($key, true, self::RATE_LIMIT_TTL);
        return true;
    }

    /**
     * Obtém o IP do cliente de forma segura
     */
    private static function get_client_ip(): string {
        // Tenta obter IP real mesmo atrás de proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // Sanitiza o IP
        return sanitize_text_field($ip);
    }
}
