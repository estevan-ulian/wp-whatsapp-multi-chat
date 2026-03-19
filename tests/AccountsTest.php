<?php

require_once __DIR__ . '/WPWC_TestCase.php';

use Brain\Monkey\Functions;

class AccountsTest extends WPWC_TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // Stub WP functions used during class load
    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();

    Functions\stubs([
      'add_action'          => true,
      'add_filter'          => true,
      'register_post_type'  => true,
      'add_meta_box'        => true,
      'plugin_dir_path'     => WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR,
      'plugin_dir_url'      => WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL,
      'is_admin'            => true,
      'get_current_screen'  => null,
    ]);

    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
  }

  public function test_post_type_slug_constant(): void
  {
    $this->assertSame('wpwc_account', WP_WhatsApp_Multi_Chat_Accounts::POST_TYPE_SLUG);
  }

  public function test_register_post_type_is_called(): void
  {
    $called = false;
    Functions\when('register_post_type')->alias(function ($slug, $args) use (&$called) {
      $called = true;
      $this->assertSame('wpwc_account', $slug);
      $this->assertIsArray($args);
    });

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->register_account_post_type();

    $this->assertTrue($called, 'register_post_type should have been called');
  }

  public function test_post_type_args_are_correct(): void
  {
    $captured_args = null;

    Functions\when('register_post_type')->alias(function ($slug, $args) use (&$captured_args) {
      $captured_args = $args;
    });

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->register_account_post_type();

    $this->assertNotNull($captured_args, 'register_post_type should have been called');
    $this->assertFalse($captured_args['public']);
    $this->assertTrue($captured_args['exclude_from_search']);
    $this->assertTrue($captured_args['show_ui']);
    $this->assertFalse($captured_args['rewrite']);
    $this->assertFalse($captured_args['query_var']);
    $this->assertContains('title', $captured_args['supports']);
    $this->assertContains('thumbnail', $captured_args['supports']);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_account_meta_skips_on_autosave(): void
  {
    if (!defined('DOING_AUTOSAVE')) {
      define('DOING_AUTOSAVE', true);
    }

    Functions\expect('update_post_meta')->never();

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(1);
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_account_meta_skips_without_capability(): void
  {
    Functions\expect('current_user_can')
      ->once()
      ->with('edit_post', 1)
      ->andReturn(false);

    Functions\expect('update_post_meta')->never();

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(1);
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_account_meta_skips_without_nonce(): void
  {
    Functions\expect('current_user_can')
      ->with('edit_post', 1)
      ->andReturn(true);

    Functions\expect('update_post_meta')->never();

    // No nonce in $_POST
    $_POST = [];

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(1);
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_account_meta_skips_with_invalid_nonce(): void
  {
    Functions\expect('current_user_can')
      ->with('edit_post', 1)
      ->andReturn(true);

    Functions\stubs([
      'sanitize_text_field' => function ($v) { return $v; },
      'wp_unslash'          => function ($v) { return $v; },
    ]);

    Functions\expect('wp_verify_nonce')
      ->once()
      ->andReturn(false);

    Functions\expect('update_post_meta')->never();

    $_POST = ['wpwc_account_nonce' => 'bad_nonce'];

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(1);

    $_POST = [];
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_account_meta_saves_with_valid_nonce(): void
  {
    Functions\expect('current_user_can')
      ->with('edit_post', 42)
      ->andReturn(true);

    Functions\stubs([
      'sanitize_text_field'    => function ($v) { return $v; },
      'sanitize_textarea_field' => function ($v) { return $v; },
      'wp_unslash'             => function ($v) { return $v; },
    ]);

    Functions\expect('wp_verify_nonce')
      ->once()
      ->with('valid_nonce', 'wpwc_save_account_42')
      ->andReturn(1);

    Functions\expect('update_post_meta')
      ->once()
      ->with(42, '_wpwc_name', 'Support');

    Functions\expect('update_post_meta')
      ->once()
      ->with(42, '_wpwc_cargo', 'Suporte');

    Functions\expect('update_post_meta')
      ->once()
      ->with(42, '_wpwc_number', '5511912341234');

    Functions\expect('update_post_meta')
      ->once()
      ->with(42, '_wpwc_predefined_message', 'Hello');

    $_POST = [
      'wpwc_account_nonce'      => 'valid_nonce',
      'wpwc_name'               => 'Support',
      'wpwc_cargo'              => 'Suporte',
      'wpwc_number'             => '55-11-91234-1234',
      'wpwc_predefined_message' => 'Hello',
    ];

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(42);

    $_POST = [];
  }

  public function test_save_account_meta_sanitizes_phone_number(): void
  {
    Functions\expect('current_user_can')
      ->with('edit_post', 10)
      ->andReturn(true);

    Functions\stubs([
      'sanitize_text_field'    => function ($v) { return $v; },
      'sanitize_textarea_field' => function ($v) { return $v; },
      'wp_unslash'             => function ($v) { return $v; },
    ]);

    Functions\expect('wp_verify_nonce')->once()->andReturn(1);

    $saved_number = null;
    Functions\expect('update_post_meta')
      ->atLeast()->once()
      ->andReturnUsing(function ($id, $key, $value) use (&$saved_number) {
        if ($key === '_wpwc_number') {
          $saved_number = $value;
        }
      });

    $_POST = [
      'wpwc_account_nonce'      => 'valid',
      'wpwc_name'               => 'Test',
      'wpwc_cargo'              => '',
      'wpwc_number'             => '+55 (11) 91234-1234',
      'wpwc_predefined_message' => '',
    ];

    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $instance->save_account_meta(10);

    $this->assertSame('5511912341234', $saved_number);

    $_POST = [];
  }

  public function test_tabulation_header_has_expected_columns(): void
  {
    $instance = WP_WhatsApp_Multi_Chat_Accounts::get_instance();
    $result = $instance->account_tabulation_header(['title' => 'Title', 'date' => 'Date']);

    $this->assertArrayHasKey('title', $result);
    $this->assertArrayHasKey('picture', $result);
    $this->assertArrayHasKey('number', $result);
    $this->assertArrayNotHasKey('date', $result);
  }
}
