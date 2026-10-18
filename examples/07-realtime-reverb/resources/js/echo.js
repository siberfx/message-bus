// resources/js/echo.js — created by `php artisan install:broadcasting`.
// The message bus clients use window.Echo unless you pass `echo` explicitly.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
});

// Plain JS usage:
//
// import { createMessageBus } from '../../vendor/siberfx/message-bus/resources/js/core.js';
// const bus = createMessageBus();
// bus.listen('private-posts.12', 'comment.posted', ({ data, result }) => renderComment(result));
// bus.listen('presence-shop-floor', ['order.status.changed', 'orders.bulk.shipped'], refresh);
