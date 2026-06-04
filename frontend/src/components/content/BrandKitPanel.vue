<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-600">
        Store logo, colors, fonts, and watermark settings for consistent content.
      </p>
      <AppButton size="sm" @click="openCreate">New brand kit</AppButton>
    </div>

    <AppEmptyState
      v-if="!kits.length && !loading"
      title="No brand kits yet"
      description="Create a kit with your logo and brand colors. The first kit becomes your default."
      icon="library"
    >
      <AppButton size="sm" @click="openCreate">Create brand kit</AppButton>
    </AppEmptyState>

    <div v-else class="grid gap-3 md:grid-cols-2">
      <AppCard
        v-for="kit in kits"
        :key="kit.id"
        class="p-4 space-y-3 brand-kit-card"
        :class="kit.is_default ? 'brand-kit-card--default' : ''"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold text-slate-800 truncate">{{ kit.name }}</h3>
              <span v-if="kit.is_default" class="brand-kit-badge">Default</span>
            </div>
          </div>
          <img
            v-if="kit.logo_url"
            :src="kit.logo_url"
            alt=""
            class="h-10 w-10 rounded-lg border border-slate-200 object-contain bg-white shrink-0"
          />
        </div>

        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="(hex, key) in kit.colors || {}"
            :key="key"
            class="brand-kit-swatch"
            :title="`${key}: ${hex}`"
            :style="{ backgroundColor: hex }"
          />
        </div>

        <div class="flex flex-wrap gap-2">
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
      </AppCard>
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
              v-if="form.logo_url"
              type="button"
              size="sm"
              variant="ghost"
              @click="form.logo_url = ''"
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

function onColorPicker(key, event) {
  form.colors[key] = event.target.value;
}

function resetForm() {
  form.name = '';
  form.logo_url = '';
  form.is_default = kits.value.length === 0;
  Object.assign(form.colors, DEFAULT_COLORS);
  Object.assign(form.fonts, DEFAULT_FONTS);
  Object.assign(form.watermark, DEFAULT_WATERMARK);
  formError.value = '';
}

function hydrateForm(kit) {
  form.name = kit.name || '';
  form.logo_url = kit.logo_url || '';
  form.is_default = Boolean(kit.is_default);
  Object.assign(form.colors, { ...DEFAULT_COLORS, ...(kit.colors || {}) });
  Object.assign(form.fonts, { ...DEFAULT_FONTS, ...(kit.fonts || {}) });
  Object.assign(form.watermark, { ...DEFAULT_WATERMARK, ...(kit.watermark || {}) });
  formError.value = '';
}

function payloadFromForm() {
  return {
    name: form.name.trim(),
    logo_url: form.logo_url.trim() || null,
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

async function onLogoUpload(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', 'image');
  try {
    const { data } = await axios.post('/api/content/assets', formData);
    const asset = data.data || data;
    if (asset?.url) {
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
.brand-kit-card--default {
  border-color: rgba(37, 99, 235, 0.35);
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.08);
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
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 0.35rem;
  border: 1px solid rgba(15, 23, 42, 0.12);
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
