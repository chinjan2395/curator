import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const WORKSPACES_TTL_MS = 30 * 60 * 1000;
const CACHE_KEY = 'workspaces';

export const useWorkspacesStore = defineStore('workspaces', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll({ force = false, background = true } = {}) {
      const cached = hydrateFromSession(CACHE_KEY);

      if (cached) {
        this.list = cached.value;
      }

      if (!force && cached && isFresh(cached, WORKSPACES_TTL_MS)) {
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
        const rows = await withDedupe(CACHE_KEY, async () => {
          const { data } = await axios.get('/api/workspaces');
          const next = data.data;
          persistToSession(CACHE_KEY, next);
          return next;
        });
        this.list = rows;
        return rows;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load workspaces';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async create(name) {
      this.error = null;
      try {
        const { data } = await axios.post('/api/workspaces', { name });
        const workspace = data.data ?? data;
        this.list.push(workspace);
        persistToSession(CACHE_KEY, [...this.list]);
        useToastStore().success('Workspace created');
        return workspace;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to create workspace';
        useToastStore().error(this.error);
        throw err;
      }
    },
    async update(id, name) {
      this.error = null;
      try {
        const { data } = await axios.put(`/api/workspaces/${id}`, { name });
        const workspace = data.data ?? data;
        const i = this.list.findIndex((w) => w.id === id);
        if (i !== -1) this.list[i] = workspace;
        persistToSession(CACHE_KEY, [...this.list]);
        useToastStore().success('Workspace updated');
        return workspace;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to update workspace';
        useToastStore().error(this.error);
        throw err;
      }
    },
    async remove(id) {
      this.error = null;
      try {
        await axios.delete(`/api/workspaces/${id}`);
        this.list = this.list.filter((w) => w.id !== id);
        persistToSession(CACHE_KEY, [...this.list]);
        useToastStore().success('Workspace deleted');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to delete workspace';
        useToastStore().error(msg);
        throw err;
      }
    },
    invalidateCache() {
      invalidate(CACHE_KEY);
    },
  },
});
