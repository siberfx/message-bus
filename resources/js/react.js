/**
 * React integration.
 *
 *   import { MessageBusProvider, useDispatch, useLiveEvent } from '@siberfx/message-bus/react';
 *
 *   <MessageBusProvider config={{ endpoint: '/message-bus/dispatch' }}>
 *     <App />
 *   </MessageBusProvider>
 *
 * Written with createElement (no JSX) so it works from vendor/ without a
 * JSX transform. Requires React 18+.
 */
import { createContext, createElement, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { createMessageBus } from './core.js';

export { createMessageBus, MessageBusError } from './core.js';

const MessageBusContext = createContext(null);

export function MessageBusProvider({ bus, config, children }) {
  const value = useMemo(() => bus || createMessageBus(config || {}), [bus]);
  return createElement(MessageBusContext.Provider, { value }, children);
}

export function useMessageBus() {
  const bus = useContext(MessageBusContext);
  if (!bus) throw new Error('useMessageBus() must be used inside <MessageBusProvider>.');
  return bus;
}

/**
 * Run `handler` for every `event` on `channel`. Pass null as channel to pause.
 */
export function useLiveEvent(channel, event, handler) {
  const bus = useMessageBus();
  const handlerRef = useRef(handler);
  handlerRef.current = handler;
  const eventKey = Array.isArray(event) ? event.join('|') : event;

  useEffect(() => {
    if (!channel || !event) return undefined;
    return bus.listen(channel, event, (payload) => handlerRef.current(payload));
  }, [bus, channel, eventKey]);
}

/**
 * const { dispatch, loading, error, errors, data, reset } = useDispatch('chat.send');
 * await dispatch({ room_id: 1, body });
 */
export function useDispatch(message) {
  const bus = useMessageBus();
  const [state, setState] = useState({ loading: false, error: null, errors: {}, data: null });
  const mounted = useRef(true);

  useEffect(() => () => {
    mounted.current = false;
  }, []);

  const dispatch = useCallback(async (payload = {}, options = {}) => {
    setState((s) => ({ ...s, loading: true, error: null, errors: {} }));
    try {
      const data = await bus.dispatch(message, payload, options);
      if (mounted.current) setState({ loading: false, error: null, errors: {}, data });
      return data;
    } catch (error) {
      if (mounted.current) setState({ loading: false, error, errors: error.errors || {}, data: null });
      throw error;
    }
  }, [bus, message]);

  const reset = useCallback(() => setState({ loading: false, error: null, errors: {}, data: null }), []);

  return { dispatch, reset, ...state };
}

/**
 * Ask a query and keep the result fresh:
 *
 *   const { data, loading, refetch } = useLiveQuery('orders.find', { id }, {
 *     refreshOn: [[`private-orders.${id}`, 'order.status.changed']],
 *   });
 */
export function useLiveQuery(message, payload = {}, { enabled = true, refreshOn = [], initialData = null } = {}) {
  const bus = useMessageBus();
  const [state, setState] = useState({ data: initialData, loading: enabled, error: null });
  const payloadKey = JSON.stringify(payload);
  const refreshKey = JSON.stringify(refreshOn);
  const requestId = useRef(0);

  const refetch = useCallback(async () => {
    const id = ++requestId.current;
    setState((s) => ({ ...s, loading: true, error: null }));
    try {
      const data = await bus.ask(message, JSON.parse(payloadKey));
      if (id === requestId.current) setState({ data, loading: false, error: null });
      return data;
    } catch (error) {
      if (id === requestId.current) setState((s) => ({ ...s, loading: false, error }));
      return undefined;
    }
  }, [bus, message, payloadKey]);

  useEffect(() => {
    if (enabled) refetch();
  }, [enabled, refetch]);

  useEffect(() => {
    if (!enabled) return undefined;
    const stops = JSON.parse(refreshKey).map(([channel, event]) => bus.listen(channel, event, () => refetch()));
    return () => stops.forEach((stop) => stop());
  }, [bus, enabled, refreshKey, refetch]);

  return { ...state, refetch };
}

/**
 * Collect realtime payloads into a list (chat messages, activity feeds...).
 *
 *   const [messages, setMessages] = useLiveList('private-chat.1', 'chat.message.sent', { max: 100 });
 */
export function useLiveList(channel, event, { initial = [], max = null, map = (payload) => payload, key = 'id' } = {}) {
  const [items, setItems] = useState(initial);
  const mapRef = useRef(map);
  mapRef.current = map;

  useLiveEvent(channel, event, (payload) => {
    const item = mapRef.current(payload);
    setItems((current) => {
      const identity = item && key ? item[key] : undefined;
      const next = identity !== undefined && current.some((i) => i && i[key] === identity)
        ? current.map((i) => (i && i[key] === identity ? item : i))
        : [...current, item];
      return max && next.length > max ? next.slice(next.length - max) : next;
    });
  });

  return [items, setItems];
}

/**
 * Members of a presence channel: const members = usePresence('room.1');
 */
export function usePresence(channel, { key = 'id' } = {}) {
  const bus = useMessageBus();
  const [members, setMembers] = useState([]);

  useEffect(() => {
    if (!channel) return undefined;
    setMembers([]);
    return bus.presence(channel, {
      here: (users) => setMembers(users),
      joining: (user) => setMembers((list) => [...list.filter((u) => u[key] !== user[key]), user]),
      leaving: (user) => setMembers((list) => list.filter((u) => u[key] !== user[key])),
    });
  }, [bus, channel, key]);

  return members;
}

/**
 * Client events: const whisper = useWhisper('private-chat.1', 'typing', (data) => ...);
 * whisper({ name: 'Ada' });
 */
export function useWhisper(channel, event, handler) {
  const bus = useMessageBus();
  const handlerRef = useRef(handler);
  handlerRef.current = handler;

  useEffect(() => {
    if (!channel || !handlerRef.current) return undefined;
    return bus.onWhisper(channel, event, (data) => handlerRef.current && handlerRef.current(data));
  }, [bus, channel, event]);

  return useCallback((data = {}) => bus.whisper(channel, event, data), [bus, channel, event]);
}
