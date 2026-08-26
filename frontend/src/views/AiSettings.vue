<template>
  <div class="ai-settings space-y-5">
    <AppPageHeader
      title="AI Settings"
      subtitle="Choose which service generates your images and captions, and use your own API keys if you prefer."
    />

    <!-- Hero -->
    <AppCard class="ai-settings-hero overflow-hidden border-slate-200/80 p-0" variant="panel">
      <div class="relative isolate overflow-hidden px-5 py-5 md:px-6 md:py-6">
        <div class="ai-settings-hero__glow ai-settings-hero__glow--one" />
        <div class="ai-settings-hero__glow ai-settings-hero__glow--two" />
        <div class="relative grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)]">
          <div class="space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/55 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700 backdrop-blur">
              <span class="h-2 w-2 rounded-full bg-violet-500 shadow-[0_0_0_4px_rgba(139,92,246,0.16)]" />
              AI engine room
            </div>
            <div class="space-y-2">
              <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-slate-950 md:text-3xl">
                Power your content pipeline your way.
              </h2>
              <p class="max-w-2xl text-sm leading-6 text-slate-600 md:text-[15px]">
                Mix and match image and caption providers, bring your own keys, and set the defaults every
                generation reaches for first.
              </p>
            </div>
            <div class="flex flex-wrap gap-2">
              <AppBadge :variant="imageReadyCount > 0 ? 'success' : 'warning'">
                {{ imageReadyCount }}/{{ imageTotalCount }} image services ready
              </AppBadge>
              <AppBadge :variant="contentReadyCount > 0 ? 'success' : 'warning'">
                {{ contentReadyCount }}/{{ contentTotalCount }} content services ready
              </AppBadge>
              <AppBadge variant="purple">Default images: {{ defaultImageLabel }}</AppBadge>
              <AppBadge variant="info">Default content: {{ defaultContentLabel }}</AppBadge>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Images</span>
                <AppIcon name="image" class="h-4 w-4 text-slate-500" />
              </div>
              <div class="mt-3 text-3xl font-semibold text-slate-950">{{ imageReadyCount }}/{{ imageTotalCount }}</div>
              <div class="mt-1 text-xs text-slate-500">Services ready to generate</div>
            </div>
            <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Captions</span>
                <AppIcon name="edit" class="h-4 w-4 text-slate-500" />
              </div>
              <div class="mt-3 text-3xl font-semibold text-slate-950">{{ contentReadyCount }}/{{ contentTotalCount }}</div>
              <div class="mt-1 text-xs text-slate-500">Services ready to write</div>
            </div>
            <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Your keys</span>
                <AppIcon name="lock" class="h-4 w-4 text-slate-500" />
              </div>
              <div class="mt-3 text-3xl font-semibold text-slate-950">{{ byokCount }}</div>
              <div class="mt-1 text-xs text-slate-500">Bring-your-own-key services</div>
            </div>
            <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fallback</span>
                <AppIcon name="shield" class="h-4 w-4 text-slate-500" />
              </div>
              <div class="mt-3 text-lg font-semibold text-slate-950">Platform key</div>
              <div class="mt-1 text-xs text-slate-500">Used when a service has no key of its own</div>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="space-y-4">
      <AppSegmentedControl
        v-model="activeSection"
        :options="sectionOptions"
        aria-label="AI settings section"
      />

      <template v-if="activeSection === 'image'">
      <AppLoader v-if="store.loading && !store.loaded" />
      <AppAlert v-else-if="store.error" variant="danger">{{ store.error }}</AppAlert>

      <template v-else>
        <AppAlert v-if="!store.hasAnyProvider" variant="warning" title="No image provider is configured">
          Add your own API key for one of the services below, or ask an administrator to configure a
          platform key. Until then image generation falls back to offline placeholders.
        </AppAlert>

        <AppCard class="overflow-hidden p-0" variant="panel">
          <div class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
            <div class="ai-settings-icon-tile">
              <AppIcon name="sparkles" class="w-4 h-4" />
            </div>
            <div>
              <AppTitle size="sm">Defaults</AppTitle>
              <AppText size="sm" muted>
                Used whenever you generate an image without picking a service for that particular run.
              </AppText>
            </div>
          </div>

          <div class="grid gap-4 px-5 py-5 sm:grid-cols-3">
            <AppFormField
              id="ai-default-provider"
              label="Default image service"
              hint="Leave unset to use the platform default."
            >
              <AppSelect
                id="ai-default-provider"
                v-model="defaultProvider"
                placeholder="Use the platform default"
              >
                <option value="">Use the platform default</option>
                <option
                  v-for="option in store.providerOptions"
                  :key="option.value"
                  :value="option.value"
                  :disabled="option.disabled"
                >
                  {{ option.label }}
                </option>
              </AppSelect>
            </AppFormField>

            <AppFormField id="ai-default-size" label="Default image size" :hint="sizeHint">
              <AppSelect
                id="ai-default-size"
                v-model="defaultSize"
                placeholder="Let the service decide"
                @change="sizeNotice = ''"
              >
                <option value="">Let the service decide</option>
                <option v-for="option in sizeOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </AppSelect>
              <p v-if="sizeNotice" class="text-xs text-amber-700">{{ sizeNotice }}</p>
            </AppFormField>

            <AppFormField id="ai-default-model" label="Default image model" :hint="modelHint">
              <AppSelect
                id="ai-default-model"
                v-model="defaultModel"
                placeholder="Let the service decide"
                @change="modelNotice = ''"
              >
                <option value="">Let the service decide</option>
                <option
                  v-for="option in modelOptions"
                  :key="option.value"
                  :value="option.value"
                  :disabled="option.disabled"
                >
                  {{ option.label }}
                </option>
              </AppSelect>
              <p v-if="modelNotice" class="text-xs text-amber-700">{{ modelNotice }}</p>
            </AppFormField>
          </div>

          <div class="flex justify-end border-t border-slate-200/80 px-5 py-3">
            <AppButton variant="primary" :loading="store.savingDefaults" @click="saveDefaults">
              Save defaults
            </AppButton>
          </div>
        </AppCard>

        <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
          <AiProviderCard
            v-for="provider in store.providers"
            :key="provider.id"
            :provider="provider"
            :is-default="provider.id === store.defaultProvider"
            :api-key-url="apiKeyUrl(provider)"
            :status-text="statusLabel(provider)"
            :field-id="keyFieldId(provider)"
            v-model:key-value="keyDrafts[provider.id]"
            v-model:key-visible="keyVisible[provider.id]"
            :saving="store.isSavingProvider(provider.id)"
            @save-key="saveKey(provider)"
            @remove-key="removeKey(provider)"
          />
        </div>
      </template>
      </template>

      <template v-else>
        <AppLoader v-if="!store.contentLoaded" />
        <AppAlert v-else-if="store.contentError" variant="danger">{{ store.contentError }}</AppAlert>

        <template v-else>
          <AppAlert v-if="!store.hasAnyContentProvider" variant="warning" title="No content provider is configured">
            Add your own API key for Groq, or ask an administrator to configure a platform key. Until
            then caption generation falls back to offline placeholders.
          </AppAlert>

          <AppCard class="overflow-hidden p-0" variant="panel">
            <div class="flex items-center gap-3 border-b border-slate-200/80 px-5 py-4">
              <div class="ai-settings-icon-tile">
                <AppIcon name="edit" class="w-4 h-4" />
              </div>
              <div>
                <AppTitle size="sm">Defaults</AppTitle>
                <AppText size="sm" muted>
                  Used whenever you generate or refine captions without picking a service for that
                  particular run.
                </AppText>
              </div>
            </div>

            <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
              <AppFormField
                id="ai-content-default-provider"
                label="Default content service"
                hint="Leave unset to use the platform default."
              >
                <AppSelect
                  id="ai-content-default-provider"
                  v-model="contentDefaultProvider"
                  placeholder="Use the platform default"
                >
                  <option value="">Use the platform default</option>
                  <option
                    v-for="option in store.contentProviderOptions"
                    :key="option.value"
                    :value="option.value"
                    :disabled="option.disabled"
                  >
                    {{ option.label }}
                  </option>
                </AppSelect>
              </AppFormField>

              <AppFormField id="ai-content-default-model" label="Default content model" :hint="contentModelHint">
                <AppSelect
                  id="ai-content-default-model"
                  v-model="contentDefaultModel"
                  placeholder="Let the service decide"
                  @change="contentModelNotice = ''"
                >
                  <option value="">Let the service decide</option>
                  <option
                    v-for="option in contentModelOptions"
                    :key="option.value"
                    :value="option.value"
                    :disabled="option.disabled"
                  >
                    {{ option.label }}
                  </option>
                </AppSelect>
                <p v-if="contentModelNotice" class="text-xs text-amber-700">{{ contentModelNotice }}</p>
              </AppFormField>
            </div>

            <div class="flex justify-end border-t border-slate-200/80 px-5 py-3">
              <AppButton variant="primary" :loading="store.savingContentDefaults" @click="saveContentDefaults">
                Save defaults
              </AppButton>
            </div>
          </AppCard>

          <div class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
            <AiProviderCard
              v-for="provider in store.contentProviders"
              :key="provider.id"
              :provider="provider"
              :is-default="provider.id === store.contentDefaultProvider"
              :api-key-url="contentApiKeyUrl(provider)"
              :status-text="contentStatusLabel(provider)"
              :field-id="contentKeyFieldId(provider)"
              :show-key-form="store.supportsContentByok(provider.id)"
              no-key-message="Uses your local Ollama server — no API key needed."
              v-model:key-value="contentKeyDrafts[provider.id]"
              v-model:key-visible="contentKeyVisible[provider.id]"
              :saving="store.isSavingContentProvider(provider.id)"
              @save-key="saveContentKey(provider)"
              @remove-key="removeContentKey(provider)"
            />
          </div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue';
