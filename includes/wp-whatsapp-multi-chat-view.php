<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_View
{
  protected static $instance = null;

  protected function __construct()
  {
    // TODO: Constructor code here
  }

  public function render_frontend_chat() {
    // TODO: Code to render the frontend chat widget
  }

  public function enqueue_frontend_scripts()
  {
    // TODO: Enqueue frontend scripts and styles here
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
