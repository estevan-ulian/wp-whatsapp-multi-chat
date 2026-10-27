<?php

require_once __DIR__ . '/WPWC_TestCase.php';

use Brain\Monkey\Functions;

class ViewTest extends WPWC_TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Functions\stubTranslationFunctions();
    Functions\stubEscapeFunctions();

    Functions\stubs([
      'add_action'  => true,
      'add_filter'  => true,
      'is_admin'    => true,
      'add_submenu_page' => true,
      'admin_url'   => function ($path = '') { return 'https://example.com/wp-admin/' . $path; },
    ]);

    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-accounts.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-options.php';
    require_once WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'includes/wp-whatsapp-multi-chat-view.php';
  }

  public function test_render_frontend_chat_contains_wrapper_div(): void
  {
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $html = $instance->render_frontend_chat();

    $this->assertStringContainsString('wp-whatsapp-multi-chat-wrapper', $html);
    $this->assertStringContainsString('<div', $html);
    $this->assertStringContainsString('id=', $html);
  }

  public function test_get_accounts_returns_array(): void
  {
    Functions\stubs([
      'get_posts'              => [],
      'get_post_meta'          => '',
      'get_the_post_thumbnail_url' => false,
    ]);

    // Use reflection to access private method
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $method = new ReflectionMethod($instance, 'get_accounts');

    $result = $method->invoke($instance);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  public function test_get_accounts_returns_proper_structure(): void
  {
    $mock_post = new stdClass();
    $mock_post->ID = 1;
    $mock_post->post_type = 'wpwc_account';

    Functions\expect('get_posts')
      ->once()
      ->andReturn([$mock_post]);

    Functions\expect('get_post_meta')
      ->andReturnUsing(function ($id, $key, $single) {
        $map = [
          '_wpwc_name'               => 'Support',
          '_wpwc_number'             => '5511912341234',
          '_wpwc_predefined_message' => 'Hello',
          '_wpwc_cargo'              => 'Suporte',
        ];
        return $map[$key] ?? '';
      });

    Functions\expect('get_the_post_thumbnail_url')
      ->andReturn('https://example.com/image.jpg');

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $method = new ReflectionMethod($instance, 'get_accounts');

    $result = $method->invoke($instance);

    $this->assertCount(1, $result);
    $this->assertSame(1, $result[0]['id']);
    $this->assertSame('Support', $result[0]['name']);
    $this->assertSame('5511912341234', $result[0]['number']);
    $this->assertSame('Hello', $result[0]['predefined_message']);
    $this->assertSame('Suporte', $result[0]['tagline']);
    $this->assertSame('https://example.com/image.jpg', $result[0]['thumbnail']);
    // Accounts saved before business hours existed stay always online.
    $this->assertSame(['mode' => 'always_online'], $result[0]['availability']);
  }

  public function test_get_accounts_handles_missing_thumbnail(): void
  {
    $mock_post = new stdClass();
    $mock_post->ID = 2;
    $mock_post->post_type = 'wpwc_account';

    Functions\expect('get_posts')->once()->andReturn([$mock_post]);
    Functions\expect('get_post_meta')->andReturn('');
    Functions\expect('get_the_post_thumbnail_url')->andReturn(false);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $method = new ReflectionMethod($instance, 'get_accounts');

    $result = $method->invoke($instance);

    $this->assertSame('', $result[0]['thumbnail']);
  }

  /* ── Display rules ──────────────────────────────────── */

  // Todas as condicionais do WordPress começam falsas; cada teste liga só as que precisa.
  private function stub_conditionals(array $overrides = []): void
  {
    $defaults = [
      'is_front_page'         => false,
      'is_home'               => false,
      'is_archive'            => false,
      'is_post_type_archive'  => false,
      'is_author'             => false,
      'is_date'               => false,
      'is_category'           => false,
      'is_tag'                => false,
      'is_tax'                => false,
      'is_singular'           => false,
      'is_search'             => false,
      'is_404'                => false,
      'get_queried_object'    => null,
      'get_queried_object_id' => 0,
      'get_post_type'         => false,
      'get_query_var'         => '',
    ];
    foreach (array_merge($defaults, $overrides) as $fn => $value) {
      Functions\when($fn)->justReturn($value);
    }
  }

  private function make_display_instance($stored): WP_WhatsApp_Multi_Chat_View
  {
    Functions\when('get_option')->justReturn($stored);
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    (new ReflectionProperty($instance, 'display_cached'))->setValue($instance, null);
    return $instance;
  }

  private function invoke_private(WP_WhatsApp_Multi_Chat_View $instance, string $method, ...$args)
  {
    return (new ReflectionMethod($instance, $method))->invoke($instance, ...$args);
  }

  // Sites que ainda têm a configuração antiga continuam com o mesmo resultado.
  public function test_should_display_uses_converted_legacy_option(): void
  {
    $this->stub_conditionals([
      'is_singular'           => true,
      'get_post_type'         => 'post',
      'get_queried_object_id' => 42,
    ]);
    $instance = $this->make_display_instance(['display_mode' => 'everywhere', 'exclude_posts' => [42]]);

    $this->assertFalse($this->invoke_private($instance, 'should_display'));
  }

  public function test_should_display_v2_hides_post_type_archive(): void
  {
    $this->stub_conditionals([
      'is_archive'           => true,
      'is_post_type_archive' => true,
      'get_query_var'        => 'cases',
    ]);
    $instance = $this->make_display_instance(['version' => 2, 'rules' => [
      ['action' => 'show', 'where' => 'entire_site', 'which' => '', 'item' => 0],
      ['action' => 'hide', 'where' => 'archive', 'which' => 'pta_cases', 'item' => 0],
    ]]);

    $this->assertFalse($this->invoke_private($instance, 'should_display'));
  }

  public function test_should_display_without_option_hides(): void
  {
    $this->stub_conditionals(['is_front_page' => true]);
    $instance = $this->make_display_instance(false);

    $this->assertFalse($this->invoke_private($instance, 'should_display'));
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_should_display_caches_result(): void
  {
    $this->stub_conditionals();
    Functions\expect('get_option')->once()->andReturn(['display_mode' => 'everywhere']);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    (new ReflectionProperty($instance, 'display_cached'))->setValue($instance, null);

    $this->invoke_private($instance, 'should_display');
    $this->invoke_private($instance, 'should_display');
  }

  // A página de posts só conta como "blog" quando é separada da página inicial.
  public function test_build_context_blog_only_when_separate(): void
  {
    $this->stub_conditionals(['is_home' => true]);
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $this->assertTrue($this->invoke_private($instance, 'build_context')['blog']);

    $this->stub_conditionals(['is_home' => true, 'is_front_page' => true]);
    $context = $this->invoke_private($instance, 'build_context');
    $this->assertFalse($context['blog']);
    $this->assertTrue($context['front_page']);
  }

  public function test_build_context_term_archive(): void
  {
    $term = (object) ['taxonomy' => 'category', 'term_id' => 7];
    $this->stub_conditionals(['is_archive' => true, 'is_category' => true, 'get_queried_object' => $term]);
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $context = $this->invoke_private($instance, 'build_context');

    $this->assertSame('category', $context['tax']);
    $this->assertSame(7, $context['term']);
  }

  public function test_build_context_post_type_archive_with_array_query_var(): void
  {
    $this->stub_conditionals(['is_archive' => true, 'is_post_type_archive' => true, 'get_query_var' => ['acos', 'cases']]);
    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $this->assertSame('acos', $this->invoke_private($instance, 'build_context')['pta']);
  }

  /* ── Business hours payload ─────────────────────────────────────── */

  public function test_get_accounts_includes_schedule_for_general_mode(): void
  {
    $mock_post = new stdClass();
    $mock_post->ID = 3;
    $mock_post->post_type = 'wpwc_account';

    $general = WP_WhatsApp_Multi_Chat_Hours::defaults();
    $general['sat'] = ['enabled' => 1, 'open' => '09:00', 'close' => '13:00'];

    Functions\expect('get_posts')->once()->andReturn([$mock_post]);
    Functions\when('get_post_meta')->alias(function ($id, $key, $single) {
      return $key === '_wpwc_availability_mode' ? 'general' : '';
    });
    Functions\when('get_option')->justReturn($general);
    Functions\when('get_the_post_thumbnail_url')->justReturn(false);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $result = (new ReflectionMethod($instance, 'get_accounts'))->invoke($instance);

    $this->assertSame(['mode' => 'schedule', 'hours' => $general], $result[0]['availability']);
  }

  public function test_get_weekday_abbrevs_uses_wp_locale(): void
  {
    $GLOBALS['wp_locale'] = new class {
      private $names = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
      public function get_weekday($n) { return $this->names[$n]; }
      public function get_weekday_abbrev($name) { return mb_substr($name, 0, 3); }
    };

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $result = (new ReflectionMethod($instance, 'get_weekday_abbrevs'))->invoke($instance);

    $this->assertSame(
      ['mon' => 'seg', 'tue' => 'ter', 'wed' => 'qua', 'thu' => 'qui', 'fri' => 'sex', 'sat' => 'sáb', 'sun' => 'dom'],
      $result
    );

    unset($GLOBALS['wp_locale']);
  }

  public function test_get_weekday_abbrevs_without_wp_locale_falls_back_to_english(): void
  {
    unset($GLOBALS['wp_locale']);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    $result = (new ReflectionMethod($instance, 'get_weekday_abbrevs'))->invoke($instance);

    $this->assertSame(
      ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'],
      $result
    );
  }

  public function test_enqueue_frontend_scripts_sends_site_timezone_and_weekdays(): void
  {
    Functions\stubs([
      'wp_enqueue_style'           => true,
      'wp_enqueue_script'          => true,
      'get_option'                 => [],
      'wp_parse_args'              => function ($args, $defaults) { return array_merge($defaults, (array) $args); },
      'get_posts'                  => [],
      'wp_timezone_string'         => 'America/Sao_Paulo',
    ]);

    $payload = null;
    Functions\expect('wp_localize_script')->once()->andReturnUsing(function ($handle, $name, $data) use (&$payload) {
      $payload = $data;
    });

    WP_WhatsApp_Multi_Chat_View::get_instance()->enqueue_frontend_scripts();

    $this->assertSame('America/Sao_Paulo', $payload['timezone']);
    $this->assertSame(WP_WhatsApp_Multi_Chat_Hours::DAYS, array_keys($payload['weekdays']));
  }
}
