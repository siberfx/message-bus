{{--
    A form that dispatches an exposed message over HTTP (no page reload):

    <x-message-bus::form message="chat.send" :payload="['room_id' => $room->id]">
        <input name="body" x-bind:disabled="loading">
        <p x-show="errors.body" x-text="errors.body?.[0]"></p>
        <button x-bind:disabled="loading">Send</button>
    </x-message-bus::form>

    Fields are merged over the static :payload. Scope: loading, error, errors, result.
    Dispatches "bus-dispatched" (detail = response) or "bus-failed" (detail = error).
--}}
@props(['message', 'payload' => [], 'reset' => true])
<form
    x-data="busDispatch({{ \Illuminate\Support\Js::from($message) }}, {{ \Illuminate\Support\Js::from(['payload' => (object) $payload, 'reset' => (bool) $reset]) }})"
    x-on:submit.prevent="submit($event)"
    {{ $attributes }}
>
    {{ $slot }}
</form>
