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

    $appearance = wp_parse_args(
      get_option('wpwc_appearance', array()),
      WP_WhatsApp_Multi_Chat_Options::appearance_defaults()
    );

    wp_localize_script(
      'wpwc-frontend',
      'wpWhatsAppMultiChat',
      array(
        'root'       => 'wp-whatsapp-multi-chat-wrapper',
        'baseUrl'    => WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/frontend',
        'accounts'   => $this->get_accounts(),
        'appearance' => $this->maybe_translate_appearance($appearance),
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

    $exclude_ids = $this->maybe_translate_post_ids(array_map('intval', (array) $conditions['exclude_posts']));
    $include_ids = $this->maybe_translate_post_ids(array_map('intval', (array) $conditions['include_posts']));

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

  /**
   * When WPML is active, translates an array of stored (original-language) post IDs
   * to their equivalents in the current language.
   *
   * @param int[] $ids
   * @return int[]
   */
  private function maybe_translate_post_ids(array $ids): array
  {
    if (!defined('ICL_LANGUAGE_CODE') || empty($ids)) {
      return $ids;
    }

    $translated = array();
    foreach ($ids as $id) {
      $post_type     = get_post_type($id) ?: 'post';
      $translated_id = (int) apply_filters('wpml_object_id', $id, $post_type, true);
      $translated[]  = $translated_id;
    }

    return $translated;
  }

  /**
   * When WPML String Translation is active, returns translated values for
   * the appearance text fields registered via wpml_register_single_string.
   *
   * @param array $appearance
   * @return array
   */
  private function maybe_translate_appearance(array $appearance): array
  {
    if (!defined('ICL_LANGUAGE_CODE')) {
      return $appearance;
    }

    $context     = 'wp-whatsapp-multi-chat';
    $text_fields = array(
      'button_text', 'tooltip_text', 'panel_title', 'panel_subtitle',
      'cta_text', 'empty_text', 'footer_text',
    );

    foreach ($text_fields as $field) {
      if (!empty($appearance[$field])) {
        $appearance[$field] = apply_filters(
          'wpml_translate_single_string',
          $appearance[$field],
          $context,
          'appearance_' . $field
        );
      }
    }

    return $appearance;
  }

  private function get_accounts()
  {
    $query_args = array(
      'post_type'        => WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
      'post_status'      => 'publish',
      'numberposts'      => -1,
      'orderby'          => 'menu_order',
      'order'            => 'ASC',
      'suppress_filters' => false, // allow WPML to filter by current language
    );

    $posts = get_posts($query_args);

    // If WPML is active and no accounts exist for the current language,
    // fall back to the default language so the widget is never empty.
    if (empty($posts) && defined('ICL_LANGUAGE_CODE')) {
      $default_lang = apply_filters('wpml_default_language', null);
      if ($default_lang && $default_lang !== ICL_LANGUAGE_CODE) {
        do_action('wpml_switch_language', $default_lang);
        $posts = get_posts($query_args);
        do_action('wpml_switch_language', ICL_LANGUAGE_CODE);
      }
    }

    $accounts = array();
    foreach ($posts as $post) {
      $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'thumbnail');
      $accounts[] = array(
        'id'                 => $post->ID,
        'name'               => (string) get_post_meta($post->ID, '_wpwc_name', true),
        'number'             => (string) get_post_meta($post->ID, '_wpwc_number', true),
        'predefined_message' => (string) get_post_meta($post->ID, '_wpwc_predefined_message', true),
        'tagline'            => (string) get_post_meta($post->ID, '_wpwc_cargo', true),
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
