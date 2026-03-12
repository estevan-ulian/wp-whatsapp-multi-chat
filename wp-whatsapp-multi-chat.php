<?php

/**
 * Plugin Name: WhatsApp Multi Chat
 * Description: A simple WhatsApp chat plugin for WordPress.
 * Version: 1.0.0
 * Author: Estevan Ulian
 * Author URI: https://github.com/estevan-ulian
 * Plugin URI: https://github.com/estevan-ulian/wp-whatsapp-multi-chat
 * Text Domain: wp-whatsapp-multi-chat
 */

if (!defined('ABSPATH')) exit;

if (!defined('WP_WHATSAPP_MULTI_CHAT_VERSION')) {
  define('WP_WHATSAPP_MULTI_CHAT_VERSION', '1.0.0');
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
    $this->load_classes();
    $this->init_classes();
  }

  private function load_classes()
  {
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-utils.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-options.php';
  }

  private function init_classes()
  {
    WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    WP_WhatsApp_Multi_Chat_Options::get_instance();
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
