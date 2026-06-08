<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-600">
        Upload images and videos to attach to content packages and brand kits.
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <ContentViewToggle v-model="viewMode" />
        <AppButton size="sm" @click="openUpload">Upload asset</AppButton>
      </div>
    </div>

    <!-- Upload/search controls live in their own card -->
    <AppCard padding="none" class="p-4 md:p-5 border border-slate-200/90">
      <div class="asset-toolbar">
        <AppInput
          v-model="query"
          placeholder="Search by file name…"
          input-class="flex-1 min-w-[12rem]"
          @keyup.enter="reload"
        />
        <AppSelect v-model="typeFilter" class="asset-toolbar__filter" :show-placeholder="false" @change="reload">
          <option value="">All types</option>
          <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </AppSelect>
        <AppButton size="sm" variant="secondary" :disabled="loading" @click="reload">
          {{ loading ? 'Searching…' : 'Search' }}
        </AppButton>
      </div>
    </AppCard>

    <!-- Asset list is outside of the upload/search card -->
    <AppLoader v-if="loading && !assets.length" label="Loading assets…" />

    <AppEmptyState
      v-else-if="!assets.length"
      title="No assets yet"
      description="Upload images or videos to attach to content packages."
      icon="library"
    >
      <AppButton size="sm" @click="openUpload">Upload asset</AppButton>
    </AppEmptyState>

    <template v-else>
      <div v-if="viewMode === 'grid'" class="asset-grid">
        <AppCard v-for="asset in assets" :key="asset.id" padding="none" class="asset-card h-full">
          <div class="asset-card__inner">
            <div class="asset-card__preview" :class="previewClass(asset)">
              <img
                v-if="isImage(asset) && asset.url"
                :src="asset.url"
                :alt="asset.file_name"
                referrerpolicy="no-referrer"
                class="asset-card__preview-image"
              />
              <div v-else class="asset-card__preview-fallback">
                <span class="asset-type-badge">{{ formatType(asset.type) }}</span>
              </div>
            </div>

            <div class="asset-card__body">
              <h3 class="font-semibold text-slate-800 truncate" :title="asset.file_name">{{ asset.file_name }}</h3>
              <p class="asset-card__meta">
                {{ formatType(asset.type) }} · {{ formatSize(asset.file_size) }}
                <span v-if="asset.storage_disk" class="text-slate-400"> · {{ asset.storage_disk }}</span>
              </p>

              <div v-if="(asset.ai_tags || []).length" class="asset-card__tags">
                <span
                  v-for="tag in asset.ai_tags"
                  :key="tag"
                  class="asset-tag"
                >{{ tag }}</span>
              </div>
            </div>

            <div class="asset-card__actions">
              <AppButton
                v-if="asset.url"
                size="sm"
                variant="secondary"
                :href="asset.url"
                target="_blank"
                rel="noopener"
              >
                Open
              </AppButton>
              <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(asset)">
                Delete
              </AppButton>
            </div>
          </div>
        </AppCard>
      </div>

      <div v-else class="asset-list">
        <ContentLibraryRow v-for="asset in assets" :key="asset.id">
          <template #leading>
            <div class="asset-row__thumb">
              <img
                v-if="isImage(asset) && asset.url"
                :src="asset.url"
                :alt="asset.file_name"
                referrerpolicy="no-referrer"
                class="asset-row__thumb-image"
              />
              <span v-else class="asset-type-badge">{{ formatType(asset.type) }}</span>
            </div>
          </template>

          <div class="flex items-center gap-2 min-w-0">
            <p class="font-semibold text-slate-800 truncate" :title="asset.file_name">{{ asset.file_name }}</p>
            <span class="text-2xs text-slate-400 whitespace-nowrap">{{ formatSize(asset.file_size) }}</span>
          </div>
          <p class="text-2xs text-slate-500 mt-0.5">
            {{ formatType(asset.type) }}<span v-if="asset.storage_disk"> · {{ asset.storage_disk }}</span>
          </p>
          <div v-if="(asset.ai_tags || []).length" class="asset-row__tags">
            <span v-for="tag in asset.ai_tags" :key="tag" class="asset-tag">{{ tag }}</span>
          </div>

          <template #actions>
            <AppButton
              v-if="asset.url"
              size="sm"
              variant="secondary"
              :href="asset.url"
              target="_blank"
              rel="noopener"
            >
              Open
            </AppButton>
            <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(asset)">
              Delete
            </AppButton>
          </template>
        </ContentLibraryRow>
      </div>

      <div v-if="hasMore" class="flex justify-center pt-2">
        <AppButton size="sm" variant="secondary" :disabled="loadingMore" @click="loadMore">
          {{ loadingMore ? 'Loading…' : 'Load more' }}
        </AppButton>
      </div>
    </template>

    <AppModal :open="uploadOpen" title="Upload asset" size="lg" @close="closeUpload">
      <form id="asset-upload-form" class="space-y-4" @submit.prevent="upload">
        <div class="asset-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">File</p>
          <AppFormField label="Choose file" required hint="Images and videos up to 50 MB.">
            <label class="asset-file-picker">
              <span class="asset-file-picker__label">Select file</span>
              <input type="file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx" @change="onFileChange" />
            </label>
            <p v-if="selectedFile" class="asset-file-picker__meta">
              {{ selectedFile.name }} · {{ formatSize(selectedFile.size) }}
            </p>
          </AppFormField>
        </div>

        <div class="asset-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Details</p>
          <AppFormField label="Asset type" required hint="Auto-detected from the file when possible.">
            <AppSelect v-model="uploadType" :show-placeholder="false">
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </AppSelect>
          </AppFormField>
        </div>

        <p v-if="formError" class="text-2xs text-red-600">{{ formError }}</p>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeUpload">Cancel</AppButton>
        <AppButton
          type="submit"
          form="asset-upload-form"
          :disabled="uploading || !selectedFile"
        >
          {{ uploading ? 'Uploading…' : 'Upload' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';
import {
  AppButton,
  AppCard,
  AppEmptyState,
  AppFormField,
  AppInput,
  AppLoader,
  AppModal,
  AppSelect,
} from '../ui';
import ContentLibraryRow from './ContentLibraryRow.vue';
import ContentViewToggle from './ContentViewToggle.vue';

const props = defineProps({
  active: { type: Boolean, default: true },
});

const toast = useToastStore();
const assets = ref([]);
const loading = ref(false);
const loadingMore = ref(false);
const uploading = ref(false);
const uploadOpen = ref(false);
const formError = ref('');
const query = ref('');
const typeFilter = ref('');
const selectedFile = ref(null);
const uploadType = ref('image');
const page = ref(1);
const lastPage = ref(1);
const viewMode = ref('grid');

const typeOptions = [
  { value: 'image', label: 'Image' },
  { value: 'video', label: 'Video' },
  { value: 'audio', label: 'Audio' },
  { value: 'document', label: 'Document' },
  { value: 'template', label: 'Template' },
];

const hasMore = computed(() => page.value < lastPage.value);

function formatType(value) {
  const match = typeOptions.find((opt) => opt.value === value);
  return match?.label || value || 'Asset';
}

function formatSize(bytes) {
  if (!bytes) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isImage(asset) {
  return asset?.type === 'image' || String(asset?.mime_type || '').startsWith('image/');
}

function previewClass(asset) {
  return isImage(asset) ? 'asset-card__preview--image' : 'asset-card__preview--fallback';
}

function detectType(file) {
  const mime = String(file?.type || '');
  if (mime.startsWith('image/')) return 'image';
  if (mime.startsWith('video/')) return 'video';
  if (mime.startsWith('audio/')) return 'audio';
  return 'document';
}

function extractList(payload) {
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload)) return payload;
  return [];
}

async function fetchPage(nextPage, append = false) {
  const { data } = await axios.get('/api/content/assets', {
    params: {
      q: query.value.trim() || undefined,
      type: typeFilter.value || undefined,
      page: nextPage,
      per_page: 24,
    },
    skipErrorToast: true,
  });

  const payload = data.data || data;
  const list = extractList(payload);
  assets.value = append ? [...assets.value, ...list] : list;
  page.value = payload?.current_page || nextPage;
  lastPage.value = payload?.last_page || 1;
}

async function reload() {
  loading.value = true;
  formError.value = '';
  try {
    await fetchPage(1, false);
  } catch (e) {
    assets.value = [];
    formError.value = e.response?.data?.message || 'Failed to load assets';
  } finally {
    loading.value = false;
  }
}

async function loadMore() {
  if (!hasMore.value || loadingMore.value) return;
  loadingMore.value = true;
  try {
    await fetchPage(page.value + 1, true);
  } catch {
    // interceptor
  } finally {
    loadingMore.value = false;
  }
}

function openUpload() {
  selectedFile.value = null;
  uploadType.value = 'image';
  formError.value = '';
  uploadOpen.value = true;
}

function closeUpload() {
  uploadOpen.value = false;
  selectedFile.value = null;
  formError.value = '';
}

function onFileChange(e) {
  const file = e.target.files?.[0];
  selectedFile.value = file || null;
  if (file) uploadType.value = detectType(file);
}

async function upload() {
  if (!selectedFile.value) return;

  uploading.value = true;
  formError.value = '';

  const form = new FormData();
  form.append('file', selectedFile.value);
  form.append('type', uploadType.value);

  try {
    await axios.post('/api/content/assets', form);
    toast.success('Asset uploaded');
    closeUpload();
    await reload();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to upload asset';
  } finally {
    uploading.value = false;
  }
}

async function confirmDelete(asset) {
  if (!window.confirm(`Delete asset "${asset.file_name}"?`)) return;
  try {
    await axios.delete(`/api/content/assets/${asset.id}`);
    toast.success('Asset deleted');
    await reload();
  } catch {
    // interceptor
  }
}

onMounted(() => {
  if (props.active) reload();
});

defineExpose({ load: reload });
</script>

<style scoped>
.asset-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
}

