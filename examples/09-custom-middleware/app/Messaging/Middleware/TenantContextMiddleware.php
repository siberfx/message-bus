<?php

namespace App\Messaging\Middleware;

use App\Messaging\Stamps\TenantStamp;
use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Support\Facades\App;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\ReceivedStamp;

/**
 * At dispatch: stamp the current tenant, locale and user.
 * In the worker: restore that context before the handlers run, then reset it.
 */
final class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TenantContext $tenants)
    {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);

        if ($stamp === null && $this->tenants->current() !== null) {
            $stamp = new TenantStamp($this->tenants->current()->id, App::getLocale(), auth()->id());
            $envelope = $envelope->with($stamp);
        }

        // Only switch context when the message comes from a transport.
        if ($stamp === null || $envelope->last(ReceivedStamp::class) === null) {
            return $next($envelope);
        }

        $previousTenant = $this->tenants->current();
        $previousLocale = App::getLocale();

        $this->tenants->switch(Tenant::findOrFail($stamp->tenantId));
        App::setLocale($stamp->locale);

        try {
            return $next($envelope);
        } finally {
            $this->tenants->switch($previousTenant);
            App::setLocale($previousLocale);
        }
    }
}
