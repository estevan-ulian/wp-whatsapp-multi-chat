<?php

if (!defined('ABSPATH')) exit;

class WP_WhatsApp_Multi_Chat_Utils
{
  public static function sanitize_phone_number($number)
  {
    // Remove all non-digit characters
    return preg_replace('/\D/', '', $number);
  }

  public static function write_log($message)
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('[WP WhatsApp Multi Chat] ' . $message);
    }
  }
}
