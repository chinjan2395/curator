<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-600">
        Store logo, colors, fonts, and watermark settings for consistent content.
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <ContentViewToggle v-model="viewMode" />
        <AppButton size="sm" @click="openCreate">New brand kit</AppButton>
      </div>
    </div>

    <AppEmptyState
      v-if="!kits.length && !loading"
      title="No brand kits yet"
      description="Create a kit with your logo and brand colors. The first kit becomes your default."
      icon="library"
    >
      <AppButton size="sm" @click="openCreate">Create brand kit</AppButton>
    </AppEmptyState>

    <div v-else-if="viewMode === 'grid'" class="brand-kit-grid">
      <AppCard
        v-for="kit in kits"
        :key="kit.id"
        padding="none"
        class="brand-kit-card h-full"
        :class="kit.is_default ? 'brand-kit-card--default' : ''"
      >
        <div class="brand-kit-card__inner">
          <div class="brand-kit-card__header">
            <div class="brand-kit-card__identity min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-slate-800 truncate">{{ kit.name }}</h3>
                <span v-if="kit.is_default" class="brand-kit-badge">Default</span>
              </div>
              <p v-if="kitSummary(kit)" class="brand-kit-card__meta">{{ kitSummary(kit) }}</p>
            </div>
            <div class="brand-kit-card__logo" :class="kit.logo_url ? '' : 'brand-kit-card__logo--empty'">
              <img
                v-if="kit.logo_url"
                :src="kit.logo_url"
                alt=""
                referrerpolicy="no-referrer"
                class="brand-kit-card__logo-image"
              />
              <span v-else class="brand-kit-card__logo-placeholder">No logo</span>
            </div>
          </div>

          <div class="brand-kit-card__section">
            <p class="brand-kit-card__label">Palette</p>
            <div class="brand-kit-card__swatches">
              <span
                v-for="(hex, key) in kit.colors || {}"
                :key="key"
                class="brand-kit-swatch"
                :title="`${colorLabel(key)}: ${hex}`"
                :style="{ backgroundColor: hex }"
              />
            </div>
          </div>

          <div class="brand-kit-card__actions">
            <AppButton size="sm" variant="secondary" @click="openEdit(kit)">Edit</AppButton>
            <AppButton
              v-if="!kit.is_default"
              size="sm"
              variant="ghost"
              @click="setDefault(kit)"
            >
              Set default
            </AppButton>
            <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(kit)">Delete</AppButton>
          </div>
        </div>
      </AppCard>
    </div>

    <div v-else class="brand-kit-list">
      <ContentLibraryRow v-for="kit in kits" :key="kit.id">
        <template #leading>
          <div class="brand-kit-row__logo" :class="kit.logo_url ? '' : 'brand-kit-row__logo--empty'">
            <img
              v-if="kit.logo_url"
              :src="kit.logo_url"
              alt=""
              referrerpolicy="no-referrer"
              class="brand-kit-row__logo-image"
            />
            <span v-else class="brand-kit-card__logo-placeholder">No logo</span>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-2 min-w-0">
          <p class="font-semibold text-slate-800 truncate">{{ kit.name }}</p>
          <span v-if="kit.is_default" class="brand-kit-badge">Default</span>
        </div>
        <p v-if="kitSummary(kit)" class="text-2xs text-slate-500 mt-0.5">{{ kitSummary(kit) }}</p>
        <div class="brand-kit-row__swatches">
          <span
            v-for="(hex, key) in kit.colors || {}"
            :key="key"
            class="brand-kit-swatch"
            :title="`${colorLabel(key)}: ${hex}`"
            :style="{ backgroundColor: hex }"
          />
        </div>

        <template #actions>
          <AppButton size="sm" variant="secondary" @click="openEdit(kit)">Edit</AppButton>
          <AppButton v-if="!kit.is_default" size="sm" variant="ghost" @click="setDefault(kit)">
            Set default
          </AppButton>
          <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(kit)">Delete</AppButton>
        </template>
      </ContentLibraryRow>
    </div>

    <AppModal
      :open="editorOpen"
      :title="editingId ? 'Edit brand kit' : 'New brand kit'"
      size="xl"
      @close="closeEditor"
    >
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <AppFormField label="Name" required class="sm:col-span-2">
            <AppInput v-model="form.name" placeholder="Acme Brand" />
          </AppFormField>

          <AppFormField label="Logo URL" hint="HTTPS URL or upload below" class="sm:col-span-2">
            <AppInput v-model="form.logo_url" placeholder="https://…" />
          </AppFormField>

          <div class="sm:col-span-2 flex flex-wrap items-end gap-2">
            <label class="text-sm text-slate-600">
              <span class="block mb-1">Upload logo</span>
              <input type="file" accept="image/*" @change="onLogoUpload" />
            </label>
            <AppButton
              v-if="form.logo_url || form.logo_asset_id"
              type="button"
              size="sm"
              variant="ghost"
              @click="clearLogo"
            >
              Clear logo
            </AppButton>
          </div>
        </div>

        <div class="brand-kit-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Colors</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <AppFormField v-for="key in colorKeys" :key="key" :label="colorLabel(key)">
              <div class="flex items-center gap-2">
                <input
                  v-model="form.colors[key]"
                  type="color"
                  class="brand-kit-color-input"
                  @input="onColorPicker(key, $event)"
                />
                <AppInput v-model="form.colors[key]" input-class="font-mono text-sm" />
              </div>
            </AppFormField>
          </div>
        </div>

        <div class="brand-kit-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Fonts</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <AppFormField label="Heading">
              <AppInput v-model="form.fonts.heading" placeholder="inherit or Inter" />
            </AppFormField>
            <AppFormField label="Body">
              <AppInput v-model="form.fonts.body" placeholder="inherit or Inter" />
            </AppFormField>
          </div>
        </div>

        <div class="brand-kit-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Watermark</p>
          <label class="flex items-center gap-2 text-sm mb-3">
            <input v-model="form.watermark.enabled" type="checkbox" />
            Enable watermark
          </label>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <AppFormField label="Watermark URL" class="sm:col-span-2">
              <AppInput v-model="form.watermark.url" placeholder="https://…" :disabled="!form.watermark.enabled" />
            </AppFormField>
            <AppFormField label="Position">
              <AppSelect v-model="form.watermark.position" :show-placeholder="false" :disabled="!form.watermark.enabled">
                <option value="top_left">Top left</option>
                <option value="top_right">Top right</option>
                <option value="bottom_left">Bottom left</option>
                <option value="bottom_right">Bottom right</option>
                <option value="center">Center</option>
              </AppSelect>
            </AppFormField>
            <AppFormField label="Opacity">
              <input
                v-model.number="form.watermark.opacity"
                type="range"
                min="0"
                max="1"
                step="0.05"
                class="w-full"
                :disabled="!form.watermark.enabled"
              />
              <span class="text-2xs text-slate-500">{{ Math.round(form.watermark.opacity * 100) }}%</span>
            </AppFormField>
          </div>
        </div>

        <label v-if="kits.length && !editingId" class="flex items-center gap-2 text-sm">
          <input v-model="form.is_default" type="checkbox" />
          Set as default brand kit
        </label>
        <label v-else-if="editingId && !form.is_default" class="flex items-center gap-2 text-sm">
          <input v-model="form.is_default" type="checkbox" />
          Set as default brand kit
        </label>

        <div class="brand-kit-preview">
          <p class="text-xs font-semibold text-slate-500 mb-2">Preview</p>
          <div
            class="brand-kit-preview-card"
            :style="{
              backgroundColor: form.colors.background,
              color: form.colors.text,
              fontFamily: previewFont,
            }"
          >
            <div class="flex items-center gap-2 mb-2">
              <img
                v-if="form.logo_url"
                :src="form.logo_url"
                alt=""
                referrerpolicy="no-referrer"
                class="h-8 w-8 object-contain rounded"
              />
              <span class="font-semibold" :style="{ color: form.colors.primary }">{{ form.name || 'Brand name' }}</span>
            </div>
            <p class="text-sm">Sample caption using your brand palette.</p>
            <span
              class="inline-block mt-2 text-xs font-medium px-2 py-1 rounded"
              :style="{ backgroundColor: form.colors.accent, color: form.colors.background }"
            >
              CTA
            </span>
          </div>
        </div>

        <p v-if="formError" class="text-2xs text-red-600">{{ formError }}</p>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeEditor">Cancel</AppButton>
        <AppButton :disabled="saving || !form.name.trim()" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';
