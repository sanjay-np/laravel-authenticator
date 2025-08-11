<?php

namespace LaravelAuthenticator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAuthenticatorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-authenticator')
            ->hasconfigfile('authenticator')
            ->hasviews();
    }
}
