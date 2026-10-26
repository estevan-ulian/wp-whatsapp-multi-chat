<?php

if (!defined('ABSPATH')) exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class WP_WhatsApp_Multi_Chat_Updater
{
  const REPOSITORY_URL = 'https://github.com/estevan-ulian/wp-whatsapp-multi-chat';
  const BRANCH = 'main';
  const SLUG = 'wp-whatsapp-multi-chat';

  protected static $instance = null;

  protected $update_checker = null;

  protected function __construct()
  {
    // vendor/ is shipped in release packages; a dev checkout without
    // `composer install` simply runs without update checks.
    $autoload = WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($autoload)) {
      require_once $autoload;
    }

    if (!class_exists(PucFactory::class)) return;

    $this->update_checker = PucFactory::buildUpdateChecker(
      self::REPOSITORY_URL,
      WP_WHATSAPP_MULTI_CHAT_PLUGIN_FILE,
      self::SLUG
    );

    $this->update_checker->setBranch(self::BRANCH);

    // Only needed if the repository becomes private; the token lives in
    // wp-config.php so it never ships in the plugin or the repository.
    if (defined('WP_WHATSAPP_MULTI_CHAT_GITHUB_TOKEN') && WP_WHATSAPP_MULTI_CHAT_GITHUB_TOKEN) {
      $this->update_checker->setAuthentication(WP_WHATSAPP_MULTI_CHAT_GITHUB_TOKEN);
    }

    // Install the packaged zip attached to each release (with built assets and
    // vendor/), not GitHub's source archive, which lacks both.
    $this->update_checker->getVcsApi()->enableReleaseAssets();
  }

  public static function get_instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }
}
