/**
 * Entry point loaded by @messageBusScripts / <x-message-bus::scripts />.
 * Exposes window.MessageBus and registers the Alpine helpers.
 */
import { createMessageBus } from './core.js';
import { registerAlpine } from './alpine.js';

const config = window.MessageBusConfig || {};

// Optional zero-build Echo setup (config "message-bus.client.echo.enabled").
// echo.iife.js exposes the Echo class as window.Echo; replace it by an instance.
if (config.echo && typeof window.Echo === 'function' && typeof window.Echo.prototype?.channel === 'function') {
  const EchoClass = window.Echo;
  window.Echo = new EchoClass({ ...config.echo });
}

const bus = createMessageBus({ ...config, echo: null });

window.MessageBus = bus;

let registered = false;
const register = () => {
  if (registered || !window.Alpine) return;
  registered = true;
  registerAlpine(window.Alpine, bus);
};

// Livewire 3 / Alpine 3 fire "alpine:init" before initializing the DOM.
document.addEventListener('alpine:init', register);
register();

window.dispatchEvent(new CustomEvent('message-bus:ready', { detail: bus }));

export default bus;
