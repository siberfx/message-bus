/**
 * Vue 3 integration.
 *
 *   import { MessageBusPlugin } from '@siberfx/message-bus/vue';
 *   app.use(MessageBusPlugin, { endpoint: '/message-bus/dispatch' });
 *
 *   const { dispatch, loading, errors } = useDispatch('chat.send');
 *   useLiveEvent(() => `private-chat.${props.roomId}`, 'chat.message.sent', (payload) => {});
 *
 * Channels and payloads may be plain values, refs or getters.
 */
import { getCurrentInstance, inject, onBeforeUnmount, ref, shallowRef, toValue, watch } from 'vue';
import { createMessageBus } from './core.js';

export { createMessageBus, MessageBusError } from './core.js';

export const MessageBusKey = Symbol.for('siberfx.message-bus');

export const MessageBusPlugin = {
  install(app, options = {}) {
    const bus = options.bus || createMessageBus(options);
    app.provide(MessageBusKey, bus);
    app.config.globalProperties.$bus = bus;
  },
};

export function useMessageBus() {
  const bus = inject(MessageBusKey, null);
  if (!bus) throw new Error('useMessageBus() requires app.use(MessageBusPlugin).');
  return bus;
}

function onCleanup(fn) {
  if (getCurrentInstance()) onBeforeUnmount(fn);
}

/**
 * Listen to a realtime event. Re-subscribes when the channel changes.
 * Returns a stop() function.
 */
export function useLiveEvent(channel, event, handler) {
  const bus = useMessageBus();
  let stopListening = null;

  const stopWatch = watch(
    () => [toValue(channel), toValue(event)],
    ([name, evt]) => {
      if (stopListening) stopListening();
      stopListening = name && evt ? bus.listen(name, evt, (payload) => handler(payload)) : null;
    },
    { immediate: true },
  );

  const stop = () => {
    stopWatch();
    if (stopListening) stopListening();
    stopListening = null;
  };

  onCleanup(stop);

  return stop;
}

export function useDispatch(message) {
  const bus = useMessageBus();
  const loading = ref(false);
  const error = shallowRef(null);
  const errors = ref({});
  const data = shallowRef(null);

  async function dispatch(payload = {}, options = {}) {
    loading.value = true;
    error.value = null;
    errors.value = {};

    try {
      data.value = await bus.dispatch(toValue(message), toValue(payload), options);
      return data.value;
    } catch (e) {
      error.value = e;
      errors.value = e.errors || {};
      throw e;
    } finally {
      loading.value = false;
    }
  }

  function reset() {
    loading.value = false;
    error.value = null;
    errors.value = {};
    data.value = null;
  }

  return { dispatch, reset, loading, error, errors, data };
}

/**
 * Ask a query, re-run it when the payload changes or a realtime event arrives.
 *
 *   const { data, loading, refetch } = useLiveQuery('orders.find', () => ({ id: props.id }), {
 *     refreshOn: () => [[`private-orders.${props.id}`, 'order.status.changed']],
 *   });
 */
export function useLiveQuery(message, payload = {}, { enabled = true, refreshOn = [], initialData = null } = {}) {
  const bus = useMessageBus();
  const data = shallowRef(initialData);
  const loading = ref(false);
  const error = shallowRef(null);
  let requestId = 0;

  async function refetch() {
    const id = ++requestId;
    loading.value = true;
    error.value = null;
    try {
      const result = await bus.ask(toValue(message), toValue(payload));
      if (id === requestId) data.value = result;
      return result;
    } catch (e) {
      if (id === requestId) error.value = e;
      return undefined;
    } finally {
      if (id === requestId) loading.value = false;
    }
  }

  const stopQuery = watch(
    () => [toValue(enabled), JSON.stringify(toValue(payload))],
    ([isEnabled]) => {
      if (isEnabled) refetch();
    },
    { immediate: true },
  );

  let stops = [];
  const stopRefresh = watch(
    () => JSON.stringify(toValue(refreshOn)),
    (json) => {
      stops.forEach((stop) => stop());
      stops = JSON.parse(json).map(([channel, event]) => bus.listen(channel, event, () => toValue(enabled) && refetch()));
    },
    { immediate: true },
  );

  onCleanup(() => {
    stopQuery();
    stopRefresh();
    stops.forEach((stop) => stop());
  });

  return { data, loading, error, refetch };
}

/**
 * Collect realtime payloads into a reactive list.
 */
export function useLiveList(channel, event, { initial = [], max = null, map = (payload) => payload, key = 'id' } = {}) {
  const items = ref([...initial]);

  useLiveEvent(channel, event, (payload) => {
    const item = map(payload);
    const identity = item && key ? item[key] : undefined;
    const index = identity !== undefined ? items.value.findIndex((i) => i && i[key] === identity) : -1;

    if (index >= 0) items.value.splice(index, 1, item);
    else items.value.push(item);

    if (max && items.value.length > max) items.value.splice(0, items.value.length - max);
  });

  return items;
}

/**
 * Reactive members of a presence channel.
 */
export function usePresence(channel, { key = 'id' } = {}) {
  const bus = useMessageBus();
  const members = ref([]);
  let leave = null;

  const stopWatch = watch(
    () => toValue(channel),
    (name) => {
      if (leave) leave();
      members.value = [];
      leave = name
        ? bus.presence(name, {
          here: (users) => { members.value = users; },
          joining: (user) => { members.value = [...members.value.filter((u) => u[key] !== user[key]), user]; },
          leaving: (user) => { members.value = members.value.filter((u) => u[key] !== user[key]); },
        })
        : null;
    },
    { immediate: true },
  );

  onCleanup(() => {
    stopWatch();
    if (leave) leave();
  });

  return members;
}

/**
 * Client events (typing indicators): const whisper = useWhisper(channel, 'typing', handler);
 */
export function useWhisper(channel, event, handler = null) {
  const bus = useMessageBus();

  if (handler) {
    let stop = null;
    const stopWatch = watch(
      () => toValue(channel),
      (name) => {
        if (stop) stop();
        stop = name ? bus.onWhisper(name, event, handler) : null;
      },
      { immediate: true },
    );
    onCleanup(() => {
      stopWatch();
      if (stop) stop();
    });
  }

  return (data = {}) => bus.whisper(toValue(channel), event, data);
}
