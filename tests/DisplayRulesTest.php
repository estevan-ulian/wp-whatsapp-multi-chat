<?php

require_once __DIR__ . '/WPWC_TestCase.php';

use Brain\Monkey\Functions;

class DisplayRulesTest extends WPWC_TestCase
{
  private function rule(string $action, string $where, string $which = '', int $item = 0): array
  {
    return array('action' => $action, 'where' => $where, 'which' => $which, 'item' => $item);
  }

  private function ctx(array $overrides = array()): array
  {
    return array_merge(WP_WhatsApp_Multi_Chat_Display_Rules::empty_context(), $overrides);
  }

  private function decide(array $rules, array $ctx): bool
  {
    return WP_WhatsApp_Multi_Chat_Display_Rules::matches($rules, $ctx);
  }

  // Sem nenhuma regra o widget fica oculto, como numa instalação nova sem configuração.
  public function test_no_rules_hides(): void
  {
    $this->assertFalse($this->decide(array(), $this->ctx(array('front_page' => true))));
  }

  // Ocultar sempre vence Exibir, independentemente da ordem das linhas.
  public function test_hide_wins_over_show(): void
  {
    $rules = array(
      $this->rule('show', 'entire_site'),
      $this->rule('hide', 'singular', 'page', 42),
    );

    $this->assertFalse($this->decide($rules, $this->ctx(array('singular' => 'page', 'object' => 42))));
    $this->assertTrue($this->decide($rules, $this->ctx(array('singular' => 'page', 'object' => 7))));
    $this->assertFalse($this->decide(array_reverse($rules), $this->ctx(array('singular' => 'page', 'object' => 42))));
  }

  public function test_entire_site_matches_any_page(): void
  {
    $this->assertTrue($this->decide(array($this->rule('show', 'entire_site')), $this->ctx(array('not_found' => true))));
  }

