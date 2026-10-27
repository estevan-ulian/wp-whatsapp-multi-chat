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
}
