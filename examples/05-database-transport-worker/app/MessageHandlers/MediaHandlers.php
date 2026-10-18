<?php

namespace App\MessageHandlers;

use App\Messages\RebuildSearchIndex;
use App\Messages\ResizeImage;
use App\Models\Media;
use App\Services\ImageResizer;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

final class MediaHandlers
{
    public function __construct(private readonly ImageResizer $resizer)
    {
    }

    #[AsMessageHandler]
    public function resize(ResizeImage $message): void
    {
        $media = Media::findOrFail($message->mediaId);

        foreach ($message->widths as $width) {
            $this->resizer->resize($media, $width);
        }
    }

    #[AsMessageHandler]
    public function reindex(RebuildSearchIndex $message): void
    {
        $message->model::query()->chunkById($message->chunk, fn ($models) => $models->searchable());
    }
}