  public function test_front_page(): void
  {
    $rules = array($this->rule('show', 'front_page'));
    $this->assertTrue($this->decide($rules, $this->ctx(array('front_page' => true))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('singular' => 'page', 'object' => 3))));
  }

  // "Todos os arquivos" cobre arquivos de termos, autores, datas e tipos, mas não busca, 404 nem a página de posts.
  public function test_all_archives_excludes_search_404_and_blog(): void
  {
    $rules = array($this->rule('show', 'archive', 'all'));

    $this->assertTrue($this->decide($rules, $this->ctx(array('archive' => true, 'tax' => 'category', 'term' => 3))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('search' => true))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('not_found' => true))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('blog' => true))));
  }

  public function test_blog(): void
  {
    $rules = array($this->rule('show', 'archive', 'blog'));
    $this->assertTrue($this->decide($rules, $this->ctx(array('blog' => true))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('archive' => true, 'author' => true))));
  }

  public function test_post_type_archive(): void
  {
    $rules = array($this->rule('show', 'archive', 'pta_cases'));
    $this->assertTrue($this->decide($rules, $this->ctx(array('archive' => true, 'pta' => 'cases'))));
    $this->assertFalse($this->decide($rules, $this->ctx(array('archive' => true, 'pta' => 'acos'))));
  }

  public function test_taxonomy_all_and_specific_term(): void
  {
    $all  = array($this->rule('show', 'archive', 'tax_category'));
    $five = array($this->rule('show', 'archive', 'tax_category', 5));

    $this->assertTrue($this->decide($all, $this->ctx(array('archive' => true, 'tax' => 'category', 'term' => 3))));
    $this->assertFalse($this->decide($all, $this->ctx(array('archive' => true, 'tax' => 'post_tag', 'term' => 3))));
    $this->assertTrue($this->decide($five, $this->ctx(array('archive' => true, 'tax' => 'category', 'term' => 5))));
    $this->assertFalse($this->decide($five, $this->ctx(array('archive' => true, 'tax' => 'category', 'term' => 3))));
  }

  public function test_author_and_date(): void
  {
    $this->assertTrue($this->decide(array($this->rule('show', 'archive', 'author')), $this->ctx(array('archive' => true, 'author' => true))));
    $this->assertFalse($this->decide(array($this->rule('show', 'archive', 'author')), $this->ctx(array('archive' => true, 'date' => true))));
    $this->assertTrue($this->decide(array($this->rule('show', 'archive', 'date')), $this->ctx(array('archive' => true, 'date' => true))));
  }

  public function test_singular_all_and_specific_item(): void
  {
    $all  = array($this->rule('show', 'singular', 'cases'));
    $item = array($this->rule('show', 'singular', 'cases', 8));

    $this->assertTrue($this->decide($all, $this->ctx(array('singular' => 'cases', 'object' => 3))));
    $this->assertFalse($this->decide($all, $this->ctx(array('singular' => 'post', 'object' => 3))));
    $this->assertTrue($this->decide($item, $this->ctx(array('singular' => 'cases', 'object' => 8))));
    $this->assertFalse($this->decide($item, $this->ctx(array('singular' => 'cases', 'object' => 3))));
  }

  public function test_search_and_404(): void
  {
    $this->assertTrue($this->decide(array($this->rule('show', 'search')), $this->ctx(array('search' => true))));
    $this->assertFalse($this->decide(array($this->rule('show', 'search')), $this->ctx(array('not_found' => true))));
    $this->assertTrue($this->decide(array($this->rule('show', '404')), $this->ctx(array('not_found' => true))));
  }

  // Uma página inicial estática é uma Página: a regra para Páginas também vale para ela.
  public function test_static_front_page_counts_as_page(): void
  {
    $rules = array(
      $this->rule('show', 'entire_site'),
      $this->rule('hide', 'singular', 'page'),
    );

    $this->assertFalse($this->decide($rules, $this->ctx(array('front_page' => true, 'singular' => 'page', 'object' => 2))));
  }

  // Quando a inicial é a lista de posts, ela não é "Página de posts (blog)" nem um arquivo.
  public function test_posts_front_page_is_not_blog_nor_archive(): void
  {
    $ctx = $this->ctx(array('front_page' => true));

    $this->assertFalse($this->decide(array($this->rule('show', 'archive', 'blog')), $ctx));
    $this->assertFalse($this->decide(array($this->rule('show', 'archive', 'all')), $ctx));
  }

  // Uma regra que aponta para um item apagado nunca corresponde, sem erro.
  public function test_missing_item_never_matches(): void
  {
    $this->assertFalse($this->decide(array($this->rule('show', 'singular', 'page', 999)), $this->ctx(array('singular' => 'page', 'object' => 2))));
  }

  public function test_unknown_where_never_matches(): void
  {
    $this->assertFalse($this->decide(array($this->rule('show', 'xyz')), $this->ctx(array('front_page' => true))));
  }

  // ── Conversão do formato antigo ────────────────────────────────

  private function legacy_manual(): array
  {
    return array(
      'display_mode'    => 'manual',
      'show_front_page' => 1,
      'post_types'      => array('post'),
      'archives'        => array('category', 'tag', 'author', 'date', 'search', '404', 'tax_steel'),
      'include_posts'   => array(10),
      'exclude_posts'   => array(5),
    );
  }

  public function test_from_stored_everywhere(): void
  {
    $this->assertSame(
      array($this->rule('show', 'entire_site')),
      WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(array('display_mode' => 'everywhere'))
    );
  }

  public function test_from_stored_manual_full(): void
  {
    Functions\when('get_post_type')->alias(function ($id) {
      return 10 === $id ? 'page' : 'post';
    });

    $this->assertSame(
      array(
        $this->rule('show', 'front_page'),
        $this->rule('show', 'singular', 'post'),
        $this->rule('show', 'archive', 'tax_category'),
        $this->rule('show', 'archive', 'tax_post_tag'),
        $this->rule('show', 'archive', 'author'),
        $this->rule('show', 'archive', 'date'),
        $this->rule('show', 'search'),
        $this->rule('show', '404'),
        $this->rule('show', 'archive', 'tax_steel'),
        $this->rule('show', 'singular', 'page', 10),
        $this->rule('hide', 'singular', 'post', 5),
      ),
      WP_WhatsApp_Multi_Chat_Display_Rules::from_stored($this->legacy_manual())
    );
  }

  public function test_from_stored_manual_empty_and_invalid(): void
  {
    $this->assertSame(array(), WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(array('display_mode' => 'manual')));
    $this->assertSame(array(), WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(false));
    $this->assertSame(array(), WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(''));
  }

  // Uma página específica que já foi apagada continua na lista, mas nunca corresponde.
  public function test_from_stored_deleted_page(): void
  {
    Functions\when('get_post_type')->justReturn(false);

    $this->assertSame(
      array($this->rule('show', 'singular', 'page', 77)),
      WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(array('display_mode' => 'manual', 'include_posts' => array(77)))
    );
  }

  public function test_from_stored_v2_passthrough(): void
  {
    $stored = array('version' => 2, 'rules' => array(
      array('action' => 'hide', 'where' => 'archive', 'which' => 'tax_category', 'item' => '5'),
    ));

    $this->assertSame(
      array($this->rule('hide', 'archive', 'tax_category', 5)),
      WP_WhatsApp_Multi_Chat_Display_Rules::from_stored($stored)
    );
  }

  public function test_to_stored(): void
  {
    $rules = array($this->rule('show', 'entire_site'));
    $this->assertSame(array('version' => 2, 'rules' => $rules), WP_WhatsApp_Multi_Chat_Display_Rules::to_stored($rules));
  }

  // A configuração convertida decide igual ao comportamento anterior do plugin.
  public function test_converted_legacy_config_keeps_decisions(): void
  {
    Functions\when('get_post_type')->alias(function ($id) {
      return 10 === $id ? 'page' : 'post';
    });

    $manual = WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(array(
      'display_mode'    => 'manual',
      'show_front_page' => 1,
      'post_types'      => array('cases'),
      'archives'        => array('category', 'search'),
      'include_posts'   => array(10),
      'exclude_posts'   => array(5),
    ));

    $this->assertTrue($this->decide($manual, $this->ctx(array('front_page' => true))));
    $this->assertTrue($this->decide($manual, $this->ctx(array('singular' => 'cases', 'object' => 3))));
    $this->assertFalse($this->decide($manual, $this->ctx(array('singular' => 'post', 'object' => 5))));
    $this->assertTrue($this->decide($manual, $this->ctx(array('singular' => 'page', 'object' => 10))));
    $this->assertFalse($this->decide($manual, $this->ctx(array('singular' => 'page', 'object' => 11))));
    $this->assertTrue($this->decide($manual, $this->ctx(array('archive' => true, 'tax' => 'category', 'term' => 2))));
    $this->assertFalse($this->decide($manual, $this->ctx(array('archive' => true, 'tax' => 'post_tag', 'term' => 2))));
    $this->assertTrue($this->decide($manual, $this->ctx(array('search' => true))));

    $everywhere = WP_WhatsApp_Multi_Chat_Display_Rules::from_stored(array('display_mode' => 'everywhere', 'exclude_posts' => array(5)));
    $this->assertFalse($this->decide($everywhere, $this->ctx(array('singular' => 'post', 'object' => 5))));
    $this->assertTrue($this->decide($everywhere, $this->ctx(array('not_found' => true))));
  }

  // ── Validação do formulário ────────────────────────────────────

  private function choices(): array
  {
    return array(
      'archive'  => array('all', 'blog', 'pta_cases', 'author', 'date', 'tax_category', 'tax_post_tag'),
      'singular' => array('post', 'page', 'cases'),
    );
  }

  private function raw(string $action, string $where, string $which = '', string $item = ''): array
  {
    return array('action' => $action, 'where' => $where, 'which' => $which, 'item' => $item);
  }

  public function test_sanitize_keeps_valid_rules(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Display_Rules::sanitize(array(
      $this->raw('show', 'entire_site'),
      $this->raw('hide', 'archive', 'pta_cases'),
      $this->raw('hide', 'archive', 'tax_category', '5'),
      $this->raw('show', 'singular', 'page', '12'),
      $this->raw('show', 'singular', 'cases', '0'),
      $this->raw('show', 'search'),
      $this->raw('show', '404'),
      $this->raw('show', 'front_page'),
    ), $this->choices());

    $this->assertSame(0, $result['discarded']);
    $this->assertSame(array(
      $this->rule('show', 'entire_site'),
      $this->rule('hide', 'archive', 'pta_cases'),
      $this->rule('hide', 'archive', 'tax_category', 5),
      $this->rule('show', 'singular', 'page', 12),
      $this->rule('show', 'singular', 'cases'),
      $this->rule('show', 'search'),
      $this->rule('show', '404'),
      $this->rule('show', 'front_page'),
    ), $result['rules']);
  }

  // Dados adulterados ou incompletos nunca são salvos; o admin é avisado da contagem.
  public function test_sanitize_discards_invalid_rows(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Display_Rules::sanitize(array(
      $this->raw('show', 'xyz'),
      $this->raw('maybe', 'entire_site'),
      $this->raw('show', 'archive'),
      $this->raw('show', 'archive', 'pta_inexistente'),
      $this->raw('show', 'singular', 'elementor_library'),
      $this->raw('show', 'singular', 'page', '-5'),
      $this->raw('show', 'singular', 'page', 'abc'),
      'not-an-array',
      $this->raw('show', 'entire_site'),
    ), $this->choices());

    $this->assertSame(8, $result['discarded']);
    $this->assertSame(array($this->rule('show', 'entire_site')), $result['rules']);
  }

  // Item só faz sentido em Individuais e em taxonomias; nos demais ele é zerado.
  public function test_sanitize_resets_item_where_not_applicable(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Display_Rules::sanitize(array(
      $this->raw('show', 'archive', 'pta_cases', '9'),
      $this->raw('show', 'entire_site', 'page', '9'),
    ), $this->choices());

    $this->assertSame(0, $result['discarded']);
    $this->assertSame(array(
      $this->rule('show', 'archive', 'pta_cases'),
      $this->rule('show', 'entire_site'),
    ), $result['rules']);
  }
}
