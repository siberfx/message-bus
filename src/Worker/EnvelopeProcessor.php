<?php

namespace Siberfx\MessageBus\Worker;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Events\MessageFailed;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\ErrorDetailsStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Stamp\SentToFailureTransportStamp;
use Siberfx\MessageBus\Stamp\TransportMessageIdStamp;
use Siberfx\MessageBus\Stamp\TransportNamesStamp;
use Siberfx\MessageBus\Transport\TransportManager;
use Throwable;

/**
 * Handles one envelope received from a transport: dispatches it on its bus,
 * acknowledges it on success, and retries or moves it to the failure
 * transport on error. Shared by the worker and the Laravel queue job.
 */
class EnvelopeProcessor
{
    public function __construct(
        protected MessageBusManager $manager,
        protected TransportManager $transports,
        protected ?Dispatcher $events = null,
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return bool Whether the message was handled successfully.
     */
    public function process(string $transportName, Envelope $envelope, TransportInterface $transport): bool
    {
        $busName = $envelope->last(BusNameStamp::class)?->busName;

        try {
            $this->manager->bus($busName)->dispatch($envelope->with(new ReceivedStamp($transportName)));
        } catch (Throwable $e) {
            $transport->reject($envelope);
            $this->handleFailure($transportName, $envelope, $transport, $e);

            return false;
        }

        $transport->ack($envelope);

        return true;
    }

    protected function handleFailure(string $transportName, Envelope $envelope, TransportInterface $transport, Throwable $e): void
    {
        $failed = $this->clean($e instanceof HandlerFailedException ? $e->getEnvelope() : $envelope);
        $retry = $this->transports->retryStrategy($transportName);
        $willRetry = $retry->isRetryable($failed, $e);

        $this->events?->dispatch(new MessageFailed($failed, $e, $transportName, $willRetry));

        if ($willRetry) {
            $delay = $retry->getWaitingTime($failed, $e);

            $transport->send($failed
                ->withoutAll(DelayStamp::class)
                ->with(
                    new RedeliveryStamp(RedeliveryStamp::getRetryCountFromEnvelope($failed) + 1),
                    new DelayStamp($delay),
                ));

            $this->logger?->warning(sprintf('[message-bus] Retrying %s from "%s" in %dms: %s', $failed->getMessage()::class, $transportName, $delay, $e->getMessage()));

            return;
        }

        $failureTransport = $this->transports->failureTransportFor($transportName);

        $this->logger?->error(sprintf('[message-bus] %s from "%s" failed: %s', $failed->getMessage()::class, $transportName, $e->getMessage()), ['exception' => $e]);

        if ($failureTransport === null || $failureTransport === $transportName) {
            return;
        }

        $this->transports->get($failureTransport)->send($failed
            ->withoutAll(DelayStamp::class)
            ->withoutAll(SentToFailureTransportStamp::class)
            ->withoutAll(ErrorDetailsStamp::class)
            ->with(
                new SentToFailureTransportStamp($envelope->last(SentToFailureTransportStamp::class)?->originalReceiverName ?? $transportName),
                ErrorDetailsStamp::create($e),
            ));
    }

    /**
     * Strip transport specific stamps and handler results before re-sending.
     * HandledStamps are kept (without results) so succeeded handlers are skipped.
     */
    protected function clean(Envelope $envelope): Envelope
    {
        $handled = array_map(
            fn (HandledStamp $stamp) => new HandledStamp(null, $stamp->handlerName),
            $envelope->all(HandledStamp::class),
        );

        return $envelope
            ->withoutAll(ReceivedStamp::class)
            ->withoutAll(TransportMessageIdStamp::class)
            ->withoutAll(TransportNamesStamp::class)
            ->withoutAll(HandledStamp::class)
            ->with(...$handled);
    }
}
