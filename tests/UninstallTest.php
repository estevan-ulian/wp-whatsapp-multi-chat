<?php

require_once __DIR__ . '/WPWC_TestCase.php';

use Brain\Monkey\Functions;

class UninstallTest extends WPWC_TestCase
{
  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_uninstall_deletes_options(): void
  {
    Functions\stubs([
      'is_admin'    => true,
      'add_action'  => true,
      'add_filter'  => true,
      'add_submenu_page' => true,
      'admin_url'   => '',
    ]);

    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();

    Functions\expect('delete_option')
      ->once()
      ->with('wpwc_display_conditions');

    Functions\expect('delete_option')
      ->once()
      ->with('wpwc_appearance');

    Functions\expect('get_posts')
      ->once()
      ->with(\Mockery::on(function ($args) {
        return $args['post_type'] === 'wpwc_account'
          && $args['post_status'] === 'any'
          && $args['fields'] === 'ids';
      }))
      ->andReturn([1, 2, 3]);

    Functions\expect('wp_delete_post')
      ->times(3)
      ->with(\Mockery::type('int'), true);

    // Simulate uninstall context
    define('WP_UNINSTALL_PLUGIN', true);

    require WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'uninstall.php';
  }
}
