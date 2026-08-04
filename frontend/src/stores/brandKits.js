import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

/**
 * Brand Kit inheritance store. Unlike most reference-data stores in this app,
 * kit data changes frequently while a user is actively editing overrides in
 * the Brand Kit Editor, so this intentionally skips the sessionCache TTL
 * pattern used by `publish.js`/`credentials.js` and just keeps an in-memory
 * list + a per-kit `resolved()` cache, matching `BrandKitPanel.vue`'s existing
 * "plain axios + local reactive state" convention for this feature area.
 */
export const useBrandKitsStore = defineStore('brandKits', {
  state: () => ({
    kits: [],
    loading: false,
    error: null,
    // Map<kitId, { resolved, overridden_paths }>
    resolvedCache: new Map(),
  }),
  getters: {
    /**
     * Groups the flat kit list into Master kits with their direct children
     * nested underneath, e.g. `[{ master, children: [...] }, ...]`. A Master
     * with no children renders identically (empty `children` array), which
     * naturally covers "masterless orphans" without a separate branch.
     */
    groupedByMaster: (state) => {
      const roots = state.kits.filter((k) => !k.parent_id);
      return roots
        .map((master) => ({
          master,
          children: state.kits.filter((k) => k.parent_id === master.id),
        }))
        .sort((a, b) => (b.master.is_default ? 1 : 0) - (a.master.is_default ? 1 : 0) || a.master.name.localeCompare(b.master.name));
    },
    kitById: (state) => (id) => state.kits.find((k) => Number(k.id) === Number(id)) || null,
  },
  actions: {
    async loadKits() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/content/brand-kits', { skipErrorToast: true });
        this.kits = data.data || data || [];
        return this.kits;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load brand kits';
        this.kits = [];
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async fetchKit(id) {
      try {
        const { data } = await axios.get(`/api/content/brand-kits/${id}`);
        const kit = data.data || data;
        this.upsertKit(kit);
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to load brand kit');
        throw err;
      }
    },
    async createKit(payload) {
      try {
        const { data } = await axios.post('/api/content/brand-kits', payload);
        const kit = data.data || data;
        this.kits.push(kit);
        useToastStore().success('Brand kit created');
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to create brand kit');
        throw err;
      }
    },
    async updateKit(id, payload) {
      try {
        const { data } = await axios.put(`/api/content/brand-kits/${id}`, payload);
        const kit = data.data || data;
        this.upsertKit(kit);
        this.invalidateCascade(id);
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to update brand kit');
        throw err;
      }
    },
    async deleteKit(id) {
      try {
        await axios.delete(`/api/content/brand-kits/${id}`);
        this.kits = this.kits.filter((k) => Number(k.id) !== Number(id));
        this.resolvedCache.delete(id);
        useToastStore().success('Brand kit deleted');
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to delete brand kit');
        throw err;
      }
    },
    async patchOverrides(id, patch) {
      try {
        const { data } = await axios.patch(`/api/content/brand-kits/${id}/overrides`, patch);
        const result = data.data || data;
        this.applyOverridesResult(id, result);
        return result;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to save changes');
        throw err;
      }
    },
    async resetOverride(id, path) {
      try {
        const { data } = await axios.post(`/api/content/brand-kits/${id}/reset`, { path });
        const result = data.data || data;
        this.applyOverridesResult(id, result);
        return result;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to reset field');
        throw err;
      }
    },
    async duplicateKit(id) {
      try {
        const { data } = await axios.post(`/api/content/brand-kits/${id}/duplicate`);
        const kit = data.data || data;
        this.kits.push(kit);
        useToastStore().success('Brand kit duplicated');
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to duplicate brand kit');
        throw err;
      }
    },
    async createChildKit(id, name) {
      try {
        const { data } = await axios.post(`/api/content/brand-kits/${id}/create-child`, { name });
        const kit = data.data || data;
        this.kits.push(kit);
        useToastStore().success('Child brand kit created');
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to create child brand kit');
        throw err;
      }
    },
    async setAsMaster(id) {
      try {
        const { data } = await axios.post(`/api/content/brand-kits/${id}/set-as-master`);
        const kit = data.data || data;
        this.upsertKit(kit);
        this.resolvedCache.delete(id);
        useToastStore().success('Brand kit set as master');
        return kit;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to update brand kit');
        throw err;
      }
    },
    async uploadLogoAsset(formData) {
      try {
        return await axios.post('/api/content/assets', formData);
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to upload logo');
        throw err;
      }
    },
    async getResolved(id, { force = false } = {}) {
      if (!force && this.resolvedCache.has(id)) {
        return this.resolvedCache.get(id);
      }
      try {
        const { data } = await axios.get(`/api/content/brand-kits/${id}/resolved`);
        const result = data.data || data;
        this.resolvedCache.set(id, result);
        return result;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to load resolved settings');
        throw err;
      }
    },
    /** Merges a patch/reset response back into local state + the resolved cache. */
    applyOverridesResult(id, result) {
      this.resolvedCache.set(id, { resolved: result.resolved, overridden_paths: result.overridden_paths });
      const kit = this.kitById(id);
      if (kit) {
        kit.overrides = result.overrides ?? kit.overrides;
        if (result.resolved) {
          kit.colors = result.resolved.colors ?? kit.colors;
          kit.fonts = result.resolved.fonts ?? kit.fonts;
          kit.watermark = result.resolved.watermark ?? kit.watermark;
        }
      }
      this.invalidateCascade(id);
    },
    /**
     * A Master's live edits cascade into any child that hasn't overridden the
     * same field, so a child's cached `resolved()` output can go stale the
     * moment its Master changes. Drop cached resolves for direct children too.
     */
    invalidateCascade(id) {
      this.resolvedCache.delete(id);
      for (const kit of this.kits) {
        if (kit.parent_id === id) {
          this.resolvedCache.delete(kit.id);
        }
      }
    },
    upsertKit(kit) {
      const idx = this.kits.findIndex((k) => Number(k.id) === Number(kit.id));
      if (idx === -1) {
        this.kits.push(kit);
      } else {
        this.kits[idx] = kit;
      }
    },
  },
});
