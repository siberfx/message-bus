<?php

namespace Siberfx\MessageBus\Contracts;

/**
 * Implement on a message to validate its payload. Used by the HTTP endpoint
 * (before hydration) and by the ValidationMiddleware (on any dispatch).
 */
interface ValidatesPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array;
}
