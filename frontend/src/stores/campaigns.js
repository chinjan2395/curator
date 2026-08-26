import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useCampaignsStore = defineStore('campaigns', {
  state: () => ({
    list: [],
    current: null,
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/campaigns');
        this.list = data.data || data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load campaigns';
      } finally {
        this.loading = false;
      }
    },
    async fetchOne(id) {
      this.error = null;
      try {
        const { data } = await axios.get(`/api/campaigns/${id}`);
        this.current = data.data || data;
        return this.current;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load campaign';
        throw e;
      }
    },
    async create(payload) {
      try {
        const { data } = await axios.post('/api/campaigns', payload);
        useToastStore().success('Campaign created');
        await this.fetchAll();
        return data.data || data;
      } catch (e) {
        throw e;
      }
    },
    async generate(id, { provider, model } = {}) {
      try {
        const { data } = await axios.post(`/api/campaigns/${id}/generate`, {
          provider: provider || undefined,
          model: model || undefined,
        });
        return data.data || data;
      } catch (e) {
        throw e;
      }
    },
    async refine(packageId, { instruction, provider, model } = {}) {
      const { data } = await axios.post(`/api/content-packages/${packageId}/refine`, {
        instruction,
        provider: provider || undefined,
        model: model || undefined,
      });
      return data.data || data;
    },
    async generateVariants(packageId, { count, provider, model } = {}) {
      const { data } = await axios.post(`/api/content-packages/${packageId}/variants`, {
        count: count || undefined,
        provider: provider || undefined,
        model: model || undefined,
      });
      return data.data || data;
    },
    /**
     * Queue an AI image for a content package. Resolves with the 202 payload;
     * the finished image arrives over the `aiGeneration` websocket channel.
     *
     * `reference` is a File; when present the request goes out as multipart so
     * the backend can store it as an asset before handing the job its id.
     * `referenceAssetId` picks an image already in the library instead.
     */
    async generateImage(
      packageId,
      { instruction, provider, referenceAssetId, reference, prompt, model } = {},
    ) {
      const url = `/api/content-packages/${packageId}/generate-image`;

      if (reference) {
        const form = new FormData();
        form.append('reference', reference);
        if (instruction) form.append('instruction', instruction);
        if (provider) form.append('provider', provider);
        if (prompt) form.append('prompt', prompt);
        if (model) form.append('model', model);

        const { data } = await axios.post(url, form);
        return data.data || data;
      }

      const { data } = await axios.post(url, {
        instruction: instruction || undefined,
        provider: provider || undefined,
        reference_asset_id: referenceAssetId || undefined,
        prompt: prompt || undefined,
        model: model || undefined,
      });
      return data.data || data;
    },
    async previewImagePrompt(packageId, { instruction, referenceAssetId } = {}) {
      const { data } = await axios.post(`/api/content-packages/${packageId}/image-prompt-preview`, {
        instruction,
        reference_asset_id: referenceAssetId || undefined,
      });
      return data.data?.prompt ?? data.prompt;
    },
  },
});
