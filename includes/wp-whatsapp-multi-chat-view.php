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
        // Business hours are evaluated in the browser against the site's
        // timezone, so the status stays correct on cached pages.
        'timezone'   => wp_timezone_string(),
        'weekdays'   => $this->get_weekday_abbrevs(),
      )
    );
  }

  private function should_display()
  {
    if ($this->display_cached !== null) return $this->display_cached;

    $rules = WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(
      get_option(WP_WhatsApp_Multi_Chat_Options::OPTION_KEY, array())
    );

    $this->display_cached = WP_WhatsApp_Multi_Chat_Display_Rules::matches(
      $this->translate_rule_items($rules),
      $this->build_context()
    );

    return $this->display_cached;
  }

  /**
   * Describes the current page for the display rules with WordPress
   * conditionals. A static front page is also a singular Page, as in WordPress.
   */
  private function build_context(): array
  {
    $context = WP_WhatsApp_Multi_Chat_Display_Rules::empty_context();

    $context['front_page'] = (bool) is_front_page();
    $context['blog']       = is_home() && !$context['front_page'];
    $context['archive']    = (bool) is_archive();
    $context['author']     = (bool) is_author();
    $context['date']       = (bool) is_date();
    $context['search']     = (bool) is_search();
    $context['not_found']  = (bool) is_404();

    if (is_post_type_archive()) {
      $type           = get_query_var('post_type');
      $context['pta'] = (string) (is_array($type) ? reset($type) : $type);
    }

    if (is_category() || is_tag() || is_tax()) {
      $term = get_queried_object();
      if (is_object($term) && isset($term->taxonomy, $term->term_id)) {
        $context['tax']  = (string) $term->taxonomy;
        $context['term'] = (int) $term->term_id;
      }
    }

    if (is_singular()) {
      $context['singular'] = (string) get_post_type();
      $context['object']   = (int) get_queried_object_id();
    }

    return $context;
  }

  /**
   * When WPML is active, rules saved with a page, post or term in the original
   * language also apply to its translation in the current language.
   */
  private function translate_rule_items(array $rules): array
  {
    if (!defined('ICL_LANGUAGE_CODE')) return $rules;

    foreach ($rules as $i => $rule) {
      if (0 === (int) $rule['item']) continue;

      if ('singular' === $rule['where']) {
        $type = $rule['which'];
      } elseif ('archive' === $rule['where'] && 0 === strpos($rule['which'], 'tax_')) {
        $type = substr($rule['which'], 4);
      } else {
        continue;
      }

      $rules[$i]['item'] = (int) apply_filters('wpml_object_id', (int) $rule['item'], $type, true);
    }

    return $rules;
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
      'status_online', 'status_offline', 'status_back',
      'status_today', 'status_tomorrow', 'offline_subtitle',
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
        'availability'       => WP_WhatsApp_Multi_Chat_Hours::resolve_availability($post->ID),
      );
    }

    return $accounts;
  }

  /**
   * Weekday abbreviations in the site language for the "Volta seg às 08:00"
   * status, keyed like the stored schedule.
   *
   * @return array<string, string>
   */
  private function get_weekday_abbrevs(): array
  {
    global $wp_locale;

    // WP_Locale numbers weekdays from 0 = Sunday.
    $indexes  = array('mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0);
    $fallback = array('mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun');

    if (!is_object($wp_locale)) {
      return $fallback;
    }

    $abbrevs = array();
    foreach (WP_WhatsApp_Multi_Chat_Hours::DAYS as $day) {
      $abbrevs[$day] = (string) $wp_locale->get_weekday_abbrev($wp_locale->get_weekday($indexes[$day]));
    }
    return $abbrevs;
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