import {
  AppButton,
  AppCard,
  AppEmptyState,
  AppFormField,
  AppInput,
  AppModal,
  AppSelect,
} from '../ui';
import ContentLibraryRow from './ContentLibraryRow.vue';
import ContentViewToggle from './ContentViewToggle.vue';

const props = defineProps({
  active: { type: Boolean, default: true },
});

const toast = useToastStore();
const kits = ref([]);
const loading = ref(false);
const editorOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const viewMode = ref('grid');

const DEFAULT_COLORS = {
  primary: '#2563eb',
  secondary: '#64748b',
  accent: '#0f172a',
  background: '#ffffff',
  text: '#0f172a',
};

const DEFAULT_FONTS = { heading: 'inherit', body: 'inherit' };

const DEFAULT_WATERMARK = {
  enabled: false,
  url: '',
  position: 'bottom_right',
  opacity: 0.3,
};

const colorKeys = ['primary', 'secondary', 'accent', 'background', 'text'];

const form = reactive({
  name: '',
  logo_url: '',
  logo_asset_id: null,
  is_default: false,
  colors: { ...DEFAULT_COLORS },
  fonts: { ...DEFAULT_FONTS },
  watermark: { ...DEFAULT_WATERMARK },
});

const previewFont = computed(() => {
  const body = form.fonts.body?.trim();
  return body && body !== 'inherit' ? body : 'inherit';
});

