<?php

return [
    'verify_url' => env(
        'CERTIFICADOS_VERIFY_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/certificados/verificar/{codigo}',
    ),
];
