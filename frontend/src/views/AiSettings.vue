<template>
  <div class="space-y-4 max-w-3xl">
    <AppPageHeader
      title="AI settings"
      subtitle="Choose which service generates your images and captions, and use your own API keys if you prefer."
      icon="sparkles"
    />

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

      <AppCard class="p-4 space-y-3">
        <AppTitle size="sm">Defaults</AppTitle>
        <AppText size="sm" muted>
          Used whenever you generate an image without picking a service for that particular run.
        </AppText>

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

        <AppButton variant="primary" :loading="store.savingDefaults" @click="saveDefaults">
          Save defaults
        </AppButton>
      </AppCard>

      <AppCard v-for="provider in store.providers" :key="provider.id" class="p-4 space-y-3">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <AppTitle size="sm">{{ provider.label }}</AppTitle>
              <AppBadge v-if="provider.id === store.defaultProvider" variant="info">Default</AppBadge>
            </div>
            <AppText size="sm" muted>{{ statusLabel(provider) }}</AppText>
            <a
              v-if="apiKeyUrl(provider)"
              :href="apiKeyUrl(provider)"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-block mt-1 text-2xs text-slate-400 hover:text-slate-600 underline"
            >
              Get an API key
            </a>
          </div>
          <AppBadge :variant="provider.available ? 'success' : 'warning'">
            {{ provider.available ? 'Ready' : 'Not configured' }}
          </AppBadge>
        </div>

        <div v-if="provider.byok.configured" class="flex items-center justify-between gap-3 text-sm">
          <span class="text-slate-600">Your key ends in •••• {{ provider.byok.last_four }}</span>
          <AppButton
            size="sm"
            variant="ghost"
            tone="destructive"
            :loading="store.isSavingProvider(provider.id)"
            @click="removeKey(provider)"
          >
            Remove key
          </AppButton>
        </div>

        <AppFormField
          :id="keyFieldId(provider)"
          :label="provider.byok.configured ? 'Replace your API key' : 'Your API key'"
          hint="Stored encrypted. It is never shown again after saving."
        >
          <div class="relative">
            <AppInput
              :id="keyFieldId(provider)"
              v-model="keyDrafts[provider.id]"
              :type="keyVisible[provider.id] ? 'text' : 'password'"
              autocomplete="off"
              placeholder="Paste your API key"
              input-class="!pr-9"
              @keyup.enter="saveKey(provider)"
            />
            <AppButton
              variant="ghost"
              size="sm"
              class="!absolute !inset-y-0 !right-0 !px-2.5 !py-0 !text-slate-400 hover:!text-slate-600"
              :title="keyVisible[provider.id] ? 'Hide API key' : 'Show API key'"
              @click="toggleKeyVisible(provider.id)"
            >
              <AppIcon name="view" class="w-4 h-4" />
            </AppButton>
          </div>
        </AppFormField>

        <AppButton
          size="sm"
          variant="secondary"
          :disabled="!hasDraft(provider.id)"
          :loading="store.isSavingProvider(provider.id)"
          @click="saveKey(provider)"
        >
          {{ provider.byok.configured ? 'Replace key' : 'Save key' }}
        </AppButton>
      </AppCard>
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

        <AppCard class="p-4 space-y-3">
          <AppTitle size="sm">Defaults</AppTitle>
          <AppText size="sm" muted>
            Used whenever you generate or refine captions without picking a service for that
            particular run.
          </AppText>

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

          <AppButton variant="primary" :loading="store.savingContentDefaults" @click="saveContentDefaults">
            Save defaults
          </AppButton>
        </AppCard>

        <AppCard v-for="provider in store.contentProviders" :key="provider.id" class="p-4 space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <AppTitle size="sm">{{ provider.label }}</AppTitle>
                <AppBadge v-if="provider.id === store.contentDefaultProvider" variant="info">Default</AppBadge>
              </div>
              <AppText size="sm" muted>{{ contentStatusLabel(provider) }}</AppText>
              <a
                v-if="contentApiKeyUrl(provider)"
                :href="contentApiKeyUrl(provider)"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-block mt-1 text-2xs text-slate-400 hover:text-slate-600 underline"
              >
                Get an API key
              </a>
            </div>
            <AppBadge :variant="provider.available ? 'success' : 'warning'">
              {{ provider.available ? 'Ready' : 'Not configured' }}
            </AppBadge>
          </div>

          <template v-if="store.supportsContentByok(provider.id)">
            <div v-if="provider.byok.configured" class="flex items-center justify-between gap-3 text-sm">
              <span class="text-slate-600">Your key ends in •••• {{ provider.byok.last_four }}</span>
              <AppButton
                size="sm"
                variant="ghost"
                tone="destructive"
                :loading="store.isSavingContentProvider(provider.id)"
                @click="removeContentKey(provider)"
              >
                Remove key
              </AppButton>
            </div>

            <AppFormField
              :id="contentKeyFieldId(provider)"
              :label="provider.byok.configured ? 'Replace your API key' : 'Your API key'"
              hint="Stored encrypted. It is never shown again after saving."
            >
              <div class="relative">
                <AppInput
                  :id="contentKeyFieldId(provider)"
                  v-model="contentKeyDrafts[provider.id]"
                  :type="contentKeyVisible[provider.id] ? 'text' : 'password'"
                  autocomplete="off"
                  placeholder="Paste your API key"
                  input-class="!pr-9"
                  @keyup.enter="saveContentKey(provider)"
                />
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="!absolute !inset-y-0 !right-0 !px-2.5 !py-0 !text-slate-400 hover:!text-slate-600"
                  :title="contentKeyVisible[provider.id] ? 'Hide API key' : 'Show API key'"
                  @click="toggleContentKeyVisible(provider.id)"
                >
                  <AppIcon name="view" class="w-4 h-4" />
                </AppButton>
              </div>
            </AppFormField>

            <AppButton
              size="sm"
              variant="secondary"
              :disabled="!hasContentDraft(provider.id)"
              :loading="store.isSavingContentProvider(provider.id)"
              @click="saveContentKey(provider)"
            >
              {{ provider.byok.configured ? 'Replace key' : 'Save key' }}
            </AppButton>
          </template>
          <AppText v-else size="sm" muted>
            Uses your local Ollama server — no API key needed.
          </AppText>
        </AppCard>
      </template>
    </template>
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
  AppInput,
  AppLoader,
  AppSegmentedControl,
  AppSelect,
  AppText,
  AppTitle,
} from '../components/ui';
import { AppPageHeader } from '../components/layout';

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
  { value: 'image', label: 'Images' },
  { value: 'content', label: 'Content' },
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

function toggleKeyVisible(providerId) {
  keyVisible[providerId] = !keyVisible[providerId];
}

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

function hasDraft(providerId) {
  return Boolean((keyDrafts[providerId] || '').trim());
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

function toggleContentKeyVisible(providerId) {
  contentKeyVisible[providerId] = !contentKeyVisible[providerId];
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

function hasContentDraft(providerId) {
  return Boolean((contentKeyDrafts[providerId] || '').trim());
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
