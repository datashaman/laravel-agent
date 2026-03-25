<?php

namespace DataShaman\LaravelAgent;

use DataShaman\LaravelAgent\Support\CacheTaskStore;
use DataShaman\LaravelAgent\Support\DatabaseTaskStore;
use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Support\ServiceProvider;

class LaravelAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-agent.php', 'laravel-agent');

        $this->app->singleton(TaskStore::class, function ($app) {
            return match ($app['config']['laravel-agent.task_store']) {
                'cache' => new CacheTaskStore(
                    $app['cache']->store($app['config']['laravel-agent.cache.store']),
                    $app['config']['laravel-agent.cache.prefix'],
                    $app['config']['laravel-agent.cache.ttl'],
                ),
                'database' => new DatabaseTaskStore(
                    $app['config']['laravel-agent.database.connection'],
                    $app['config']['laravel-agent.database.table'],
                ),
                default => new InMemoryTaskStore,
            };
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-agent.php' => config_path('laravel-agent.php'),
            ], 'laravel-agent-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-agent-migrations');
        }
    }
}
