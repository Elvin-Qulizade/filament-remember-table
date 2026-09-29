<?php

namespace Sehsah\FilamentRememberTable;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentRememberTableServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-remember-table';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile('remember-table')
            ->hasTranslations()
            ->hasMigration('create_remember_table_views_table')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('sehsah/filament-remember-table');
            });
    }
}
