<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public web mirror
    |--------------------------------------------------------------------------
    |
    | Apache serves this directory. The Laravel source stays outside htdocs.
    |
    */

    'public_path' => env('PLANTGRID_PUBLIC_PATH'),

    'display_timezone' => 'Africa/Nairobi',

    'outage_hostname' => 'PLT-SW-PACK',

    'degraded_hostname' => 'PLT-FS01',

];
