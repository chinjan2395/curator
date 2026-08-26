<template>
  <AppModal :open="open" title="Generate an AI image" size="xl" @close="$emit('close')">
    <div class="space-y-4">
      <AppAlert v-if="!aiSettings.hasAnyProvider" variant="warning" title="No image service is configured">
        Add an API key on the
        <router-link to="/settings/ai" class="underline font-medium">AI settings</router-link>
        page, or ask an administrator to configure a platform key.
      </AppAlert>

      <section class="space-y-2">
        <p class="text-xs font-medium text-slate-700">Reference image (optional)</p>
        <p class="text-2xs text-slate-500">
          Supply an image to build on. Without one the image is generated from the description alone.
        </p>

        <AppSegmentedControl
          v-model="referenceMode"
          :options="referenceModes"
          aria-label="Reference image source"
          compact
        />

        <div v-if="referenceMode === 'upload'" class="space-y-2">
          <AppInput
            type="file"
            accept="image/png,image/jpeg,image/webp"
            input-class="!h-9 !text-xs !py-1.5"
            @change="onFileChange"
          />
          <p v-if="uploadError" class="text-2xs text-rose-600">{{ uploadError }}</p>
          <p v-else-if="uploadFile" class="text-2xs text-slate-500">
            {{ uploadFile.name }} — also saved to your content library.
          </p>
          <img
            v-if="uploadPreviewUrl"
            :src="uploadPreviewUrl"
            alt="Reference preview"
            class="h-20 w-20 rounded-lg border border-slate-200 object-cover"
          />
        </div>

        <div v-else-if="referenceMode === 'library'" class="space-y-2">
          <AppSelect v-model="referenceAssetId" placeholder="Choose an image">
            <option v-for="asset in imageAssets" :key="asset.id" :value="String(asset.id)">
              {{ asset.file_name }}
            </option>
          </AppSelect>
          <p v-if="!imageAssets.length" class="text-2xs text-amber-700">
            No images in your library yet — upload one instead.
          </p>
          <img
            v-if="selectedLibraryAsset?.url"
            :src="selectedLibraryAsset.url"
            :alt="selectedLibraryAsset.file_name"
            class="h-20 w-20 rounded-lg border border-slate-200 object-cover"
          />
        </div>
      </section>

      <AppFormField label="Description" hint="What the image should show, or how to change the reference.">
        <AppInput
          v-model="instruction"
          type="textarea"
          :rows="3"
          placeholder="e.g. minimal flat lay on marble, soft natural light"
          input-class="!text-sm"
        />
      </AppFormField>

      <section class="space-y-2">
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs font-medium text-slate-700">Full prompt</p>
          <AppButton
            variant="ghost"
            size="sm"
            :loading="previewLoading"
            :disabled="!props.packageId"
            @click="fetchPromptPreview"
          >
            {{ promptFetched ? 'Refresh prompt' : 'Preview prompt' }}
          </AppButton>
        </div>

        <AppAlert v-if="previewError" variant="danger" title="Couldn't preview the prompt">
          {{ previewError }}
        </AppAlert>

        <template v-if="promptFetched">
          <p v-if="promptStale" class="text-2xs text-amber-700">
            Description or reference changed since this preview — refresh to see the latest prompt.
          </p>
          <AppInput
            v-model="promptOverride"
            type="textarea"
            :rows="4"
            input-class="!text-xs !font-mono"
          />
        </template>
      </section>

      <div class="space-y-1">
        <div class="flex items-center gap-1.5">
          <label class="label-pro">Image service &amp; model</label>
          <router-link
            v-if="isMenuEnabled('ai-settings')"
            to="/settings/ai"
            title="Manage AI settings"
            class="text-slate-400 hover:text-slate-600"
          >
            <AppIcon name="settings" class="w-3.5 h-3.5" />
          </router-link>
        </div>
        <AiProviderModelPicker
          kind="image"
          v-model="providerModel"
          :require-reference="referenceMode !== 'none'"
        />
        <p class="text-xs text-slate-400">{{ providerHint }}</p>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <AppButton variant="ghost" @click="$emit('close')">Cancel</AppButton>
        <AppButton variant="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">
          Generate image
        </AppButton>
      </div>
    </template>
  </AppModal>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useAiSettingsStore } from '../../stores/aiSettings';
import { useCampaignsStore } from '../../stores/campaigns';
import { useNavigationVisibility } from '../../composables/useNavigationVisibility';
import AiProviderModelPicker from './AiProviderModelPicker.vue';
import {
  AppAlert,
  AppButton,
  AppFormField,
  AppIcon,
  AppInput,
  AppModal,
  AppSegmentedControl,
  AppSelect,
} from '../ui';

const MAX_REFERENCE_BYTES = 8 * 1024 * 1024;
const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

const props = defineProps({
  open: { type: Boolean, required: true },
  packageId: { type: Number, default: null },
  /** Image assets already in the user's library, for the "pick" tab. */
  assets: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'generate']);

