<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Options
{
  protected static $instance = null;

  const OPTION_KEY            = 'wpwc_display_conditions';
  const APPEARANCE_OPTION_KEY = 'wpwc_appearance';
  const MISC_OPTION_KEY       = 'wpwc_misc';

  protected $page_hook = '';

  protected function __construct()
  {
    add_action('admin_menu', array($this, 'add_options_page'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_wpwc_search_posts', array($this, 'ajax_search_posts'));
    add_action('wp_ajax_wpwc_search_terms', array($this, 'ajax_search_terms'));
    add_action('admin_post_wpwc_save_display_conditions', array($this, 'save_display_conditions'));
    add_action('admin_post_wpwc_save_appearance', array($this, 'save_appearance'));
    add_action('admin_post_wpwc_save_hours', array($this, 'save_hours'));
    add_action('admin_post_wpwc_save_misc', array($this, 'save_misc'));
    add_action('admin_post_wpwc_reset_all', array($this, 'reset_all'));
  }

  public function add_options_page()
  {
    $this->page_hook = add_submenu_page(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
      __('Configuração do WhatsApp Multi Chat', 'wp-whatsapp-multi-chat'),
      __('Configuração', 'wp-whatsapp-multi-chat'),
      'manage_options',
      'wp-whatsapp-multi-chat-options',
      array($this, 'render_options_page')
    );
  }

  public function enqueue_admin_scripts($hook)
  {
    if ($hook !== $this->page_hook) return;

    wp_register_style(
      'wpwc-admin-hours',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/admin/css/admin-hours.css',
      array(),
      WP_WHATSAPP_MULTI_CHAT_VERSION
    );

    wp_enqueue_style(
      'wpwc-admin-options',
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL . 'assets/admin/css/admin-options.css',
      array('wpwc-admin-hours'),
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
      'ajaxUrl'          => admin_url('admin-ajax.php'),
      'nonce'            => wp_create_nonce('wpwc_search_posts'),
      'noResults'        => __('Nenhum resultado encontrado', 'wp-whatsapp-multi-chat'),
      'removeLabel'      => __('Remover', 'wp-whatsapp-multi-chat'),
      'resetConfirmText' => __('Tem certeza? Esta ação é irreversível e apagará todos os dados do plugin.', 'wp-whatsapp-multi-chat'),
    ));
  }

  public function ajax_search_posts()
  {
    check_ajax_referer('wpwc_search_posts', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized', 403);
    }

    $search    = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'any';

    // Only post types offered on the rules screen can be searched.
    if ('any' !== $post_type && !array_key_exists($post_type, $this->get_available_post_types($this->get_saved_rules()))) {
      wp_send_json_error('Invalid post type', 400);
    }

    $query = new WP_Query(array(
      'post_type'      => $post_type,
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

  public function ajax_search_terms()
  {
    check_ajax_referer('wpwc_search_posts', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error('Unauthorized', 403);
    }

    $search   = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';

    // Only taxonomies offered on the rules screen can be searched.
    if (!array_key_exists($taxonomy, $this->get_available_taxonomies())) {
      wp_send_json_error('Invalid taxonomy', 400);
    }

    $terms = get_terms(array(
      'taxonomy'   => $taxonomy,
      'search'     => $search,
      'number'     => 20,
      'hide_empty' => false,
    ));

    $results = array();
    foreach (is_array($terms) ? $terms : array() as $term) {
      $results[] = array('id' => (int) $term->term_id, 'title' => $term->name);
    }

    wp_send_json_success($results);
  }

  /**
   * Options shown in the "Qual" field of the display rules, in screen order.
   * Post types already used by a saved rule stay listed so no setting turns
   * invisible.
   *
   * @return array ['archive' => [which => label], 'singular' => [post type => label]]
   */
  public function get_rule_choices(array $rules): array
  {
    $post_types = $this->get_available_post_types($rules);

    $archive = array('all' => __('Todos os arquivos', 'wp-whatsapp-multi-chat'));

    if ('page' === get_option('show_on_front') && (int) get_option('page_for_posts') > 0) {
      $archive['blog'] = __('Página de posts (blog)', 'wp-whatsapp-multi-chat');
    }

    foreach ($post_types as $name => $type) {
      if (empty($type->has_archive)) continue;
      $archive['pta_' . $name] = sprintf(
        /* translators: %s: post type plural name */
        __('Arquivo de %s', 'wp-whatsapp-multi-chat'),
        $type->labels->name
      );
    }

    foreach ($this->get_available_taxonomies() as $name => $label) {
      $archive['tax_' . $name] = $label;
    }

    $archive['author'] = __('Autores', 'wp-whatsapp-multi-chat');
    $archive['date']   = __('Datas', 'wp-whatsapp-multi-chat');

    $singular = array();
    foreach ($post_types as $name => $type) {
      $singular[$name] = $type->labels->name;
    }

    return array('archive' => $archive, 'singular' => $singular);
  }

  /**
   * Title of the page, post or term chosen in a saved rule. `missing` flags an
   * item deleted since, so the screen can show it as unavailable.
   */
  public function describe_item(string $which, int $item): array
  {
    if (0 === strpos($which, 'tax_')) {
      $term = get_term($item, substr($which, 4));
      if ($term && !is_wp_error($term)) {
        return array('title' => $term->name, 'missing' => false);
      }
      return array('title' => '', 'missing' => true);
    }

    $post = get_post($item);
    if ($post) {
      return array('title' => get_the_title($post), 'missing' => false);
    }
    return array('title' => '', 'missing' => true);
  }

  private function get_saved_rules(): array
  {
    return WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(get_option(self::OPTION_KEY, array()));
  }

  public function render_options_page()
  {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'display_condition';
    $base_url   = admin_url('edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG . '&page=wp-whatsapp-multi-chat-options');
    ?>
    <div class="wrap wpwc-options-page">
      <h1><?php esc_html_e('WhatsApp Multi Chat', 'wp-whatsapp-multi-chat'); ?></h1>

      <?php if (isset($_GET['updated'])) : ?>
        <?php if ('reset' === $_GET['updated']) : ?>
          <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('Plugin resetado. Todos os dados foram apagados e as configurações foram restauradas ao padrão.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        <?php elseif ('1' === $_GET['updated']) : ?>
          <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Configurações salvas.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'display_condition', $base_url)); ?>"
           class="nav-tab <?php echo 'display_condition' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Condições de Exibição', 'wp-whatsapp-multi-chat'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'appearance', $base_url)); ?>"
           class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Aparência', 'wp-whatsapp-multi-chat'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'hours', $base_url)); ?>"
           class="nav-tab <?php echo 'hours' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Horários', 'wp-whatsapp-multi-chat'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'misc', $base_url)); ?>"
           class="nav-tab <?php echo 'misc' === $active_tab ? 'nav-tab-active' : ''; ?>">
          <?php esc_html_e('Diversas', 'wp-whatsapp-multi-chat'); ?>
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
          case 'hours':
            $this->tab_hours();
            break;
          case 'misc':
            $this->tab_misc();
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
        <h2 class="wpwc-section-title"><?php esc_html_e('Exibir widget em', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-radio-group">
          <label class="wpwc-radio-label">
            <input type="radio" name="display_mode" value="everywhere"
              <?php checked($display_mode, 'everywhere'); ?>>
            <span class="wpwc-radio-text">
              <?php esc_html_e('Site inteiro', 'wp-whatsapp-multi-chat'); ?>
              <span class="wpwc-radio-desc"><?php esc_html_e('Exibir em todas as páginas do site.', 'wp-whatsapp-multi-chat'); ?></span>
            </span>
          </label>

          <label class="wpwc-radio-label">
            <input type="radio" name="display_mode" value="manual"
              <?php checked($display_mode, 'manual'); ?>>
            <span class="wpwc-radio-text">
              <?php esc_html_e('Seleção manual', 'wp-whatsapp-multi-chat'); ?>
              <span class="wpwc-radio-desc"><?php esc_html_e('Escolha exatamente onde o widget será exibido.', 'wp-whatsapp-multi-chat'); ?></span>
            </span>
          </label>
        </div>

        <div class="wpwc-manual-conditions<?php echo 'everywhere' === $display_mode ? ' is-hidden' : ''; ?>">

          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Página inicial', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
              <label class="wpwc-checkbox-label">
                <input type="checkbox" name="show_front_page" value="1" <?php checked($show_front_page); ?>>
                <?php esc_html_e('Página inicial', 'wp-whatsapp-multi-chat'); ?>
              </label>
            </div>
          </div>

          <?php if (!empty($post_types)) : ?>
          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Tipos de post', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
              <fieldset>
                <legend class="screen-reader-text"><?php esc_html_e('Tipos de post', 'wp-whatsapp-multi-chat'); ?></legend>
                <?php foreach ($post_types as $pt) : ?>
                <label class="wpwc-checkbox-label">
                  <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt->name); ?>"
                    <?php checked(in_array($pt->name, $selected_post_types, true)); ?>>
                  <?php echo esc_html($pt->labels->name); ?>
                  <span class="wpwc-slug-label"><?php echo esc_html($pt->name); ?></span>
                </label>
                <?php endforeach; ?>
              </fieldset>
              <p class="description"><?php esc_html_e('Exibir em posts individuais dos tipos selecionados.', 'wp-whatsapp-multi-chat'); ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($archives)) : ?>
          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Páginas de arquivo', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
              <fieldset>
                <legend class="screen-reader-text"><?php esc_html_e('Arquivos', 'wp-whatsapp-multi-chat'); ?></legend>
                <?php foreach ($archives as $key => $label) : ?>
                <label class="wpwc-checkbox-label">
                  <input type="checkbox" name="archives[]" value="<?php echo esc_attr($key); ?>"
                    <?php checked(in_array($key, $selected_archives, true)); ?>>
                  <?php echo esc_html($label); ?>
                </label>
                <?php endforeach; ?>
              </fieldset>
              <p class="description"><?php esc_html_e('Exibir nas páginas de arquivo / especiais selecionadas.', 'wp-whatsapp-multi-chat'); ?></p>
            </div>
          </div>
          <?php endif; ?>

          <div class="wpwc-condition-row">
            <div class="wpwc-condition-label"><?php esc_html_e('Páginas / posts específicos', 'wp-whatsapp-multi-chat'); ?></div>
            <div class="wpwc-condition-control">
              <div class="wpwc-post-selector" data-field="include_posts">
                <div class="wpwc-search-wrap">
                  <input type="text" class="wpwc-search-input regular-text"
                         placeholder="<?php esc_attr_e('Buscar páginas ou posts…', 'wp-whatsapp-multi-chat'); ?>"
                         autocomplete="off">
                  <div class="wpwc-search-results" hidden></div>
                </div>
                <ul class="wpwc-selected-items">
                  <?php foreach ($include_post_data as $item) : ?>
                  <li data-id="<?php echo esc_attr($item['id']); ?>">
                    <span class="wpwc-item-title"><?php echo esc_html($item['title']); ?></span>
                    <span class="wpwc-type-label"><?php echo esc_html($item['type']); ?></span>
                    <button type="button" class="wpwc-remove-item button-link" aria-label="<?php esc_attr_e('Remover', 'wp-whatsapp-multi-chat'); ?>">&times;</button>
                    <input type="hidden" name="include_posts[]" value="<?php echo esc_attr($item['id']); ?>">
                  </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <p class="description"><?php esc_html_e('Sempre exibir nestas páginas ou posts (acumulado às condições acima).', 'wp-whatsapp-multi-chat'); ?></p>
            </div>
          </div>

        </div><!-- /.wpwc-manual-conditions -->
      </div><!-- /.wpwc-section -->

      <!-- ── Section: Exceptions ─────────────────────────────────── -->
      <div class="wpwc-section wpwc-section-exceptions">
        <h2 class="wpwc-section-title"><?php esc_html_e('Exceções — nunca exibir em', 'wp-whatsapp-multi-chat'); ?></h2>
        <p class="wpwc-section-description"><?php esc_html_e('O widget ficará oculto nestas páginas independentemente das regras acima.', 'wp-whatsapp-multi-chat'); ?></p>

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

      <?php submit_button(__('Salvar Configurações', 'wp-whatsapp-multi-chat')); ?>
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
        <h2 class="wpwc-section-title"><?php esc_html_e('Posição', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Canto', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-corner-picker">
              <?php
              $corners = array(
                'top-left'     => __('Superior Esquerdo', 'wp-whatsapp-multi-chat'),
                'top-right'    => __('Superior Direito',  'wp-whatsapp-multi-chat'),
                'bottom-left'  => __('Inferior Esquerdo', 'wp-whatsapp-multi-chat'),
                'bottom-right' => __('Inferior Direito',  'wp-whatsapp-multi-chat'),
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
          <div class="wpwc-condition-label"><?php esc_html_e('Deslocamento', 'wp-whatsapp-multi-chat'); ?></div>
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
        <h2 class="wpwc-section-title"><?php esc_html_e('Estilo do Botão', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Layout', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-radio-group wpwc-style-group">
              <label class="wpwc-radio-label">
                <input type="radio" name="button_style" value="icon-only"
                  <?php checked($o['button_style'], 'icon-only'); ?>>
                <span class="wpwc-radio-text">
                  <?php esc_html_e('Apenas ícone', 'wp-whatsapp-multi-chat'); ?>
                  <span class="wpwc-radio-desc"><?php esc_html_e('Botão circular com o ícone do WhatsApp.', 'wp-whatsapp-multi-chat'); ?></span>
                </span>
              </label>
              <label class="wpwc-radio-label">
                <input type="radio" name="button_style" value="icon-text"
                  <?php checked($o['button_style'], 'icon-text'); ?>>
                <span class="wpwc-radio-text">
                  <?php esc_html_e('Ícone + texto', 'wp-whatsapp-multi-chat'); ?>
                  <span class="wpwc-radio-desc"><?php esc_html_e('Botão arredondado com ícone e rótulo.', 'wp-whatsapp-multi-chat'); ?></span>
                </span>
              </label>
            </div>
          </div>
        </div>

        <div class="wpwc-condition-row wpwc-button-text-row<?php echo 'icon-only' === $o['button_style'] ? ' is-hidden' : ''; ?>">
          <div class="wpwc-condition-label"><?php esc_html_e('Texto do botão', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="button_text" value="<?php echo esc_attr($o['button_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Fale conosco', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Tamanho', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-size-group">
              <?php
              $sizes = array(
                'small'  => __('Pequeno', 'wp-whatsapp-multi-chat'),
                'medium' => __('Médio',   'wp-whatsapp-multi-chat'),
                'large'  => __('Grande',  'wp-whatsapp-multi-chat'),
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
        <h2 class="wpwc-section-title"><?php esc_html_e('Cores', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Fundo', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-color-row">
            <input type="color" name="color_bg" value="<?php echo esc_attr($o['color_bg']); ?>" class="wpwc-color-input">
            <input type="text" name="color_bg_text" value="<?php echo esc_attr($o['color_bg']); ?>" class="wpwc-color-hex small-text" maxlength="7" pattern="#[0-9a-fA-F]{6}">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Ícone / texto', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-color-row">
            <input type="color" name="color_fg" value="<?php echo esc_attr($o['color_fg']); ?>" class="wpwc-color-input">
            <input type="text" name="color_fg_text" value="<?php echo esc_attr($o['color_fg']); ?>" class="wpwc-color-hex small-text" maxlength="7" pattern="#[0-9a-fA-F]{6}">
          </div>
        </div>
      </div>

      <!-- ── Attention Animation ───────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Animação de Atenção', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Efeito', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <div class="wpwc-radio-group wpwc-style-group">
              <?php
              $animations = array(
                'none'   => array('label' => __('Nenhum',  'wp-whatsapp-multi-chat'), 'desc' => __('Sem animação.', 'wp-whatsapp-multi-chat')),
                'pulse'  => array('label' => __('Pulso',   'wp-whatsapp-multi-chat'), 'desc' => __('Anel expansivo que se repete.', 'wp-whatsapp-multi-chat')),
                'bounce' => array('label' => __('Salto',   'wp-whatsapp-multi-chat'), 'desc' => __('Movimento suave para cima e para baixo.', 'wp-whatsapp-multi-chat')),
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
        <h2 class="wpwc-section-title"><?php esc_html_e('Dica', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Texto', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="tooltip_text" value="<?php echo esc_attr($o['tooltip_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Precisa de ajuda? Fale conosco!', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Deixe em branco para desativar a dica.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>
      </div>

      <!-- ── Behaviour ──────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Comportamento', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Aparecer após', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control wpwc-offset-row">
            <div class="wpwc-number-wrap">
              <input type="number" name="appear_delay" value="<?php echo esc_attr($o['appear_delay']); ?>" min="0" max="10" step="0.5" class="small-text"> <?php esc_html_e('segundos', 'wp-whatsapp-multi-chat'); ?>
            </div>
            <p class="description"><?php esc_html_e('Atraso antes de o widget ficar visível (0 = instantâneo).', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Visibilidade', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <label class="wpwc-checkbox-label">
              <input type="checkbox" name="hide_mobile" value="1" <?php checked(!empty($o['hide_mobile'])); ?>>
              <?php esc_html_e('Ocultar no mobile', 'wp-whatsapp-multi-chat'); ?>
            </label>
            <label class="wpwc-checkbox-label">
              <input type="checkbox" name="hide_desktop" value="1" <?php checked(!empty($o['hide_desktop'])); ?>>
              <?php esc_html_e('Ocultar no desktop', 'wp-whatsapp-multi-chat'); ?>
            </label>
          </div>
        </div>
      </div>

      <!-- ── Text / Labels ─────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Textos / Rótulos', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Título do painel', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="panel_title" value="<?php echo esc_attr($o['panel_title']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: WhatsApp', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Subtítulo do painel', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="panel_subtitle" value="<?php echo esc_attr($o['panel_subtitle']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Geralmente responde em minutos', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('CTA do contato', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="cta_text" value="<?php echo esc_attr($o['cta_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Conversar', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Rótulo exibido ao lado da seta em cada linha de contato.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Mensagem sem contatos', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="empty_text" value="<?php echo esc_attr($o['empty_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Nenhum contato disponível.', 'wp-whatsapp-multi-chat'); ?>">
          </div>
        </div>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Texto do rodapé', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="footer_text" value="<?php echo esc_attr($o['footer_text']); ?>" class="regular-text"
                   placeholder="<?php esc_attr_e('ex.: Criptografia de ponta a ponta', 'wp-whatsapp-multi-chat'); ?>">
            <p class="description"><?php esc_html_e('Deixe em branco para ocultar o rodapé.', 'wp-whatsapp-multi-chat'); ?></p>
          </div>
        </div>

        <?php
        // Shown only for accounts that follow business hours (see the "Horários" tab).
        $status_fields = array(
          'status_online'    => array(__('Status online', 'wp-whatsapp-multi-chat'), ''),
          'status_offline'   => array(__('Status offline', 'wp-whatsapp-multi-chat'), __('Usado quando a conta não tem próxima abertura (ex.: "Sempre offline").', 'wp-whatsapp-multi-chat')),
          'status_back'      => array(__('Status "volta em"', 'wp-whatsapp-multi-chat'), __('Use {dia} e {hora}. Ex.: "Volta seg às 08:00".', 'wp-whatsapp-multi-chat')),
          'status_today'     => array(__('Palavra para "hoje"', 'wp-whatsapp-multi-chat'), ''),
          'status_tomorrow'  => array(__('Palavra para "amanhã"', 'wp-whatsapp-multi-chat'), ''),
          'offline_subtitle' => array(__('Subtítulo quando todos estão offline', 'wp-whatsapp-multi-chat'), __('Substitui o subtítulo do painel quando todas as contas estão fora do horário. Deixe em branco para manter o subtítulo normal.', 'wp-whatsapp-multi-chat')),
        );
        foreach ($status_fields as $field => $meta) :
        ?>
        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php echo esc_html($meta[0]); ?></div>
          <div class="wpwc-condition-control">
            <input type="text" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($o[$field]); ?>" class="regular-text">
            <?php if ($meta[1]) : ?>
              <p class="description"><?php echo esc_html($meta[1]); ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php submit_button(__('Salvar Configurações', 'wp-whatsapp-multi-chat')); ?>
    </form>
    <?php
  }

  public function save_appearance()
  {
    check_admin_referer('wpwc_save_appearance', 'wpwc_appearance_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Você não tem permissões suficientes.', 'wp-whatsapp-multi-chat'));
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
      'status_online'   => sanitize_text_field(wp_unslash(isset($_POST['status_online'])   ? $_POST['status_online']   : '')),
      'status_offline'  => sanitize_text_field(wp_unslash(isset($_POST['status_offline'])  ? $_POST['status_offline']  : '')),
      'status_back'     => sanitize_text_field(wp_unslash(isset($_POST['status_back'])     ? $_POST['status_back']     : '')),
      'status_today'    => sanitize_text_field(wp_unslash(isset($_POST['status_today'])    ? $_POST['status_today']    : '')),
      'status_tomorrow' => sanitize_text_field(wp_unslash(isset($_POST['status_tomorrow']) ? $_POST['status_tomorrow'] : '')),
      'offline_subtitle' => sanitize_text_field(wp_unslash(isset($_POST['offline_subtitle']) ? $_POST['offline_subtitle'] : '')),
    );

    update_option(self::APPEARANCE_OPTION_KEY, $options);

    // Register translatable text strings with WPML String Translation.
    $context     = 'wp-whatsapp-multi-chat';
    $text_fields = array(
      'button_text', 'tooltip_text', 'panel_title', 'panel_subtitle',
      'cta_text', 'empty_text', 'footer_text',
      'status_online', 'status_offline', 'status_back',
      'status_today', 'status_tomorrow', 'offline_subtitle',
    );
    foreach ($text_fields as $field) {
      if (!empty($options[$field])) {
        do_action('wpml_register_single_string', $context, 'appearance_' . $field, $options[$field]);
      }
    }

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
      'button_text'     => __('Fale conosco', 'wp-whatsapp-multi-chat'),
      'button_size'     => 'medium',
      'color_bg'        => '#25d366',
      'color_fg'        => '#ffffff',
      'animation'       => 'none',
      'tooltip_text'    => '',
      'appear_delay'    => 0,
      'hide_mobile'     => 0,
      'hide_desktop'    => 0,
      'panel_title'     => __('WhatsApp', 'wp-whatsapp-multi-chat'),
      'panel_subtitle'  => __('Geralmente responde em minutos', 'wp-whatsapp-multi-chat'),
      'cta_text'        => __('Conversar', 'wp-whatsapp-multi-chat'),
      'empty_text'      => __('Nenhum contato disponível.', 'wp-whatsapp-multi-chat'),
      'footer_text'     => __('Criptografia de ponta a ponta', 'wp-whatsapp-multi-chat'),
      'status_online'   => __('Online agora', 'wp-whatsapp-multi-chat'),
      'status_offline'  => __('Offline', 'wp-whatsapp-multi-chat'),
      /* translators: keep {dia} and {hora}; they become e.g. "seg" and "08:00" */
      'status_back'     => __('Volta {dia} às {hora}', 'wp-whatsapp-multi-chat'),
      'status_today'    => __('hoje', 'wp-whatsapp-multi-chat'),
      'status_tomorrow' => __('amanhã', 'wp-whatsapp-multi-chat'),
      'offline_subtitle' => __('Fora do horário de atendimento. Responderemos assim que voltarmos.', 'wp-whatsapp-multi-chat'),
      'aria_open'       => __('Abrir chat do WhatsApp', 'wp-whatsapp-multi-chat'),
      'aria_panel'      => __('Contatos do WhatsApp', 'wp-whatsapp-multi-chat'),
    );
  }

  public function tab_hours()
  {
    $errors   = WP_WhatsApp_Multi_Chat_Hours::pull_errors();
    $timezone = wp_timezone_string();
    ?>
    <?php if (!empty($errors)) : ?>
      <div class="notice notice-error is-dismissible">
        <?php foreach ($errors as $day => $code) : ?>
          <p><?php echo esc_html(WP_WhatsApp_Multi_Chat_Hours::error_message($day, $code)); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('wpwc_save_hours', 'wpwc_hours_nonce'); ?>
      <input type="hidden" name="action" value="wpwc_save_hours">

      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Horário geral de atendimento', 'wp-whatsapp-multi-chat'); ?></h2>
        <p class="description">
          <?php esc_html_e('Vale para as contas com o modo "Horário geral". Fora destes horários, essas contas aparecem como offline no widget.', 'wp-whatsapp-multi-chat'); ?>
        </p>
        <p class="description">
          <?php
          printf(
            /* translators: 1: timezone name, 2: link to the General Settings screen */
            esc_html__('Horários no fuso %1$s. Para alterar, acesse %2$s.', 'wp-whatsapp-multi-chat'),
            '<strong>' . esc_html($timezone) . '</strong>',
            '<a href="' . esc_url(admin_url('options-general.php')) . '">' . esc_html__('Configurações → Geral', 'wp-whatsapp-multi-chat') . '</a>'
          );
          ?>
        </p>

        <div class="wpwc-hours-grid">
          <?php WP_WhatsApp_Multi_Chat_Hours::render_week_fields('wpwc_hours', WP_WhatsApp_Multi_Chat_Hours::get_general()); ?>
        </div>
      </div>

      <?php submit_button(__('Salvar Configurações', 'wp-whatsapp-multi-chat')); ?>
    </form>
    <?php
  }

  public function save_hours()
  {
    check_admin_referer('wpwc_save_hours', 'wpwc_hours_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Você não tem permissões suficientes.', 'wp-whatsapp-multi-chat'));
    }

    $raw    = isset($_POST['wpwc_hours']) ? wp_unslash($_POST['wpwc_hours']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by sanitize_week()
    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week($raw, WP_WhatsApp_Multi_Chat_Hours::get_general());

    update_option(WP_WhatsApp_Multi_Chat_Hours::OPTION_KEY, $result['hours']);
    WP_WhatsApp_Multi_Chat_Hours::store_errors($result['errors']);

    wp_safe_redirect(admin_url(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
      . '&page=wp-whatsapp-multi-chat-options&tab=hours&updated=1'
    ));
    exit;
  }

  public function tab_misc()
  {
    $saved           = get_option(self::MISC_OPTION_KEY, array());
    $delete_on_uninstall = !empty($saved['delete_on_uninstall']);

    $form_action = admin_url('admin-post.php');
    ?>
    <form method="post" action="<?php echo esc_url($form_action); ?>">
      <?php wp_nonce_field('wpwc_save_misc', 'wpwc_misc_nonce'); ?>
      <input type="hidden" name="action" value="wpwc_save_misc">

      <!-- ── Uninstall ───────────────────────────────────────────── -->
      <div class="wpwc-section">
        <h2 class="wpwc-section-title"><?php esc_html_e('Desinstalar', 'wp-whatsapp-multi-chat'); ?></h2>

        <div class="wpwc-condition-row">
          <div class="wpwc-condition-label"><?php esc_html_e('Remover dados ao desinstalar', 'wp-whatsapp-multi-chat'); ?></div>
          <div class="wpwc-condition-control">
            <label class="wpwc-checkbox-label">
              <input type="checkbox" name="delete_on_uninstall" value="1" <?php checked($delete_on_uninstall); ?>>
              <?php esc_html_e('Excluir todas as configurações e dados do plugin ao desinstalar.', 'wp-whatsapp-multi-chat'); ?>
            </label>
            <p class="description">
              <?php esc_html_e('Quando marcado, todas as opções e registros de contatos do WhatsApp criados por este plugin serão removidos permanentemente na desinstalação. Esta ação não pode ser desfeita.', 'wp-whatsapp-multi-chat'); ?>
            </p>
          </div>
        </div>
      </div>

      <?php submit_button(__('Salvar Configurações', 'wp-whatsapp-multi-chat')); ?>
    </form>

    <!-- ── Reset ───────────────────────────────────────────────────── -->
    <div class="wpwc-section wpwc-section-danger">
      <h2 class="wpwc-section-title wpwc-section-title-danger"><?php esc_html_e('Resetar Plugin', 'wp-whatsapp-multi-chat'); ?></h2>

      <p class="wpwc-danger-description">
        <?php esc_html_e('Selecione o que deseja resetar. As ações marcadas são irreversíveis:', 'wp-whatsapp-multi-chat'); ?>
      </p>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpwc-reset-form">
        <?php wp_nonce_field('wpwc_reset_all', 'wpwc_reset_nonce'); ?>
        <input type="hidden" name="action" value="wpwc_reset_all">

        <label class="wpwc-reset-select-all">
          <input type="checkbox" id="wpwc-reset-select-all">
          <?php esc_html_e('Selecionar tudo', 'wp-whatsapp-multi-chat'); ?>
        </label>

        <ul class="wpwc-reset-list">
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="accounts">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Apagar todas as contas', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('Todos os contatos do WhatsApp cadastrados serão removidos permanentemente.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="appearance">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Redefinir aparência', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('Cores, posição, tamanho do botão e animação voltarão ao padrão.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="texts">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Redefinir textos do widget', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('Título, subtítulo, CTA, mensagens de rodapé e textos de acessibilidade serão restaurados.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="display_conditions">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Redefinir condições de exibição', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('Regras de onde o widget é exibido (páginas, tipos de post, arquivos) serão removidas.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="hours">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Redefinir horários', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('O horário geral volta ao padrão (seg a sex, 08:00 às 18:00). Os horários próprios das contas não são alterados.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
          <li>
            <label class="wpwc-reset-item">
              <input type="checkbox" name="wpwc_reset[]" value="misc">
              <span class="wpwc-reset-item-body">
                <strong><?php esc_html_e('Redefinir configurações diversas', 'wp-whatsapp-multi-chat'); ?></strong>
                <span class="wpwc-reset-list-desc"><?php esc_html_e('Opções da aba &ldquo;Diversas&rdquo; (como remoção de dados ao desinstalar) serão restauradas ao padrão.', 'wp-whatsapp-multi-chat'); ?></span>
              </span>
            </label>
          </li>
        </ul>

        <button type="submit" class="button wpwc-reset-button" disabled>
          <?php esc_html_e('Resetar selecionados', 'wp-whatsapp-multi-chat'); ?>
        </button>
      </form>
    </div>
    <?php
  }

  public function save_misc()
  {
    check_admin_referer('wpwc_save_misc', 'wpwc_misc_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Você não tem permissões suficientes.', 'wp-whatsapp-multi-chat'));
    }

    $options = array(
      'delete_on_uninstall' => isset($_POST['delete_on_uninstall']) ? 1 : 0,
    );

    update_option(self::MISC_OPTION_KEY, $options);

    $redirect = admin_url(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
      . '&page=wp-whatsapp-multi-chat-options&tab=misc&updated=1'
    );

    wp_safe_redirect($redirect);
    exit;
  }

  public function reset_all()
  {
    check_admin_referer('wpwc_reset_all', 'wpwc_reset_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Você não tem permissões suficientes.', 'wp-whatsapp-multi-chat'));
    }

    $to_reset = isset($_POST['wpwc_reset'])
      ? array_map('sanitize_key', (array) wp_unslash($_POST['wpwc_reset']))
      : array();

    if (empty($to_reset)) {
      wp_safe_redirect(admin_url(
        'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
        . '&page=wp-whatsapp-multi-chat-options&tab=misc'
      ));
      exit;
    }

    if (in_array('accounts', $to_reset, true)) {
      $posts = get_posts(array(
        'post_type'        => WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG,
        'post_status'      => 'any',
        'numberposts'      => -1,
        'fields'           => 'ids',
        'suppress_filters' => false,
      ));
      foreach ($posts as $post_id) {
        wp_delete_post((int) $post_id, true);
      }
    }

    if (in_array('appearance', $to_reset, true) || in_array('texts', $to_reset, true)) {
      // Both appearance and texts are stored in the same option key; replace with
      // only the defaults of the sub-set the user chose to reset.
      $current = get_option(self::APPEARANCE_OPTION_KEY, array());
      $defaults = self::appearance_defaults();

      $appearance_keys = array(
        'position_corner', 'offset_x', 'offset_y',
        'button_style', 'button_size',
        'color_bg', 'color_fg',
        'animation', 'appear_delay',
        'hide_mobile', 'hide_desktop',
      );
      $text_keys = array(
        'button_text', 'tooltip_text',
        'panel_title', 'panel_subtitle',
        'cta_text', 'empty_text', 'footer_text',
        'aria_open', 'aria_panel',
        'status_online', 'status_offline', 'status_back',
        'status_today', 'status_tomorrow', 'offline_subtitle',
      );

      $keys_to_reset = array();
      if (in_array('appearance', $to_reset, true)) {
        $keys_to_reset = array_merge($keys_to_reset, $appearance_keys);
      }
      if (in_array('texts', $to_reset, true)) {
        $keys_to_reset = array_merge($keys_to_reset, $text_keys);
      }

      foreach ($keys_to_reset as $key) {
        $current[$key] = $defaults[$key];
      }

      update_option(self::APPEARANCE_OPTION_KEY, $current);
    }

    if (in_array('display_conditions', $to_reset, true)) {
      delete_option(self::OPTION_KEY);
    }

    if (in_array('hours', $to_reset, true)) {
      delete_option(WP_WhatsApp_Multi_Chat_Hours::OPTION_KEY);
    }

    if (in_array('misc', $to_reset, true)) {
      delete_option(self::MISC_OPTION_KEY);
    }

    $redirect = admin_url(
      'edit.php?post_type=' . WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG
      . '&page=wp-whatsapp-multi-chat-options&tab=misc&updated=reset'
    );

    wp_safe_redirect($redirect);
    exit;
  }

  public function save_display_conditions()
  {
    check_admin_referer('wpwc_save_display_conditions', 'wpwc_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('Você não tem permissões suficientes.', 'wp-whatsapp-multi-chat'));
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

  /**
   * Post types visitors can reach: public and shown in menus. This leaves out
   * builder templates and similar internal types. Types used by a saved rule
   * are kept so that rule stays visible on screen.
   */
  private function get_available_post_types(array $rules = array()): array
  {
    $used = array();
    foreach ($rules as $rule) {
      if ('singular' === $rule['where']) $used[] = $rule['which'];
    }

    $post_types = array();
    foreach (get_post_types(array('public' => true), 'objects') as $name => $type) {
      if ('attachment' === $name) continue;
      if (empty($type->show_in_nav_menus) && !in_array($name, $used, true)) continue;
      $post_types[$name] = $type;
    }

    return apply_filters('wpwc_display_post_types', $post_types);
  }

  /**
   * Public taxonomies with term archives, Categories and Tags first.
   *
   * @return array [taxonomy => label]
   */
  private function get_available_taxonomies(): array
  {
    $taxonomies = array(
      'category' => __('Categorias', 'wp-whatsapp-multi-chat'),
      'post_tag' => __('Tags', 'wp-whatsapp-multi-chat'),
    );

    foreach (get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects') as $name => $taxonomy) {
      if (isset($taxonomies[$name])) continue;
      $taxonomies[$name] = $taxonomy->labels->name;
    }

    return $taxonomies;
  }

  private function get_available_archives()
  {
    $archives = array(
      'category' => __('Arquivo de Categorias', 'wp-whatsapp-multi-chat'),
      'tag'      => __('Arquivo de Tags', 'wp-whatsapp-multi-chat'),
      'author'   => __('Arquivo de Autores', 'wp-whatsapp-multi-chat'),
      'date'     => __('Arquivo por Data', 'wp-whatsapp-multi-chat'),
      'search'   => __('Resultados de Busca', 'wp-whatsapp-multi-chat'),
      '404'      => __('Página 404', 'wp-whatsapp-multi-chat'),
    );

    $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects');
    foreach ($taxonomies as $taxonomy) {
      $archives['tax_' . $taxonomy->name] = sprintf(
        /* translators: %s: taxonomy name */
        __('Arquivo de %s', 'wp-whatsapp-multi-chat'),
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
