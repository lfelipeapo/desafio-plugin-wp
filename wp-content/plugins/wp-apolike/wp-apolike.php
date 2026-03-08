<?php
/**
 * Plugin Name: WP ApoLike - Sistema de Votação Like/Dislike
 * Plugin URI: https://github.com/luizfelipeapolinario/wp-apolike
 * Description: Sistema completo de votação (Like/Dislike) para posts com ranking via Gutenberg.
 * Version: 1.0.0
 * Author: Luiz Felipe Apolinário
 * Author URI: https://github.com/luizfelipeapolinario
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-apolike
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPLD_VERSION', '1.0.0');
define('WPLD_PATH', plugin_dir_path(__FILE__));
define('WPLD_URL', plugin_dir_url(__FILE__));
define('WPLD_BASENAME', plugin_basename(__FILE__));

// Carrega as classes principais
require_once WPLD_PATH . 'includes/class-db.php';
require_once WPLD_PATH . 'includes/class-rest.php';
require_once WPLD_PATH . 'includes/class-hooks.php';
require_once WPLD_PATH . 'includes/class-block.php';
require_once WPLD_PATH . 'includes/class-cache.php';
require_once WPLD_PATH . 'includes/class-rate-limit.php';

// Hook de ativação
register_activation_hook(__FILE__, ['WPLD_DB', 'activate']);

// Hook de desativação
register_deactivation_hook(__FILE__, ['WPLD_Cache', 'clear_all']);

// Inicialização do plugin
add_action('plugins_loaded', function () {
    WPLD_DB::init();
    (new WPLD_Rest())->register();
    (new WPLD_Hooks())->register();
    (new WPLD_Block())->register();
    
    // Carrega tradução
    load_plugin_textdomain('wp-apolike', false, dirname(WPLD_BASENAME) . '/languages');
});
