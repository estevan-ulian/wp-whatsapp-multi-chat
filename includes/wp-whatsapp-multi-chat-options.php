<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Options
{
  protected static $instance = null;

  const OPTION_KEY = 'wpwc_display_conditions';

  protected $page_hook = '';

  protected function __construct()
  {
    add_action('admin_menu', array($this, 'add_options_page'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_wpwc_search_posts', array($this, 'ajax_search_posts'));
    add_action('admin_post_wpwc_save_display_conditions', array($this, 'save_display_conditions'));
  }

  public function add_options_page()
  {
    $this->page_hook = add_submenu_page(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
      __('WhatsApp Multi Chat Configuration', 'wp-whatsapp-multi-chat'),
      __('Configuration', 'wp-whatsapp-multi-chat'),
      'manage_options',
      'wp-whatsapp-multi-chat-options',
      array($this, 'render_options_page')
    );
  }

  public function enqueue_admin_scripts($hook)
  {
    if ($hook !== $this->page_hook) return;

    wp_enqueue_style(
      'wpwc-admin-options',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/admin/css/admin-options.css',
      array(),
      WP_WHATSAPP_MULTI_CHAT_VERSION
    );

    wp_enqueue_script(
      'wpwc-admin-options',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/admin/js/admin-options.js',
      array('jquery'),
      WP_WHATSAPP_MULTI_CHAT_VERSION,
      true
    );

    wp_localize_script('wpwc-admin-options', 'wpwcOptions', array(
      'ajaxUrl'     => admin_url('admin-ajax.php'),
      'nonce'       => wp_create_nonce('wpwc_search_posts'),
      'noResults'   => __('No results found', 'wp-whatsapp-multi-chat'),
    ));
  }

  public function ajax_search_posts()
  {
    check_ajax_referer('wpwc_search_posts', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized', 403);
    }

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $query = new WP_Query(array(
      'post_type'      => 'any',
      'post_status'    => 'publish',
      's'              => $search,
      'posts_per_page' => 20,
      'no_found_rows'  => true,
    ));

    $results = array();
    foreach ($query->posts as $post) {
      $pt = get_post_type_object($post->post_type);
      $results[] = array(
        'id'    => $post->ID,
        'title' => get_the_title($post),
        'type'  => $pt ? $pt->labels->singular_name : $post->post_type,
      );
    }

    wp_send_json_success($results);
  }

  public function render_options_page()
  {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'display_condition';
    $base_url   = admin_url('edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG . '&page=wp-whatsapp-multi-chat-options');
    ?>
    <div class="wrap wpwc-options-page">
      <h1><?php esc_html_e('WhatsApp Multi Chat', 'wp-whatsapp-multi-chat'); ?></h1>

      <?php if (isset($_GET['updated']) && '1' === $_GET['updated']) : ?>
        <div class="notice notice-success is-dismissible">
          <p><?php esc_html_e('Settings saved.', 'wp-whatsapp-multi-chat'); ?></p>
        </div>
      <?php endif; ?>

      <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'display_condition', $base_url)); ?>"
           class="nav-tab <?php echo 'display_condition' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Display Conditions', 'wp-whatsapp-multi-chat'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'appearance', $base_url)); ?>"
           class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Appearance', 'wp-whatsapp-multi-chat'); ?>
        </a>
      </nav>

      <div class="tab-content">
        <?php
        switch ($active_tab) {
          case 'display_condition':
            $this->tab_display_condition();
            break;
          case 'appearance':
            $this->tab_appearance();
            break;
        }
        ?>
      </div>
    </div>
    <?php
  }

  public function tab_display_condition()
  {
    $options = get_option(self::OPTION_KEY, array());

    $show_everywhere      = !empty($options['show_everywhere']);
    $show_front_page      = !empty($options['show_front_page']);
    $selected_post_types  = isset($options['post_types']) ? (array) $options['post_types'] : array();
    $selected_archives    = isset($options['archives']) ? (array) $options['archives'] : array();
    $include_posts        = isset($options['include_posts']) ? array_map('absint', (array) $options['include_posts']) : array();
    $exclude_posts        = isset($options['exclude_posts']) ? array_map('absint', (array) $options['exclude_posts']) : array();

    $post_types        = $this->get_available_post_types();
    $archives          = $this->get_available_archives();
    $include_post_data = $this->get_posts_by_ids($include_posts);
    $exclude_post_data = $this->get_posts_by_ids($exclude_posts);

    $form_action = admin_url('admin-post.php');
    ?>
    <form method="post" action="<?php echo esc_url($form_action); ?>">
      <?php wp_nonce_field('wpwc_save_display_conditions', 'wpwc_nonce'); ?>
      <input type="hidden" name="action" value="wpwc_save_display_conditions">

      <table class="form-table" role="presentation">

        <tr>
          <th scope="row"><?php esc_html_e('Visibility', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="show_everywhere" value="1" <?php checked($show_everywhere); ?>>
              <?php esc_html_e('Entire site', 'wp-whatsapp-multi-chat'); ?>
            </label>
            <p class="description"><?php esc_html_e('Show the widget on all pages of the site.', 'wp-whatsapp-multi-chat'); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row"><?php esc_html_e('Home Page', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <label>
              <input type="checkbox" name="show_front_page" value="1" <?php checked($show_front_page); ?>>
              <?php esc_html_e('Front page', 'wp-whatsapp-multi-chat'); ?>
            </label>
          </td>
        </tr>

        <?php if (!empty($post_types)) : ?>
        <tr>
          <th scope="row"><?php esc_html_e('Post Types', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><?php esc_html_e('Post Types', 'wp-whatsapp-multi-chat'); ?></legend>
              <?php foreach ($post_types as $pt) : ?>
              <label class="wpwc-checkbox-label">
                <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt->name); ?>"
                  <?php checked(in_array($pt->name, $selected_post_types, true)); ?>>
                <?php echo esc_html($pt->labels->name); ?>
                <span class="wpwc-slug-label"><?php echo esc_html($pt->name); ?></span>
              </label>
              <?php endforeach; ?>
            </fieldset>
            <p class="description"><?php esc_html_e('Show on single posts of the selected types.', 'wp-whatsapp-multi-chat'); ?></p>
          </td>
        </tr>
        <?php endif; ?>

        <?php if (!empty($archives)) : ?>
        <tr>
          <th scope="row"><?php esc_html_e('Archives', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><?php esc_html_e('Archives', 'wp-whatsapp-multi-chat'); ?></legend>
              <?php foreach ($archives as $key => $label) : ?>
              <label class="wpwc-checkbox-label">
                <input type="checkbox" name="archives[]" value="<?php echo esc_attr($key); ?>"
                  <?php checked(in_array($key, $selected_archives, true)); ?>>
                <?php echo esc_html($label); ?>
              </label>
              <?php endforeach; ?>
            </fieldset>
            <p class="description"><?php esc_html_e('Show on the selected archive pages.', 'wp-whatsapp-multi-chat'); ?></p>
          </td>
        </tr>
        <?php endif; ?>

        <tr>
          <th scope="row"><?php esc_html_e('Include Pages/Posts', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <div class="wpwc-post-selector" data-field="include_posts">
              <div class="wpwc-search-wrap">
                <input type="text" class="wpwc-search-input regular-text"
                       placeholder="<?php esc_attr_e('Search pages or posts…', 'wp-whatsapp-multi-chat'); ?>"
                       autocomplete="off">
                <div class="wpwc-search-results" hidden></div>
              </div>
              <ul class="wpwc-selected-items">
                <?php foreach ($include_post_data as $item) : ?>
                <li data-id="<?php echo esc_attr($item['id']); ?>">
                  <span class="wpwc-item-title"><?php echo esc_html($item['title']); ?></span>
                  <span class="wpwc-type-label"><?php echo esc_html($item['type']); ?></span>
                  <button type="button" class="wpwc-remove-item button-link" aria-label="<?php esc_attr_e('Remove', 'wp-whatsapp-multi-chat'); ?>">&times;</button>
                  <input type="hidden" name="include_posts[]" value="<?php echo esc_attr($item['id']); ?>">
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <p class="description"><?php esc_html_e('Always show the widget on these pages or posts, regardless of other conditions.', 'wp-whatsapp-multi-chat'); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row"><?php esc_html_e('Exclude Pages/Posts', 'wp-whatsapp-multi-chat'); ?></th>
          <td>
            <div class="wpwc-post-selector" data-field="exclude_posts">
              <div class="wpwc-search-wrap">
                <input type="text" class="wpwc-search-input regular-text"
                       placeholder="<?php esc_attr_e('Search pages or posts…', 'wp-whatsapp-multi-chat'); ?>"
                       autocomplete="off">
                <div class="wpwc-search-results" hidden></div>
              </div>
              <ul class="wpwc-selected-items">
                <?php foreach ($exclude_post_data as $item) : ?>
                <li data-id="<?php echo esc_attr($item['id']); ?>">
                  <span class="wpwc-item-title"><?php echo esc_html($item['title']); ?></span>
                  <span class="wpwc-type-label"><?php echo esc_html($item['type']); ?></span>
                  <button type="button" class="wpwc-remove-item button-link" aria-label="<?php esc_attr_e('Remove', 'wp-whatsapp-multi-chat'); ?>">&times;</button>
                  <input type="hidden" name="exclude_posts[]" value="<?php echo esc_attr($item['id']); ?>">
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <p class="description"><?php esc_html_e('Never show the widget on these pages or posts.', 'wp-whatsapp-multi-chat'); ?></p>
          </td>
        </tr>

      </table>

      <?php submit_button(__('Save Settings', 'wp-whatsapp-multi-chat')); ?>
    </form>
    <?php
  }

  public function tab_appearance()
  {
    // TODO: implements
    echo '<p>' . esc_html__('Appearance settings coming soon.', 'wp-whatsapp-multi-chat') . '</p>';
  }

  public function save_display_conditions()
  {
    check_admin_referer('wpwc_save_display_conditions', 'wpwc_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have sufficient permissions.', 'wp-whatsapp-multi-chat'));
    }

    $options = array(
      'show_everywhere' => isset($_POST['show_everywhere']) ? 1 : 0,
      'show_front_page' => isset($_POST['show_front_page']) ? 1 : 0,
      'post_types'      => isset($_POST['post_types'])
        ? array_map('sanitize_key', (array) wp_unslash($_POST['post_types']))
        : array(),
      'archives'        => isset($_POST['archives'])
        ? array_map('sanitize_key', (array) wp_unslash($_POST['archives']))
        : array(),
      'include_posts'   => isset($_POST['include_posts'])
        ? array_map('absint', (array) wp_unslash($_POST['include_posts']))
        : array(),
      'exclude_posts'   => isset($_POST['exclude_posts'])
        ? array_map('absint', (array) wp_unslash($_POST['exclude_posts']))
        : array(),
    );

    update_option(self::OPTION_KEY, $options);

    $redirect = admin_url(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
      . '&page=wp-whatsapp-multi-chat-options&tab=display_condition&updated=1'
    );

    wp_safe_redirect($redirect);
    exit;
  }

  private function get_available_post_types()
  {
    $post_types = get_post_types(array('public' => true), 'objects');
    unset($post_types['attachment']);
    return $post_types;
  }

  private function get_available_archives()
  {
    $archives = array(
      'category' => __('Category Archive', 'wp-whatsapp-multi-chat'),
      'tag'      => __('Tag Archive', 'wp-whatsapp-multi-chat'),
      'author'   => __('Author Archive', 'wp-whatsapp-multi-chat'),
      'date'     => __('Date Archive', 'wp-whatsapp-multi-chat'),
    );

    $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
    foreach ($taxonomies as $taxonomy) {
      $archives['tax_' . $taxonomy->name] = sprintf(
        /* translators: %s: taxonomy name */
        __('%s Archive', 'wp-whatsapp-multi-chat'),
        $taxonomy->labels->name
      );
    }

    return $archives;
  }

  private function get_posts_by_ids(array $ids)
  {
    if (empty($ids)) return array();

    $posts = get_posts(array(
      'post__in'    => $ids,
      'post_type'   => 'any',
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby'     => 'post__in',
    ));

    $results = array();
    foreach ($posts as $post) {
      $pt = get_post_type_object($post->post_type);
      $results[] = array(
        'id'    => $post->ID,
        'title' => get_the_title($post),
        'type'  => $pt ? $pt->labels->singular_name : $post->post_type,
      );
    }

    return $results;
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
