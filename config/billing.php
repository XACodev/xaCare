<?php

return [

    /*
    | Driver de cobro. `manual` = el super admin asigna plan/estado en /hospitals.
    | `stripe` queda reservado para Laravel Cashier cuando existan STRIPE_KEY y
    | STRIPE_SECRET reales (Hospital como Billable). No hay procesador inventado.
    */
    'driver' => env('BILLING_DRIVER', 'manual'),

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    'plans' => [
        'basic' => [
            'name' => 'Básico',
            'stripe_price_id' => env('STRIPE_PRICE_BASIC'),
            'features' => [
                'qxlog',
                'patients',
                'admissions',
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'stripe_price_id' => env('STRIPE_PRICE_PRO'),
            'features' => [
                'qxlog',
                'patients',
                'admissions',
                'insurance',
            ],
        ],
    ],

    /*
    | Features del destino HIS. Hoy no tienen rutas; el mismo hasFeature() las
    | activará cuando existan los paquetes PRO.
    */
    'reserved_features' => [
        'ine',
        'pdf_forms',
        'ehr',
        'insurance_automation',
    ],

];
