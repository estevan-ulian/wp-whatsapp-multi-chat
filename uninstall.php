<?php

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

$misc = get_option('wpwc_misc', array());

if (empty($misc['delete_on_uninstall'])) {
  return;
}

delete_option('wpwc_display_conditions');
delete_option('wpwc_appearance');
delete_option('wpwc_misc');
delete_option('wpwc_hours');

$posts = get_posts(array(
  'post_type'   => 'wpwc_account',
  'numberposts' => -1,
  'post_status' => 'any',
  'fields'      => 'ids',
));

foreach ($posts as $post_id) {
  wp_delete_post($post_id, true);
}
