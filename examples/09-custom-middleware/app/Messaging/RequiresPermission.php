<?php

namespace App\Messaging;

interface RequiresPermission
{
    /**
     * @return array{0: string, 1?: mixed} The Gate ability and its arguments.
     */
    public function permission(): array;
}
