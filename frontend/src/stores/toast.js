import { defineStore } from 'pinia';

export const useToastStore = defineStore('toast', {
  state: () => ({
    items: [],
    defaultDuration: 4000,
  }),
  actions: {
    add(message, type = 'info', duration = null) {
      const text = String(message ?? '');
      // Axios interceptor toasts every 4xx/5xx, and many stores/views also toast in their
      // catch blocks with the same message — without this guard the user sees two identical
      // popups for a single failure.
      const existing = this.items.find((t) => t.type === type && t.message === text);
      if (existing) {
        return existing.id;
      }

      const id = Date.now() + Math.random();
      const d = duration ?? this.defaultDuration;
      this.items.push({ id, message: text, type, duration: d });
      if (d > 0) {
        setTimeout(() => this.remove(id), d);
      }
      return id;
    },
    remove(id) {
      this.items = this.items.filter((t) => t.id !== id);
    },
    success(message, duration = null) {
      return this.add(message, 'success', duration);
    },
    error(message, duration = null) {
      return this.add(message, 'error', duration ?? 6000);
    },
    info(message, duration = null) {
      return this.add(message, 'info', duration);
    },
  },
});
