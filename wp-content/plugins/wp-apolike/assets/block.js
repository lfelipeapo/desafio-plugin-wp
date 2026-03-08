/* global wp */
/* WP ApoLike - Bloco Gutenberg sem build (createElement puro) */
(function () {
    'use strict';

    var blocks      = wp.blocks;
    var el          = wp.element.createElement;
    var blockEditor = wp.blockEditor || wp.editor;
    var components  = wp.components;
    var i18n        = wp.i18n;

    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody         = components.PanelBody;
    var RangeControl      = components.RangeControl;
    var ToggleControl     = components.ToggleControl;
    var __                = i18n.__;

    blocks.registerBlockType('wpld/ranking', {
        title: __('Ranking de Posts (Like/Dislike)', 'wp-apolike'),
        description: __('Lista posts ordenados por pontuação (score) do sistema de votação Like/Dislike.', 'wp-apolike'),
        category: 'widgets',
        icon: 'chart-bar',
        keywords: [
            __('ranking', 'wp-apolike'),
            __('votação', 'wp-apolike'),
            __('like', 'wp-apolike'),
            __('dislike', 'wp-apolike'),
            __('score', 'wp-apolike'),
        ],

        attributes: {
            postsToShow: {
                type: 'number',
                default: 5,
            },
            showCounts: {
                type: 'boolean',
                default: true,
            },
            showExcerpt: {
                type: 'boolean',
                default: false,
            },
        },

        /**
         * Função edit: renderiza o bloco no editor Gutenberg.
         * Usa createElement puro — sem JSX, sem build.
         */
        edit: function (props) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;
            var postsToShow  = attributes.postsToShow;
            var showCounts   = attributes.showCounts;
            var showExcerpt  = attributes.showExcerpt;

            // Painel lateral de controles
            var inspector = el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: __('Configurações do Ranking', 'wp-apolike') },
                    el(RangeControl, {
                        label: __('Quantidade de posts', 'wp-apolike'),
                        value: postsToShow,
                        min: 1,
                        max: 20,
                        onChange: function (value) {
                            setAttributes({ postsToShow: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Mostrar contagens (likes/dislikes)', 'wp-apolike'),
                        checked: showCounts,
                        onChange: function (value) {
                            setAttributes({ showCounts: value });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Mostrar resumo do post', 'wp-apolike'),
                        checked: showExcerpt,
                        onChange: function (value) {
                            setAttributes({ showExcerpt: value });
                        },
                    })
                )
            );

            // Prévia visual no editor
            var preview = el(
                'div',
                {
                    style: {
                        padding: '16px',
                        border: '2px dashed #0073aa',
                        borderRadius: '8px',
                        backgroundColor: '#f0f6fc',
                    },
                },
                el(
                    'div',
                    {
                        style: {
                            fontWeight: 'bold',
                            marginBottom: '8px',
                            color: '#0073aa',
                            fontSize: '14px',
                        },
                    },
                    '📊 ' + __('Ranking de Posts (Like/Dislike)', 'wp-apolike')
                ),
                el(
                    'div',
                    { style: { fontSize: '13px', color: '#555', lineHeight: '1.6' } },
                    el(
                        'p',
                        { style: { margin: '0 0 4px' } },
                        __('Mostrando os', 'wp-apolike') + ' ',
                        el('strong', null, postsToShow),
                        ' ' + __('posts com maior pontuação.', 'wp-apolike')
                    ),
                    el(
                        'p',
                        { style: { margin: '0' } },
                        showCounts  ? '✓ ' + __('Contagens visíveis', 'wp-apolike')  : '',
                        showCounts && showExcerpt ? ' | ' : '',
                        showExcerpt ? '✓ ' + __('Resumo visível', 'wp-apolike') : ''
                    )
                ),
                el(
                    'div',
                    {
                        style: {
                            fontSize: '12px',
                            color: '#999',
                            marginTop: '12px',
                            fontStyle: 'italic',
                        },
                    },
                    __('Renderizado dinamicamente no front-end com dados atualizados.', 'wp-apolike')
                )
            );

            return el(wp.element.Fragment, null, inspector, preview);
        },

        /**
         * Bloco dinâmico: save retorna null.
         * O conteúdo é sempre renderizado pelo render_callback em PHP.
         */
        save: function () {
            return null;
        },
    });
})();