import { useAiSettingsStore } from '../stores/aiSettings';
import {
  AppAlert,
  AppBadge,
  AppButton,
  AppCard,
  AppFormField,
  AppIcon,
  AppLoader,
  AppSegmentedControl,
  AppSelect,
  AppText,
  AppTitle,
} from '../components/ui';
import { AppPageHeader } from '../components/layout';
import AiProviderCard from '../components/ai/AiProviderCard.vue';

// External "get an API key" links per provider. The stub has no BYOK, so it is
// intentionally omitted here rather than added to the backend provider registry.
const API_KEY_URLS = {
  openai: 'https://platform.openai.com/api-keys',
  flux: 'https://docs.bfl.ai/',
  gemini: 'https://ai.google.dev/gemini-api/docs/api-key',
  grok: 'https://console.x.ai/team/default/api-keys',
};

const CONTENT_API_KEY_URLS = {
  groq: 'https://console.groq.com/keys',
  grok: 'https://console.x.ai/team/default/api-keys',
};

const store = useAiSettingsStore();
const confirm = inject('confirm');

const sectionOptions = [
  { value: 'image', label: 'Images', icon: 'image' },
  { value: 'content', label: 'Content', icon: 'edit' },
];
const activeSection = ref('image');

const defaultProvider = ref('');
const defaultSize = ref('');
const sizeNotice = ref('');
const defaultModel = ref('');
const modelNotice = ref('');
// Never seeded from the server — a stored key is write-only.
const keyDrafts = reactive({});
// Per-provider show/hide toggle for the API key input; purely local UI state.
const keyVisible = reactive({});

