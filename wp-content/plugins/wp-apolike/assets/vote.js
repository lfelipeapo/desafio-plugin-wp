/* global WPLD, wp */
(function () {
    'use strict';

    if (!window.WPLD) {
        return;
    }

    const root = document.querySelector('.wpld[data-post-id]');
    if (!root) {
        return;
    }

    const postId = parseInt(root.dataset.postId, 10);
    const likeCount = root.querySelector('.wpld-like-count');
    const dislikeCount = root.querySelector('.wpld-dislike-count');
    const scoreValue = root.querySelector('.wpld-score-value');
    const buttons = root.querySelectorAll('.wpld-btn');

    // Configura middleware de nonce para a API
    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(WPLD.nonce));

    /**
     * Atualiza a UI com o estado atual
     */
    function paint(state) {
        if (state.error) {
            console.error('Erro ao votar:', state.error);
            return;
        }

        likeCount.textContent = state.likes ?? 0;
        dislikeCount.textContent = state.dislikes ?? 0;
        scoreValue.textContent = state.score ?? 0;

        // Remove classe ativa de todos os botões
        buttons.forEach((b) => b.classList.remove('is-active'));

        // Adiciona classe ativa ao botão correspondente ao voto do visitante
        if (state.myVote === 1) {
            root.querySelector('[data-vote="1"]').classList.add('is-active');
        }
        if (state.myVote === -1) {
            root.querySelector('[data-vote="-1"]').classList.add('is-active');
        }
    }

    /**
     * Carrega o estado inicial de votação
     */
    async function load() {
        try {
            const state = await wp.apiFetch({
                path: `/wpld/v1/state/${postId}`,
            });
            paint(state);
        } catch (error) {
            console.error('Erro ao carregar estado de votação:', error);
        }
    }

    /**
     * Registra um voto
     */
    async function vote(v) {
        try {
            const state = await wp.apiFetch({
                path: `/wpld/v1/vote`,
                method: 'POST',
                data: { postId, vote: v },
            });
            paint(state);
        } catch (error) {
            console.error('Erro ao votar:', error);
            paint({ error: 'Erro ao registrar voto' });
        }
    }

    /**
     * Listener de clique nos botões de votação
     */
    root.addEventListener('click', (e) => {
        const btn = e.target.closest('.wpld-btn');
        if (!btn) {
            return;
        }
        vote(parseInt(btn.dataset.vote, 10));
    });

    // Carrega estado inicial
    load();
})();
