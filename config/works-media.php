<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Known transport ceiling
    |--------------------------------------------------------------------------
    |
    | Optional deployment-level request-body ceiling in kilobytes. Keep this
    | at or below the reverse proxy / ingress limit when one is present.
    | A null value leaves PHP and the work media policy as the known limits.
    |
    */
    'transport_max_kb' => env('WORKS_MEDIA_TRANSPORT_MAX_KB'),
];
