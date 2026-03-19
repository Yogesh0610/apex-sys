<?php

namespace Apexsys\ServerMigration;

use Illuminate\Support\ServiceProvider;
use Apexsys\ServerMigration\Commands\InstallPackageCommand;
use Apexsys\ServerMigration\Commands\MigrateServerCommand;

class ServerMigrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/server-migration.php',
            'server-migration'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/server-migration.php' => config_path('server-migration.php'),
        ], 'server-migration-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'server-migration-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/server-migration'),
        ], 'server-migration-views');

        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'server-migration'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallPackageCommand::class,
                MigrateServerCommand::class,
            ]);
        }
    }
}
