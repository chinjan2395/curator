import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

/**
 * Per-user AI image settings: default provider/size and the user's own
 * provider API keys (BYOK).
 *
 * The server never returns a stored key — only `byok.configured` and
 * `byok.last_four` — so nothing here ever holds a secret after a save.
 */
// Content providers with no BYOK concept (Ollama runs locally, no API key to store).
// The `/api/ai/settings/content` payload has no boolean flag for this, so the
// small, fixed set of provider ids is tracked here — mirrors `API_KEY_URLS` in
// `AiSettings.vue`, which hardcodes the same kind of provider-specific extra.
const CONTENT_BYOK_PROVIDERS = new Set(['groq', 'grok']);

function withOrphanedModel(options, currentValue, providers) {
  if (!currentValue || options.some((option) => option.value === currentValue)) {
    return options;
  }
  for (const p of providers) {
    const model = (p.models || []).find((m) => m.id === currentValue);
    if (model) {
      return [...options, { value: model.id, label: `${model.label} — no API key`, disabled: true }];
    }
  }
  return options;
}

export const useAiSettingsStore = defineStore('aiSettings', {
  state: () => ({
    defaultProvider: null,
    defaultSize: null,
    defaultModel: null,
    fallbackProvider: 'stub',
    providers: [],
    loaded: false,
    loading: false,
    // Saving is tracked per target: the defaults form and each provider's key
    // form are independent, so one in-flight request must not disable the rest.
    savingDefaults: false,
    savingProvider: null,
    error: null,

    contentDefaultProvider: null,
    contentDefaultModel: null,
    contentFallbackProvider: 'stub',
    contentProviders: [],
    contentLoaded: false,
    savingContentDefaults: false,
    savingContentProvider: null,
    contentError: null,
  }),
  getters: {
    providerById: (state) => (id) => state.providers.find((p) => p.id === id) || null,
    availableProviders: (state) => state.providers.filter((p) => p.available),
    hasAnyProvider: (state) => state.providers.some((p) => p.available),
    /**
     * Options for a provider `AppSelect`. Unavailable providers stay visible but
     * disabled, so the reason is discoverable rather than the row just missing.
     */
    providerOptions: (state) =>
      state.providers.map((p) => ({
        value: p.id,
        label: p.available ? p.label : `${p.label} — no API key`,
        disabled: !p.available,
      })),
    sizeOptionsFor: (state) => (providerId) => {
      const provider = state.providers.find((p) => p.id === providerId);
      return (provider?.sizes || []).map((size) => ({ value: size, label: size }));
    },
    /**
     * Every size any provider offers. Used when no default provider is chosen —
     * the API validates a stored default against this same union, and a size the
     * provider that ends up running the job does not offer is simply ignored.
     */
    allSizeOptions: (state) => {
      const seen = new Set();
      state.providers.forEach((p) => (p.sizes || []).forEach((size) => seen.add(size)));
      return [...seen].map((size) => ({ value: size, label: size }));
    },
    modelOptionsFor: (state) => (providerId, currentValue = null) => {
      const provider = state.providers.find((p) => p.id === providerId);
      const options =
        provider && provider.available
          ? (provider.models || []).map((model) => ({ value: model.id, label: model.label, disabled: false }))
          : [];
      return withOrphanedModel(options, currentValue, state.providers);
    },
    /**
     * Every model any available provider offers. Used when no default provider is
     * chosen — the API validates a stored default against this same union, and a
     * model the provider that ends up running the job does not offer is simply
     * ignored.
     */
    allModelOptions: (state) => (currentValue = null) => {
      const seen = new Map();
      state.providers
        .filter((p) => p.available)
        .forEach((p) => (p.models || []).forEach((model) => seen.set(model.id, model.label)));
      const options = [...seen].map(([value, label]) => ({ value, label, disabled: false }));
      return withOrphanedModel(options, currentValue, state.providers);
    },
    isSavingProvider: (state) => (providerId) => state.savingProvider === providerId,

    contentProviderById: (state) => (id) => state.contentProviders.find((p) => p.id === id) || null,
    hasAnyContentProvider: (state) => state.contentProviders.some((p) => p.available),
    contentProviderOptions: (state) =>
      state.contentProviders.map((p) => ({
        value: p.id,
        label: p.available ? p.label : `${p.label} — no API key`,
        disabled: !p.available,
      })),
    contentModelOptionsFor: (state) => (providerId, currentValue = null) => {
      const provider = state.contentProviders.find((p) => p.id === providerId);
      const options =
        provider && provider.available
          ? (provider.models || []).map((model) => ({ value: model.id, label: model.label, disabled: false }))
          : [];
      return withOrphanedModel(options, currentValue, state.contentProviders);
    },
    allContentModelOptions: (state) => (currentValue = null) => {
      const seen = new Map();
      state.contentProviders
        .filter((p) => p.available)
        .forEach((p) => (p.models || []).forEach((model) => seen.set(model.id, model.label)));
      const options = [...seen].map(([value, label]) => ({ value, label, disabled: false }));
      return withOrphanedModel(options, currentValue, state.contentProviders);
    },
    isSavingContentProvider: (state) => (providerId) => state.savingContentProvider === providerId,
    supportsContentByok: () => (providerId) => CONTENT_BYOK_PROVIDERS.has(providerId),
  },
  actions: {
    apply(payload) {
      this.defaultProvider = payload.default_provider ?? null;
      this.defaultSize = payload.default_size ?? null;
      this.defaultModel = payload.default_model ?? null;
      this.fallbackProvider = payload.fallback_provider || 'stub';
      this.providers = payload.providers || [];
      this.loaded = true;
    },
    applyContent(payload) {
      this.contentDefaultProvider = payload.default_provider ?? null;
      this.contentDefaultModel = payload.default_model ?? null;
      this.contentFallbackProvider = payload.fallback_provider || 'stub';
      this.contentProviders = payload.providers || [];
      this.contentLoaded = true;
    },
    async load({ force = false } = {}) {
      const [providers] = await Promise.all([
        this.loadImage({ force }),
        this.loadContent({ force }),
      ]);
      return providers;
    },
    async loadImage({ force = false } = {}) {
      if (this.loaded && !force) return this.providers;

      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/ai/settings', { skipErrorToast: true });
        this.apply(data.data || data || {});
        return this.providers;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load AI settings';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async loadContent({ force = false } = {}) {
      if (this.contentLoaded && !force) return this.contentProviders;

      try {
        const { data } = await axios.get('/api/ai/settings/content', { skipErrorToast: true });
        this.applyContent(data.data || data || {});
        return this.contentProviders;
      } catch (err) {
        this.contentError = err.response?.data?.message || 'Failed to load AI content settings';
        useToastStore().error(this.contentError);
        throw err;
      }
    },
    async saveDefaults({ defaultProvider, defaultSize, defaultModel }) {
      this.savingDefaults = true;
      try {
        const { data } = await axios.put('/api/ai/settings', {
          default_provider: defaultProvider ?? null,
          default_size: defaultSize ?? null,
          default_model: defaultModel ?? null,
        });
        this.apply(data.data || data || {});
        useToastStore().success('AI settings saved');
        return this.providers;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to save AI settings');
        throw err;
      } finally {
        this.savingDefaults = false;
      }
    },
    async saveKey(provider, apiKey) {
      this.savingProvider = provider;
      try {
        const { data } = await axios.put(`/api/ai/providers/image/${provider}/key`, { api_key: apiKey });
        this.apply(data.data || data || {});
        useToastStore().success(data.message || 'API key saved');
        return this.providers;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to save the API key');
        throw err;
      } finally {
        this.savingProvider = null;
      }
    },
    async removeKey(provider) {
      this.savingProvider = provider;
      try {
        const { data } = await axios.delete(`/api/ai/providers/image/${provider}/key`);
        this.apply(data.data || data || {});
        useToastStore().success(data.message || 'API key removed');
        return this.providers;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to remove the API key');
        throw err;
      } finally {
        this.savingProvider = null;
      }
    },
    async saveContentDefaults({ defaultProvider, defaultModel }) {
      this.savingContentDefaults = true;
      try {
        const { data } = await axios.put('/api/ai/settings/content', {
          default_provider: defaultProvider ?? null,
          default_model: defaultModel ?? null,
        });
        this.applyContent(data.data || data || {});
        useToastStore().success('AI settings saved');
        return this.contentProviders;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to save AI settings');
        throw err;
      } finally {
        this.savingContentDefaults = false;
      }
    },
    async saveContentKey(provider, apiKey) {
      this.savingContentProvider = provider;
      try {
        const { data } = await axios.put(`/api/ai/providers/text/${provider}/key`, { api_key: apiKey });
        this.applyContent(data.data || data || {});
        useToastStore().success(data.message || 'API key saved');
        return this.contentProviders;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to save the API key');
        throw err;
      } finally {
        this.savingContentProvider = null;
      }
    },
    async removeContentKey(provider) {
      this.savingContentProvider = provider;
      try {
        const { data } = await axios.delete(`/api/ai/providers/text/${provider}/key`);
        this.applyContent(data.data || data || {});
        useToastStore().success(data.message || 'API key removed');
        return this.contentProviders;
      } catch (err) {
        useToastStore().error(err.response?.data?.message || 'Failed to remove the API key');
        throw err;
      } finally {
        this.savingContentProvider = null;
      }
    },
  },
});
