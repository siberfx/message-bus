<?php

namespace Siberfx\MessageBus\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the zero-build browser client (ES modules) used by Blade pages.
 */
class ClientScriptController
{
    /**
     * @var list<string>
     */
    public const FILES = ['core.js', 'alpine.js', 'blade.js'];

    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        abort_unless(in_array($file, self::FILES, true), 404);

        $response = new BinaryFileResponse(dirname(__DIR__, 3).'/resources/js/'.$file, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
        ], true, null, true, true);

        $response->setMaxAge(app()->isProduction() ? 86400 : 0)->setPublic();
        $response->isNotModified($request);

        return $response;
    }
}
