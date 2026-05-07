import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useOAuthAppsStore = defineStore('oauthApps', {
  state: () => ({
    items: [],
    isAdmin: false,
    loading: false,
    error: null,
    saving: false,
    promoting: false,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/oauth-app-configs');
        this.items = data.items || [];
        this.isAdmin = Boolean(data.is_admin);
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load OAuth app settings';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    entryFor(provider) {
      return this.items.find((x) => x.provider === provider) || null;
    },
    configFor(provider) {
      return this.effectiveConfigFor(provider);
    },
    effectiveConfigFor(provider) {
      return this.entryFor(provider)?.effective || null;
    },
    userConfigFor(provider) {
      return this.entryFor(provider)?.user || null;
    },
    sharedConfigFor(provider) {
      return this.entryFor(provider)?.shared || null;
    },
    effectiveScopeFor(provider) {
      return this.entryFor(provider)?.effective_scope || null;
    },
    async save({ provider, scope, client_id, client_secret, redirect_uri }) {
      this.saving = true;
      this.error = null;
      try {
        const { data } = await axios.post('/api/oauth-app-configs', {
          scope,
          provider,
          client_id,
          client_secret,
          redirect_uri,
        });
        await this.fetchAll();
        useToastStore().success('OAuth app settings saved');
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save OAuth app settings';
        useToastStore().error(msg);
        throw err;
      } finally {
        this.saving = false;
      }
    },
    async remove(provider, scope = 'user') {
      try {
        await axios.delete(`/api/oauth-app-configs/${provider}`, { params: { scope } });
        await this.fetchAll();
        useToastStore().success('OAuth app settings removed');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to remove OAuth app settings';
        useToastStore().error(msg);
        throw err;
      }
    },
    async promoteMyUserConfigsToShared({ overwrite = false } = {}) {
      this.promoting = true;
      this.error = null;
      try {
        const { data } = await axios.post('/api/oauth-app-configs/promote-my-user-configs-to-shared', { overwrite });
        await this.fetchAll();
        useToastStore().success(`Promoted configs (created: ${data.created}, updated: ${data.updated}, skipped: ${data.skipped})`);
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to promote user configs to shared defaults';
        useToastStore().error(msg);
        throw err;
      } finally {
        this.promoting = false;
      }
    },
  },
});

