<?php

namespace App\Messages;

final class RebuildSearchIndex
{
    public function __construct(
        public readonly string $model,
        public readonly int $chunk = 500,
    ) {
    }
}
