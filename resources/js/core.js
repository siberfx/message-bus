/**
 * @siberfx/message-bus — framework agnostic browser client.
 *
 *   const bus = createMessageBus({ endpoint: '/message-bus/dispatch' });
 *
 *   // Dispatch an exposed message (POST /message-bus/dispatch)
 *   const { id, result } = await bus.dispatch('chat.send', { room_id: 1, body: 'Hi' });
 *
 *   // Ask a query and get the handler result
 *   const order = await bus.ask('orders.find', { id: 42 });
 *
 *   // Listen to realtime events (Laravel Echo: Reverb, Pusher, Ably, Soketi)
 *   const stop = bus.listen('private-chat.1', 'chat.message.sent', (payload) => {});
 *   stop();
 *
 * No dependencies, no build step. Uses window.Echo unless `echo` is given.
 */

export class MessageBusError extends Error {
  constructor(message, status = 0, errors = {}, response = null) {
    super(message);
    this.name = 'MessageBusError';
    this.status = status;
    this.errors = errors;
    this.response = response;
  }

  get isValidationError() {
    return this.status === 422;
  }
}

const PRIVATE = 'private-';
const PRESENCE = 'presence-';

function readCookie(name) {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

function readMeta(name) {
  if (typeof document === 'undefined') return null;
  const el = document.querySelector(`meta[name="${name}"]`);
  return el ? el.getAttribute('content') : null;
}

export function eventName(event) {
  return event.startsWith('.') ? event : '.' + event;
}

export function createMessageBus(options = {}) {
  const config = {
    endpoint: '/message-bus/dispatch',
    headers: {},
    credentials: 'same-origin',
    csrfToken: null,
    echo: null,
    fetch: null,
    ...options,
  };

  /** @type {Map<string, {channel: any, events: Map<string, {callbacks: Set<Function>, handler: Function}>}>} */
  const subscriptions = new Map();
  /** @type {Map<string, Set<Function>>} */
  const hooks = new Map();
  let warnedNoEcho = false;

  function emit(type, detail) {
    (hooks.get(type) || []).forEach((cb) => {
      try {
        cb(detail);
      } catch (e) {
        console.error(e);
      }
    });
  }

  function on(type, callback) {
    if (!hooks.has(type)) hooks.set(type, new Set());
    hooks.get(type).add(callback);
    return () => hooks.get(type).delete(callback);
  }

  function echo() {
    const instance = typeof config.echo === 'function'
      ? config.echo()
      : (config.echo && typeof config.echo.channel === 'function' ? config.echo : (typeof window !== 'undefined' ? window.Echo : null));

    if (!instance || typeof instance.channel !== 'function') {
      if (!warnedNoEcho) {
        warnedNoEcho = true;
        console.warn('[message-bus] Laravel Echo is not configured; realtime listeners are disabled.');
      }
      return null;
    }

    return instance;
  }

  function csrfHeaders() {
    const token = config.csrfToken || readMeta('csrf-token');
    if (token) return { 'X-CSRF-TOKEN': token };
    const xsrf = readCookie('XSRF-TOKEN');
    return xsrf ? { 'X-XSRF-TOKEN': xsrf } : {};
  }

  function socketId() {
    const instance = echo();
    return instance && typeof instance.socketId === 'function' ? instance.socketId() : null;
  }

  /**
   * Dispatch an exposed message by alias.
   * Resolves with {id, message, handled, sent, result}.
   */
  async function dispatch(message, payload = {}, requestOptions = {}) {
    if (!config.endpoint) {
      throw new MessageBusError('The message bus HTTP endpoint is disabled.');
    }

    const headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...csrfHeaders(),
      ...config.headers,
      ...(requestOptions.headers || {}),
    };

    const socket = socketId();
    if (socket) headers['X-Socket-ID'] = socket;

    emit('dispatching', { message, payload });

    const doFetch = config.fetch || fetch;
    let response;

    try {
      response = await doFetch(requestOptions.endpoint || config.endpoint, {
        method: 'POST',
        headers,
        credentials: config.credentials,
        body: JSON.stringify({ message, payload }),
        signal: requestOptions.signal,
      });
    } catch (e) {
      const error = new MessageBusError(e.message || 'Network error');
      emit('error', { message, payload, error });
      throw error;
    }

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
      const error = new MessageBusError(body.message || response.statusText, response.status, body.errors || {}, body);
      emit('error', { message, payload, error });
      throw error;
    }

    emit('dispatched', { message, payload, response: body });

    return body;
  }

  /**
   * Dispatch a query and resolve with the handler result.
   */
  async function ask(message, payload = {}, requestOptions = {}) {
    const response = await dispatch(message, payload, requestOptions);
    return response.result;
  }

  function channelFor(name) {
    const instance = echo();
    if (!instance) return null;
    if (name.startsWith(PRIVATE)) return instance.private(name.slice(PRIVATE.length));
    if (name.startsWith(PRESENCE)) return instance.join(name.slice(PRESENCE.length));
    return instance.channel(name);
  }

  function subscription(name) {
    let sub = subscriptions.get(name);
    if (sub) return sub;

    const channel = channelFor(name);
    if (!channel) return null;

    sub = { channel, events: new Map() };
    subscriptions.set(name, sub);
    return sub;
  }

  /**
   * Listen to an event on a channel. Returns an unsubscribe function.
   * Channels are left automatically once their last listener is removed.
   */
  function listen(channelName, event, callback) {
    const events = Array.isArray(event) ? event : [event];
    const stops = events.map((single) => listenOne(channelName, single, callback));
    return () => stops.forEach((stop) => stop());
  }

  function listenOne(channelName, event, callback) {
    const sub = subscription(channelName);
    if (!sub) return () => {};

    let entry = sub.events.get(event);

    if (!entry) {
      const callbacks = new Set();
      const handler = (payload) => {
        callbacks.forEach((cb) => cb(payload));
        emit('message', { channel: channelName, event, payload });
      };
      sub.channel.listen(eventName(event), handler);
      entry = { callbacks, handler };
      sub.events.set(event, entry);
    }

    entry.callbacks.add(callback);

    return () => {
      entry.callbacks.delete(callback);

      if (entry.callbacks.size === 0) {
        if (typeof sub.channel.stopListening === 'function') {
          sub.channel.stopListening(eventName(event), entry.handler);
        }
        sub.events.delete(event);
      }

      if (sub.events.size === 0) leave(channelName);
    };
  }

  /**
   * Listen once, then unsubscribe.
   */
  function once(channelName, event, callback) {
    const stop = listen(channelName, event, (payload) => {
      stop();
      callback(payload);
    });
    return stop;
  }

  function leave(channelName) {
    subscriptions.delete(channelName);
    const instance = echo();
    if (instance && typeof instance.leaveChannel === 'function') {
      instance.leaveChannel(channelName);
    }
  }

  /**
   * Presence channels: here / joining / leaving callbacks.
   * Accepts "room.1" or "presence-room.1".
   */
  function presence(channelName, { here, joining, leaving, error } = {}) {
    const name = channelName.startsWith(PRESENCE) ? channelName : PRESENCE + channelName;
    const sub = subscription(name);
    if (!sub) return () => {};

    if (here) sub.channel.here(here);
    if (joining) sub.channel.joining(joining);
    if (leaving) sub.channel.leaving(leaving);
    if (error && typeof sub.channel.error === 'function') sub.channel.error(error);

    return () => {
      if (sub.events.size === 0) leave(name);
    };
  }

  /**
   * Client events (typing indicators...). Requires a private/presence channel
   * and client events enabled in Reverb / Pusher.
   */
  function whisper(channelName, event, data = {}) {
    const sub = subscription(channelName);
    if (sub && typeof sub.channel.whisper === 'function') sub.channel.whisper(event, data);
  }

  function onWhisper(channelName, event, callback) {
    const sub = subscription(channelName);
    if (!sub || typeof sub.channel.listenForWhisper !== 'function') return () => {};
    sub.channel.listenForWhisper(event, callback);
    return () => {
      if (typeof sub.channel.stopListeningForWhisper === 'function') {
        sub.channel.stopListeningForWhisper(event, callback);
      }
    };
  }

  return {
    config,
    dispatch,
    ask,
    listen,
    once,
    leave,
    presence,
    whisper,
    onWhisper,
    on,
    echo,
    socketId,
  };
}

export default createMessageBus;
