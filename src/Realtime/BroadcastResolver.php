<?php

namespace Siberfx\MessageBus\Realtime;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Siberfx\MessageBus\Contracts\ShouldBroadcastLive;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BroadcastStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\HttpOriginStamp;
use Siberfx\MessageBus\Stamp\NoBroadcastStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Siberfx\MessageBus\Support\MessageMetadata;
use Siberfx\MessageBus\Support\MessageRegistry;
use Siberfx\MessageBus\Support\Normalizer;

/**
 * Works out if, where and what to broadcast for a handled envelope.
 *
 * The broadcast payload always has the same shape:
 *
 *     {
 *       "id": "01j...",              // UniqueIdStamp, same id the HTTP endpoint returned
 *       "type": "chat.send",         // alias or FQCN of the message
 *       "event": "chat.message.sent",
 *       "data": {...},               // public properties or broadcastWith()
 *       "result": ...,               // handler result (opt-in)
 *       "sent_at": "2026-10-18T12:00:00+00:00"
 *     }
 */
class BroadcastResolver
{
    public function __construct(protected MessageRegistry $registry)
    {
    }

    /**
     * @return array{channels: list<string>, event: string, payload: array<string, mixed>, via: string|null}|null
     */
    public function resolve(Envelope $envelope): ?array
    {
        if ($envelope->last(NoBroadcastStamp::class) !== null) {
            return null;
        }

        $message = $envelope->getMessage();
        $data = null;

        if (($stamp = $envelope->last(BroadcastStamp::class)) !== null) {
            $channels = $this->resolveChannels($stamp->channels, $message);
            $event = $stamp->event;
            $via = $stamp->via;
            $data = $stamp->payload;
            $withResult = $stamp->withResult;
            $toOthers = $stamp->toOthers;
        } elseif ($message instanceof ShouldBroadcastLive) {
            if (method_exists($message, 'broadcastWhen') && ! $message->broadcastWhen()) {
                return null;
            }

            $channels = $this->resolveChannels((array) $message->broadcastOn(), $message);
            $event = method_exists($message, 'broadcastAs') ? $message->broadcastAs() : null;
            $via = method_exists($message, 'broadcastVia') ? $message->broadcastVia() : null;
            $data = method_exists($message, 'broadcastWith') ? $message->broadcastWith() : null;
            $withResult = method_exists($message, 'broadcastWithResult') && $message->broadcastWithResult();
            $toOthers = method_exists($message, 'broadcastToOthers') && $message->broadcastToOthers();
        } elseif (($attribute = MessageMetadata::broadcastOn($message::class)) !== null) {
            $channels = $this->resolveChannels($attribute->channels, $message);
            $event = $attribute->as;
            $via = $attribute->via;
            $withResult = $attribute->withResult;
            $toOthers = $attribute->toOthers;
        } else {
            return null;
        }

        $event ??= $this->defaultEventName($message);

        $payload = [
            'id' => $envelope->last(UniqueIdStamp::class)?->id,
            'type' => $this->registry->aliasFor($message::class) ?? $message::class,
            'event' => $event,
            'data' => Normalizer::normalize($data ?? Normalizer::toArray($message)),
            'sent_at' => Carbon::now()->toIso8601String(),
        ];

        if ($withResult) {
            $payload['result'] = Normalizer::normalize($envelope->last(HandledStamp::class)?->result);
        }

        if ($toOthers && ($socket = $envelope->last(HttpOriginStamp::class)?->socketId)) {
            // Laravel's Pusher/Reverb/Ably broadcasters exclude this socket.
            $payload['socket'] = $socket;
        }

        return [
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
            'via' => $via,
        ];
    }

    /**
     * "SendChatMessage" becomes "send.chat.message", unless the message has an alias.
     */
    public function defaultEventName(object $message): string
    {
        return $this->registry->aliasFor($message::class)
            ?? Str::snake(class_basename($message), '.');
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    protected function resolveChannels(array $channels, object $message): array
    {
        $data = null;

        return array_values(array_filter(array_map(function (string $channel) use ($message, &$data) {
            if (! str_contains($channel, '{')) {
                return $channel;
            }

            $data ??= Normalizer::toArray($message);

            return preg_replace_callback('/\{([\w.]+)\}/', function (array $match) use ($data, $message) {
                $value = Arr::get($data, $match[1], data_get($message, $match[1]));

                return is_scalar($value) ? (string) $value : $match[0];
            }, $channel);
        }, $channels)));
    }
}
