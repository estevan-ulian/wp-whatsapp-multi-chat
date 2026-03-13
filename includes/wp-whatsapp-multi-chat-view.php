<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_View
{
  protected static $instance = null;

  protected $display_cached = null;

  protected function __construct()
  {
    add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_scripts'));
    add_action('wp_footer', array($this, 'maybe_render'));
  }

  public function maybe_enqueue_scripts()
  {
    if (!$this->should_display()) return;
    $this->enqueue_frontend_scripts();
  }

  public function maybe_render()
  {
    if (!$this->should_display()) return;
    echo $this->render_frontend_chat(); // phpcs:ignore WordPress.Security.EscapeOutput
  }

  public function render_frontend_chat()
  {
    ob_start();
    ?>
      <div id="wp-whatsapp-multi-chat-wrapper"></div>
    <?php
    return ob_get_clean();
  }

  public function enqueue_frontend_scripts()
  {
    wp_enqueue_style(
      'wpwc-frontend',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/frontend/css/wp-whatsapp-multi-chat-plugin.css',
      array(),
      WP_WHATSAPP_MULTI_CHAT_VERSION
    );

    wp_enqueue_script(
      'wpwc-frontend',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/frontend/js/wp-whatsapp-multi-chat-plugin.js',
      array(),
      WP_WHATSAPP_MULTI_CHAT_VERSION,
      true
    );

    wp_localize_script(
      'wpwc-frontend',
      'wpWhatsAppMultiChat',
      array(
        'root'       => 'wp-whatsapp-multi-chat-wrapper',
        'baseUrl'    => WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/frontend',
        'accounts'   => $this->get_accounts(),
        'appearance' => wp_parse_args(
          get_option('wpwc_appearance', array()),
          array(
            'position_corner' => 'bottom-right',
            'offset_x'        => 20,
            'offset_y'        => 20,
            'button_style'    => 'icon-only',
            'button_text'     => __('Chat with us', 'wp-whatsapp-multi-chat'),
            'button_size'     => 'medium',
            'color_bg'        => '#25d366',
            'color_fg'        => '#ffffff',
            'animation'       => 'none',
            'tooltip_text'    => '',
            'appear_delay'    => 0,
            'hide_mobile'     => 0,
            'hide_desktop'    => 0,
            'panel_title'     => __('WhatsApp', 'wp-whatsapp-multi-chat'),
            'panel_subtitle'  => __('Typically replies within minutes', 'wp-whatsapp-multi-chat'),
            'cta_text'        => __('Chat', 'wp-whatsapp-multi-chat'),
            'empty_text'      => __('No contacts available.', 'wp-whatsapp-multi-chat'),
            'footer_text'     => __('End-to-end encrypted', 'wp-whatsapp-multi-chat'),
          )
        ),
        'conditions' => wp_parse_args(
          get_option('wpwc_display_conditions', array()),
          array(
            'display_mode'    => 'manual',
            'show_front_page' => 0,
            'post_types'      => array(),
            'archives'        => array(),
            'include_posts'   => array(),
            'exclude_posts'   => array(),
          )
        ),
      )
    );
  }

  private function should_display()
  {
    if ($this->display_cached !== null) return $this->display_cached;

    $conditions = wp_parse_args(
      get_option(WP_WhatsApp_Multi_Chat_Options::OPTION_KEY, array()),
      array(
        'display_mode'    => 'manual',
        'show_front_page' => 0,
        'post_types'      => array(),
        'archives'        => array(),
        'include_posts'   => array(),
        'exclude_posts'   => array(),
      )
    );

    $exclude_ids = array_map('intval', (array) $conditions['exclude_posts']);
    $include_ids = array_map('intval', (array) $conditions['include_posts']);

    // Exclusions always win
    if (!empty($exclude_ids) && is_singular() && in_array((int) get_the_ID(), $exclude_ids, true)) {
      $this->display_cached = false;
      return false;
    }

    // Specific-page inclusions override manual rules
    if (!empty($include_ids) && is_singular() && in_array((int) get_the_ID(), $include_ids, true)) {
      $this->display_cached = true;
      return true;
    }

    if ('everywhere' === $conditions['display_mode']) {
      $this->display_cached = true;
      return true;
    }

    // Manual mode
    if (!empty($conditions['show_front_page']) && is_front_page()) {
      $this->display_cached = true;
      return true;
    }

    $post_types = (array) $conditions['post_types'];
    if (!empty($post_types) && is_singular($post_types)) {
      $this->display_cached = true;
      return true;
    }

    foreach ((array) $conditions['archives'] as $key) {
      $match = false;
      switch ($key) {
        case 'category': $match = is_category(); break;
        case 'tag':      $match = is_tag();      break;
        case 'author':   $match = is_author();   break;
        case 'date':     $match = is_date();     break;
        case 'search':   $match = is_search();   break;
        case '404':      $match = is_404();      break;
        default:
          if (strpos($key, 'tax_') === 0) {
            $match = is_tax(substr($key, 4));
          }
      }
      if ($match) {
        $this->display_cached = true;
        return true;
      }
    }

    $this->display_cached = false;
    return false;
  }

  private function get_accounts()
  {
    $posts = get_posts(array(
      'post_type'   => WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby'     => 'menu_order',
      'order'       => 'ASC',
    ));

    $accounts = array();
    foreach ($posts as $post) {
      $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'thumbnail');
      $accounts[] = array(
        'id'                 => $post->ID,
        'name'               => (string) get_post_meta($post->ID, '_wpwc_name', true),
        'number'             => (string) get_post_meta($post->ID, '_wpwc_number', true),
        'predefined_message' => (string) get_post_meta($post->ID, '_wpwc_predefined_message', true),
        'thumbnail'          => $thumbnail_url ? (string) $thumbnail_url : '',
      );
    }

    return $accounts;
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
