<?php

return [
    'version' => '1.0.0',

    'voucher_legend' => env('QXLOG_VOUCHER_LEGEND', 'Por honorarios correspondientes a servicios prestados en procedimientos quirúrgicos.'),
    'org_name' => env('QXLOG_ORG_NAME', 'Mi Hospital'),

    'default_rate' => (float) env('QXLOG_DEFAULT_RATE', 200.00),

    //Condiciones de pago
    'conditions_payment' => [
        'cash' => 'Efectivo',
        'check' => 'Cheque',
        'transfer' => 'Transferencia',
        'other' => 'Otro',
    ],

    //Roles quirúrgicos
    'roles' => [
        'surgeon' => 'Cirujano Principal',
        'assistant' => 'Ayudante',
        'anesthesiologist' => 'Anestesiólogo',
        'instrumentist' => 'Instrumentista',
        'circulating' => 'Circulante',
    ],

    'special' => [
        'enabled' => (bool) env('QXLOG_SPECIAL_ENABLED', true),

        'business_hours' => [
            'start' => env('QXLOG_BH_START', '05:00'),
            'end' => env('QXLOG_BH_END', '21:00'),
        ],

        'rates' => [
            'business_hours' => (float) env('QXLOG_RATE_BH', 200.00),
            'night' => (float) env('QXLOG_RATE_NIGHT', 250.00),
            'over_2hours' => (float) env('QXLOG_RATE_OVER2H', 300.00),
            'video' => (float) env('QXLOG_RATE_VIDEO', 300.00),
        ],
    ],

];