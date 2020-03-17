<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\ExampleEvent' => [
            'App\Listeners\ExampleListener',
        ],
        'App\Events\Base\BalanceEvent' => [
            'App\Listeners\Base\BalanceListener',
        ],
        'App\Events\Base\AdEvent' => [
            'App\Listeners\Base\AdListener',
        ],
        'App\Events\Base\ItemEvent' => [
            'App\Listeners\TestItemListener',
        ]
    ];
}