function colorLabel(key) {
  return key.charAt(0).toUpperCase() + key.slice(1);
}

function kitSummary(kit) {
  const parts = [];
  const heading = kit.fonts?.heading?.trim();
  const body = kit.fonts?.body?.trim();
  if (heading && heading !== 'inherit') parts.push(`Heading: ${heading}`);
  if (body && body !== 'inherit') parts.push(`Body: ${body}`);
  if (kit.watermark?.enabled) parts.push('Watermark on');
  return parts.join(' · ');
}

function onColorPicker(key, event) {
  form.colors[key] = event.target.value;
}

function resetForm() {
  form.name = '';
  form.logo_url = '';
  form.logo_asset_id = null;
  form.is_default = kits.value.length === 0;
  Object.assign(form.colors, DEFAULT_COLORS);
  Object.assign(form.fonts, DEFAULT_FONTS);
  Object.assign(form.watermark, DEFAULT_WATERMARK);
  formError.value = '';
}

function hydrateForm(kit) {
  form.name = kit.name || '';
  form.logo_url = kit.logo_url || '';
  form.logo_asset_id = kit.logo_asset_id ?? null;
  form.is_default = Boolean(kit.is_default);
  Object.assign(form.colors, { ...DEFAULT_COLORS, ...(kit.colors || {}) });
  Object.assign(form.fonts, { ...DEFAULT_FONTS, ...(kit.fonts || {}) });
  Object.assign(form.watermark, { ...DEFAULT_WATERMARK, ...(kit.watermark || {}) });
  formError.value = '';
}

function payloadFromForm() {
  return {
    name: form.name.trim(),
    logo_url: form.logo_asset_id ? null : (form.logo_url.trim() || null),
    logo_asset_id: form.logo_asset_id,
    is_default: form.is_default,
    colors: { ...form.colors },
    fonts: { ...form.fonts },
    watermark: { ...form.watermark },
  };
}

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/content/brand-kits', { skipErrorToast: true });
    kits.value = data.data || data || [];
  } catch {
    kits.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  resetForm();
  editorOpen.value = true;
}