function apiKeyUrl(provider) {
  return API_KEY_URLS[provider.id] || null;
}


const imageTotalCount = computed(() => store.providers.length);
const imageReadyCount = computed(() => store.providers.filter((p) => p.available).length);
const contentTotalCount = computed(() => store.contentProviders.length);
const contentReadyCount = computed(() => store.contentProviders.filter((p) => p.available).length);
const byokCount = computed(() =>
  store.providers.filter((p) => p.byok.configured).length +
  store.contentProviders.filter((p) => p.byok.configured).length,
);
const defaultImageLabel = computed(() => store.providerById(store.defaultProvider)?.label || 'Platform default');
const defaultContentLabel = computed(() => store.contentProviderById(store.contentDefaultProvider)?.label || 'Platform default');

// With no default service chosen we cannot know which provider will run the job,
// so offer every size rather than leaving the field empty and unexplained. The
// API validates against the same union and ignores a size the eventual provider
// does not offer.
const sizeOptions = computed(() =>
  defaultProvider.value ? store.sizeOptionsFor(defaultProvider.value) : store.allSizeOptions,
);

const sizeHint = computed(() => {
  const provider = store.providerById(defaultProvider.value);
  return provider
    ? `Sizes offered by ${provider.label}.`
    : 'No default service chosen, so every size is listed. It applies only if the service that runs the job offers it.';
});

