import { defineStore } from 'pinia';

export const useLoaderStore = defineStore('loader', {
  state: () => ({
    isLoading: false,
    message: '',
    _count: 0, // ref-count so nested calls don't cancel each other
  }),

  actions: {
    start(message = 'Please wait…') {
      this._count++;
      this.isLoading = true;
      this.message = message;
    },

    stop() {
      this._count = Math.max(0, this._count - 1);
      if (this._count === 0) {
        this.isLoading = false;
        this.message = '';
      }
    },

    // Convenience wrapper — use this instead of start/stop manually:
    // const result = await loader.run(() => apiClient.post(...), 'Saving…')
    async run(fn, message = 'Please wait…') {
      this.start(message);
      try {
        return await fn();
      } finally {
        this.stop();
      }
    },
  },
});
