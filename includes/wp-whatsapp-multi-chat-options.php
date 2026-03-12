<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Options
{
  protected static $instance = null;

  protected function __construct()
  {
    add_action('admin_menu', array($this, 'add_options_page'));
  }

  public function add_options_page()
  {
    add_submenu_page(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
      __('WhatsApp Multi Chat Configuration', 'wp-whatsapp-multi-chat'),
      __('Configuration', 'wp-whatsapp-multi-chat'),
      'manage_options',
      'wp-whatsapp-multi-chat-options',
      array($this, 'render_options_page')
    );
  }

  public function render_options_page() {
    // TODO: implements
  }

  public function tab_display_condition()
  {
    // TODO: implements
  }

  public function tab_appearance()
  {
    // TODO: implements
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