const modelOptions = computed(() =>
  defaultProvider.value
    ? store.modelOptionsFor(defaultProvider.value, defaultModel.value)
    : store.allModelOptions(defaultModel.value),
);

const modelHint = computed(() => {
  const provider = store.providerById(defaultProvider.value);
  return provider
    ? `Models offered by ${provider.label}.`
    : 'No default service chosen, so every model is listed. It applies only if the service that runs the job offers it.';
});

function keyFieldId(provider) {
  return `ai-key-${provider.id}`;
}

function statusLabel(provider) {
  if (provider.byok.configured) return 'Using your own API key.';
  if (provider.platform_configured) return 'Using the platform API key.';
  return 'Add an API key to use this service.';
}

function syncFromStore() {
  defaultProvider.value = store.defaultProvider || '';
  defaultSize.value = store.defaultSize || '';
  sizeNotice.value = '';
  defaultModel.value = store.defaultModel || '';
  modelNotice.value = '';
}

const contentDefaultProvider = ref('');
const contentDefaultModel = ref('');
const contentModelNotice = ref('');
// Never seeded from the server — a stored key is write-only.
const contentKeyDrafts = reactive({});
// Per-provider show/hide toggle for the API key input; purely local UI state.
const contentKeyVisible = reactive({});

function contentApiKeyUrl(provider) {
  return CONTENT_API_KEY_URLS[provider.id] || null;
}

const contentModelOptions = computed(() =>
  contentDefaultProvider.value
    ? store.contentModelOptionsFor(contentDefaultProvider.value, contentDefaultModel.value)
    : store.allContentModelOptions(contentDefaultModel.value),
);

const contentModelHint = computed(() => {
  const provider = store.contentProviderById(contentDefaultProvider.value);
  return provider
    ? `Models offered by ${provider.label}.`
    : 'No default service chosen, so every model is listed. It applies only if the service that runs the job offers it.';
});

function contentKeyFieldId(provider) {
  return `ai-content-key-${provider.id}`;
}

function contentStatusLabel(provider) {
  if (!store.supportsContentByok(provider.id)) return 'Uses your local Ollama server.';
  if (provider.byok.configured) return 'Using your own API key.';
  if (provider.platform_configured) return 'Using the platform API key.';
  return 'Add an API key to use this service.';
}

function syncContentFromStore() {
  contentDefaultProvider.value = store.contentDefaultProvider || '';
  contentDefaultModel.value = store.contentDefaultModel || '';
  contentModelNotice.value = '';
}

watch(contentDefaultProvider, (providerId) => {
  if (!contentDefaultModel.value) return;
  const dropped = contentDefaultModel.value;
  const models = store.contentModelOptionsFor(providerId).map((option) => option.value);
  if (models.length && !models.includes(dropped)) {
    contentDefaultModel.value = '';
    const label = store.contentProviderById(providerId)?.label || 'That service';
    contentModelNotice.value = `${label} does not offer ${dropped}, so it is not selected. Pick a model it supports.`;
  }
});

