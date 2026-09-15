<?php
class SFPlugin_pwa_pack extends PluginBase {
    public function route($route, $get, $post) {
        $base = sf_url('');
        if ($route === 'manifest.json') {
            $m = array(
                'name' => 'STORYFOUNDRY — AI Content Studio', 'short_name' => 'STORYFOUNDRY',
                'start_url' => sf_url('index.php?r=dashboard'), 'scope' => rtrim($base, '/') . '/',
                'display' => 'standalone', 'orientation' => 'portrait-primary',
                'background_color' => '#0c0d10', 'theme_color' => '#5b8def',
                'description' => 'From idea to finished story: AI video and content production studio.',
                'icons' => array(
                    array('src' => sf_url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'),
                    array('src' => sf_url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'),
                ),
            );
            return sf_response_raw(json_encode($m, JSON_UNESCAPED_SLASHES), 'application/manifest+json');
        }
        if ($route === 'sw.js') {
            $js = "const CACHE='sf-v1';\n"
                . "const SHELL=['" . sf_asset('css/app.css') . "','" . sf_asset('js/app.js') . "','" . sf_url('index.php?r=login') . "'];\n"
                . "self.addEventListener('install',e=>{self.skipWaiting();e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL)).catch(()=>{}))});\n"
                . "self.addEventListener('activate',e=>{e.waitUntil(self.clients.claim())});\n"
                . "self.addEventListener('fetch',e=>{\n"
                . "  const u=new URL(e.request.url);\n"
                . "  if(e.request.method!=='GET')return;\n"
                . "  if(u.searchParams.has('r')&&u.searchParams.get('r').indexOf('api')===0)return;\n"
                . "  e.respondWith(fetch(e.request).then(r=>{const c=r.clone();caches.open(CACHE).then(k=>k.put(e.request,c)).catch(()=>{});return r}).catch(()=>caches.match(e.request).then(m=>m||caches.match(SHELL[0]))))\n"
                . "});\n";
            return sf_response_raw($js, 'application/javascript');
        }
        return null;
    }
    public function boot() {
        add_action('layout.head', array($this, 'head'));
    }
    public function head() {
        echo '<link rel="manifest" href="' . e(sf_url('index.php?r=manifest.json')) . '">' . "\n";
        echo '<meta name="theme-color" content="#5b8def">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
        echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("' . e(sf_url('index.php?r=sw.js')) . '").catch(function(){})})}</script>' . "\n";
    }
}
