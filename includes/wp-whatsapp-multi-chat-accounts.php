<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Accounts
{
  protected static $instance = null;
  const POST_TYPE_SLUG = 'wpwc_account';

  protected function __construct()
  {
    add_action("init", array($this, "register_account_post_type"));

    if (!is_admin()) return;
    add_filter('manage_wpwc_account_posts_columns', array($this, 'account_tabulation_header'));
    add_action('manage_wpwc_account_posts_custom_column', array($this, 'account_tabulation_data'), 10, 2);

    add_action('add_meta_boxes', array($this, 'account_meta_boxes'));
    add_action('save_post_' . self::POST_TYPE_SLUG, array($this, 'save_account_meta'));
  }

  public function register_account_post_type()
  {
    $labels = array(
      'name'               => _x('WhatsApp Chats', 'post type general name', 'wp-whatsapp-multi-chat'),
      'singular_name'      => _x('WhatsApp Chat', 'post type singular name', 'wp-whatsapp-multi-chat'),
      'menu_name'          => _x('WhatsApp', 'admin menu', 'wp-whatsapp-multi-chat'),
      'name_admin_bar'     => _x('Chat', 'add new on admin bar', 'wp-whatsapp-multi-chat'),
      'add_new'            => _x('Add New', 'book', 'wp-whatsapp-multi-chat'),
      'add_new_item'       => __('Add New Chat', 'wp-whatsapp-multi-chat'),
      'new_item'           => __('New Chat', 'wp-whatsapp-multi-chat'),
      'edit_item'          => __('Edit Chat', 'wp-whatsapp-multi-chat'),
      'view_item'          => __('View Chat', 'wp-whatsapp-multi-chat'),
      'all_items'          => __('All Chats', 'wp-whatsapp-multi-chat'),
      'search_items'       => __('Search Chats', 'wp-whatsapp-multi-chat'),
      'parent_item_colon'  => __('Parent Chats:', 'wp-whatsapp-multi-chat'),
      'not_found'          => __('No chats found.', 'wp-whatsapp-multi-chat'),
      'not_found_in_trash' => __('No chats found in Trash.', 'wp-whatsapp-multi-chat')
    );

    $args = array(
      'labels'             => $labels,
      'description'        => __('WhatsApp Chats', 'wp-whatsapp-multi-chat'),
      'public'             => false,
      'exclude_from_search' => true,
      'show_ui'            => true,
      'query_var'          => false,
      'rewrite'            => false,
      'capability_type'    => 'post',
      'menu_position'      => null,
      'menu_icon' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.555 4.12 1.524 5.855L0 24l6.337-1.502A11.947 11.947 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.886 0-3.645-.497-5.166-1.365l-.371-.214-3.761.892.937-3.656-.235-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>'),
      'supports'           => array('title', 'thumbnail')
    );

    register_post_type(self::POST_TYPE_SLUG, $args);
  }

  public function account_meta_boxes()
  {
    add_meta_box(
      self::POST_TYPE_SLUG . '_details',
      __('Account Details', 'wp-whatsapp-multi-chat'),
      array($this, 'render_account_meta_box'),
      self::POST_TYPE_SLUG,
      'normal'
    );
  }

  public function render_account_meta_box($post)
  {
    $name = get_post_meta($post->ID, '_wpwc_name', true);
    $number = get_post_meta($post->ID, '_wpwc_number', true);
    $predefined_message = get_post_meta($post->ID, '_wpwc_predefined_message', true);
?>
    <div style="margin-bottom: 15px;">
      <label for="wpwc_name" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Name', 'wp-whatsapp-multi-chat'); ?></label>
      <input type="text" id="wpwc_name" name="wpwc_name" value="<?php echo esc_attr($name); ?>" placeholder="<?php esc_attr_e('e.g., Support', 'wp-whatsapp-multi-chat'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
    </div>

    <div style="margin-bottom: 15px;">
      <label for="wpwc_number" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Phone Number', 'wp-whatsapp-multi-chat'); ?></label>
      <input type="text" id="wpwc_number" name="wpwc_number" value="<?php echo esc_attr($number); ?>" placeholder="<?php esc_attr_e('e.g., 5511912341234', 'wp-whatsapp-multi-chat'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
      <small style="color: #666;"><?php esc_html_e('Include country code and area code', 'wp-whatsapp-multi-chat'); ?></small>
    </div>

    <div style="margin-bottom: 15px;">
      <label for="wpwc_predefined_message" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Predefined Message', 'wp-whatsapp-multi-chat'); ?></label>
      <textarea id="wpwc_predefined_message" name="wpwc_predefined_message" rows="4" placeholder="<?php esc_attr_e('e.g., Hello, I came through the website and would like a quote.', 'wp-whatsapp-multi-chat'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"><?php echo esc_textarea($predefined_message); ?></textarea>
      <small style="color: #666;"><?php esc_html_e('This message will be pre-filled in the WhatsApp chat', 'wp-whatsapp-multi-chat'); ?></small>
    </div>
<?php
  }

  public function save_account_meta($post_id)
  {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['wpwc_name'])) {
      update_post_meta($post_id, '_wpwc_name', sanitize_text_field($_POST['wpwc_name']));
    }

    if (isset($_POST['wpwc_number'])) {
      update_post_meta($post_id, '_wpwc_number', sanitize_text_field($_POST['wpwc_number']));
    }

    if (isset($_POST['wpwc_predefined_message'])) {
      update_post_meta($post_id, '_wpwc_predefined_message', sanitize_textarea_field($_POST['wpwc_predefined_message']));
    }
  }

  public function account_tabulation_header($defaults)
  {
    unset($defaults['date']);
    $defaults['title'] = esc_html(__('Name', 'wp-whatsapp-multi-chat'));
    $defaults['picture'] = esc_html(__('Picture', 'wp-whatsapp-multi-chat'));
    $defaults['picture']  = esc_html__('Picture', 'wptwa');
    $defaults['number']  = esc_html__('Number', 'wptwa');
    return $defaults;
  }

  public function account_tabulation_data($column_name, $post_id)
  {
    if ($column_name === 'picture') {
      if (has_post_thumbnail($post_id)) {
        echo '<img src="' . get_the_post_thumbnail_url($post_id, 'thumbnail') . '" alt="' . get_the_title($post_id) . '" style="width: 40px; height: 50px; object-fit: cover;">';
      }
    }
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
