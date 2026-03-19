<?php

require_once __DIR__ . '/WPWC_TestCase.php';
require_once __DIR__ . '/WPWC_RedirectException.php';

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

/**
 * Tests that guarantee the plugin works correctly both when WPML is absent
 * and when it is active.
 *
 * WPML is detected at runtime via the ICL_LANGUAGE_CODE constant.
 * Tests that define it are run in an isolated process to avoid polluting the
 * constant pool for the rest of the suite.
 */
class WpmlTest extends WPWC_TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();

    Functions\stubs([
      'add_action'       => true,
      'add_filter'       => true,
      'is_admin'         => true,
      'add_submenu_page' => true,
      'admin_url'        => function ($path = '') {
        return 'https://example.com/wp-admin/' . $path;
      },
    ]);

    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-options.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-view.php';
  }

  private function view(): WP_WhatsApp_Multi_Chat_View
  {
    return WP_WhatsApp_Multi_Chat_View::get_instance();
  }

  private function callPrivate(object $obj, string $method, mixed ...$args): mixed
  {
    return (new ReflectionMethod($obj, $method))->invoke($obj, ...$args);
  }

  // ===========================================================================
  // maybe_translate_post_ids — WITHOUT WPML (ICL_LANGUAGE_CODE not defined)
  // ===========================================================================

  public function test_translate_post_ids_without_wpml_returns_ids_unchanged(): void
  {
    $ids = [10, 20, 30];

    $result = $this->callPrivate($this->view(), 'maybe_translate_post_ids', $ids);

    $this->assertSame($ids, $result);
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_translate_post_ids_without_wpml_does_not_apply_wpml_object_id_filter(): void
  {
    Filters\expectApplied('wpml_object_id')->never();

    $this->callPrivate($this->view(), 'maybe_translate_post_ids', [1, 2]);
  }

  public function test_translate_post_ids_without_wpml_empty_array_returns_empty(): void
  {
    $result = $this->callPrivate($this->view(), 'maybe_translate_post_ids', []);

    $this->assertSame([], $result);
  }

  // ===========================================================================
  // maybe_translate_appearance — WITHOUT WPML
  // ===========================================================================

  public function test_translate_appearance_without_wpml_returns_appearance_unchanged(): void
  {
    $appearance = [
      'button_text'    => 'Chat',
      'tooltip_text'   => 'Hello',
      'panel_title'    => 'WhatsApp',
      'panel_subtitle' => 'Subtitle',
      'cta_text'       => 'Start',
      'empty_text'     => 'No agents',
      'footer_text'    => 'Footer',
    ];

    $result = $this->callPrivate($this->view(), 'maybe_translate_appearance', $appearance);

    $this->assertSame($appearance, $result);
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_translate_appearance_without_wpml_does_not_apply_wpml_translate_filter(): void
  {
    Filters\expectApplied('wpml_translate_single_string')->never();

    $appearance = ['button_text' => 'Chat'];
    $this->callPrivate($this->view(), 'maybe_translate_appearance', $appearance);
  }

  // ===========================================================================
  // get_accounts — WITHOUT WPML
  // ===========================================================================

  public function test_get_accounts_without_wpml_returns_posts_normally(): void
  {
    $mock_post = new stdClass();
    $mock_post->ID = 42;

    Functions\expect('get_posts')->once()->andReturn([$mock_post]);
    Functions\expect('get_post_meta')->andReturn('');
    Functions\expect('get_the_post_thumbnail_url')->andReturn(false);

    $result = $this->callPrivate($this->view(), 'get_accounts');

    $this->assertCount(1, $result);
    $this->assertSame(42, $result[0]['id']);
  }

  public function test_get_accounts_without_wpml_does_not_switch_language_when_posts_are_empty(): void
  {
    Actions\expectDone('wpml_switch_language')->never();

    Functions\expect('get_posts')->once()->andReturn([]);

    $result = $this->callPrivate($this->view(), 'get_accounts');

    $this->assertSame([], $result);
  }

  // ===========================================================================
  // save_appearance — WITHOUT WPML
  // ===========================================================================

  public function test_save_appearance_without_wpml_still_persists_option(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
      },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $_POST = [
      'button_text'    => 'Chat with us',
      'position_corner' => 'bottom-right',
      'button_style'   => 'icon-only',
      'button_size'    => 'medium',
      'animation'      => 'none',
      'color_bg'       => '#25d366',
      'color_fg'       => '#ffffff',
      'offset_x'       => '20',
      'offset_y'       => '20',
    ];

    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // expected
    }

    $_POST = [];

    $this->assertNotNull($saved);
    $this->assertSame('Chat with us', $saved['button_text']);
  }

  // ===========================================================================
  // maybe_translate_post_ids — WITH WPML active
  // ===========================================================================

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_translate_post_ids_with_wpml_calls_wpml_object_id_for_each_id(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    Functions\expect('get_post_type')
      ->twice()
      ->andReturn('post');

    Filters\expectApplied('wpml_object_id')
      ->twice()
      ->andReturnUsing(function ($id) {
        return $id + 100; // simulated translated ID
      });

    $result = $this->callPrivate($this->view(), 'maybe_translate_post_ids', [1, 2]);

    $this->assertSame([101, 102], $result);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_translate_post_ids_with_wpml_empty_array_skips_filter(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    Filters\expectApplied('wpml_object_id')->never();

    $result = $this->callPrivate($this->view(), 'maybe_translate_post_ids', []);

    $this->assertSame([], $result);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_translate_post_ids_with_wpml_uses_post_as_default_type_when_unknown(): void
  {
    define('ICL_LANGUAGE_CODE', 'es');

    // get_post_type returns false for unknown posts; code falls back to 'post'
    Functions\expect('get_post_type')->once()->andReturn(false);

    Filters\expectApplied('wpml_object_id')
      ->once()
      ->with(\Mockery::any(), 'post', true)
      ->andReturnFirstArg();

    $this->callPrivate($this->view(), 'maybe_translate_post_ids', [99]);
  }

  // ===========================================================================
  // maybe_translate_appearance — WITH WPML active
  // ===========================================================================

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_translate_appearance_with_wpml_translates_non_empty_fields(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    $appearance = [
      'button_text'    => 'Chat',
      'tooltip_text'   => '',       // empty – must NOT be translated
      'panel_title'    => 'WhatsApp',
      'panel_subtitle' => '',
      'cta_text'       => 'Start',
      'empty_text'     => '',
      'footer_text'    => 'Footer',
    ];

    // Non-empty fields: button_text, panel_title, cta_text, footer_text = 4 calls
    Filters\expectApplied('wpml_translate_single_string')
      ->times(4)
      ->andReturnUsing(function ($value) {
        return $value . '_fr';
      });

    $result = $this->callPrivate($this->view(), 'maybe_translate_appearance', $appearance);

    $this->assertSame('Chat_fr',      $result['button_text']);
    $this->assertSame('WhatsApp_fr',  $result['panel_title']);
    $this->assertSame('Start_fr',     $result['cta_text']);
    $this->assertSame('Footer_fr',    $result['footer_text']);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_translate_appearance_with_wpml_skips_empty_fields(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    $appearance = [
      'button_text'    => '',
      'tooltip_text'   => '',
      'panel_title'    => '',
      'panel_subtitle' => '',
      'cta_text'       => '',
      'empty_text'     => '',
      'footer_text'    => '',
    ];

    Filters\expectApplied('wpml_translate_single_string')->never();

    $result = $this->callPrivate($this->view(), 'maybe_translate_appearance', $appearance);

    $this->assertSame($appearance, $result);
  }

  // ===========================================================================
  // get_accounts — WITH WPML active
  // ===========================================================================

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_get_accounts_with_wpml_falls_back_to_default_language_when_empty(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    $mock_post = new stdClass();
    $mock_post->ID = 7;

    // First call (current language 'fr') returns nothing; second call (default 'en') returns a post
    Functions\expect('get_posts')
      ->twice()
      ->andReturnValues([[], [$mock_post]]);

    Filters\expectApplied('wpml_default_language')
      ->once()
      ->andReturn('en');

    Actions\expectDone('wpml_switch_language')->twice();

    Functions\expect('get_post_meta')->andReturn('');
    Functions\expect('get_the_post_thumbnail_url')->andReturn(false);

    $result = $this->callPrivate($this->view(), 'get_accounts');

    $this->assertCount(1, $result);
    $this->assertSame(7, $result[0]['id']);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_get_accounts_with_wpml_no_fallback_when_posts_exist(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    $mock_post = new stdClass();
    $mock_post->ID = 5;

    Functions\expect('get_posts')->once()->andReturn([$mock_post]);

    // Language switch must never be triggered when posts already exist
    Actions\expectDone('wpml_switch_language')->never();

    Functions\expect('get_post_meta')->andReturn('');
    Functions\expect('get_the_post_thumbnail_url')->andReturn(false);

    $result = $this->callPrivate($this->view(), 'get_accounts');

    $this->assertCount(1, $result);
  }

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  public function test_get_accounts_with_wpml_no_fallback_when_already_in_default_language(): void
  {
    define('ICL_LANGUAGE_CODE', 'en');

    Functions\expect('get_posts')->once()->andReturn([]);

    Filters\expectApplied('wpml_default_language')
      ->once()
      ->andReturn('en'); // same as current language

    // No switch should happen because we are already in the default language
    Actions\expectDone('wpml_switch_language')->never();

    $result = $this->callPrivate($this->view(), 'get_accounts');

    $this->assertSame([], $result);
  }

  // ===========================================================================
  // save_appearance — WITH WPML active
  // ===========================================================================

  #[PHPUnit\Framework\Attributes\RunInSeparateProcess]
  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_save_appearance_with_wpml_registers_non_empty_text_strings(): void
  {
    define('ICL_LANGUAGE_CODE', 'fr');

    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null;
      },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('update_option')->once();

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    // Only the three non-empty text fields should be registered
    Actions\expectDone('wpml_register_single_string')->times(3);

    $_POST = [
      'button_text'    => 'Chat',       // registered
      'panel_title'    => 'WhatsApp',   // registered
      'footer_text'    => 'Footer',     // registered
      'tooltip_text'   => '',           // empty – skipped
      'panel_subtitle' => '',
      'cta_text'       => '',
      'empty_text'     => '',
      'position_corner' => 'bottom-right',
      'button_style'   => 'icon-only',
      'button_size'    => 'medium',
      'animation'      => 'none',
      'color_bg'       => '#25d366',
      'color_fg'       => '#ffffff',
      'offset_x'       => '20',
      'offset_y'       => '20',
    ];

    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // expected
    }

    $_POST = [];
  }
}
