import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const OAUTH_TTL_MS = 30 * 60 * 1000;
const CACHE_KEY = 'oauth-app-configs';

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
    async fetchAll({ force = false, background = true } = {}) {
      const cached = hydrateFromSession(CACHE_KEY);

      if (cached) {
        this.items = cached.value.items || [];
        this.isAdmin = Boolean(cached.value.isAdmin);
      }

      if (!force && cached && isFresh(cached, OAUTH_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidate().catch(() => {});
        return cached.value;
      }

      return this.revalidate();
    },
    async revalidate() {
      this.loading = true;
      this.error = null;
      try {
        const result = await withDedupe(CACHE_KEY, async () => {
          const { data } = await axios.get('/api/oauth-app-configs');
          const next = { items: data.items || [], isAdmin: Boolean(data.is_admin) };
          persistToSession(CACHE_KEY, next);
          return next;
        });
        this.items = result.items;
        this.isAdmin = result.isAdmin;
        return result;
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
        await this.fetchAll({ force: true });
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
        await this.fetchAll({ force: true });
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
        await this.fetchAll({ force: true });
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
    invalidateCache() {
      invalidate(CACHE_KEY);
    },
  },
});

