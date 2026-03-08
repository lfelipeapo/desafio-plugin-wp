# WP ApoLike

Plugin WordPress de votação **Like/Dislike** por post, com persistência nativa, bloco Gutenberg dinâmico de ranking e controle de voto por visitante via cookie.

---

## Requisitos

| Requisito | Versão mínima |
|---|---|
| WordPress | 5.2+ |
| PHP | 7.4+ |
| MySQL | 5.7+ |

---

## Instalação

1. Faça upload da pasta `wp-apolike/` em `wp-content/plugins/`.
2. Ative o plugin no painel **Plugins**.
3. A tabela de votos é criada automaticamente na ativação.

---

## Como funciona

### Votação nos posts

A interface de Like/Dislike é injetada automaticamente ao final de cada post singular via filtro `the_content`. O visitante clica em 👍 ou 👎; a pontuação é atualizada via REST API sem recarregar a página. Clicar no mesmo botão novamente remove o voto (toggle).

### Identificação do visitante

Cada visitante recebe um UUID gerado pelo servidor e armazenado em cookie `wpld_vid` (validade de 1 ano). O UUID nunca é gravado diretamente no banco; o que é persistido é `hash_hmac('sha256', $uuid, wp_salt('auth'))`, garantindo que o dado armazenado seja opaco e não reversível.

### Persistência

O plugin cria a tabela `{prefix}_wpld_votes` com uma `UNIQUE KEY` em `(post_id, visitor_hash)`, o que impõe no banco a regra de um voto por visitante por post. Os agregados `wpld_likes`, `wpld_dislikes` e `wpld_score` são mantidos como post meta e atualizados por delta a cada voto, tornando a leitura do ranking eficiente sem precisar de `COUNT()` em tempo real.

### Rate limiting

Um transient de 2 segundos por `(post_id + IP)` impede que o mesmo visitante dispare votos em sequência rápida.

### Cache do ranking

O HTML gerado pelo bloco Gutenberg e pelo shortcode é armazenado em transients com TTL de 1 hora. A chave de cache é parametrizada por `(postsToShow, showCounts, showExcerpt)`, de modo que configurações diferentes do bloco mantêm caches independentes. A cada voto registrado, `WPLD_DB::apply_vote()` chama `WPLD_Cache::invalidate_ranking()`, que remove todos os transients com o prefixo `wpld_ranking_` do banco, garantindo que o ranking reflita imediatamente a nova pontuação.

### Bloco Gutenberg — Ranking de Posts

O bloco `wpld/ranking` é registrado inteiramente via PHP (`register_block_type` com `render_callback`), sem `block.json` e sem pipeline de build. O script do editor (`assets/block.js`) usa `wp.element.createElement` puro — sem JSX, sem `import/export`. O `save` retorna `null` (bloco dinâmico); o HTML é sempre gerado pelo PHP no momento da requisição, refletindo a pontuação persistida.

Controles disponíveis no painel lateral do editor:

| Controle | Tipo | Padrão |
|---|---|---|
| Quantidade de posts | RangeControl (1–20) | 5 |
| Mostrar contagens | ToggleControl | ativado |
| Mostrar resumo | ToggleControl | desativado |

### Shortcode

O shortcode `[wpld-ranking]` oferece a mesma listagem fora do editor de blocos.

```
[wpld-ranking posts_to_show="10" show_counts="true" show_excerpt="false"]
```

Os valores booleanos são interpretados via `filter_var(FILTER_VALIDATE_BOOLEAN)`, portanto `"false"` é corretamente tratado como falso.

---

## REST API

### `GET /wp-json/wpld/v1/state/<postId>`

Retorna o placar atual e o voto do visitante identificado pelo cookie.

```json
{ "likes": 12, "dislikes": 3, "score": 9, "myVote": 1 }
```

### `POST /wp-json/wpld/v1/vote`

Registra ou altera o voto. O endpoint valida o nonce `wp_rest` via `wp_verify_nonce`, lido do header `X-WP-Nonce` que o `wp.apiFetch` envia automaticamente. Requisições sem nonce válido recebem `403 Forbidden`. O endpoint `GET /state` permanece público, pois é apenas leitura.

```json
{ "postId": 42, "vote": 1 }
```

Resposta: mesmo formato do `GET /state`.

---

## Estrutura de arquivos

```
wp-apolike/
├── wp-apolike.php              # Bootstrap: defines, requires, hooks de ativação
├── includes/
│   ├── class-db.php            # Tabela de votos, agregados e lógica de apply_vote
│   ├── class-rest.php          # Registro e callbacks dos endpoints REST
│   ├── class-hooks.php         # Injeção de UI no_content, enqueue, shortcode
│   ├── class-block.php         # Registro do bloco e render_callback
│   ├── class-cache.php         # Helpers de transient para cache do ranking
│   ├── class-rate-limit.php    # Transient de 2 s por (post_id + IP)
│   └── render-ranking.php      # Arquivo PHP alternativo de renderização (não usado pelo bloco)
├── assets/
│   ├── vote.js                 # Lógica de votação no front-end (sem build)
│   ├── vote.css                # Estilos da UI de votação + dark mode + responsivo
│   └── block.js                # Registro do bloco no editor (createElement puro, sem build)
└── blocks/
    └── ranking/
        └── style.css           # Estilos do bloco no front-end
```

---

## Banco de dados

### Tabela `{prefix}_wpld_votes`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Chave primária |
| `post_id` | `BIGINT UNSIGNED` | ID do post votado |
| `visitor_hash` | `CHAR(64)` | HMAC-SHA256 do UUID do visitante |
| `vote` | `TINYINT` | `1` = like, `-1` = dislike |
| `updated_at` | `DATETIME` | Data/hora da última atualização |

A constraint `UNIQUE KEY uniq_vote (post_id, visitor_hash)` garante no banco a regra de um voto por visitante por post.

### Post meta

| Chave | Descrição |
|---|---|
| `wpld_likes` | Total de likes do post |
| `wpld_dislikes` | Total de dislikes do post |
| `wpld_score` | Score = likes − dislikes |

---

## Autor

**Luiz Felipe Apolinário**

## Licença

GPL v2 ou posterior.
