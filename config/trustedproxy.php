<?php

return [
    // Read by Laravel 13's TrustProxies middleware after configuration loads,
    // so this also works with config:cache. Never trust arbitrary proxies in production.
    'proxies' => env('TRUSTED_PROXIES'),
];