.asset-toolbar__filter {
  min-width: 9rem;
}

.asset-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 640px) {
  .asset-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .asset-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.asset-card {
  display: flex;
  flex-direction: column;
}

.asset-card__inner {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.asset-card__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 8.5rem;
  border-bottom: 1px solid #e6ebf2;
  background: rgba(248, 250, 252, 0.95);
}

.asset-card__preview--image {
  padding: 0.5rem;
}

.asset-card__preview-image {
  width: 100%;
  max-height: 10rem;
  object-fit: contain;
  border-radius: 0.5rem;
}

.asset-card__preview-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 8.5rem;
}

.asset-card__body {
  display: grid;
  gap: 0.45rem;
  padding: 0.85rem 1rem 0.75rem;
  min-width: 0;
}

.asset-card__meta {
  font-size: 0.72rem;
  line-height: 1.4;
  color: #64748b;
}

.asset-card__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.asset-tag {
  font-size: 0.68rem;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: rgba(241, 245, 249, 0.95);
  color: #475569;
  border: 1px solid #e2e8f0;
}

.asset-card__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-top: auto;
  padding: 0.85rem 1rem;
  border-top: 1px solid #e6ebf2;
}

.asset-list {
  display: grid;
  gap: 0.75rem;
}

.asset-row__thumb {
  width: 3.25rem;
  height: 3.25rem;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.95);
  overflow: hidden;
}

.asset-row__thumb-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.asset-row__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.45rem;
}

.asset-type-badge {
  font-size: 0.72rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.asset-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}

.asset-file-picker {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.asset-file-picker input[type='file'] {
  font-size: 0.82rem;
}

.asset-file-picker__label {
  display: inline-flex;
  align-items: center;
  min-height: 2rem;
  padding: 0.35rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.65rem;
  background: #fff;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}

.asset-file-picker__meta {
  margin-top: 0.45rem;
  font-size: 0.72rem;
  color: #64748b;
}
</style>
