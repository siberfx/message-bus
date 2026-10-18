<?php

namespace Siberfx\MessageBus\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Siberfx\MessageBus\Contracts\AuthorizesDispatch;
use Siberfx\MessageBus\Contracts\ValidatesPayload;
use Siberfx\MessageBus\Exception\HydrationException;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\HttpOriginStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Siberfx\MessageBus\Support\MessageHydrator;
use Siberfx\MessageBus\Support\MessageRegistry;
use Siberfx\MessageBus\Support\Normalizer;

/**
 * POST /message-bus/dispatch  {"message": "chat.send", "payload": {...}}
 *
 * 200: handled synchronously, 202: accepted and sent to an async transport.
 */
class DispatchController
{
    public function __invoke(
        Request $request,
        MessageBusManager $bus,
        MessageRegistry $registry,
        MessageHydrator $hydrator,
        ValidationFactory $validator,
    ): JsonResponse {
        $input = $request->validate([
            'message' => ['required', 'string', 'max:190'],
            'payload' => ['sometimes', 'nullable', 'array'],
        ]);

        $alias = $input['message'];
        $class = $registry->exposedClass($alias);

        abort_if($class === null, 404, sprintf('Message [%s] is not exposed.', $alias));

        $payload = $input['payload'] ?? [];

        if (is_subclass_of($class, ValidatesPayload::class)) {
            $payload = $validator->make($payload, $class::rules())->validate();
        }

        try {
            $message = $hydrator->hydrate($class, $payload);
        } catch (HydrationException $e) {
            throw ValidationException::withMessages([$e->field ?? 'payload' => $e->getMessage()]);
        }

        if ($message instanceof AuthorizesDispatch && ! $message->authorize($request->user(), $request)) {
            abort(403, 'This action is unauthorized.');
        }

        $envelope = $bus->dispatch($message, [new HttpOriginStamp(
            $request->user()?->getAuthIdentifier(),
            $request->header('X-Socket-ID'),
            $request->ip(),
        )]);

        $handled = $envelope->all(HandledStamp::class);
        $sent = array_values(array_filter(array_map(fn (SentStamp $stamp) => $stamp->senderAlias, $envelope->all(SentStamp::class))));

        return new JsonResponse([
            'id' => $envelope->last(UniqueIdStamp::class)?->id,
            'message' => $alias,
            'handled' => $handled !== [],
            'sent' => $sent,
            'result' => count($handled) === 1 ? Normalizer::normalize($handled[0]->result) : null,
        ], $handled === [] && $sent !== [] ? 202 : 200);
    }
}
