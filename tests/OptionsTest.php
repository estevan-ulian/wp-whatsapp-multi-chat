<?php

require_once __DIR__ . '/WPWC_TestCase.php';
require_once __DIR__ . '/WPWC_RedirectException.php';

use Brain\Monkey\Functions;

class OptionsTest extends WPWC_TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();

    Functions\stubs([
      'add_action'   => true,
      'add_filter'   => true,
      'is_admin'     => true,
      'admin_url'    => function ($path = '') { return 'https://example.com/wp-admin/' . $path; },
      'add_submenu_page' => true,
    ]);

    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-options.php';
  }

  public function test_option_key_constant(): void
  {
    $this->assertSame('wpwc_display_conditions', WP_WhatsApp_Multi_Chat_Options::OPTION_KEY);
  }

  public function test_appearance_option_key_constant(): void
  {
    $this->assertSame('wpwc_appearance', WP_WhatsApp_Multi_Chat_Options::APPEARANCE_OPTION_KEY);
  }

  public function test_appearance_defaults_returns_all_keys(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Options::appearance_defaults();

    $expected_keys = [
      'position_corner', 'offset_x', 'offset_y',
      'button_style', 'button_text', 'button_size',
      'color_bg', 'color_fg', 'animation',
      'tooltip_text', 'appear_delay',
      'hide_mobile', 'hide_desktop',
      'panel_title', 'panel_subtitle',
      'cta_text', 'empty_text', 'footer_text',
      'status_online', 'status_offline', 'status_back',
      'status_today', 'status_tomorrow', 'offline_subtitle',
    ];

    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $defaults, "Missing default key: $key");
    }
  }

  public function test_appearance_defaults_position_corner(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Options::appearance_defaults();
    $this->assertSame('bottom-right', $defaults['position_corner']);
  }

  public function test_appearance_defaults_colors(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Options::appearance_defaults();
    $this->assertSame('#25d366', $defaults['color_bg']);
    $this->assertSame('#ffffff', $defaults['color_fg']);
  }

  public function test_appearance_defaults_numeric_values(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Options::appearance_defaults();
    $this->assertSame(20, $defaults['offset_x']);
    $this->assertSame(20, $defaults['offset_y']);
    $this->assertSame(0, $defaults['appear_delay']);
    $this->assertSame(0, $defaults['hide_mobile']);
    $this->assertSame(0, $defaults['hide_desktop']);
  }

  public function test_save_display_conditions_blocks_unauthorized(): void
  {
    Functions\expect('check_admin_referer')->once();

    Functions\expect('current_user_can')
      ->with('manage_options')
      ->andReturn(false);

    Functions\expect('wp_die')->once()->andReturnUsing(function () {
      throw new \RuntimeException('wp_die called');
    });

    Functions\expect('update_option')->never();

    $this->expectException(\RuntimeException::class);

    $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
    $instance->save_display_conditions();
  }

  public function test_save_display_conditions_defaults_to_manual(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key' => function ($v) { return $v; },
      'wp_unslash'   => function ($v) { return $v; },
      'absint'       => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    // No display_mode in POST → should default to 'manual'
    $_POST = [];

    try {
      $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
      $instance->save_display_conditions();
    } catch (WPWC_RedirectException $e) {
      // Expected – prevents exit from running
    }

    $this->assertSame('manual', $saved['display_mode']);
    $this->assertSame(0, $saved['show_front_page']);
    $this->assertSame([], $saved['post_types']);
    $this->assertSame([], $saved['archives']);
    $this->assertSame([], $saved['include_posts']);
    $this->assertSame([], $saved['exclude_posts']);

    $_POST = [];
  }

  public function test_save_display_conditions_saves_everywhere(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key' => function ($v) { return $v; },
      'wp_unslash'   => function ($v) { return $v; },
      'absint'       => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    $_POST = [
      'display_mode'    => 'everywhere',
      'show_front_page' => '1',
      'post_types'      => ['post', 'page'],
      'include_posts'   => ['10', '20'],
      'exclude_posts'   => ['5'],
    ];

    try {
      $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
      $instance->save_display_conditions();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame('everywhere', $saved['display_mode']);
    $this->assertSame(1, $saved['show_front_page']);
    $this->assertSame(['post', 'page'], $saved['post_types']);
    $this->assertSame([10, 20], $saved['include_posts']);
    $this->assertSame([5], $saved['exclude_posts']);

    $_POST = [];
  }

  public function test_save_appearance_validates_corner(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null; },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    // Invalid corner value should fall back to 'bottom-right'
    $_POST = [
      'position_corner' => 'invalid-corner',
      'button_style'    => 'icon-only',
      'button_size'     => 'medium',
      'animation'       => 'none',
      'color_bg'        => '#25d366',
      'color_fg'        => '#ffffff',
      'offset_x'        => '20',
      'offset_y'        => '20',
    ];

    try {
      $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
      $instance->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame('bottom-right', $saved['position_corner']);

    $_POST = [];
  }

  public function test_save_appearance_validates_color_fallback(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null; },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    // Invalid colors should fall back to defaults
    $_POST = [
      'position_corner' => 'bottom-right',
      'button_style'    => 'icon-only',
      'button_size'     => 'medium',
      'animation'       => 'none',
      'color_bg'        => 'not-a-color',
      'color_fg'        => 'invalid',
      'offset_x'        => '20',
      'offset_y'        => '20',
    ];

    try {
      $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
      $instance->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame('#25d366', $saved['color_bg']);
    $this->assertSame('#ffffff', $saved['color_fg']);

    $_POST = [];
  }

  public function test_save_appearance_clamps_offset(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);

    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : null; },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    Functions\expect('wp_safe_redirect')
      ->once()
      ->andReturnUsing(function ($url) {
        throw new WPWC_RedirectException($url);
      });

    $saved = null;
    Functions\expect('update_option')
      ->once()
      ->andReturnUsing(function ($key, $value) use (&$saved) {
        $saved = $value;
      });

    $_POST = [
      'position_corner' => 'bottom-right',
      'button_style'    => 'icon-only',
      'button_size'     => 'medium',
      'animation'       => 'none',
      'color_bg'        => '#25d366',
      'color_fg'        => '#ffffff',
      'offset_x'        => '999',  // Over the max of 200
      'offset_y'        => '-50',  // Negative → clamped to 0
    ];

    try {
      $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
      $instance->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame(200, $saved['offset_x']);
    $this->assertSame(50, $saved['offset_y']);  // absint('-50') = 50, clamped to [0,200]

    $_POST = [];
  }

  public function test_save_appearance_blocks_unauthorized(): void
  {
    Functions\expect('check_admin_referer')->once();

    Functions\expect('current_user_can')
      ->with('manage_options')
      ->andReturn(false);

    Functions\expect('wp_die')->once()->andReturnUsing(function () {
      throw new \RuntimeException('wp_die called');
    });

    Functions\expect('update_option')->never();

    $this->expectException(\RuntimeException::class);

    $instance = WP_WhatsApp_Multi_Chat_Options::get_instance();
    $instance->save_appearance();
  }
  /* ── Business hours ─────────────────────────────────────────────── */

  private function stub_hours_save_environment(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);
    Functions\stubs([
      'wp_unslash'          => function ($v) { return $v; },
      'get_option'          => [],
      'get_current_user_id' => 7,
    ]);
  }

  public function test_save_hours_blocks_unauthorized(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->with('manage_options')->andReturn(false);
    Functions\expect('wp_die')->once()->andReturnUsing(function () {
      throw new \RuntimeException('wp_die called');
    });
    Functions\expect('update_option')->never();

    $this->expectException(\RuntimeException::class);

    WP_WhatsApp_Multi_Chat_Options::get_instance()->save_hours();
  }

  public function test_save_hours_saves_sanitized_week(): void
  {
    $this->stub_hours_save_environment();

    $saved = null;
    Functions\expect('update_option')->once()->andReturnUsing(function ($key, $value) use (&$saved) {
      $this->assertSame('wpwc_hours', $key);
      $saved = $value;
    });
    Functions\expect('set_transient')->never();

    $redirect = null;
    Functions\expect('wp_safe_redirect')->once()->andReturnUsing(function ($url) use (&$redirect) {
      $redirect = $url;
      throw new WPWC_RedirectException($url);
    });

    $_POST = ['wpwc_hours' => ['mon' => ['enabled' => '1', 'open' => '07:00', 'close' => '13:00']]];

    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->save_hours();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame(['enabled' => 1, 'open' => '07:00', 'close' => '13:00'], $saved['mon']);
    $this->assertStringContainsString('tab=hours&updated=1', $redirect);

    $_POST = [];
  }

  public function test_save_hours_stores_errors_for_rejected_days(): void
  {
    $this->stub_hours_save_environment();

    $saved = null;
    Functions\expect('update_option')->once()->andReturnUsing(function ($key, $value) use (&$saved) {
      $saved = $value;
    });

    $stored = null;
    Functions\expect('set_transient')->once()->andReturnUsing(function ($key, $value) use (&$stored) {
      $this->assertSame('wpwc_hours_errors_7', $key);
      $stored = $value;
    });

    Functions\expect('wp_safe_redirect')->once()->andReturnUsing(function ($url) {
      throw new WPWC_RedirectException($url);
    });

    $_POST = ['wpwc_hours' => ['mon' => ['enabled' => '1', 'open' => '18:00', 'close' => '08:00']]];

    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->save_hours();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame(['mon' => 'close_before_open'], $stored);
    $this->assertSame(WP_WhatsApp_Multi_Chat_Hours::defaults()['mon'], $saved['mon']);

    $_POST = [];
  }

  public function test_reset_hours_deletes_option(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);
    Functions\stubs([
      'wp_unslash'   => function ($v) { return $v; },
      'sanitize_key' => function ($v) { return $v; },
    ]);

    Functions\expect('delete_option')->once()->with('wpwc_hours');
    Functions\expect('wp_safe_redirect')->once()->andReturnUsing(function ($url) {
      throw new WPWC_RedirectException($url);
    });

    $_POST = ['wpwc_reset' => ['hours']];

    $redirect = null;
    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->reset_all();
    } catch (WPWC_RedirectException $e) {
      $redirect = $e->url;
    }

    $this->assertStringContainsString('updated=reset', $redirect);

    $_POST = [];
  }
  public function test_appearance_defaults_status_texts(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Options::appearance_defaults();

    $this->assertSame('Online agora', $defaults['status_online']);
    $this->assertSame('Offline', $defaults['status_offline']);
    $this->assertSame('Volta {dia} às {hora}', $defaults['status_back']);
    $this->assertSame('hoje', $defaults['status_today']);
    $this->assertSame('amanhã', $defaults['status_tomorrow']);
    $this->assertSame('Fora do horário de atendimento. Responderemos assim que voltarmos.', $defaults['offline_subtitle']);
  }

  public function test_save_appearance_saves_status_texts(): void
  {
    Functions\expect('check_admin_referer')->once();
    Functions\expect('current_user_can')->andReturn(true);
    Functions\stubs([
      'sanitize_key'        => function ($v) { return strtolower(trim($v)); },
      'sanitize_text_field' => function ($v) { return $v; },
      'sanitize_hex_color'  => function ($v) { return $v; },
      'wp_unslash'          => function ($v) { return $v; },
      'absint'              => function ($v) { return abs(intval($v)); },
    ]);

    $saved = null;
    Functions\expect('update_option')->once()->andReturnUsing(function ($key, $value) use (&$saved) {
      $saved = $value;
    });
    Functions\expect('wp_safe_redirect')->once()->andReturnUsing(function ($url) {
      throw new WPWC_RedirectException($url);
    });

    $_POST = [
      'status_back'      => 'Back {dia} at {hora}',
      'offline_subtitle' => '',
    ];

    try {
      WP_WhatsApp_Multi_Chat_Options::get_instance()->save_appearance();
    } catch (WPWC_RedirectException $e) {
      // Expected
    }

    $this->assertSame('Back {dia} at {hora}', $saved['status_back']);
    $this->assertSame('', $saved['offline_subtitle']);

    $_POST = [];
  }
}
