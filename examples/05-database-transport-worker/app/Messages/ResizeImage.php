<?php

namespace App\Messages;

final class ResizeImage
{
    /**
     * @param  list<int>  $widths
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly array $widths = [320, 768, 1280],
    ) {
    }
}
