<?php

if (!defined('ABSPATH')) exit;

/**
 * Business-hours rules shared by the settings tab, the account editor and the
 * widget payload. A week is always the seven days in DAYS order, each with
 * `enabled` (0 = closed), `open` and `close` as "HH:MM" in the site timezone.
 */
class WP_WhatsApp_Multi_Chat_Hours
{
  const OPTION_KEY     = 'wpwc_hours';
  const MODE_META_KEY  = '_wpwc_availability_mode';
  const HOURS_META_KEY = '_wpwc_hours';

  const DAYS  = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');
  const MODES = array('always_online', 'general', 'custom', 'always_offline');

  const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

  public static function defaults(): array
  {
    $week = array();
    foreach (self::DAYS as $day) {
      $week[$day] = array(
        'enabled' => in_array($day, array('sat', 'sun'), true) ? 0 : 1,
        'open'    => '08:00',
        'close'   => '18:00',
      );
    }
    return $week;
  }

  /**
   * Completes a stored week with the defaults, so a partial or corrupted value
   * never leaves a day without hours.
   */
  public static function normalize_week($week): array
  {
    $defaults = self::defaults();
    $week     = is_array($week) ? $week : array();

    $normalized = array();
    foreach (self::DAYS as $day) {
      $stored = isset($week[$day]) && is_array($week[$day]) ? $week[$day] : array();
      $normalized[$day] = array(
        'enabled' => array_key_exists('enabled', $stored)
          ? (empty($stored['enabled']) ? 0 : 1)
          : $defaults[$day]['enabled'],
        'open'    => self::is_time($stored['open'] ?? null) ? $stored['open'] : $defaults[$day]['open'],
        'close'   => self::is_time($stored['close'] ?? null) ? $stored['close'] : $defaults[$day]['close'],
      );
    }
    return $normalized;
  }

  /**
   * Validates submitted hours. An open day must have valid times with closing
   * strictly after opening; otherwise that day keeps its previous value and an
   * error code is reported, so a typo never wipes or silently closes a day.
   *
   * @return array{hours: array, errors: array<string, string>}
   */
  public static function sanitize_week($raw, array $previous): array
  {
    $raw      = is_array($raw) ? $raw : array();
    $previous = self::normalize_week($previous);

    $hours  = array();
    $errors = array();

    foreach (self::DAYS as $day) {
      $submitted = isset($raw[$day]) && is_array($raw[$day]) ? $raw[$day] : array();
      $enabled   = !empty($submitted['enabled']);
      $open      = isset($submitted['open']) ? trim((string) $submitted['open']) : '';
      $close     = isset($submitted['close']) ? trim((string) $submitted['close']) : '';
      $valid     = self::is_time($open) && self::is_time($close);

      if (!$enabled) {
        $hours[$day] = array(
          'enabled' => 0,
          'open'    => $valid ? $open : $previous[$day]['open'],
          'close'   => $valid ? $close : $previous[$day]['close'],
        );
        continue;
      }

      if (!$valid) {
        $errors[$day] = 'invalid_format';
      } elseif (strcmp($close, $open) <= 0) {
        $errors[$day] = 'close_before_open';
      }

      $hours[$day] = isset($errors[$day])
        ? $previous[$day]
        : array('enabled' => 1, 'open' => $open, 'close' => $close);
    }

    return array('hours' => $hours, 'errors' => $errors);
  }

  public static function get_general(): array
  {
    return self::normalize_week(get_option(self::OPTION_KEY, array()));
  }

  /**
   * Accounts saved before business hours existed have no mode and stay
   * "always online", so updating the plugin changes nothing on the site.
   */
  public static function get_account_mode(int $post_id): string
  {
    $mode = (string) get_post_meta($post_id, self::MODE_META_KEY, true);
    return in_array($mode, self::MODES, true) ? $mode : 'always_online';
  }

  /**
   * The availability the widget needs: the effective schedule is chosen here,
   * so the browser never has to know about general vs. custom hours.
   */
  public static function resolve_availability(int $post_id): array
  {
    switch (self::get_account_mode($post_id)) {
      case 'always_offline':
        return array('mode' => 'always_offline');
      case 'general':
        return array('mode' => 'schedule', 'hours' => self::get_general());
      case 'custom':
        return array(
          'mode'  => 'schedule',
          'hours' => self::normalize_week(get_post_meta($post_id, self::HOURS_META_KEY, true)),
        );
      default:
        return array('mode' => 'always_online');
    }
  }

