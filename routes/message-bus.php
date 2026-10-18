<?php

use Illuminate\Support\Facades\Route;
use Siberfx\MessageBus\Http\Controllers\ClientScriptController;
use Siberfx\MessageBus\Http\Controllers\DispatchController;

$config = config('message-bus.http');

Route::prefix($config['prefix'] ?? 'message-bus')
    ->as('message-bus.')
    ->group(function () use ($config) {
        Route::get('js/{file}', ClientScriptController::class)
            ->where('file', '[a-z\-]+\.js')
            ->name('js');

        if ($config['enabled'] ?? true) {
            $route = Route::post('dispatch', DispatchController::class)
                ->middleware($config['middleware'] ?? ['web'])
                ->name('dispatch');

            if (! empty($config['throttle'])) {
                $route->middleware('throttle:'.$config['throttle']);
            }
        }
    });