function openEdit(kit) {
  editingId.value = kit.id;
  hydrateForm(kit);
  editorOpen.value = true;
}

function closeEditor() {
  editorOpen.value = false;
  editingId.value = null;
}

async function save() {
  if (!form.name.trim()) return;
  saving.value = true;
  formError.value = '';
  const body = payloadFromForm();
  try {
    if (editingId.value) {
      await axios.put(`/api/content/brand-kits/${editingId.value}`, body);
      toast.success('Brand kit updated');
    } else {
      await axios.post('/api/content/brand-kits', body);
      toast.success('Brand kit created');
    }
    closeEditor();
    await load();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to save brand kit';
  } finally {
    saving.value = false;
  }
}

async function setDefault(kit) {
  try {
    await axios.put(`/api/content/brand-kits/${kit.id}`, { is_default: true });
    toast.success('Default brand kit updated');
    await load();
  } catch {
    // interceptor
  }
}

async function confirmDelete(kit) {
  if (!window.confirm(`Delete brand kit "${kit.name}"?`)) return;
  try {
    await axios.delete(`/api/content/brand-kits/${kit.id}`);
    toast.success('Brand kit deleted');
    await load();
  } catch {
    // interceptor
  }
}

function clearLogo() {
  form.logo_url = '';
  form.logo_asset_id = null;
}

async function onLogoUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', 'image');
  try {
    const { data } = await axios.post('/api/content/assets', formData);
    const asset = data.data || data;
    if (asset?.id && asset?.url) {
      form.logo_asset_id = asset.id;
      form.logo_url = asset.url;
      toast.success('Logo uploaded');
    }
  } catch {
    // interceptor
  } finally {
    e.target.value = '';
  }
}

onMounted(() => {
  if (props.active) load();
});

defineExpose({ load });
</script>

<style scoped>
.brand-kit-list {
  display: grid;
  gap: 0.75rem;
}

.brand-kit-row__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #fff;
  overflow: hidden;
}

.brand-kit-row__logo--empty {
  background: rgba(248, 250, 252, 0.95);
}

.brand-kit-row__logo-image {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: contain;
}

.brand-kit-row__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.45rem;
}

.brand-kit-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
  .brand-kit-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.brand-kit-card {
  display: flex;
  flex-direction: column;
}

.brand-kit-card--default {
  border-color: rgba(37, 99, 235, 0.35);
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.08);
}

.brand-kit-card__inner {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  height: 100%;
  padding: 1rem;
}

.brand-kit-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
}

.brand-kit-card__identity {
  flex: 1 1 auto;
  min-width: 0;
}

.brand-kit-card__meta {
  margin-top: 0.35rem;
  font-size: 0.72rem;
  line-height: 1.4;
  color: #64748b;
}

.brand-kit-card__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  flex-shrink: 0;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #fff;
}

.brand-kit-card__logo--empty {
  background: rgba(248, 250, 252, 0.95);
}

.brand-kit-card__logo-image {
  width: 2.25rem;
  height: 2.25rem;
  object-fit: contain;
  border-radius: 0.35rem;
}

.brand-kit-card__logo-placeholder {
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #94a3b8;
  text-align: center;
  padding: 0 0.25rem;
}

.brand-kit-card__section {
  display: grid;
  gap: 0.55rem;
  min-height: 2.75rem;
}

.brand-kit-card__label {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}

.brand-kit-card__swatches {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
}

.brand-kit-card__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.85rem;
  border-top: 1px solid #e6ebf2;
}

.brand-kit-badge {
  font-size: 0.68rem;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
  font-weight: 600;
}

.brand-kit-swatch {
  display: inline-block;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 0.4rem;
  border: 1px solid rgba(15, 23, 42, 0.12);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.brand-kit-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}

.brand-kit-color-input {
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  cursor: pointer;
}

.brand-kit-preview-card {
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1rem;
}
</style>
