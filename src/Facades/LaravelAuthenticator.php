<?php

namespace LaravelAuthenticator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelAuthenticator\LaravelAuthenticator
 *
 * */
class LaravelAuthenticator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaravelAuthenticator\LaravelAuthenticator::class;
    }
}
