// Type definitions for @siberfx/message-bus

export interface DispatchResponse<TResult = unknown> {
  /** Unique id of the message; also present in the broadcast payload. */
  id: string;
  message: string;
  /** True when the message was handled synchronously. */
  handled: boolean;
  /** Transports the message was sent to (HTTP 202 when only sent). */
  sent: string[];
  result: TResult;
}

export interface BroadcastPayload<TData = Record<string, unknown>, TResult = unknown> {
  id: string;
  type: string;
  event: string;
  data: TData;
  result?: TResult;
  sent_at: string;
}

export interface MessageBusConfig {
  endpoint?: string | null;
  headers?: Record<string, string>;
  credentials?: RequestCredentials;
  csrfToken?: string | null;
  /** A Laravel Echo instance, or a function returning one. Defaults to window.Echo. */
  echo?: unknown | (() => unknown) | null;
  fetch?: typeof fetch | null;
}

export interface RequestOptions {
  headers?: Record<string, string>;
  endpoint?: string;
  signal?: AbortSignal;
}

export type Unsubscribe = () => void;

export interface PresenceCallbacks<TUser = any> {
  here?: (users: TUser[]) => void;
  joining?: (user: TUser) => void;
  leaving?: (user: TUser) => void;
  error?: (error: unknown) => void;
}

export interface MessageBusClient {
  config: Required<MessageBusConfig>;
  dispatch<TResult = unknown>(message: string, payload?: Record<string, unknown>, options?: RequestOptions): Promise<DispatchResponse<TResult>>;
  ask<TResult = unknown>(message: string, payload?: Record<string, unknown>, options?: RequestOptions): Promise<TResult>;
  listen<TPayload = BroadcastPayload>(channel: string, event: string | string[], callback: (payload: TPayload) => void): Unsubscribe;
  once<TPayload = BroadcastPayload>(channel: string, event: string, callback: (payload: TPayload) => void): Unsubscribe;
  leave(channel: string): void;
  presence<TUser = any>(channel: string, callbacks: PresenceCallbacks<TUser>): Unsubscribe;
  whisper(channel: string, event: string, data?: Record<string, unknown>): void;
  onWhisper<TData = any>(channel: string, event: string, callback: (data: TData) => void): Unsubscribe;
  on(type: 'dispatching' | 'dispatched' | 'error' | 'message', callback: (detail: any) => void): Unsubscribe;
  echo(): any;
  socketId(): string | null;
}

export declare class MessageBusError extends Error {
  status: number;
  errors: Record<string, string[]>;
  response: unknown;
  readonly isValidationError: boolean;
}

export declare function createMessageBus(config?: MessageBusConfig): MessageBusClient;
export declare function eventName(event: string): string;
export declare function registerAlpine(alpine: unknown, bus: MessageBusClient): void;
export default createMessageBus;
