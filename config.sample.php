<?php
/* Copy to config.php — the web installer (install.php) writes this file for you. */
return array(
  'db' => array(
    'host' => 'localhost',
    'name' => 'yourcpanel_storyfoundry',
    'user' => 'yourcpanel_sf',
    'pass' => 'CHANGE_ME',
    'charset' => 'utf8mb4',
  ),
  'app' => array(
    'url'        => 'https://www.storyfoundry.online/',
    'name'       => 'STORYFOUNDRY',
    'tagline'    => 'From Idea to Finished Story.',
    'env'        => 'production',   // production | development
    'debug'      => false,          // NEVER true in production: hides errors from visitors
    'timezone'   => 'Africa/Lagos',
    'cron_token' => 'CHANGE_ME_LONG_RANDOM',   // required by cron.php
    'key'        => 'CHANGE_ME_RANDOM_64_CHARS', // HMAC key for expiring file links
    'queue_inline_seconds' => 20,   // how long a web request may drain jobs
    'mock_fallback_allowed' => true, // MOCK is allowed, but ALWAYS labelled
  ),
  'security' => array(
    'csrf'               => true,
    'rate_limit'         => true,   // 900 requests / hour / IP
    'min_pass'           => 8,
    'login_max_attempts' => 8,      // failed sign-ins before lockout
    'login_window'       => 900,    // lockout window in seconds
  ),
  'uploads' => array(
    'max_mb'     => 64,
    'allowed'    => 'jpg,jpeg,png,webp,gif,mp3,wav,m4a,mp4,mov,webm,srt,vtt,pdf,zip,json,svg',
  ),
);
