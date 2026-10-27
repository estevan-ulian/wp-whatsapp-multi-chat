<?php

if (!defined('ABSPATH')) exit;

/**
 * Display rules decide on which pages the widget is loaded. Each rule is
 * `action` (show|hide), `where`, `which` and `item` (post or term ID, 0 = all).
 * A page shows the widget when it matches at least one "show" rule and no
 * "hide" rule, so admins can say "entire site except the Cases archive".
 * Without rules the widget stays hidden, as on a fresh install.
 *
 * The context describes the current page with plain values (see
 * empty_context()), so the decision can be tested without WordPress.
 */
final class WP_WhatsApp_Multi_Chat_Display_Rules
{
  public static function empty_context(): array
  {
    return array(
      'front_page' => false,
      'blog'       => false, // posts page set apart from the front page
      'archive'    => false,
      'pta'        => '',    // post type of a post type archive
      'author'     => false,
      'date'       => false,
      'tax'        => '',    // taxonomy of a term archive
      'term'       => 0,
      'singular'   => '',    // post type of a single view
      'object'     => 0,
      'search'     => false,
      'not_found'  => false,
    );
  }

  public static function matches(array $rules, array $context): bool
  {
    $show = false;

    foreach ($rules as $rule) {
      if (!self::match_rule($rule, $context)) continue;
      if ('hide' === $rule['action']) return false;
      if ('show' === $rule['action']) $show = true;
    }

    return $show;
  }

  private static function match_rule(array $rule, array $ctx): bool
  {
    $which = (string) $rule['which'];
    $item  = (int) $rule['item'];

    switch ($rule['where']) {
      case 'entire_site':
        return true;
      case 'front_page':
        return $ctx['front_page'];
      case 'search':
        return $ctx['search'];
      case '404':
        return $ctx['not_found'];
      case 'singular':
        return '' !== $ctx['singular'] && $which === $ctx['singular']
          && (0 === $item || $item === (int) $ctx['object']);
      case 'archive':
        return self::match_archive($which, $item, $ctx);
    }

    return false;
  }

  private static function match_archive(string $which, int $item, array $ctx): bool
  {
    if ('blog' === $which) return $ctx['blog'];
    if (!$ctx['archive']) return false;

    switch ($which) {
      case 'all':
        return true;
      case 'author':
        return $ctx['author'];
      case 'date':
        return $ctx['date'];
    }

    if (0 === strpos($which, 'pta_')) {
      return '' !== $ctx['pta'] && substr($which, 4) === $ctx['pta'];
    }

    if (0 === strpos($which, 'tax_')) {
      return '' !== $ctx['tax'] && substr($which, 4) === $ctx['tax']
        && (0 === $item || $item === (int) $ctx['term']);
    }

    return false;
  }
}
