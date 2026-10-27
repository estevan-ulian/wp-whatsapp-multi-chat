<?php

require_once __DIR__ . '/WPWC_TestCase.php';

use Brain\Monkey\Functions;

class HoursTest extends WPWC_TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Functions\stubTranslationFunctions();
  }

  private function day($enabled, $open, $close)
  {
    return array('enabled' => $enabled, 'open' => $open, 'close' => $close);
  }

  private function stub_meta(array $map)
  {
    Functions\when('get_post_meta')->alias(function ($id, $key, $single) use ($map) {
      return $map[$key] ?? '';
    });
  }

  public function test_defaults_weekdays_open_weekend_closed(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Hours::defaults();

    $this->assertSame(WP_WhatsApp_Multi_Chat_Hours::DAYS, array_keys($defaults));
    foreach (array('mon', 'tue', 'wed', 'thu', 'fri') as $day) {
      $this->assertSame($this->day(1, '08:00', '18:00'), $defaults[$day]);
    }
    $this->assertSame($this->day(0, '08:00', '18:00'), $defaults['sat']);
    $this->assertSame($this->day(0, '08:00', '18:00'), $defaults['sun']);
  }

  public function test_normalize_week_fills_missing_days_and_keys(): void
  {
    $defaults = WP_WhatsApp_Multi_Chat_Hours::defaults();

    $week = WP_WhatsApp_Multi_Chat_Hours::normalize_week(array('mon' => array('enabled' => 1, 'open' => '09:00')));
    $this->assertSame($this->day(1, '09:00', '18:00'), $week['mon']);
    $this->assertSame($defaults['tue'], $week['tue']);
    $this->assertSame(WP_WhatsApp_Multi_Chat_Hours::DAYS, array_keys($week));

    $this->assertSame($defaults, WP_WhatsApp_Multi_Chat_Hours::normalize_week('garbage'));
  }

  public function test_sanitize_week_accepts_valid_day(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week(
      array('mon' => array('enabled' => '1', 'open' => '07:30', 'close' => '12:00')),
      WP_WhatsApp_Multi_Chat_Hours::defaults()
    );

    $this->assertSame($this->day(1, '07:30', '12:00'), $result['hours']['mon']);
    $this->assertSame(array(), $result['errors']);
  }

  public function test_sanitize_week_unchecked_day_is_closed_and_keeps_times(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week(
      array('tue' => array('open' => '10:00', 'close' => '16:00')),
      WP_WhatsApp_Multi_Chat_Hours::defaults()
    );

    $this->assertSame($this->day(0, '10:00', '16:00'), $result['hours']['tue']);
    $this->assertSame(array(), $result['errors']);
  }

  public function test_sanitize_week_rejects_bad_format_keeping_previous(): void
  {
    $previous = WP_WhatsApp_Multi_Chat_Hours::defaults();
    $previous['wed'] = $this->day(1, '09:00', '17:00');

    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week(
      array('wed' => array('enabled' => '1', 'open' => '8h', 'close' => '17:00')),
      $previous
    );

    $this->assertSame($previous['wed'], $result['hours']['wed']);
    $this->assertSame(array('wed' => 'invalid_format'), $result['errors']);
  }

  public function test_sanitize_week_rejects_close_not_after_open(): void
  {
    $previous = WP_WhatsApp_Multi_Chat_Hours::defaults();

    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week(
      array(
        'thu' => array('enabled' => '1', 'open' => '18:00', 'close' => '08:00'),
        'fri' => array('enabled' => '1', 'open' => '09:00', 'close' => '09:00'),
      ),
      $previous
    );

    $this->assertSame($previous['thu'], $result['hours']['thu']);
    $this->assertSame($previous['fri'], $result['hours']['fri']);
    $this->assertSame(array('thu' => 'close_before_open', 'fri' => 'close_before_open'), $result['errors']);
  }

  public function test_sanitize_week_disabled_day_with_bad_times_keeps_previous_times_without_error(): void
  {
    $previous = WP_WhatsApp_Multi_Chat_Hours::defaults();
    $previous['sat'] = $this->day(1, '09:00', '13:00');

    $result = WP_WhatsApp_Multi_Chat_Hours::sanitize_week(
      array('sat' => array('open' => 'xx', 'close' => '13:00')),
      $previous
    );

    $this->assertSame($this->day(0, '09:00', '13:00'), $result['hours']['sat']);
    $this->assertSame(array(), $result['errors']);
  }

  public function test_get_account_mode_defaults_to_always_online(): void
  {
    $this->stub_meta(array());
    $this->assertSame('always_online', WP_WhatsApp_Multi_Chat_Hours::get_account_mode(1));

    $this->stub_meta(array('_wpwc_availability_mode' => 'bogus'));
    $this->assertSame('always_online', WP_WhatsApp_Multi_Chat_Hours::get_account_mode(1));
  }

  public function test_resolve_availability_per_mode(): void
  {
    $general = WP_WhatsApp_Multi_Chat_Hours::defaults();
    $general['sat'] = $this->day(1, '09:00', '13:00');
    Functions\when('get_option')->justReturn($general);

    $this->stub_meta(array('_wpwc_availability_mode' => 'always_online'));
    $this->assertSame(array('mode' => 'always_online'), WP_WhatsApp_Multi_Chat_Hours::resolve_availability(1));

    $this->stub_meta(array('_wpwc_availability_mode' => 'always_offline'));
    $this->assertSame(array('mode' => 'always_offline'), WP_WhatsApp_Multi_Chat_Hours::resolve_availability(1));

    $this->stub_meta(array('_wpwc_availability_mode' => 'general'));
    $this->assertSame(array('mode' => 'schedule', 'hours' => $general), WP_WhatsApp_Multi_Chat_Hours::resolve_availability(1));

    $custom = WP_WhatsApp_Multi_Chat_Hours::defaults();
    $custom['mon'] = $this->day(1, '10:00', '14:00');
    $this->stub_meta(array('_wpwc_availability_mode' => 'custom', '_wpwc_hours' => $custom));
    $this->assertSame(array('mode' => 'schedule', 'hours' => $custom), WP_WhatsApp_Multi_Chat_Hours::resolve_availability(1));
  }

  public function test_resolve_custom_without_saved_hours_uses_defaults(): void
  {
    $this->stub_meta(array('_wpwc_availability_mode' => 'custom'));

    $this->assertSame(
      array('mode' => 'schedule', 'hours' => WP_WhatsApp_Multi_Chat_Hours::defaults()),
      WP_WhatsApp_Multi_Chat_Hours::resolve_availability(1)
    );
  }
}
