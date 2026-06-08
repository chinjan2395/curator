<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-600">
        Reusable building blocks (CTAs, disclaimers, hooks) for captions and templates.
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <ContentViewToggle v-model="viewMode" />
        <AppButton size="sm" @click="openCreate">New block</AppButton>
      </div>
    </div>

    <AppEmptyState
      v-if="!blocks.length && !loading"
      title="No content blocks yet"
      description="Create reusable blocks you can insert into captions and templates."
      icon="library"
    >
      <AppButton size="sm" @click="openCreate">Create block</AppButton>
    </AppEmptyState>

    <div v-else-if="viewMode === 'grid'" class="block-grid">
      <AppCard v-for="block in blocks" :key="block.id" padding="none" class="block-card h-full">
        <div class="block-card__inner">
          <div class="block-card__header">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-slate-800 truncate">{{ block.name }}</h3>
                <span class="block-type-badge">{{ formatType(block.type) }}</span>
              </div>
              <p v-if="bodyPreview(block)" class="block-card__preview line-clamp-3">{{ bodyPreview(block) }}</p>
              <p v-else class="block-card__preview block-card__preview--empty">No body yet</p>
            </div>
          </div>

          <div class="block-card__actions">
            <AppButton size="sm" variant="secondary" @click="openEdit(block)">Edit</AppButton>
            <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(block)">Delete</AppButton>
          </div>
        </div>
      </AppCard>
    </div>

    <div v-else class="block-list">
      <ContentLibraryRow v-for="block in blocks" :key="block.id">
        <template #leading>
          <div class="block-row__badge">
            <span class="block-type-badge">{{ formatType(block.type) }}</span>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-2 min-w-0">
          <p class="font-semibold text-slate-800 truncate">{{ block.name }}</p>
        </div>
        <p v-if="bodyPreview(block)" class="text-2xs text-slate-500 mt-0.5 line-clamp-2">{{ bodyPreview(block) }}</p>
        <p v-else class="text-2xs text-slate-400 mt-0.5">No body yet</p>

        <template #actions>
          <AppButton size="sm" variant="secondary" @click="openEdit(block)">Edit</AppButton>
          <AppButton size="sm" variant="ghost" class="text-red-600" @click="confirmDelete(block)">Delete</AppButton>
        </template>
      </ContentLibraryRow>
    </div>

    <AppModal
      :open="editorOpen"
      :title="editingId ? 'Edit block' : 'New block'"
      size="lg"
      @close="closeEditor"
    >
      <form id="block-form" class="space-y-4" @submit.prevent="save">
        <div class="block-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Basics</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <AppFormField label="Name" required class="sm:col-span-2">
              <AppInput v-model="form.name" placeholder="Short CTA" />
            </AppFormField>

            <AppFormField label="Type" required hint="Used to group blocks in the UI.">
              <AppSelect v-model="form.typeMode">
                <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </AppSelect>
            </AppFormField>

            <AppFormField v-if="form.typeMode === 'custom'" label="Custom type" required>
              <AppInput v-model="form.customType" placeholder="e.g. disclaimer" />
            </AppFormField>
          </div>
        </div>

        <div class="block-form-panel">
          <p class="text-sm font-semibold text-slate-800 mb-3">Body</p>
          <AppFormField label="Text" hint="Supports multi-line text.">
            <AppInput v-model="form.body" type="textarea" :rows="6" placeholder="Learn more at…" />
          </AppFormField>
        </div>

        <p v-if="formError" class="text-2xs text-red-600">{{ formError }}</p>
      </form>

      <template #footer>
        <AppButton variant="secondary" @click="closeEditor">Cancel</AppButton>
        <AppButton type="submit" form="block-form" :disabled="saving || !form.name.trim() || !effectiveType.trim()">
          {{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Create block' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';
import { AppButton, AppCard, AppEmptyState, AppFormField, AppInput, AppModal, AppSelect } from '../ui';
import ContentLibraryRow from './ContentLibraryRow.vue';
import ContentViewToggle from './ContentViewToggle.vue';

const props = defineProps({
  active: { type: Boolean, default: true },
});

const toast = useToastStore();
const blocks = ref([]);
const loading = ref(false);
const editorOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formError = ref('');
const viewMode = ref('grid');

const typeOptions = [
  { value: 'cta', label: 'CTA' },
  { value: 'hook', label: 'Hook' },
  { value: 'hashtags', label: 'Hashtags' },
  { value: 'disclaimer', label: 'Disclaimer' },
  { value: 'custom', label: 'Custom…' },
];

const form = reactive({
  name: '',
  typeMode: 'cta',
  customType: '',
  body: '',
});

const effectiveType = computed(() => {
  if (form.typeMode === 'custom') return String(form.customType || '').trim();
  return String(form.typeMode || '').trim();
});

function formatType(value) {
  const v = String(value || '').trim();
  const match = typeOptions.find((opt) => opt.value === v);
  return match?.label || (v ? v : 'Block');
}

function bodyPreview(block) {
  const text = String(block?.body || '').trim();
  return text;
}

function resetForm() {
  form.name = '';
  form.typeMode = 'cta';
  form.customType = '';
  form.body = '';
  formError.value = '';
}

function hydrateForm(block) {
  const type = String(block?.type || '').trim();
  const match = typeOptions.find((opt) => opt.value === type);
  form.name = block?.name || '';
  form.typeMode = match ? match.value : 'custom';
  form.customType = match ? '' : type;
  form.body = block?.body || '';
  formError.value = '';
}

function payloadFromForm() {
  return {
    type: effectiveType.value,
    name: form.name.trim(),
    body: String(form.body || '').trim() || null,
    metadata: null,
  };
}

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/content/blocks', { skipErrorToast: true });
    blocks.value = data.data || data || [];
  } catch {
    blocks.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  resetForm();
  editorOpen.value = true;
}

function openEdit(block) {
  editingId.value = block.id;
  hydrateForm(block);
  editorOpen.value = true;
}

function closeEditor() {
  editorOpen.value = false;
  editingId.value = null;
}

async function save() {
  if (!form.name.trim() || !effectiveType.value) return;
  saving.value = true;
  formError.value = '';
  const body = payloadFromForm();

  try {
    if (editingId.value) {
      await axios.put(`/api/content/blocks/${editingId.value}`, body);
      toast.success('Block updated');
    } else {
      await axios.post('/api/content/blocks', body);
      toast.success('Block created');
    }
    closeEditor();
    await load();
  } catch (e) {
    formError.value = e.response?.data?.message || 'Failed to save block';
  } finally {
    saving.value = false;
  }
}

async function confirmDelete(block) {
  if (!window.confirm(`Delete block "${block.name}"?`)) return;
  try {
    await axios.delete(`/api/content/blocks/${block.id}`);
    toast.success('Block deleted');
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
.block-list {
  display: grid;
  gap: 0.75rem;
}

.block-row__badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: rgba(248, 250, 252, 0.95);
}

.block-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

@media (min-width: 768px) {
  .block-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.block-card {
  display: flex;
  flex-direction: column;
}

.block-card__inner {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  height: 100%;
  padding: 1rem;
}

.block-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
}

.block-type-badge {
  font-size: 0.68rem;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.1);
  color: #475569;
  font-weight: 600;
}

.block-card__preview {
  margin-top: 0.5rem;
  font-size: 0.78rem;
  line-height: 1.35;
  color: #64748b;
  white-space: pre-wrap;
}

.block-card__preview--empty {
  color: #94a3b8;
}

.block-card__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.85rem;
  border-top: 1px solid #e6ebf2;
}

.block-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}
</style>

