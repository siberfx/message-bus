/**
 * Alpine.js integration (used by Blade & Livewire pages).
 *
 *   <div x-data="busListener({ channel: 'orders', event: 'order.placed', max: 10 })">
 *     <template x-for="m in messages" :key="m.id"><p x-text="m.data.number"></p></template>
 *   </div>
 *
 *   <form x-data="busDispatch('chat.send', { payload: { room_id: 1 } })" @submit.prevent="submit($event)">
 *     <input name="body"> <button :disabled="loading">Send</button>
 *   </form>
 *
 *   <button @click="$bus.dispatch('cart.add', { sku: 'A1' })">Add</button>
 */
export function registerAlpine(Alpine, bus) {
  Alpine.magic('bus', () => bus);

  Alpine.data('busListener', (options = {}) => ({
    messages: [],
    last: null,
    count: 0,
    _stop: null,

    init() {
      if (!options.channel || !options.event) return;

      this._stop = bus.listen(options.channel, options.event, (payload) => {
        this.last = payload;
        this.count++;
        this.messages.push(payload);

        if (options.max && this.messages.length > options.max) {
          this.messages.splice(0, this.messages.length - options.max);
        }

        this.$dispatch('bus-message', payload);
      });
    },

    destroy() {
      if (this._stop) this._stop();
    },
  }));

  Alpine.data('busDispatch', (message, options = {}) => ({
    loading: false,
    error: null,
    errors: {},
    result: null,
    response: null,

    async send(payload = {}) {
      this.loading = true;
      this.error = null;
      this.errors = {};

      try {
        this.response = await bus.dispatch(message, { ...(options.payload || {}), ...payload });
        this.result = this.response.result;
        this.$dispatch('bus-dispatched', this.response);
        return this.response;
      } catch (error) {
        this.error = error.message;
        this.errors = error.errors || {};
        this.$dispatch('bus-failed', error);
        return null;
      } finally {
        this.loading = false;
      }
    },

    async submit(event) {
      const form = event.target.closest('form') || event.target;
      const payload = {};

      new FormData(form).forEach((value, key) => {
        if (key === '_token') return;
        if (key.endsWith('[]')) {
          const name = key.slice(0, -2);
          (payload[name] = payload[name] || []).push(value);
        } else {
          payload[key] = value;
        }
      });

      const response = await this.send(payload);

      if (response && options.reset !== false) form.reset();

      return response;
    },
  }));
}

export default registerAlpine;
