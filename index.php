<?php
/**
 * STORYFOUNDRY — front controller (cPanel / Apache / PHP 7.4+)
 * Everything funnels through here. No composer, no build step.
 */
declare(strict_types=0);
require_once __DIR__ . '/core/bootstrap.php';   /* loads sf_session_harden() + headers + guard */
session_start();

if (!sf_installed()) { sf_redirect('install.php'); }

$route = isset($_GET['r']) ? trim($_GET['r'], '/') : 'home';
if ($route === '' ) { $route = 'home'; }

sf_boot();                 // db, auth, plugins, feature flags
$out = sf_route($route);   // returns ['title'=>, 'view'=>, 'vars'=>[]] or raw
sf_render_response($out);
