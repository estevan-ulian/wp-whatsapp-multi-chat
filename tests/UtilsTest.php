<?php

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
  #[PHPUnit\Framework\Attributes\DataProvider('phoneNumberProvider')]
  public function test_sanitize_phone_number(string $input, string $expected): void
  {
    $result = WP_WhatsApp_Multi_Chat_Utils::sanitize_phone_number($input);
    $this->assertSame($expected, $result);
  }

  public static function phoneNumberProvider(): array
  {
    return [
      'digits only'              => ['5511912341234', '5511912341234'],
      'with dashes'              => ['55-11-91234-1234', '5511912341234'],
      'with spaces'              => ['55 11 91234 1234', '5511912341234'],
      'with parentheses'         => ['+55 (11) 91234-1234', '5511912341234'],
      'with dots'                => ['55.11.91234.1234', '5511912341234'],
      'with plus sign'           => ['+5511912341234', '5511912341234'],
      'with letters (XSS)'       => ['55<script>alert(1)</script>11', '55111'],
      'empty string'             => ['', ''],
      'only non-digits'          => ['abc-xyz', ''],
      'mixed special characters' => ['(+55) 11 / 91234.1234', '5511912341234'],
    ];
  }

  public function test_sanitize_phone_number_returns_string(): void
  {
    $result = WP_WhatsApp_Multi_Chat_Utils::sanitize_phone_number('123');
    $this->assertIsString($result);
  }
}
