<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPLD_Rest {
    /**
     * Registra as rotas REST API
     */
    public function register() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Registra as rotas REST
     */
    public function register_routes() {
        // Rota para obter o estado de votação de um post
        register_rest_route('wpld/v1', '/state/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'get_state'],
            'args' => ['id' => ['required' => true]],
        ]);

        // Rota para registrar um voto
        register_rest_route('wpld/v1', '/vote', [
            'methods'             => 'POST',
            // Valida o nonce wp_rest enviado pelo frontend via X-WP-Nonce.
            // Visitantes anônimos passam normalmente; a checagem apenas garante
            // que a requisição veio de uma página servida pelo próprio WordPress.
            'permission_callback' => [$this, 'verify_nonce'],
            'callback'            => [$this, 'post_vote'],
        ]);
    }

    /**
     * Valida o nonce wp_rest para o endpoint de escrita (POST /vote).
     * O nonce é enviado automaticamente pelo wp.apiFetch via header X-WP-Nonce.
     * Retorna true para qualquer visitante com nonce válido (anônimo ou logado).
     */
    public function verify_nonce(): bool {
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE'])
            ? sanitize_text_field($_SERVER['HTTP_X_WP_NONCE'])
            : '';

        if (wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Nonce inválido ou ausente.', 'wp-apolike'),
            ['status' => 403]
        );
    }

    /**
     * Obtém ou cria um ID de visitante
     */
    private function get_or_set_visitor_id(): string {
        if (!empty($_COOKIE['wpld_vid'])) {
            return sanitize_text_field($_COOKIE['wpld_vid']);
        }

        $vid = wp_generate_uuid4();
        // 1 ano
        setcookie('wpld_vid', $vid, time() + 365 * 24 * 60 * 60, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['wpld_vid'] = $vid;
        return $vid;
    }

    /**
     * Gera hash do visitante usando HMAC SHA256
     */
    private function visitor_hash(): string {
        $vid = $this->get_or_set_visitor_id();
        return hash_hmac('sha256', $vid, wp_salt('auth'));
    }

    /**
     * Callback para GET /wpld/v1/state/<postId>
     */
    public function get_state(WP_REST_Request $req) {
        $post_id = (int) $req['id'];

        // Valida o post
        if (!get_post($post_id)) {
            return new WP_REST_Response(['error' => 'Post inválido'], 404);
        }

        // Inicializa meta fields se necessário
        WPLD_DB::ensure_meta_initialized($post_id);

        // Obtém contagens
        $counts = WPLD_DB::get_counts($post_id);
        $myVote = WPLD_DB::get_my_vote($post_id, $this->visitor_hash());

        return new WP_REST_Response(array_merge($counts, ['myVote' => $myVote]), 200);
    }

    /**
     * Callback para POST /wpld/v1/vote
     */
    public function post_vote(WP_REST_Request $req) {
        $post_id = (int) $req->get_param('postId');
        $vote = (int) $req->get_param('vote'); // 1 like, -1 dislike

        // Valida o voto
        if (!in_array($vote, [1, -1], true)) {
            return new WP_REST_Response(['error' => 'Voto inválido'], 400);
        }

        // Valida o post
        if (!get_post($post_id)) {
            return new WP_REST_Response(['error' => 'Post inválido'], 404);
        }

        // Aplica o voto
        $result = WPLD_DB::apply_vote($post_id, $this->visitor_hash(), $vote);

        return new WP_REST_Response($result, 200);
    }
}
