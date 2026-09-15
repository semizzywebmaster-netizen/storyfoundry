<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Full MySQL schema. InnoDB + utf8mb4. Safe for cPanel MySQL 5.7 / MariaDB 10.x */
function sf_schema() {
    $t = array();
    $t['sessions'] = "CREATE TABLE IF NOT EXISTS `sessions` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `token` VARCHAR(64) NOT NULL, `ip` VARCHAR(45) DEFAULT NULL, `agent` VARCHAR(255) DEFAULT NULL,
      `created_at` DATETIME NOT NULL, `last_at` DATETIME NOT NULL, PRIMARY KEY (`id`),
      UNIQUE KEY `uk_token` (`token`), KEY `idx_user` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $t['users'] = "CREATE TABLE IF NOT EXISTS `users` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(120) NOT NULL,
      `email` VARCHAR(190) NOT NULL,
      `username` VARCHAR(60) DEFAULT NULL,
      `pass_hash` VARCHAR(255) NOT NULL,
      `role` VARCHAR(32) NOT NULL DEFAULT 'creator',
      `plan` VARCHAR(32) NOT NULL DEFAULT 'free',
      `credits` INT NOT NULL DEFAULT 0,
      `reserved` INT NOT NULL DEFAULT 0,
      `wallet` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `avatar` VARCHAR(255) DEFAULT NULL,
      `bio` TEXT,
      `culture` VARCHAR(60) DEFAULT 'Yoruba',
      `lang` VARCHAR(60) DEFAULT 'English (Nigeria)',
      `tz` VARCHAR(64) DEFAULT 'Africa/Lagos',
      `region` VARCHAR(64) DEFAULT 'Nigeria',
      `referral_code` VARCHAR(32) DEFAULT NULL,
      `referred_by` INT UNSIGNED DEFAULT NULL,
      `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
      `twofa` TINYINT(1) NOT NULL DEFAULT 0,
      `status` VARCHAR(20) NOT NULL DEFAULT 'active',
      `last_login` DATETIME DEFAULT NULL,
      `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_email` (`email`), KEY `ix_role` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['password_resets'] = "CREATE TABLE IF NOT EXISTS `password_resets` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `token` VARCHAR(80) NOT NULL, `expires_at` DATETIME NOT NULL, `used` TINYINT(1) DEFAULT 0,
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['projects'] = "CREATE TABLE IF NOT EXISTS `projects` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `title` VARCHAR(190) NOT NULL, `genre` VARCHAR(60) DEFAULT NULL, `tone` VARCHAR(80) DEFAULT NULL,
      `audience` VARCHAR(80) DEFAULT NULL, `culture` VARCHAR(60) DEFAULT 'Yoruba', `theme` VARCHAR(120) DEFAULT NULL,
      `setting` VARCHAR(160) DEFAULT NULL, `length` VARCHAR(60) DEFAULT NULL, `style` VARCHAR(60) DEFAULT 'cinematic',
      `status` VARCHAR(40) DEFAULT 'Draft', `approval` VARCHAR(40) DEFAULT 'Draft',
      `cover` VARCHAR(255) DEFAULT NULL, `folder` VARCHAR(80) DEFAULT 'Personal', `tags` VARCHAR(255) DEFAULT NULL,
      `series_id` INT UNSIGNED DEFAULT NULL, `agency_id` INT UNSIGNED DEFAULT NULL, `client_id` INT UNSIGNED DEFAULT NULL,
      `visibility` VARCHAR(20) DEFAULT 'private',
      `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, `deleted_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`), KEY `ix_upd` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['project_data'] = "CREATE TABLE IF NOT EXISTS `project_data` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL,
      `stage_key` VARCHAR(60) NOT NULL, `data` LONGTEXT,
      `updated_at` DATETIME NOT NULL, PRIMARY KEY (`id`),
      UNIQUE KEY `uq_stage` (`project_id`,`stage_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['project_collaborators'] = "CREATE TABLE IF NOT EXISTS `project_collaborators` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL,
      `user_id` INT UNSIGNED DEFAULT NULL, `email` VARCHAR(190) DEFAULT NULL,
      `role` VARCHAR(40) DEFAULT 'viewer', `status` VARCHAR(20) DEFAULT 'active',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_proj` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['project_activity'] = "CREATE TABLE IF NOT EXISTS `project_activity` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL,
      `user_id` INT UNSIGNED DEFAULT NULL, `who` VARCHAR(120) DEFAULT NULL,
      `what` VARCHAR(255) DEFAULT NULL, `icon` VARCHAR(16) DEFAULT NULL,
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_proj` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['assets'] = "CREATE TABLE IF NOT EXISTS `assets` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `kind` VARCHAR(40) DEFAULT 'image',
      `name` VARCHAR(190) DEFAULT NULL, `path` VARCHAR(255) NOT NULL, `mime` VARCHAR(80) DEFAULT NULL,
      `size` INT UNSIGNED DEFAULT 0, `folder` VARCHAR(80) DEFAULT 'General', `fav` TINYINT(1) NOT NULL DEFAULT 0, `tags` VARCHAR(255) DEFAULT NULL,
      `meta` TEXT, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`),
      KEY `ix_user` (`user_id`), KEY `ix_proj` (`project_id`), KEY `ix_kind` (`kind`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['jobs'] = "CREATE TABLE IF NOT EXISTS `jobs` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `feature` VARCHAR(60) NOT NULL,
      `capability` VARCHAR(40) DEFAULT 'text', `label` VARCHAR(190) DEFAULT NULL,
      `payload` LONGTEXT, `result` LONGTEXT, `provider` VARCHAR(60) DEFAULT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'queued', `progress` TINYINT UNSIGNED DEFAULT 0,
      `cost` INT NOT NULL DEFAULT 0, `priority` TINYINT NOT NULL DEFAULT 5,
      `attempts` INT NOT NULL DEFAULT 0, `error` TEXT,
      `created_at` DATETIME NOT NULL, `started_at` DATETIME DEFAULT NULL, `finished_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`), KEY `ix_status` (`status`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['reservations'] = "CREATE TABLE IF NOT EXISTS `reservations` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `job_id` INT UNSIGNED DEFAULT NULL, `amount` INT NOT NULL DEFAULT 0,
      `ref` VARCHAR(190) DEFAULT NULL, `status` VARCHAR(20) DEFAULT 'held',
      `created_at` DATETIME NOT NULL, `released_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`), KEY `ix_job` (`job_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['credit_tx'] = "CREATE TABLE IF NOT EXISTS `credit_tx` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `type` VARCHAR(32) NOT NULL, `amount` INT NOT NULL DEFAULT 0,
      `ref` VARCHAR(60) DEFAULT NULL, `note` VARCHAR(255) DEFAULT NULL,
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['transactions'] = "CREATE TABLE IF NOT EXISTS `transactions` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `type` VARCHAR(40) NOT NULL, `ref` VARCHAR(80) DEFAULT NULL,
      `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00, `currency` VARCHAR(8) DEFAULT 'NGN',
      `method` VARCHAR(40) DEFAULT NULL, `status` VARCHAR(20) DEFAULT 'pending',
      `note` VARCHAR(255) DEFAULT NULL, `meta` TEXT, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_ref` (`ref`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['plans'] = "CREATE TABLE IF NOT EXISTS `plans` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `pkey` VARCHAR(40) NOT NULL,
      `name` VARCHAR(80) NOT NULL, `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `credits` INT NOT NULL DEFAULT 0, `features` TEXT, `limits` TEXT,
      `sort` INT DEFAULT 0, `active` TINYINT(1) DEFAULT 1, PRIMARY KEY (`id`), UNIQUE KEY `uq_key` (`pkey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['subscriptions'] = "CREATE TABLE IF NOT EXISTS `subscriptions` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `plan_key` VARCHAR(40) NOT NULL, `status` VARCHAR(20) DEFAULT 'active',
      `renews_at` DATETIME DEFAULT NULL, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['coupons'] = "CREATE TABLE IF NOT EXISTS `coupons` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(40) NOT NULL,
      `type` VARCHAR(20) DEFAULT 'percentage', `value` DECIMAL(12,2) DEFAULT 0,
      `uses` INT DEFAULT 0, `max_uses` INT DEFAULT 0, `expires_at` DATETIME DEFAULT NULL,
      `restriction` VARCHAR(120) DEFAULT NULL, `status` VARCHAR(20) DEFAULT 'active',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['referrals'] = "CREATE TABLE IF NOT EXISTS `referrals` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `referrer_id` INT UNSIGNED NOT NULL,
      `referred_id` INT UNSIGNED DEFAULT NULL, `email` VARCHAR(190) DEFAULT NULL,
      `status` VARCHAR(20) DEFAULT 'pending', `reward` INT DEFAULT 0,
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_ref` (`referrer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['providers'] = "CREATE TABLE IF NOT EXISTS `providers` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `pkey` VARCHAR(60) NOT NULL,
      `name` VARCHAR(120) DEFAULT NULL, `capability` VARCHAR(40) DEFAULT 'text',
      `status` VARCHAR(24) DEFAULT 'MOCK', `priority` INT DEFAULT 50,
      `config` TEXT, `models` TEXT, `enabled` TINYINT(1) DEFAULT 1,
      `health` VARCHAR(20) DEFAULT 'unknown', `calls` INT DEFAULT 0, `errors` INT DEFAULT 0,
      `last_latency` INT DEFAULT 0, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_pkey` (`pkey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['usage_log'] = "CREATE TABLE IF NOT EXISTS `usage_log` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `provider` VARCHAR(60) DEFAULT NULL, `model` VARCHAR(80) DEFAULT NULL,
      `feature` VARCHAR(60) DEFAULT NULL, `tokens` INT DEFAULT 0, `credits` INT DEFAULT 0,
      `cost` DECIMAL(10,4) DEFAULT 0, `ms` INT DEFAULT 0, `status` VARCHAR(20) DEFAULT 'ok',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_user` (`user_id`), KEY `ix_feat` (`feature`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['settings'] = "CREATE TABLE IF NOT EXISTS `settings` (
      `k` VARCHAR(80) NOT NULL, `v` LONGTEXT, PRIMARY KEY (`k`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['plugins'] = "CREATE TABLE IF NOT EXISTS `plugins` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `pkey` VARCHAR(80) NOT NULL,
      `name` VARCHAR(160) DEFAULT NULL, `version` VARCHAR(20) DEFAULT '1.0.0',
      `enabled` TINYINT(1) DEFAULT 0, `config` TEXT, `installed_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_pkey` (`pkey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['feature_flags'] = "CREATE TABLE IF NOT EXISTS `feature_flags` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `fkey` VARCHAR(60) NOT NULL,
      `name` VARCHAR(160) DEFAULT NULL, `enabled` TINYINT(1) DEFAULT 1,
      `min_plan` VARCHAR(20) DEFAULT 'free', `credit_cost` INT DEFAULT 1,
      `daily_limit` INT DEFAULT 200, `maintenance` TINYINT(1) DEFAULT 0,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_fkey` (`fkey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['notifications'] = "CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `type` VARCHAR(20) DEFAULT 'info', `title` VARCHAR(190) DEFAULT NULL, `body` TEXT,
      `read` TINYINT(1) DEFAULT 0, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['announcements'] = "CREATE TABLE IF NOT EXISTS `announcements` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `type` VARCHAR(30) DEFAULT 'Feature',
      `title` VARCHAR(190) DEFAULT NULL, `body` TEXT, `target` VARCHAR(80) DEFAULT 'all',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['audit'] = "CREATE TABLE IF NOT EXISTS `audit` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED DEFAULT NULL,
      `actor` VARCHAR(190) DEFAULT NULL, `action` VARCHAR(80) DEFAULT NULL,
      `target` VARCHAR(190) DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL,
      `sev` VARCHAR(10) DEFAULT 'info', `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_action` (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['logs'] = "CREATE TABLE IF NOT EXISTS `logs` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `level` VARCHAR(10) DEFAULT 'info',
      `src` VARCHAR(40) DEFAULT NULL, `message` TEXT, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_level` (`level`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['shares'] = "CREATE TABLE IF NOT EXISTS `shares` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL,
      `token` VARCHAR(64) NOT NULL, `permission` VARCHAR(40) DEFAULT 'view',
      `expires_at` DATETIME DEFAULT NULL, `uses` INT DEFAULT 0, `max_uses` INT DEFAULT 0,
      `created_by` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_token` (`token`), KEY `ix_proj` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['exports'] = "CREATE TABLE IF NOT EXISTS `exports` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `kind` VARCHAR(80) DEFAULT NULL,
      `path` VARCHAR(255) DEFAULT NULL, `size` VARCHAR(40) DEFAULT NULL,
      `status` VARCHAR(20) DEFAULT 'ready', `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['comments'] = "CREATE TABLE IF NOT EXISTS `comments` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `project_id` INT UNSIGNED NOT NULL,
      `user_id` INT UNSIGNED DEFAULT NULL, `who` VARCHAR(120) DEFAULT NULL,
      `body` TEXT, `kind` VARCHAR(30) DEFAULT 'comment', `resolved` TINYINT(1) DEFAULT 0,
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_proj` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['marketplace_items'] = "CREATE TABLE IF NOT EXISTS `marketplace_items` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `seller_id` INT UNSIGNED NOT NULL,
      `title` VARCHAR(190) DEFAULT NULL, `cat` VARCHAR(60) DEFAULT 'asset',
      `description` TEXT, `price` DECIMAL(12,2) DEFAULT 0, `preview` VARCHAR(255) DEFAULT NULL,
      `file_path` VARCHAR(255) DEFAULT NULL, `sales` INT DEFAULT 0, `rating` DECIMAL(3,2) DEFAULT 0,
      `status` VARCHAR(20) DEFAULT 'active', `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_seller` (`seller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['marketplace_orders'] = "CREATE TABLE IF NOT EXISTS `marketplace_orders` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `item_id` INT UNSIGNED NOT NULL,
      `buyer_id` INT UNSIGNED NOT NULL, `amount` DECIMAL(12,2) DEFAULT 0,
      `commission` DECIMAL(12,2) DEFAULT 0, `status` VARCHAR(20) DEFAULT 'paid',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_buyer` (`buyer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['agencies'] = "CREATE TABLE IF NOT EXISTS `agencies` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(160) DEFAULT NULL,
      `owner_id` INT UNSIGNED NOT NULL, `seats` INT DEFAULT 5, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['agency_members'] = "CREATE TABLE IF NOT EXISTS `agency_members` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `agency_id` INT UNSIGNED NOT NULL,
      `user_id` INT UNSIGNED DEFAULT NULL, `email` VARCHAR(190) DEFAULT NULL,
      `role` VARCHAR(40) DEFAULT 'member', `status` VARCHAR(20) DEFAULT 'active',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_agency` (`agency_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['agency_clients'] = "CREATE TABLE IF NOT EXISTS `agency_clients` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `agency_id` INT UNSIGNED NOT NULL,
      `name` VARCHAR(160) DEFAULT NULL, `contact` VARCHAR(190) DEFAULT NULL,
      `status` VARCHAR(20) DEFAULT 'active', `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_agency` (`agency_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['brand_kits'] = "CREATE TABLE IF NOT EXISTS `brand_kits` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `name` VARCHAR(120) NOT NULL DEFAULT 'My brand',
      `logo` VARCHAR(255) DEFAULT NULL, `watermark` VARCHAR(255) DEFAULT NULL,
      `intro` VARCHAR(255) DEFAULT NULL, `outro` VARCHAR(255) DEFAULT NULL,
      `colors` TEXT, `fonts` TEXT, `style` VARCHAR(40) DEFAULT 'cinematic',
      `wm_pos` VARCHAR(20) DEFAULT 'bottom-right', `wm_opacity` TINYINT UNSIGNED DEFAULT 60,
      `kind` VARCHAR(20) DEFAULT 'kit', `is_default` TINYINT(1) DEFAULT 0,
      `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`), KEY `ix_kind` (`kind`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['social_accounts'] = "CREATE TABLE IF NOT EXISTS `social_accounts` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `platform` VARCHAR(30) DEFAULT NULL, `handle` VARCHAR(120) DEFAULT NULL,
      `token` TEXT, `refresh` TEXT, `expires_at` DATETIME DEFAULT NULL,
      `status` VARCHAR(24) DEFAULT 'NOT CONFIGURED', `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['publishing'] = "CREATE TABLE IF NOT EXISTS `publishing` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `platform` VARCHAR(30) DEFAULT NULL,
      `caption` TEXT, `scheduled_at` DATETIME DEFAULT NULL, `status` VARCHAR(20) DEFAULT 'draft',
      `result` TEXT, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['series'] = "CREATE TABLE IF NOT EXISTS `series` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `title` VARCHAR(190) DEFAULT NULL,
      `episodes` INT UNSIGNED NOT NULL DEFAULT 0, `style` VARCHAR(60) DEFAULT 'cinematic',
      `bible` LONGTEXT, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`),
      KEY `ix_series_project` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['series_episodes'] = "CREATE TABLE IF NOT EXISTS `series_episodes` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `series_id` INT UNSIGNED NOT NULL,
      `season` INT DEFAULT 1, `episode` INT DEFAULT 1, `title` VARCHAR(190) DEFAULT NULL,
      `project_id` INT UNSIGNED DEFAULT NULL, `status` VARCHAR(30) DEFAULT 'Idea',
      `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `ix_series` (`series_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $t['ad_events'] = "CREATE TABLE IF NOT EXISTS `ad_events` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT UNSIGNED NOT NULL,
      `placement` VARCHAR(60) DEFAULT NULL, `event` VARCHAR(30) DEFAULT 'view',
      `reward` INT DEFAULT 0, `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`), KEY `ix_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $t;
}

/** Canonical feature list used for governance, credits and limits (mirrors the spec). */
function sf_features() {
    return array(
        array('idea', 'AI Story Idea Engine', 1, 'free', 100),
        array('story', 'AI Story Generator', 4, 'free', 60),
        array('rewrite', 'Rewrite Studio', 1, 'free', 200),
        array('doctor', 'Story Doctor', 2, 'pro', 40),
        array('characters', 'Character Studio', 2, 'free', 80),
        array('scenes', 'Scene Engine', 3, 'free', 80),
        array('director', 'AI Director', 3, 'pro', 60),
        array('shots', 'Shot List', 2, 'pro', 80),
        array('storyboard', 'Storyboard', 1, 'free', 80),
        array('image', 'AI Image Generation', 2, 'free', 300),
        array('image_edit', 'Image Editor', 2, 'pro', 120),
        array('voice', 'Voice Studio', 3, 'pro', 60),
        array('music', 'Music Studio', 3, 'pro', 40),
        array('sfx', 'Sound Effects', 1, 'free', 100),
        array('video', 'Video Generation', 12, 'pro', 30),
        array('timeline', 'Video Timeline', 0, 'free', 500),
        array('subtitles', 'Subtitle Studio', 2, 'free', 80),
        array('thumb', 'Thumbnail Studio', 2, 'free', 80),
        array('social', 'Social Content Studio', 1, 'free', 120),
        array('clips', 'Auto Clips', 3, 'pro', 40),
        array('viral', 'Viral Optimizer', 1, 'pro', 60),
        array('factory', 'Content Factory', 6, 'studio', 20),
        array('series', 'Series Builder', 2, 'studio', 40),
        array('assets', 'Asset Library', 0, 'free', 1000),
        array('export', 'Export System', 3, 'free', 60),
        array('share', 'Sharing', 0, 'free', 200),
        array('marketplace', 'Marketplace', 0, 'free', 200),
        array('publishing', 'Social Publishing', 2, 'pro', 40),
        array('agency', 'Agency Workspace', 0, 'studio', 200),
        array('brandkit', 'Brand Kit', 0, 'pro', 100),
        array('analytics', 'Analytics', 0, 'free', 200),
        array('audit', 'Audit Logs', 0, 'free', 200),
        array('pwa', 'PWA', 0, 'free', 500),
        array('referrals', 'Referrals', 0, 'free', 100),
        array('coupons', 'Coupons', 0, 'free', 100),
        array('ads', 'Advertising', 0, 'free', 500),
        array('rewarded_ads', 'Rewarded Ads', 0, 'free', 20),
        array('notifications', 'Notifications', 0, 'free', 1000),
        array('announcements', 'Announcements', 0, 'free', 100),
        array('culture', 'Culture & Language', 0, 'free', 500),
        array('providers', 'AI Provider Admin', 0, 'free', 200),
        array('jobs', 'Job Management', 0, 'free', 1000),
        array('wallet', 'Wallet', 0, 'free', 200),
        array('subscriptions', 'Subscriptions', 0, 'free', 100),
        array('credits', 'Credit System', 0, 'free', 2000),
    );
}
