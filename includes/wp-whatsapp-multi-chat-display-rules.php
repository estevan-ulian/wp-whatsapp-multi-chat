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

  const VERSION = 2;
  const WHERE   = array('entire_site', 'front_page', 'archive', 'singular', 'search', '404');

  /**
   * Reads the stored option. Settings saved before display rules existed are
   * converted on read and only replaced when the admin saves the screen, so
   * going back to an older plugin version loses nothing.
   */
  public static function from_stored($stored): array
  {
    if (!is_array($stored)) return array();

    if (isset($stored['version']) && self::VERSION === (int) $stored['version']) {
      $rules = array();
      foreach ((array) ($stored['rules'] ?? array()) as $rule) {
        if (!is_array($rule)) continue;
        $rules[] = self::rule(
          (string) ($rule['action'] ?? ''),
          (string) ($rule['where'] ?? ''),
          (string) ($rule['which'] ?? ''),
          (int) ($rule['item'] ?? 0)
        );
      }
      return $rules;
    }

    return self::from_legacy($stored);
  }

  public static function to_stored(array $rules): array
  {
    return array('version' => self::VERSION, 'rules' => $rules);
  }

  /**
   * Validates rows posted by the settings form against the choices offered on
   * screen. Rows that are incomplete or out of those choices are discarded and
   * counted, so the admin can be told that something was not saved.
   *
   * @param array $choices ['archive' => which[], 'singular' => post type[]]
   * @return array ['rules' => array, 'discarded' => int]
   */
  public static function sanitize(array $raw, array $choices): array
  {
    $rules     = array();
    $discarded = 0;

    foreach ($raw as $row) {
      $rule = is_array($row) ? self::sanitize_row($row, $choices) : null;
      if (null === $rule) {
        $discarded++;
        continue;
      }
      $rules[] = $rule;
    }

    return array('rules' => $rules, 'discarded' => $discarded);
  }

  private static function sanitize_row(array $row, array $choices): ?array
  {
    $action = (string) ($row['action'] ?? '');
    $where  = (string) ($row['where'] ?? '');
    $which  = (string) ($row['which'] ?? '');
    $item   = trim((string) ($row['item'] ?? ''));

    if (!in_array($action, array('show', 'hide'), true)) return null;
    if (!in_array($where, self::WHERE, true)) return null;
    if ('' !== $item && !ctype_digit($item)) return null;

    if ('archive' === $where) {
      if (!in_array($which, (array) ($choices['archive'] ?? array()), true)) return null;
      return self::rule($action, $where, $which, 0 === strpos($which, 'tax_') ? (int) $item : 0);
    }

    if ('singular' === $where) {
      if (!in_array($which, (array) ($choices['singular'] ?? array()), true)) return null;
      return self::rule($action, $where, $which, (int) $item);
    }

    return self::rule($action, $where);
  }

  private static function from_legacy(array $old): array
  {
    if ('everywhere' === ($old['display_mode'] ?? '')) {
      $rules = array(self::rule('show', 'entire_site'));
    } else {
      $rules = array();
      if (!empty($old['show_front_page'])) {
        $rules[] = self::rule('show', 'front_page');
      }
      foreach ((array) ($old['post_types'] ?? array()) as $type) {
        $rules[] = self::rule('show', 'singular', (string) $type);
      }
      foreach ((array) ($old['archives'] ?? array()) as $key) {
        $rules[] = self::legacy_archive_rule((string) $key);
      }
    }

    // Specific pages applied in both modes before, so they convert in both.
    foreach ((array) ($old['include_posts'] ?? array()) as $id) {
      $rules[] = self::rule('show', 'singular', self::legacy_post_type((int) $id), (int) $id);
    }
    foreach ((array) ($old['exclude_posts'] ?? array()) as $id) {
      $rules[] = self::rule('hide', 'singular', self::legacy_post_type((int) $id), (int) $id);
    }

    return $rules;
  }

  private static function legacy_archive_rule(string $key): array
  {
    switch ($key) {
      case 'category': return self::rule('show', 'archive', 'tax_category');
      case 'tag':      return self::rule('show', 'archive', 'tax_post_tag');
      case 'search':   return self::rule('show', 'search');
      case '404':      return self::rule('show', '404');
    }
    // 'author', 'date' and 'tax_{taxonomy}' keep their key as `which`.
    return self::rule('show', 'archive', $key);
  }

  // A page deleted since it was chosen has no type left; "page" keeps the rule harmless.
  private static function legacy_post_type(int $id): string
  {
    $type = get_post_type($id);
    return $type ? (string) $type : 'page';
  }

  private static function rule(string $action, string $where, string $which = '', int $item = 0): array
  {
    return array('action' => $action, 'where' => $where, 'which' => $which, 'item' => $item);
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
