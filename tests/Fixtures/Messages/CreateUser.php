<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Contracts\ValidatesPayload;

#[AsMessage(alias: 'user.create', exposed: true)]
final class CreateUser implements ValidatesPayload
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly Status $status = Status::Active,
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'status' => ['sometimes', 'string'],
        ];
    }
}
