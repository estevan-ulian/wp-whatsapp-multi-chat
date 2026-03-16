<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

define('ABSPATH', '/tmp/wordpress/');

define('WP_WHATSAPP_MULTI_CHAT_VERSION', '1.0.0');
define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_FILE', dirname(__DIR__) . '/wp-whatsapp-multi-chat.php');
define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_DIR', dirname(__DIR__) . '/');
define('WP_WHATSAPP_MULTI_CHAT_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-whatsapp-multi-chat/');

require_once dirname(__DIR__) . '/includes/wp-whatsapp-multi-chat-utils.php';