const aiSettings = useAiSettingsStore();
const campaigns = useCampaignsStore();
const { isMenuEnabled } = useNavigationVisibility();

const referenceModes = [
  { value: 'none', label: 'No reference' },
  { value: 'upload', label: 'Upload' },
  { value: 'library', label: 'From library' },
];

const referenceMode = ref('none');
const referenceAssetId = ref('');
const uploadFile = ref(null);
const uploadError = ref('');
const instruction = ref('');
const providerModel = ref({ provider: '', model: '' });
const provider = computed(() => providerModel.value.provider);
const promptOverride = ref('');
const promptFetched = ref(false);
const promptStale = ref(false);
const previewLoading = ref(false);
const previewError = ref('');

const imageAssets = computed(() => props.assets.filter((asset) => asset.type === 'image'));

const uploadPreviewUrl = ref('');

const selectedLibraryAsset = computed(() => {
  if (!referenceAssetId.value) return null;
  const asset = imageAssets.value.find((a) => String(a.id) === String(referenceAssetId.value));
  return asset && asset.type === 'image' ? asset : null;
});

const providerHint = computed(() => {
  const selected = provider.value || aiSettings.defaultProvider;
  if (!selected) return 'Falls back to the platform default.';
  const found = aiSettings.providerById(selected);
  if (!found) return 'Falls back to the platform default.';
  return found.byok?.configured ? 'Using your own API key.' : 'Using the platform API key.';
});

const canSubmit = computed(() => {
  if (props.submitting || uploadError.value) return false;
  if (referenceMode.value === 'upload' && !uploadFile.value) return false;
  if (referenceMode.value === 'library' && !referenceAssetId.value) return false;
  return true;
});

function revokeUploadPreview() {
  if (uploadPreviewUrl.value) URL.revokeObjectURL(uploadPreviewUrl.value);
  uploadPreviewUrl.value = '';
}

function reset() {
  referenceMode.value = 'none';
  referenceAssetId.value = '';
  uploadFile.value = null;
  uploadError.value = '';
  instruction.value = '';
  providerModel.value = { provider: '', model: '' };
  promptOverride.value = '';
  promptFetched.value = false;
  promptStale.value = false;
  previewLoading.value = false;
  previewError.value = '';
  revokeUploadPreview();
}

async function fetchPromptPreview() {
  if (!props.packageId) return;
  previewLoading.value = true;
  previewError.value = '';
  try {
    const prompt = await campaigns.previewImagePrompt(props.packageId, {
      instruction: instruction.value.trim(),
      referenceAssetId: referenceMode.value === 'library' ? Number(referenceAssetId.value) : null,
    });
    promptOverride.value = prompt || '';
    promptFetched.value = true;
    promptStale.value = false;
  } catch (e) {
    previewError.value = e.response?.data?.message || 'Failed to preview the prompt.';
  } finally {
    previewLoading.value = false;
  }
}

function onFileChange(event) {
  const file = event.target.files?.[0] || null;
  uploadFile.value = null;
  uploadError.value = '';
  revokeUploadPreview();

  if (!file) return;

  // Mirrors the server-side rules so the user hears about it before the upload.
  if (!ALLOWED_TYPES.includes(file.type)) {
    uploadError.value = 'Reference must be a PNG, JPEG, or WebP image.';
    return;
  }
  if (file.size > MAX_REFERENCE_BYTES) {
    uploadError.value = 'Reference must be 8 MB or smaller.';
    return;
  }

  uploadFile.value = file;
  uploadPreviewUrl.value = URL.createObjectURL(file);
}

function submit() {
  if (!canSubmit.value) return;

  emit('generate', {
    instruction: instruction.value.trim(),
    provider: providerModel.value.provider || null,
    model: providerModel.value.model || null,
    reference: referenceMode.value === 'upload' ? uploadFile.value : null,
    referenceAssetId: referenceMode.value === 'library' ? Number(referenceAssetId.value) : null,
    prompt: promptFetched.value ? promptOverride.value.trim() || null : null,
  });
}

watch([instruction, referenceMode, referenceAssetId, uploadFile], () => {
  if (promptFetched.value) promptStale.value = true;
});

// Drop a provider selection that can no longer run once a reference is attached.
watch(referenceMode, (mode) => {
  if (mode === 'none' || !provider.value) return;
  const selected = aiSettings.providerById(provider.value);
  if (selected?.supports_reference === false) {
    providerModel.value = { provider: '', model: '' };
  }
});

watch(
  () => props.open,
  (open) => {
    if (!open) {
      revokeUploadPreview();
      providerModel.value = { ...providerModel.value, model: '' };
      return;
    }
    reset();
    aiSettings.load().catch(() => {});
  },
);

onBeforeUnmount(() => {
  revokeUploadPreview();
});
</script>
