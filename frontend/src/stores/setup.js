import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from './auth';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

// Short by design: this changes the moment the user fixes something, and every
// mutation site calls invalidate() anyway.
const SETUP_TTL_MS = 5 * 60 * 1000;

function cacheKeyForUser(userId) {
  return userId ? `setup-readiness:${userId}` : 'setup-readiness:guest';
}

function emptyPayload() {
  return { ready: true, blocking: [], requirements: {} };
}

export const useSetupStore = defineStore('setup', {
  state: () => ({
    ready: true,
    blockingKeys: [],
    requirements: {},
    loaded: false,
    loading: false,
    notifying: false,
    error: null,
  }),
  getters: {
    // A single requirement object, or null when the payload has not arrived yet.
    requirement: (state) => (key) => state.requirements[key] || null,

    isSatisfied: (state) => (key) => state.requirements[key]?.state === 'satisfied',

    // Requirement objects (not keys) that hold the whole app hostage.
    blocking: (state) => state.blockingKeys
      .map((key) => state.requirements[key])
      .filter(Boolean),

    // Unmet requirements from a route's meta.requires list, strongest first.
    unmetFor: (state) => (keys) => (keys || [])
      .map((key) => state.requirements[key])
      .filter((requirement) => requirement && requirement.state !== 'satisfied'),

    // Everything still outstanding, for the readiness meter.
    outstanding: (state) => Object.values(state.requirements)
      .filter((requirement) => requirement.state !== 'satisfied'),

    satisfiedCount: (state) => Object.values(state.requirements)
      .filter((requirement) => requirement.state === 'satisfied').length,

    totalCount: (state) => Object.keys(state.requirements).length,
  },
  actions: {
    resolveCacheKey() {
      return cacheKeyForUser(useAuthStore().user?.id);
    },

    applyPayload(data) {
      const payload = data || emptyPayload();
      this.requirements = payload.requirements || {};
      this.blockingKeys = payload.blocking || [];
      this.ready = Boolean(payload.ready);
    },

    async fetch({ force = false, background = true } = {}) {
      const cacheKey = this.resolveCacheKey();
      const cached = hydrateFromSession(cacheKey);

      if (cached) {
        this.applyPayload(cached.value);
        this.loaded = true;
      }

      if (!force && cached && isFresh(cached, SETUP_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidate().catch(() => {});
        return cached.value;
      }

      return this.revalidate();
    },

    async ensureLoaded() {
      if (this.loaded) {
        // Refresh in the background, but never make the router wait on it.
        this.fetch({ background: true }).catch(() => {});
        return;
      }
      await this.fetch({ background: false });
    },

    async revalidate() {
      const cacheKey = this.resolveCacheKey();
      this.loading = true;
      this.error = null;
      try {
        // withDedupe shares one in-flight GET across concurrent callers
        // (the router guard and the layout both ask on a cold boot).
        const payload = await withDedupe(cacheKey, async () => {
          const { data } = await axios.get('/api/setup/status', { skipErrorToast: true });
          const next = data.data || data;
          persistToSession(cacheKey, next);
          return next;
        });
        this.applyPayload(payload);
        this.loaded = true;
        return payload;
      } catch (err) {
        // Fail open. A flaky readiness call must never lock a working user out
        // of the app, so we report the error and keep `blocking` empty.
        this.error = err.response?.data?.message || 'Could not check setup status';
        this.applyPayload(emptyPayload());
        this.loaded = true;
        return emptyPayload();
      } finally {
        this.loading = false;
      }
    },

    // Called from every store action that can satisfy a requirement.
    invalidate() {
      invalidate(this.resolveCacheKey());
      this.loaded = false;
    },

    async refresh() {
      this.invalidate();
      return this.fetch({ force: true, background: false });
    },

    async notifyAdmin() {
      this.notifying = true;
      try {
        const { data } = await axios.post('/api/setup/notify-admin');
        useToastStore().success(data.message || 'Your admins have been notified.');
        return data.data || data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Could not notify your admins';
        throw err;
      } finally {
        this.notifying = false;
      }
    },
  },
});
