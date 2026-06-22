import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const PUBLISH_STATS_TTL_MS = 60 * 1000;
const PUBLISH_CODE_TTL_MS = 10 * 60 * 1000;

function statsCacheKey(workspaceId) {
  return `publish-stats:${workspaceId}`;
}

function codeCacheKey(workspaceId) {
  return `publish-code:${workspaceId}`;
}

export const usePublishStore = defineStore('publish', {
  state: () => ({
    loading: false,
    publishing: false,
    savingSettings: false,
    error: null,
    stats: null,
    publishSettings: null,
    code: null,
  }),
  actions: {
    applyStats(stats) {
      this.stats = stats;
      this.publishSettings = stats?.publish_settings ?? null;
    },
    async fetchStats(workspaceId, { force = false, background = true } = {}) {
      const cacheKey = statsCacheKey(workspaceId);
      const cached = hydrateFromSession(cacheKey);

      if (cached) {
        this.applyStats(cached.value);
      }

      if (!force && cached && isFresh(cached, PUBLISH_STATS_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidateStats(workspaceId).catch(() => {});
        return cached.value;
      }

      return this.revalidateStats(workspaceId);
    },
    async revalidateStats(workspaceId) {
      const cacheKey = statsCacheKey(workspaceId);
      this.loading = true;
      this.error = null;
      try {
        const stats = await withDedupe(cacheKey, async () => {
          const { data } = await axios.get(`/api/workspaces/${workspaceId}/publish/stats`);
          const next = Array.isArray(data) ? data : data.data ?? data;
          persistToSession(cacheKey, next);
          return next;
        });
        this.applyStats(stats);
        return stats;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to load publish stats';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async publish(workspaceId) {
      this.publishing = true;
      this.error = null;
      try {
        const { data } = await axios.post(`/api/workspaces/${workspaceId}/publish`);
        const result = Array.isArray(data) ? data : data.data ?? data;
        useToastStore().success(`Published ${result.published} posts`);
        await this.fetchStats(workspaceId, { force: true, background: false });
        return result;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to publish';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.publishing = false;
      }
    },
    async savePublishSettings(workspaceId, publishSettings) {
      this.savingSettings = true;
      this.error = null;
      try {
        const { data } = await axios.put(
          `/api/workspaces/${workspaceId}/publish/settings`,
          { publish_settings: publishSettings },
        );
        const result = Array.isArray(data) ? data : data.data ?? data;
        this.publishSettings = result.publish_settings;
        if (this.stats) {
          this.stats = { ...this.stats, publish_settings: result.publish_settings };
          persistToSession(statsCacheKey(workspaceId), this.stats);
        }
        useToastStore().success('Feed appearance saved');
        return result.publish_settings;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save settings';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.savingSettings = false;
      }
    },
    async fetchCode(workspaceId, { force = false, background = true } = {}) {
      const cacheKey = codeCacheKey(workspaceId);
      const cached = hydrateFromSession(cacheKey);

      if (cached) {
        this.code = cached.value;
      }

      if (!force && cached && isFresh(cached, PUBLISH_CODE_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidateCode(workspaceId).catch(() => {});
        return cached.value;
      }

      return this.revalidateCode(workspaceId);
    },
    async revalidateCode(workspaceId) {
      const cacheKey = codeCacheKey(workspaceId);
      this.loading = true;
      this.error = null;
      try {
        const code = await withDedupe(cacheKey, async () => {
          const { data } = await axios.get(`/api/workspaces/${workspaceId}/publish/code`);
          const next = Array.isArray(data) ? data : data.data ?? data;
          persistToSession(cacheKey, next);
          return next;
        });
        this.code = code;
        return code;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to load embed code';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    clear() {
      this.error = null;
      this.stats = null;
      this.publishSettings = null;
      this.code = null;
    },
    invalidateWorkspace(workspaceId) {
      invalidate(statsCacheKey(workspaceId));
      invalidate(codeCacheKey(workspaceId));
    },
  },
});
