import type { ReactNode } from 'react';
import type { BroadcastPayload, DispatchResponse, MessageBusClient, MessageBusConfig, MessageBusError, RequestOptions } from './index';

export { createMessageBus, MessageBusError } from './index';

export declare function MessageBusProvider(props: { bus?: MessageBusClient; config?: MessageBusConfig; children?: ReactNode }): JSX.Element;
export declare function useMessageBus(): MessageBusClient;
export declare function useLiveEvent<TPayload = BroadcastPayload>(channel: string | null | undefined, event: string | string[], handler: (payload: TPayload) => void): void;
export declare function useDispatch<TResult = unknown>(message: string): {
  dispatch(payload?: Record<string, unknown>, options?: RequestOptions): Promise<DispatchResponse<TResult>>;
  reset(): void;
  loading: boolean;
  error: MessageBusError | null;
  errors: Record<string, string[]>;
  data: DispatchResponse<TResult> | null;
};
export declare function useLiveQuery<TResult = unknown>(
  message: string,
  payload?: Record<string, unknown>,
  options?: { enabled?: boolean; refreshOn?: Array<[string, string]>; initialData?: TResult | null },
): { data: TResult | null; loading: boolean; error: MessageBusError | null; refetch(): Promise<TResult | undefined> };
export declare function useLiveList<TItem = BroadcastPayload>(
  channel: string | null | undefined,
  event: string | string[],
  options?: { initial?: TItem[]; max?: number | null; map?: (payload: BroadcastPayload) => TItem; key?: string | null },
): [TItem[], (items: TItem[] | ((items: TItem[]) => TItem[])) => void];
export declare function usePresence<TUser = any>(channel: string | null | undefined, options?: { key?: string }): TUser[];
export declare function useWhisper<TData = any>(channel: string, event: string, handler?: (data: TData) => void): (data?: Record<string, unknown>) => void;
