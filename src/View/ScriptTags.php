<?php

namespace Siberfx\MessageBus\View;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Js;

/**
 * Renders the <script> tags for Blade pages:
 *
 *     @messageBusScripts   or   <x-message-bus::scripts />
 *
 * Exposes window.MessageBus (the browser client) and registers the Alpine
 * helpers (x-data="busListener(...)", x-data="busDispatch(...)", $bus).
 */
class ScriptTags
{
    public function __construct(protected Repository $config)
    {
    }

    /**
     * @param  array<string, mixed>  $options  Overrides for the client config.
     */
    public function render(array $options = []): string
    {
        $echo = (array) $this->config->get('message-bus.client.echo', []);
        $autoEcho = (bool) ($echo['enabled'] ?? false);
        $scripts = (array) ($echo['scripts'] ?? []);
        unset($echo['enabled'], $echo['scripts']);

        $clientConfig = array_replace([
            'endpoint' => app('router')->has('message-bus.dispatch') ? route('message-bus.dispatch', absolute: false) : null,
            'csrfToken' => csrf_token(),
            'echo' => $autoEcho ? array_filter($echo, fn ($value) => $value !== null) : null,
        ], $options);

        $html = '<script>window.MessageBusConfig = '.Js::from($clientConfig).';</script>'.PHP_EOL;

        if ($autoEcho) {
            foreach ($scripts as $src) {
                $html .= '<script src="'.e($src).'"></script>'.PHP_EOL;
            }
        }

        $html .= '<script type="module" src="'.e(route('message-bus.js', 'blade.js', absolute: false)).'"></script>'.PHP_EOL;

        return $html;
    }
}
