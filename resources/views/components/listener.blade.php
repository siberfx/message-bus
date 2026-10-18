{{--
    Listens to a realtime event and exposes the received payloads to Alpine:

    <x-message-bus::listener channel="private-orders.{{ $order->id }}" event="order.status.changed" :max="20">
        <template x-for="item in messages" :key="item.id">
            <p x-text="item.data.status"></p>
        </template>
        <span x-text="count"></span>
    </x-message-bus::listener>

    Scope: messages (array), last (latest payload), count (number received).
    Also dispatches a "bus-message" browser event with the payload.
--}}
@props(['channel', 'event', 'max' => null, 'as' => 'div'])
<{{ $as }} x-data="busListener({{ \Illuminate\Support\Js::from(['channel' => $channel, 'event' => $event, 'max' => $max]) }})" {{ $attributes }}>
    {{ $slot }}
</{{ $as }}>
