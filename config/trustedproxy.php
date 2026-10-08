<?php

/*
|--------------------------------------------------------------------------
| Trusted reverse proxies
|--------------------------------------------------------------------------
|
| Read by Laravel's TrustProxies middleware. Comma separated IPs or CIDR
| ranges in TRUSTED_PROXIES (load balancer, Nginx in front of PHP-FPM on
| another host…). Empty = no proxy trusted: X-Forwarded-* headers are
| ignored and the client IP is the TCP peer. Never "*" by default.
|
*/

return [

    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

];