async function saveContentDefaults() {
  await store.saveContentDefaults({
    defaultProvider: contentDefaultProvider.value || null,
    defaultModel: contentDefaultModel.value || null,
  });
  syncContentFromStore();
}

async function saveContentKey(provider) {
  const apiKey = (contentKeyDrafts[provider.id] || '').trim();
  if (!apiKey) return;

  await store.saveContentKey(provider.id, apiKey);
  contentKeyDrafts[provider.id] = '';
}

async function removeContentKey(provider) {
  const confirmed = await confirm({
    title: `Remove your ${provider.label} key?`,
    message: provider.platform_configured
      ? 'Generations will fall back to the platform key.'
      : 'This service will stop working until another key is added.',
    confirmLabel: 'Remove key',
    variant: 'danger',
  });
  if (!confirmed) return;

  await store.removeContentKey(provider.id);
}

// A size the chosen service does not offer would be silently ignored by the API,
// so drop it here too — and say so, rather than letting the selection appear to
// vanish on its own. This also fires on load, when a size stored under a previous
// default provider is no longer offered, so the wording suits both cases.
watch(defaultProvider, (providerId) => {
  if (!defaultSize.value) return;
  const dropped = defaultSize.value;
  const sizes = store.sizeOptionsFor(providerId).map((option) => option.value);
  if (sizes.length && !sizes.includes(dropped)) {
    defaultSize.value = '';
    const label = store.providerById(providerId)?.label || 'That service';
    sizeNotice.value = `${label} does not offer ${dropped}, so it is not selected. Pick a size it supports.`;
  }
});

watch(defaultProvider, (providerId) => {
  if (!defaultModel.value) return;
  const dropped = defaultModel.value;
  const models = store.modelOptionsFor(providerId).map((option) => option.value);
  if (models.length && !models.includes(dropped)) {
    defaultModel.value = '';
    const label = store.providerById(providerId)?.label || 'That service';
    modelNotice.value = `${label} does not offer ${dropped}, so it is not selected. Pick a model it supports.`;
  }
});

async function saveDefaults() {
  await store.saveDefaults({
    defaultProvider: defaultProvider.value || null,
    defaultSize: defaultSize.value || null,
    defaultModel: defaultModel.value || null,
  });
  syncFromStore();
}

async function saveKey(provider) {
  const apiKey = (keyDrafts[provider.id] || '').trim();
  if (!apiKey) return;

  await store.saveKey(provider.id, apiKey);
  keyDrafts[provider.id] = '';
}

async function removeKey(provider) {
  const confirmed = await confirm({
    title: `Remove your ${provider.label} key?`,
    message: provider.platform_configured
      ? 'Generations will fall back to the platform key.'
      : 'This service will stop working until another key is added.',
    confirmLabel: 'Remove key',
    variant: 'danger',
  });
  if (!confirmed) return;

  await store.removeKey(provider.id);
}

onMounted(async () => {
  await store.load({ force: true });
  syncFromStore();
  syncContentFromStore();
});
</script>

<style scoped>
.ai-settings {
  position: relative;
}

.ai-settings-hero {
  background:
    radial-gradient(circle at top left, rgba(99, 102, 241, 0.14), transparent 34%),
    radial-gradient(circle at top right, rgba(56, 189, 248, 0.12), transparent 30%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.92));
}

.ai-settings-hero__glow {
  position: absolute;
  border-radius: 9999px;
  filter: blur(42px);
  opacity: 0.8;
  pointer-events: none;
}

.ai-settings-hero__glow--one {
  top: -1.5rem;
  right: 10%;
  width: 11rem;
  height: 11rem;
  background: rgba(129, 140, 248, 0.18);
}

.ai-settings-hero__glow--two {
  bottom: -2rem;
  left: 6%;
  width: 13rem;
  height: 13rem;
  background: rgba(56, 189, 248, 0.14);
}

.ai-settings-icon-tile {
  @apply flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 text-violet-600;
}
</style>
