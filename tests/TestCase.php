<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use LaravelAuthenticator\LaravelAuthenticatorServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelAuthenticatorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // In-memory sqlite for fast tests
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set a valid APP_KEY for Crypt to work
        $key = base64_encode(str_repeat('a', 32));
        $app['config']->set('app.key', "base64:$key");
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create the table used by the package model
        Schema::create('authenticator_secrets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('label');
            $table->text('secret');
            $table->string('algorithm')->default('sha1');
            $table->integer('digits')->default(6);
            $table->integer('period')->default(60);
            $table->string('issuer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}