import type { App, MaybeRefOrGetter, Ref, ShallowRef } from 'vue';
import type { BroadcastPayload, DispatchResponse, MessageBusClient, MessageBusConfig, MessageBusError, RequestOptions } from './index';

export { createMessageBus, MessageBusError } from './index';

export declare const MessageBusKey: unique symbol;
export declare const MessageBusPlugin: { install(app: App, options?: MessageBusConfig & { bus?: MessageBusClient }): void };
export declare function useMessageBus(): MessageBusClient;
export declare function useLiveEvent<TPayload = BroadcastPayload>(
  channel: MaybeRefOrGetter<string | null | undefined>,
  event: MaybeRefOrGetter<string | string[]>,
  handler: (payload: TPayload) => void,
): () => void;
export declare function useDispatch<TResult = unknown>(message: MaybeRefOrGetter<string>): {
  dispatch(payload?: MaybeRefOrGetter<Record<string, unknown>>, options?: RequestOptions): Promise<DispatchResponse<TResult>>;
  reset(): void;
  loading: Ref<boolean>;
  error: ShallowRef<MessageBusError | null>;
  errors: Ref<Record<string, string[]>>;
  data: ShallowRef<DispatchResponse<TResult> | null>;
};
export declare function useLiveQuery<TResult = unknown>(
  message: MaybeRefOrGetter<string>,
  payload?: MaybeRefOrGetter<Record<string, unknown>>,
  options?: { enabled?: MaybeRefOrGetter<boolean>; refreshOn?: MaybeRefOrGetter<Array<[string, string]>>; initialData?: TResult | null },
): { data: ShallowRef<TResult | null>; loading: Ref<boolean>; error: ShallowRef<MessageBusError | null>; refetch(): Promise<TResult | undefined> };
export declare function useLiveList<TItem = BroadcastPayload>(
  channel: MaybeRefOrGetter<string | null | undefined>,
  event: MaybeRefOrGetter<string | string[]>,
  options?: { initial?: TItem[]; max?: number | null; map?: (payload: BroadcastPayload) => TItem; key?: string | null },
): Ref<TItem[]>;
export declare function usePresence<TUser = any>(channel: MaybeRefOrGetter<string | null | undefined>, options?: { key?: string }): Ref<TUser[]>;
export declare function useWhisper<TData = any>(channel: MaybeRefOrGetter<string>, event: string, handler?: ((data: TData) => void) | null): (data?: Record<string, unknown>) => void;
