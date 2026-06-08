<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-600">
        Reusable caption starters for AI generation and publishing workflows.
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <ContentViewToggle v-model="viewMode" />
        <AppButton size="sm" @click="openCreate">New template</AppButton>
      </div>
    </div>

    <AppEmptyState
      v-if="!templates.length && !loading"
      title="No templates yet"
      description="Create a template with a default caption and optional platform targeting."
      icon="library"
    >
      <AppButton size="sm" @click="openCreate">Create template</AppButton>
    </AppEmptyState>

    <div v-else-if="viewMode === 'grid'" class="template-grid">
      <AppCard v-for="tpl in templates" :key="tpl.id" padding="none" class="template-card h-full">
        <div class="template-card__inner">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-semibold text-slate-800 truncate">{{ tpl.name }}</h3>
            <span v-if="tpl.content_type" class="template-type-badge">{{ formatContentType(tpl.content_type) }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-2 mt-1">
            <SocialPlatformLabel v-if="tpl.platform" :type="tpl.platform" size="sm" />
            <span v-else class="text-2xs text-slate-500">Any platform</span>
          </div>
          <p v-if="captionPreview(tpl)" class="text-2xs text-slate-500 mt-2 line-clamp-2">
            {{ captionPreview(tpl) }}
          </p>
        </div>

        <div class="template-card__actions">
          <AppButton size="sm" variant="secondary" @click="openEdit(tpl)">Edit</AppButton>
          <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(tpl)">Delete</AppButton>
        </div>
        </div>
      </AppCard>
    </div>

    <div v-else class="template-list">
      <ContentLibraryRow v-for="tpl in templates" :key="tpl.id">
        <template #leading>
          <div class="template-row__badge">
            <SocialPlatformLabel v-if="tpl.platform" :type="tpl.platform" size="sm" />
            <span v-else class="template-type-badge">Any</span>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-2 min-w-0">
          <p class="font-semibold text-slate-800 truncate">{{ tpl.name }}</p>
          <span v-if="tpl.content_type" class="template-type-badge">{{ formatContentType(tpl.content_type) }}</span>
        </div>
        <p v-if="captionPreview(tpl)" class="text-2xs text-slate-500 mt-0.5 line-clamp-2">{{ captionPreview(tpl) }}</p>
        <p v-else class="text-2xs text-slate-400 mt-0.5">No caption yet</p>

        <template #actions>
          <AppButton size="sm" variant="secondary" @click="openEdit(tpl)">Edit</AppButton>
          <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(tpl)">Delete</AppButton>
        </template>
      </ContentLibraryRow>
    </div>

    <AppModal
      :open="editorOpen"
      :title="editingId ? 'Edit template' : 'New template'"
      size="lg"
      @close="closeEditor"
    >
      <form id="template-form" class="space-y-4" @submit.prevent="save">
        <div class="template-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Basics</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <AppFormField label="Name" required class="sm:col-span-2">
              <AppInput v-model="form.name" placeholder="Product launch caption" />
            </AppFormField>

            <AppFormField label="Platform" hint="Leave blank to use on any platform">
              <AppSelect v-model="form.platform">
                <option value="">Any platform</option>
                <option v-for="p in platformOptions" :key="p.type" :value="p.type">{{ p.label }}</option>
              </AppSelect>
            </AppFormField>

            <AppFormField label="Content type" hint="Optional format hint for generation">
              <AppSelect v-model="form.content_type">
                <option value="">Any type</option>
                <option v-for="type in contentTypeOptions" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </AppSelect>
            </AppFormField>
          </div>
        </div>

        <div class="template-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Caption template</p>
          <AppFormField
            label="Default caption"
            hint="Starter text reused when generating or editing content packages."
          >
            <AppInput
              v-model="form.caption"
              type="textarea"
              :rows="5"
              placeholder="Introducing our latest…"
            />
          </AppFormField>
        </div>

        <p v-if="formError" class="text-2xs text-red-600">{{ formError }}</p>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeEditor">Cancel</AppButton>
        <AppButton
          type="submit"
          form="template-form"
          :disabled="saving || !form.name.trim()"
        >
          {{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Create template' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';
import SocialPlatformLabel from '../SocialPlatformLabel.vue';
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
const templates = ref([]);
const loading = ref(false);
const editorOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const viewMode = ref('grid');

const platformOptions = [
  { type: 'instagram', label: 'Instagram' },
  { type: 'facebook', label: 'Facebook' },
  { type: 'twitter', label: 'X / Twitter' },
  { type: 'youtube', label: 'YouTube' },
  { type: 'tiktok', label: 'TikTok' },
  { type: 'threads', label: 'Threads' },
  { type: 'linkedin', label: 'LinkedIn' },
];

const contentTypeOptions = [
  { value: 'post', label: 'Post' },
  { value: 'video', label: 'Video' },
  { value: 'image', label: 'Image' },
  { value: 'reel', label: 'Reel' },
  { value: 'story', label: 'Story' },
];

const form = reactive({
  name: '',
  platform: '',
  content_type: '',
  caption: '',
});

function formatContentType(value) {
  const match = contentTypeOptions.find((item) => item.value === value);
  return match?.label || value;
}

function captionPreview(tpl) {
  const caption = tpl.template_data?.caption;
  return typeof caption === 'string' && caption.trim() ? caption.trim() : '';
}

function resetForm() {
  form.name = '';
  form.platform = '';
  form.content_type = '';
  form.caption = '';
  formError.value = '';
}

function hydrateForm(tpl) {
  form.name = tpl.name || '';
  form.platform = tpl.platform || '';
  form.content_type = tpl.content_type || '';
  form.caption = tpl.template_data?.caption || '';
  formError.value = '';
}

function payloadFromForm() {
  const caption = form.caption.trim();
  return {
    name: form.name.trim(),
    platform: form.platform || null,
    content_type: form.content_type || null,
    template_data: { caption },
  };
}

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/content/templates', { skipErrorToast: true });
    templates.value = data.data || data || [];
  } catch {
    templates.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  resetForm();
  editorOpen.value = true;
}

function openEdit(tpl) {
  editingId.value = tpl.id;
  hydrateForm(tpl);
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
      await axios.put(`/api/content/templates/${editingId.value}`, body);
      toast.success('Template updated');
    } else {
      await axios.post('/api/content/templates', body);
      toast.success('Template created');
    }
    closeEditor();
    await load();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to save template';
  } finally {
    saving.value = false;
  }
}

async function confirmDelete(tpl) {
  if (!window.confirm(`Delete template "${tpl.name}"?`)) return;
  try {
    await axios.delete(`/api/content/templates/${tpl.id}`);
    toast.success('Template deleted');
    await load();
  } catch {
    // interceptor
  }
}

onMounted(() => {
  if (props.active) load();
});

defineExpose({ load });
</script>

<style scoped>
.template-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
  .template-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.template-card {
  display: flex;
  flex-direction: column;
}

.template-card__inner {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  height: 100%;
  padding: 1rem;
}

.template-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.85rem;
  border-top: 1px solid #e6ebf2;
}

.template-list {
  display: grid;
  gap: 0.75rem;
}

.template-row__badge {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 3.25rem;
  height: 3.25rem;
  padding: 0 0.35rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.95);
}

.template-type-badge {
  font-size: 0.68rem;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.1);
  color: #475569;
  font-weight: 600;
}

.template-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}
</style>
