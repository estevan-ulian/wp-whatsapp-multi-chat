<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Accounts
{
  protected static $instance = null;

  protected function __construct() {
    add_action("init", array($this, "register_account_post_type"));
  }

  public function register_account_post_type()
  {
    $labels = array(
      'name'               => _x('WhatsApp Accounts', 'post type general name', 'wp-whatsapp-chat'),
      'singular_name'      => _x('WhatsApp Account', 'post type singular name', 'wp-whatsapp-chat'),
      'menu_name'          => _x('Accounts', 'admin menu', 'wp-whatsapp-chat'),
      'name_admin_bar'     => _x('Account', 'add new on admin bar', 'wp-whatsapp-chat'),
      'add_new'            => _x('Add New', 'book', 'wp-whatsapp-chat'),
      'add_new_item'       => __('Add New Account', 'wp-whatsapp-chat'),
      'new_item'           => __('New Account', 'wp-whatsapp-chat'),
      'edit_item'          => __('Edit Account', 'wp-whatsapp-chat'),
      'view_item'          => __('View Account', 'wp-whatsapp-chat'),
      'all_items'          => __('All Accounts', 'wp-whatsapp-chat'),
      'search_items'       => __('Search Accounts', 'wp-whatsapp-chat'),
      'parent_item_colon'  => __('Parent Accounts:', 'wp-whatsapp-chat'),
      'not_found'          => __('No accounts found.', 'wp-whatsapp-chat'),
      'not_found_in_trash' => __('No accounts found in Trash.', 'wp-whatsapp-chat')
    );

    $args = array(
      'labels'             => $labels,
      'description'        => __('WhatsApp Accounts', 'wp-whatsapp-chat'),
      'public'             => false,
      'exclude_from_search' => true,
      'show_ui'            => true,
      'show_in_menu'       => 'wp-whatsapp-chat_parent',
      'query_var'          => false,
      'rewrite'            => false,
      'capability_type'    => 'post',
      'menu_position'      => null,
      'supports'           => array('title', 'thumbnail')
    );

    register_post_type('wpwc_account', $args);
  }

  public function account_tabulation_header($defaults)
  {
    unset($defaults['date']);
    $defaults['title'] = esc_html(__('Name', 'wp-whatsapp-chat'));
    $defaults['picture'] = esc_html(__('Picture', 'wp-whatsapp-chat'));
    $defaults['picture']  = esc_html__('Picture', 'wptwa');
    $defaults['number']  = esc_html__('Number', 'wptwa');
    return $defaults;
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
