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
    $this->assertSame('https://example.com/image.jpg', $result[0]['thumbnail']);
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

  public function test_should_display_everywhere_mode(): void
  {
    Functions\expect('get_option')
      ->andReturn([
        'display_mode'    => 'everywhere',
        'exclude_posts'   => [],
        'include_posts'   => [],
        'show_front_page' => 0,
        'post_types'      => [],
        'archives'        => [],
      ]);

    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) {
        return array_merge($defaults, $args);
      },
      'is_singular' => false,
      'get_the_ID'  => 0,
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    // Reset cache via reflection
    $prop = new ReflectionProperty($instance, 'display_cached');
    $prop->setValue($instance, null);

    $method = new ReflectionMethod($instance, 'should_display');

    $this->assertTrue($method->invoke($instance));
  }

  public function test_should_display_exclusion_wins_over_everywhere(): void
  {
    Functions\expect('get_option')
      ->andReturn([
        'display_mode'    => 'everywhere',
        'exclude_posts'   => [42],
        'include_posts'   => [],
        'show_front_page' => 0,
        'post_types'      => [],
        'archives'        => [],
      ]);

    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) {
        return array_merge($defaults, $args);
      },
      'is_singular' => true,
      'get_the_ID'  => 42,
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $prop = new ReflectionProperty($instance, 'display_cached');
    $prop->setValue($instance, null);

    $method = new ReflectionMethod($instance, 'should_display');

    $this->assertFalse($method->invoke($instance));
  }

  public function test_should_display_include_overrides_manual(): void
  {
    Functions\expect('get_option')
      ->andReturn([
        'display_mode'    => 'manual',
        'exclude_posts'   => [],
        'include_posts'   => [99],
        'show_front_page' => 0,
        'post_types'      => [],
        'archives'        => [],
      ]);

    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) {
        return array_merge($defaults, $args);
      },
      'is_singular' => true,
      'get_the_ID'  => 99,
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $prop = new ReflectionProperty($instance, 'display_cached');
    $prop->setValue($instance, null);

    $method = new ReflectionMethod($instance, 'should_display');

    $this->assertTrue($method->invoke($instance));
  }

  public function test_should_display_manual_no_match_returns_false(): void
  {
    Functions\expect('get_option')
      ->andReturn([
        'display_mode'    => 'manual',
        'exclude_posts'   => [],
        'include_posts'   => [],
        'show_front_page' => 0,
        'post_types'      => [],
        'archives'        => [],
      ]);

    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) {
        return array_merge($defaults, $args);
      },
      'is_singular'   => false,
      'get_the_ID'    => 0,
      'is_front_page' => false,
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $prop = new ReflectionProperty($instance, 'display_cached');
    $prop->setValue($instance, null);

    $method = new ReflectionMethod($instance, 'should_display');

    $this->assertFalse($method->invoke($instance));
  }

  #[PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
  public function test_should_display_caches_result(): void
  {
    Functions\expect('get_option')
      ->once()  // Should only be called once due to cache
      ->andReturn([
        'display_mode'    => 'everywhere',
        'exclude_posts'   => [],
        'include_posts'   => [],
        'show_front_page' => 0,
        'post_types'      => [],
        'archives'        => [],
      ]);

    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) {
        return array_merge($defaults, $args);
      },
      'is_singular' => false,
      'get_the_ID'  => 0,
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();

    $prop = new ReflectionProperty($instance, 'display_cached');
    $prop->setValue($instance, null);

    $method = new ReflectionMethod($instance, 'should_display');

    // Call twice
    $method->invoke($instance);
    $method->invoke($instance);
  }

  /* ── Helper ─────────────────────────────────────────── */

  private function make_display_instance(array $conditions): WP_WhatsApp_Multi_Chat_View
  {
    Functions\expect('get_option')->andReturn($conditions);
    Functions\stubs([
      'wp_parse_args' => function ($args, $defaults) { return array_merge($defaults, $args); },
    ]);

    $instance = WP_WhatsApp_Multi_Chat_View::get_instance();
    (new ReflectionProperty($instance, 'display_cached'))->setValue($instance, null);
    return $instance;
  }

  private function invoke_should_display(WP_WhatsApp_Multi_Chat_View $instance): bool
  {
    return (new ReflectionMethod($instance, 'should_display'))->invoke($instance);
  }

  /* ── show_front_page ─────────────────────────────────── */

  public function test_should_display_front_page_when_enabled(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'    => 'manual',
      'show_front_page' => 1,
      'post_types'      => [],
      'archives'        => [],
      'include_posts'   => [],
      'exclude_posts'   => [],
    ]);

    Functions\stubs([
      'is_singular'   => false,
      'get_the_ID'    => 0,
      'is_front_page' => true,
    ]);

    $this->assertTrue($this->invoke_should_display($instance));
  }

  public function test_should_display_front_page_when_disabled(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'    => 'manual',
      'show_front_page' => 0,
      'post_types'      => [],
      'archives'        => [],
      'include_posts'   => [],
      'exclude_posts'   => [],
    ]);

    Functions\stubs([
      'is_singular'   => false,
      'get_the_ID'    => 0,
      'is_front_page' => true,
    ]);

    $this->assertFalse($this->invoke_should_display($instance));
  }

  /* ── post_types ──────────────────────────────────────── */

  public function test_should_display_matching_post_type(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'  => 'manual',
      'show_front_page' => 0,
      'post_types'    => ['post', 'product'],
      'archives'      => [],
      'include_posts' => [],
      'exclude_posts' => [],
    ]);

    Functions\stubs([
      'get_the_ID'    => 0,
      'is_front_page' => false,
    ]);

    Functions\when('is_singular')->alias(function ($types = '') {
      // Called with no args → false; called with array containing 'post' → true
      return !empty($types) && in_array('post', (array) $types, true);
    });

    $this->assertTrue($this->invoke_should_display($instance));
  }

  public function test_should_display_non_matching_post_type(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'  => 'manual',
      'show_front_page' => 0,
      'post_types'    => ['product'],
      'archives'      => [],
      'include_posts' => [],
      'exclude_posts' => [],
    ]);

    Functions\stubs([
      'get_the_ID'    => 0,
      'is_front_page' => false,
    ]);

    Functions\when('is_singular')->alias(function ($types = '') {
      // 'post' is not in the post_types list
      return false;
    });

    $this->assertFalse($this->invoke_should_display($instance));
  }

  /* ── archives ────────────────────────────────────────── */

  #[PHPUnit\Framework\Attributes\DataProvider('archiveProvider')]
  public function test_should_display_archive_types(string $archive_key, string $wp_function): void
  {
    $instance = $this->make_display_instance([
      'display_mode'    => 'manual',
      'show_front_page' => 0,
      'post_types'      => [],
      'archives'        => [$archive_key],
      'include_posts'   => [],
      'exclude_posts'   => [],
    ]);

    Functions\when('is_singular')->justReturn(false);
    Functions\stubs([
      'get_the_ID'    => 0,
      'is_front_page' => false,
    ]);

    // Make the matching archive function return true, all others false
    foreach (['is_category', 'is_tag', 'is_author', 'is_date', 'is_search', 'is_404'] as $fn) {
      Functions\when($fn)->justReturn($fn === $wp_function);
    }

    $this->assertTrue($this->invoke_should_display($instance));
  }

  public static function archiveProvider(): array
  {
    return [
      'category' => ['category', 'is_category'],
      'tag'      => ['tag',      'is_tag'],
      'author'   => ['author',   'is_author'],
      'date'     => ['date',     'is_date'],
      'search'   => ['search',   'is_search'],
      '404'      => ['404',      'is_404'],
    ];
  }

  public function test_should_display_archive_not_active_returns_false(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'    => 'manual',
      'show_front_page' => 0,
      'post_types'      => [],
      'archives'        => ['category'],
      'include_posts'   => [],
      'exclude_posts'   => [],
    ]);

    Functions\when('is_singular')->justReturn(false);
    Functions\stubs([
      'get_the_ID'    => 0,
      'is_front_page' => false,
      'is_category'   => false,
    ]);

    $this->assertFalse($this->invoke_should_display($instance));
  }

  /* ── custom taxonomy archive ─────────────────────────── */

  public function test_should_display_custom_taxonomy_archive(): void
  {
    $instance = $this->make_display_instance([
      'display_mode'    => 'manual',
      'show_front_page' => 0,
      'post_types'      => [],
      'archives'        => ['tax_genre'],
      'include_posts'   => [],
      'exclude_posts'   => [],
    ]);

    Functions\when('is_singular')->justReturn(false);
    Functions\stubs([
      'get_the_ID'    => 0,
      'is_front_page' => false,
    ]);

    Functions\when('is_tax')->alias(function ($taxonomy) {
      return $taxonomy === 'genre';
    });

    $this->assertTrue($this->invoke_should_display($instance));
  }

  /* ── exclusion overrides everywhere ─────────────────── */

  public function test_exclusion_wins_over_include(): void
  {
    // A post that is both in include and exclude → exclude wins
    $instance = $this->make_display_instance([
      'display_mode'    => 'everywhere',
      'show_front_page' => 0,
      'post_types'      => [],
      'archives'        => [],
      'include_posts'   => [5],
      'exclude_posts'   => [5],
    ]);

    Functions\stubs([
      'is_singular' => true,
      'get_the_ID'  => 5,
    ]);

    $this->assertFalse($this->invoke_should_display($instance));
  }
}

