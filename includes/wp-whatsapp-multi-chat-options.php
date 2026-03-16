<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Options
{
  protected static $instance = null;

  const OPTION_KEY            = 'wpwc_display_conditions';
  const APPEARANCE_OPTION_KEY = 'wpwc_appearance';

  protected $page_hook = '';

  protected function __construct()
  {
    add_action('admin_menu', array($this, 'add_options_page'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_wpwc_search_posts', array($this, 'ajax_search_posts'));
    add_action('admin_post_wpwc_save_display_conditions', array($this, 'save_display_conditions'));
    add_action('admin_post_wpwc_save_appearance', array($this, 'save_appearance'));
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

    $display_mode        = isset($options['display_mode']) ? $options['display_mode'] : 'manual';
    $show_front_page     = !empty($options['show_front_page']);
    $selected_post_types = isset($options['post_types']) ? (array) $options['post_types'] : array();
    $selected_archives   = isset($options['archives']) ? (array) $options['archives'] : array();
    $include_posts       = isset($options['include_posts']) ? array_map('absint', (array) $options['include_posts']) : array();
    $exclude_posts       = isset($options['exclude_posts']) ? array_map('absint', (array) $options['exclude_posts']) : array();

    $post_types        = $this->get_available_post_types();
    $archives          = $this->get_available_archives();
    $include_post_data = $this->get_posts_by_ids($include_posts);
    $exclude_post_data = $this->get_posts_by_ids($exclude_posts);

    $form_action = admin_url('admin-post.php');
    ?>
    <form method="post" action="<?php echo esc_url($form_action); ?>">
      <?php wp_nonce_field('wpwc_save_display_conditions', 'wpwc_nonce'); ?>
      <input type="hidden" name="action" value="wpwc_save_display_conditions">

      <!-- ── Section: Show widget on ─────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Show widget on', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-radio-group">
          <label class="wpwc-radio-label">
            <input type="radio" name="display_mode" value="everywhere"
              <?php checked($display_mode, 'everywhere'); ?>>
            <span class="wpwc-radio-text">
              <?php esc_html_e('Entire site', 'wp-whatsapp-multi-chat'); ?>
              <span class="wpwc-radio-desc"><?php esc_html_e('Show on every page of the site.', 'wp-whatsapp-multi-chat'); ?></span>
            </span>
          </label>

          <label class="wpwc-radio-label">
            <input type="radio" name="display_mode" value="manual"
              <?php checked($display_mode, 'manual'); ?>>
            <span class="wpwc-radio-text">
              <?php esc_html_e('Manual selection', 'wp-whatsapp-multi-chat'); ?>
              <span class="wpwc-radio-desc"><?php esc_html_e('Choose exactly where the widget appears.', 'wp-whatsapp-multi-chat'); ?></span>
            </span>
          </label>
        </div>

        <div class="wpwc-manual-conditions<?php echo 'everywhere' === $display_mode ? ' is-hidden' : ''; ?>">

          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Home page', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
              <label class="wpwc-checkbox-label">
                <input type="checkbox" name="show_front_page" value="1" <?php checked($show_front_page); ?>>
                <?php esc_html_e('Front page', 'wp-whatsapp-multi-chat'); ?>
              </label>
            </div>
          </div>

          <?php if (!empty($post_types)) : ?>
          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Post types', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
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
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($archives)) : ?>
          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Archive pages', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
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
              <p class="description"><?php esc_html_e('Show on the selected archive / special pages.', 'wp-whatsapp-multi-chat'); ?></p>
            </div>
          </div>
          <?php endif; ?>

          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Specific pages / posts', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
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
              <p class="description"><?php esc_html_e('Always show on these pages or posts (added to the conditions above).', 'wp-whatsapp-multi-chat'); ?></p>
            </div>
          </div>

        </div><!-- /.wpwc-manual-conditions -->
      </div><!-- /.wpwc-section -->

      <!-- ── Section: Exceptions ─────────────────────────────────── -->
      <div class="wpwc-section wpwc-section-exceptions">
        <h2 class="wpwc-section-title"><?php esc_html_e('Exceptions — never show on', 'wp-whatsapp-multi-chat'); ?></h2>
        <p class="wpwc-section-description"><?php esc_html_e('The widget will be hidden on these pages regardless of the rules above.', 'wp-whatsapp-multi-chat'); ?></p>

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
      </div><!-- /.wpwc-section -->

      <?php submit_button(__('Save Settings', 'wp-whatsapp-multi-chat')); ?>
    </form>
    <?php
  }

  public function tab_appearance()
  {
    $saved    = get_option(self::APPEARANCE_OPTION_KEY, array());
    $defaults = $this->appearance_defaults();
    $o        = wp_parse_args($saved, $defaults);

    $form_action = admin_url('admin-post.php');
    ?>
    <form method="post" action="<?php echo esc_url($form_action); ?>">
      <?php wp_nonce_field('wpwc_save_appearance', 'wpwc_appearance_nonce'); ?>
      <input type="hidden" name="action" value="wpwc_save_appearance">

      <!-- ── Position ─────────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Position', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Corner', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-corner-picker">
              <?php
              $corners = array(
                'top-left'     => __('Top Left',     'wp-whatsapp-multi-chat'),
                'top-right'    => __('Top Right',    'wp-whatsapp-multi-chat'),
                'bottom-left'  => __('Bottom Left',  'wp-whatsapp-multi-chat'),
                'bottom-right' => __('Bottom Right', 'wp-whatsapp-multi-chat'),
              );
              foreach ($corners as $val => $label) : ?>
              <label class="wpwc-corner-option <?php echo $val; ?>" title="<?php echo esc_attr($label); ?>">
                <input type="radio" name="position_corner" value="<?php echo esc_attr($val); ?>"
                  <?php checked($o['position_corner'], $val); ?>>
                <span><?php echo esc_html($label); ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Offset', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-offset-row">
            <label class="wpwc-inline-label">
              <?php esc_html_e('Horizontal', 'wp-whatsapp-multi-chat'); ?>
              <div class="wpwc-number-wrap">
                <input type="number" name="offset_x" value="<?php echo esc_attr($o['offset_x']); ?>" min="0" max="200" class="small-text"> px
              </div>
            </label>
            <label class="wpwc-inline-label">
              <?php esc_html_e('Vertical', 'wp-whatsapp-multi-chat'); ?>
              <div class="wpwc-number-wrap">
                <input type="number" name="offset_y" value="<?php echo esc_attr($o['offset_y']); ?>" min="0" max="200" class="small-text"> px
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- ── Button Style ─────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Button Style', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Layout', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-radio-group wpwc-style-group">
              <label class="wpwc-radio-label">
                <input type="radio" name="button_style" value="icon-only"
                  <?php checked($o['button_style'], 'icon-only'); ?>>
                <span class="wpwc-radio-text">
                  <?php esc_html_e('Icon only', 'wp-whatsapp-multi-chat'); ?>
                  <span class="wpwc-radio-desc"><?php esc_html_e('Circular button with the WhatsApp icon.', 'wp-whatsapp-multi-chat'); ?></span>
                </span>
              </label>
              <label class="wpwc-radio-label">
                <input type="radio" name="button_style" value="icon-text"
                  <?php checked($o['button_style'], 'icon-text'); ?>>
                <span class="wpwc-radio-text">
                  <?php esc_html_e('Icon + text', 'wp-whatsapp-multi-chat'); ?>
                  <span class="wpwc-radio-desc"><?php esc_html_e('Pill-shaped button with icon and label.', 'wp-whatsapp-multi-chat'); ?></span>
                </span>
              </label>
            </div>
          </div>
        </div>

        <div class="wpwc-condition-row wpwc-button-text-row<?php echo 'icon-only' === $o['button_style'] ? ' is-hidden' : ''; ?>">
          <div class="wpwc-condition-label"><?php esc_html_e('Button text', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="button_text" value="<?php echo esc_attr($o['button_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. Chat with us', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Size', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-size-group">
              <?php
              $sizes = array(
                'small'  => __('Small',  'wp-whatsapp-multi-chat'),
                'medium' => __('Medium', 'wp-whatsapp-multi-chat'),
                'large'  => __('Large',  'wp-whatsapp-multi-chat'),
              );
              foreach ($sizes as $val => $label) : ?>
              <label class="wpwc-size-option">
                <input type="radio" name="button_size" value="<?php echo esc_attr($val); ?>"
                  <?php checked($o['button_size'], $val); ?>>
                <span><?php echo esc_html($label); ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Colors ───────────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Colors', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Background', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-color-row">
            <input type="color" name="color_bg" value="<?php echo esc_attr($o['color_bg']); ?>" class="wpwc-color-input">
            <input type="text" name="color_bg_text" value="<?php echo esc_attr($o['color_bg']); ?>" class="wpwc-color-hex small-text" maxlength="7" pattern="#[0-9a-fA-F]{6}">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Icon / text', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-color-row">
            <input type="color" name="color_fg" value="<?php echo esc_attr($o['color_fg']); ?>" class="wpwc-color-input">
            <input type="text" name="color_fg_text" value="<?php echo esc_attr($o['color_fg']); ?>" class="wpwc-color-hex small-text" maxlength="7" pattern="#[0-9a-fA-F]{6}">
          </div>
        </div>
      </div>

      <!-- ── Attention Animation ───────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Attention Animation', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Effect', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-radio-group wpwc-style-group">
              <?php
              $animations = array(
                'none'   => array('label' => __('None',   'wp-whatsapp-multi-chat'), 'desc' => __('No animation.', 'wp-whatsapp-multi-chat')),
                'pulse'  => array('label' => __('Pulse',  'wp-whatsapp-multi-chat'), 'desc' => __('Expanding ring that repeats.', 'wp-whatsapp-multi-chat')),
                'bounce' => array('label' => __('Bounce', 'wp-whatsapp-multi-chat'), 'desc' => __('Gentle up-and-down motion.', 'wp-whatsapp-multi-chat')),
              );
              foreach ($animations as $val => $anim) : ?>
              <label class="wpwc-radio-label">
                <input type="radio" name="animation" value="<?php echo esc_attr($val); ?>"
                  <?php checked($o['animation'], $val); ?>>
                <span class="wpwc-radio-text">
                  <?php echo esc_html($anim['label']); ?>
                  <span class="wpwc-radio-desc"><?php echo esc_html($anim['desc']); ?></span>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Tooltip ─────────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Tooltip', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Text', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="tooltip_text" value="<?php echo esc_attr($o['tooltip_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. Need help? Chat with us!', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Leave empty to disable the tooltip.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>
      </div>

      <!-- ── Behaviour ──────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Behaviour', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Appear after', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-offset-row">
            <div class="wpwc-number-wrap">
              <input type="number" name="appear_delay" value="<?php echo esc_attr($o['appear_delay']); ?>" min="0" max="10" step="0.5" class="small-text"> <?php esc_html_e('seconds', 'wp-whatsapp-multi-chat'); ?>
            </div>
            <p class="description"><?php esc_html_e('Delay before the widget becomes visible (0 = instant).', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Visibility', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <label class="wpwc-checkbox-label">
              <input type="checkbox" name="hide_mobile" value="1" <?php checked(!empty($o['hide_mobile'])); ?>>
              <?php esc_html_e('Hide on mobile', 'wp-whatsapp-multi-chat'); ?>
            </label>
            <label class="wpwc-checkbox-label">
              <input type="checkbox" name="hide_desktop" value="1" <?php checked(!empty($o['hide_desktop'])); ?>>
              <?php esc_html_e('Hide on desktop', 'wp-whatsapp-multi-chat'); ?>
            </label>
          </div>
        </div>
      </div>

      <!-- ── Text / Labels ─────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Text / Labels', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Panel title', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="panel_title" value="<?php echo esc_attr($o['panel_title']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. WhatsApp', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Panel subtitle', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="panel_subtitle" value="<?php echo esc_attr($o['panel_subtitle']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. Typically replies within minutes', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Contact CTA', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="cta_text" value="<?php echo esc_attr($o['cta_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. Chat', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Label shown next to the arrow on each contact row.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('No contacts message', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="empty_text" value="<?php echo esc_attr($o['empty_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. No contacts available.', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Footer text', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="footer_text" value="<?php echo esc_attr($o['footer_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('e.g. End-to-end encrypted', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Leave empty to hide the footer.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>
      </div>

      <?php submit_button(__('Save Settings', 'wp-whatsapp-multi-chat')); ?>
    </form>
    <?php
  }

  public function save_appearance()
  {
    check_admin_referer('wpwc_save_appearance', 'wpwc_appearance_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have sufficient permissions.', 'wp-whatsapp-multi-chat'));
    }

    $valid_corners    = array('top-left', 'top-right', 'bottom-left', 'bottom-right');
    $valid_styles     = array('icon-only', 'icon-text');
    $valid_sizes      = array('small', 'medium', 'large');
    $valid_animations = array('none', 'pulse', 'bounce');

    $raw_corner    = isset($_POST['position_corner']) ? sanitize_key(wp_unslash($_POST['position_corner'])) : 'bottom-right';
    $raw_style     = isset($_POST['button_style'])    ? sanitize_key(wp_unslash($_POST['button_style']))    : 'icon-only';
    $raw_size      = isset($_POST['button_size'])     ? sanitize_key(wp_unslash($_POST['button_size']))     : 'medium';
    $raw_animation = isset($_POST['animation'])       ? sanitize_key(wp_unslash($_POST['animation']))       : 'none';

    $hex_pattern = '/^#[0-9a-fA-F]{6}$/';
    $raw_color_bg = isset($_POST['color_bg']) ? sanitize_hex_color(wp_unslash($_POST['color_bg'])) : '#25d366';
    $raw_color_fg = isset($_POST['color_fg']) ? sanitize_hex_color(wp_unslash($_POST['color_fg'])) : '#ffffff';

    $options = array(
      'position_corner' => in_array($raw_corner, $valid_corners, true) ? $raw_corner : 'bottom-right',
      'offset_x'        => min(200, max(0, absint(isset($_POST['offset_x']) ? wp_unslash($_POST['offset_x']) : 20))),
      'offset_y'        => min(200, max(0, absint(isset($_POST['offset_y']) ? wp_unslash($_POST['offset_y']) : 20))),
      'button_style'    => in_array($raw_style, $valid_styles, true) ? $raw_style : 'icon-only',
      'button_text'     => sanitize_text_field(wp_unslash(isset($_POST['button_text']) ? $_POST['button_text'] : '')),
      'button_size'     => in_array($raw_size, $valid_sizes, true) ? $raw_size : 'medium',
      'color_bg'        => $raw_color_bg && preg_match($hex_pattern, $raw_color_bg) ? $raw_color_bg : '#25d366',
      'color_fg'        => $raw_color_fg && preg_match($hex_pattern, $raw_color_fg) ? $raw_color_fg : '#ffffff',
      'animation'       => in_array($raw_animation, $valid_animations, true) ? $raw_animation : 'none',
      'tooltip_text'    => sanitize_text_field(wp_unslash(isset($_POST['tooltip_text']) ? $_POST['tooltip_text'] : '')),
      'appear_delay'    => min(10, max(0, (float) (isset($_POST['appear_delay']) ? wp_unslash($_POST['appear_delay']) : 0))),
      'hide_mobile'     => isset($_POST['hide_mobile'])  ? 1 : 0,
      'hide_desktop'    => isset($_POST['hide_desktop']) ? 1 : 0,
      'panel_title'     => sanitize_text_field(wp_unslash(isset($_POST['panel_title'])   ? $_POST['panel_title']   : '')),
      'panel_subtitle'  => sanitize_text_field(wp_unslash(isset($_POST['panel_subtitle']) ? $_POST['panel_subtitle'] : '')),
      'cta_text'        => sanitize_text_field(wp_unslash(isset($_POST['cta_text'])      ? $_POST['cta_text']      : '')),
      'empty_text'      => sanitize_text_field(wp_unslash(isset($_POST['empty_text'])    ? $_POST['empty_text']    : '')),
      'footer_text'     => sanitize_text_field(wp_unslash(isset($_POST['footer_text'])   ? $_POST['footer_text']   : '')),
    );

    update_option(self::APPEARANCE_OPTION_KEY, $options);

    $redirect = admin_url(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
      . '&page=wp-whatsapp-multi-chat-options&tab=appearance&updated=1'
    );

    wp_safe_redirect($redirect);
    exit;
  }

  public static function appearance_defaults()
  {
    return array(
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
    );
  }

  public function save_display_conditions()
  {
    check_admin_referer('wpwc_save_display_conditions', 'wpwc_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have sufficient permissions.', 'wp-whatsapp-multi-chat'));
    }

    $options = array(
      'display_mode'    => (isset($_POST['display_mode']) && 'everywhere' === $_POST['display_mode']) ? 'everywhere' : 'manual',
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
      'search'   => __('Search Results', 'wp-whatsapp-multi-chat'),
      '404'      => __('404 Page', 'wp-whatsapp-multi-chat'),
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
