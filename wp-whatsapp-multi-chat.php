<?php

/**
 * Plugin Name: WhatsApp Multi Chat
 * Description: A simple WhatsApp chat plugin for WordPress.
 * Version: 1.3.0
 * Author: Estevan Ulian
 * Author URI: https://github.com/estevan-ulian
 * Plugin URI: https://github.com/estevan-ulian/wp-whatsapp-multi-chat
 * Update URI: https://github.com/estevan-ulian/wp-whatsapp-multi-chat
 * Text Domain: wp-whatsapp-multi-chat
 * Requires at least: 5.0
 * Requires PHP: 8.4.27
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) exit;

if (!defined('WP_WHATSAPP_MULTI_CHAT_VERSION')) {
  define('WP_WHATSAPP_MULTI_CHAT_VERSION', '1.3.0');
}

if (!defined('WP_WHATSAPP_MULTI_CHAT_PLUGIN_FILE')) {
  define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_FILE', __FILE__);
}

if (!defined('WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR')) {
  define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL')) {
  define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

class WP_WhatsApp_Multi_Chat_Plugin
{
  protected static $instance = null;

  protected function __construct()
  {
    add_action('plugins_loaded', array($this, 'load_textdomain'));
    $this->load_classes();
    $this->init_classes();
  }

  public function load_textdomain()
  {
    load_plugin_textdomain(
      'wp-whatsapp-multi-chat',
      false,
      dirname(plugin_basename(__FILE__)) . '/languages'
    );
  }

  private function load_classes()
  {
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-utils.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-hours.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-display-rules.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-options.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-view.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-updater.php';
  }

  private function init_classes()
  {
    WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    WP_WhatsApp_Multi_Chat_Options::get_instance();
    WP_WhatsApp_Multi_Chat_View::get_instance();
    WP_WhatsApp_Multi_Chat_Updater::get_instance();
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}

WP_WhatsApp_Multi_Chat_Plugin::get_instance();
