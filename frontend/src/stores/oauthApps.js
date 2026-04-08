import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useOAuthAppsStore = defineStore('oauthApps', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
    saving: false,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/oauth-app-configs');
        this.list = data;
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load OAuth app settings';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    configFor(provider) {
      return this.list.find((x) => x.provider === provider) || null;
    },
    async save({ provider, client_id, client_secret, redirect_uri }) {
      this.saving = true;
      this.error = null;
      try {
        const { data } = await axios.post('/api/oauth-app-configs', {
          provider,
          client_id,
          client_secret,
          redirect_uri,
        });
        const idx = this.list.findIndex((x) => x.provider === provider);
        if (idx === -1) this.list.push(data);
        else this.list[idx] = data;
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
    async remove(provider) {
      try {
        await axios.delete(`/api/oauth-app-configs/${provider}`);
        this.list = this.list.filter((x) => x.provider !== provider);
        useToastStore().success('OAuth app settings removed');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to remove OAuth app settings';
        useToastStore().error(msg);
        throw err;
      }
    },
  },
});

