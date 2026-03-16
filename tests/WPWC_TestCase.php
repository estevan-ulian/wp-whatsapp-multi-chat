<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

abstract class WPWC_TestCase extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Monkey\setUp();
  }

  protected function tearDown(): void
  {
    // Reset singleton instances so each test starts fresh
    $singletons = [
      'WP_WhatsApp_Multi_Chat_Accounts',
      'WP_WhatsApp_Multi_Chat_Options',
      'WP_WhatsApp_Multi_Chat_View',
    ];
    foreach ($singletons as $class) {
      if (class_exists($class, false)) {
        $ref = new \ReflectionClass($class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);
      }
    }

    Monkey\tearDown();
    parent::tearDown();
  }
}
