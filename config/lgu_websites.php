<?php

return [
    // Enable only after DNS, HTTPS and the virtual host serve the same public/ root.
    // Leave blank to use APP_URL/lgus/{slug} without subdomains.
    'domain' => env('LGU_WEBSITE_DOMAIN'),
];
