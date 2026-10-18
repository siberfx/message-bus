<?php

namespace App\MessageHandlers;

use App\Messages\PostComment;
use App\Messages\UpdateOrderStatus;
use App\Models\Order;
use App\Models\Post;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\HttpOriginStamp;

final class CommentHandlers
{
    /**
     * The returned array is broadcast as "result" (withResult: true).
     */
    #[AsMessageHandler]
    public function post(PostComment $message, Envelope $envelope): array
    {
        $userId = $envelope->last(HttpOriginStamp::class)?->userId ?? auth()->id();

        $comment = Post::findOrFail($message->postId)->comments()->create([
            'user_id' => $userId,
            'body' => $message->body,
        ]);

        return [
            'id' => $comment->id,
            'author' => $comment->user->name,
            'avatar' => $comment->user->avatar_url,
            'created_at' => $comment->created_at->toIso8601String(),
        ];
    }

    #[AsMessageHandler]
    public function status(UpdateOrderStatus $message): void
    {
        Order::whereKey($message->orderId)->update(['status' => $message->status]);
    }
}
