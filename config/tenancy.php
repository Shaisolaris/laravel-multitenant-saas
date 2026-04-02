<?php

return [
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'limits' => [
                'users' => 3,
                'teams' => 1,
                'api_calls' => 1000,
                'storage_mb' => 100,
            ],
            'features' => ['basic_api', 'single_team'],
        ],
        'starter' => [
            'name' => 'Starter',
            'price' => 29,
            'stripe_price_id' => env('STRIPE_PRICE_STARTER', 'price_starter_monthly'),
            'limits' => [
                'users' => 10,
                'teams' => 5,
                'api_calls' => 50000,
                'storage_mb' => 5000,
            ],
            'features' => ['basic_api', 'teams', 'api_keys', 'webhooks'],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 99,
            'stripe_price_id' => env('STRIPE_PRICE_PRO', 'price_pro_monthly'),
            'limits' => [
                'users' => 50,
                'teams' => 20,
                'api_calls' => 500000,
                'storage_mb' => 50000,
            ],
            'features' => ['basic_api', 'teams', 'api_keys', 'webhooks', 'priority_support', 'analytics'],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 299,
            'stripe_price_id' => env('STRIPE_PRICE_ENTERPRISE', 'price_enterprise_monthly'),
            'limits' => [
                'users' => -1,
                'teams' => -1,
                'api_calls' => -1,
                'storage_mb' => -1,
            ],
            'features' => ['basic_api', 'teams', 'api_keys', 'webhooks', 'priority_support', 'analytics', 'sso', 'audit_log', 'sla'],
        ],
    ],

    'trial_days' => 14,
    'db_prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
];
