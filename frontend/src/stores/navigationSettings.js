import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const NAV_TTL_MS = 60 * 60 * 1000;
const CACHE_KEY = 'navigation-settings';

const DEFAULT_MENUS = {};
const DEFAULT_FEATURES = { publish_brand_kit: true };

export const useNavigationSettingsStore = defineStore('navigationSettings', {
  state: () => ({
    menus: { ...DEFAULT_MENUS },
    features: { ...DEFAULT_FEATURES },
    registry: { menus: {}, features: {} },
    loaded: false,
    loading: false,
    saving: false,
    error: null,
  }),
  getters: {
    isMenuEnabled: (state) => (id) => {
      if (state.menus[id] === undefined) return true;
      return Boolean(state.menus[id]);
    },
    isFeatureEnabled: (state) => (id) => {
      if (state.features[id] === undefined) return true;
      return Boolean(state.features[id]);
    },
  },
  actions: {
    applyPayload(data) {
      if (data?.menus) this.menus = { ...data.menus };
      if (data?.features) this.features = { ...data.features };
      if (data?.registry) this.registry = data.registry;
    },
    async fetch({ force = false, background = true } = {}) {
      const cached = hydrateFromSession(CACHE_KEY);

      if (cached) {
        this.applyPayload(cached.value);
        this.loaded = true;
      }

      if (!force && cached && isFresh(cached, NAV_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidate().catch(() => {});
        return cached.value;
      }

      return this.revalidate();
    },
    async revalidate() {
      if (this.loading) return;
      this.loading = true;
      this.error = null;
      try {
        const payload = await withDedupe(CACHE_KEY, async () => {
          const { data } = await axios.get('/api/navigation-settings', { skipErrorToast: true });
          const next = data.data || data;
          persistToSession(CACHE_KEY, next);
          return next;
        });
        this.applyPayload(payload);
        this.loaded = true;
        return payload;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load navigation settings';
      } finally {
        this.loading = false;
      }
    },
    async fetchAdmin() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/admin/navigation-settings', { skipErrorToast: true });
        const payload = data.data || data;
        this.applyPayload(payload);
        this.loaded = true;
        return payload;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load navigation settings';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async update(payload) {
      this.saving = true;
      this.error = null;
      try {
        const { data } = await axios.put('/api/admin/navigation-settings', payload);
        const body = data.data || data;
        this.applyPayload(body);
        persistToSession(CACHE_KEY, body);
        useToastStore().success(data.message || 'Navigation settings saved');
        return body;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to save navigation settings';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.saving = false;
      }
    },
  },
});
