import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

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
    async fetch() {
      if (this.loading) return;
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/navigation-settings', { skipErrorToast: true });
        const payload = data.data || data;
        this.applyPayload(payload);
        this.loaded = true;
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
