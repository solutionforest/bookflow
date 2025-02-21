<?php

namespace SolutionForest\Bookflow;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BookflowServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('bookflow')
            ->hasConfigFile()
            ->hasMigration('create_bookflow_table')
            ->hasCommand(Commands\BookflowCommand::class);
    }
}
