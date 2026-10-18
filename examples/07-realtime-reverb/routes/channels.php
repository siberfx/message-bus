<?php

use App\Models\Post;
use Illuminate\Support\Facades\Broadcast;

// "private-posts.{postId}" on the server  →  "posts.{post}" here (Laravel strips "private-").
Broadcast::channel('posts.{post}', fn ($user, Post $post) => $user->can('view', $post));

Broadcast::channel('customers.{customerId}', fn ($user, int $customerId) => $user->customer_id === $customerId);

Broadcast::channel('admin', fn ($user) => $user->is_admin);

Broadcast::channel('orders.{orderId}', fn ($user) => $user->is_admin);

// Presence channels return the member info shown to others.
Broadcast::channel('shop-floor', fn ($user) => $user->is_staff
    ? ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->avatar_url]
    : false);

// "admin.activity" is public: no authorization needed.
