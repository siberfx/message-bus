<?php

namespace App\Messages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Attribute\BroadcastOn;
use Siberfx\MessageBus\Contracts\AuthorizesDispatch;
use Siberfx\MessageBus\Contracts\ValidatesPayload;

/**
 * Dispatched by the browser: bus.dispatch('comments.post', { postId: 12, body: 'Nice!' }).
 * Broadcast to everybody watching the post, except the author's own tab.
 */
#[AsMessage(alias: 'comments.post', exposed: true)]
#[BroadcastOn(['private-posts.{postId}', 'admin.activity'], as: 'comment.posted', withResult: true, toOthers: true)]
final class PostComment implements AuthorizesDispatch, ValidatesPayload
{
    public function __construct(
        public readonly int $postId,
        public readonly string $body,
    ) {
        // Never accept the author id from the payload: the handler reads the
        // authenticated user from the HttpOriginStamp instead.
    }

    public static function rules(): array
    {
        return [
            'postId' => ['required', 'integer', 'exists:posts,id'],
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ];
    }

    public function authorize(?Authenticatable $user, Request $request): bool
    {
        return $user !== null && $user->can('comment', \App\Models\Post::find($this->postId));
    }
}