  public static function mode_labels(): array
  {
    return array(
      'always_online'  => __('Sempre online', 'wp-whatsapp-multi-chat'),
      'general'        => __('Horário geral', 'wp-whatsapp-multi-chat'),
      'custom'         => __('Horário próprio', 'wp-whatsapp-multi-chat'),
      'always_offline' => __('Sempre offline', 'wp-whatsapp-multi-chat'),
    );
  }

  public static function day_labels(): array
  {
    return array(
      'mon' => __('Segunda-feira', 'wp-whatsapp-multi-chat'),
      'tue' => __('Terça-feira', 'wp-whatsapp-multi-chat'),
      'wed' => __('Quarta-feira', 'wp-whatsapp-multi-chat'),
      'thu' => __('Quinta-feira', 'wp-whatsapp-multi-chat'),
      'fri' => __('Sexta-feira', 'wp-whatsapp-multi-chat'),
      'sat' => __('Sábado', 'wp-whatsapp-multi-chat'),
      'sun' => __('Domingo', 'wp-whatsapp-multi-chat'),
    );
  }

  public static function error_message(string $day, string $code): string
  {
    $labels = self::day_labels();
    $label  = $labels[$day] ?? $day;

    if ('close_before_open' === $code) {
      /* translators: %s: weekday name */
      return sprintf(__('%s: o fechamento precisa ser depois da abertura. O horário anterior foi mantido.', 'wp-whatsapp-multi-chat'), $label);
    }

    /* translators: %s: weekday name */
    return sprintf(__('%s: use o formato HH:MM. O horário anterior foi mantido.', 'wp-whatsapp-multi-chat'), $label);
  }

  /**
   * Rejected days are reported on the next page load (after the save redirect),
   * per user so two admins saving at once never see each other's errors.
   */
  public static function store_errors(array $errors): void
  {
    if (empty($errors)) return;
    set_transient(self::errors_transient_key(), $errors, 60);
  }

  public static function pull_errors(): array
  {
    $key    = self::errors_transient_key();
    $errors = get_transient($key);
    if (!is_array($errors)) return array();

    delete_transient($key);
    return $errors;
  }

  public static function render_week_fields(string $name, array $week): void
  {
    $week   = self::normalize_week($week);
    $labels = self::day_labels();

    foreach (self::DAYS as $day) {
      $field = $name . '[' . $day . ']';
      ?>
      <div class="wpwc-hours-row">
        <span class="wpwc-hours-day"><?php echo esc_html($labels[$day]); ?></span>
        <label class="wpwc-hours-enabled">
          <input type="checkbox" name="<?php echo esc_attr($field . '[enabled]'); ?>" value="1" <?php checked($week[$day]['enabled'], 1); ?>>
          <?php esc_html_e('Atende', 'wp-whatsapp-multi-chat'); ?>
        </label>
        <label class="wpwc-hours-time">
          <span class="screen-reader-text"><?php echo esc_html(sprintf(__('%s: abertura', 'wp-whatsapp-multi-chat'), $labels[$day])); ?></span>
          <input type="time" step="60" name="<?php echo esc_attr($field . '[open]'); ?>" value="<?php echo esc_attr($week[$day]['open']); ?>">
        </label>
        <span class="wpwc-hours-separator" aria-hidden="true">–</span>
        <label class="wpwc-hours-time">
          <span class="screen-reader-text"><?php echo esc_html(sprintf(__('%s: fechamento', 'wp-whatsapp-multi-chat'), $labels[$day])); ?></span>
          <input type="time" step="60" name="<?php echo esc_attr($field . '[close]'); ?>" value="<?php echo esc_attr($week[$day]['close']); ?>">
        </label>
      </div>
      <?php
    }
  }

  private static function is_time($value): bool
  {
    return is_string($value) && 1 === preg_match(self::TIME_PATTERN, $value);
  }

  private static function errors_transient_key(): string
  {
    return 'wpwc_hours_errors_' . get_current_user_id();
  }
}
