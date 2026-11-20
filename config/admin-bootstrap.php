<?php

return [
    'name' => env('FOKUS_ADMIN_BOOTSTRAP_NAME'),
    'email' => env('FOKUS_ADMIN_BOOTSTRAP_EMAIL'),
    // Store the SHA-256 digest in external environment configuration, never the token itself.
    'token_sha256' => env('FOKUS_ADMIN_BOOTSTRAP_TOKEN_SHA256'),
];
