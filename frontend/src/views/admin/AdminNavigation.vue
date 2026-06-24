<template>
  <div class="space-y-4">
    <AppPageHeader
      title="Navigation"
      subtitle="Control which sidebar menus and publish features are visible platform-wide."
      icon="grid"
      :breadcrumb="['Admin', 'Navigation']"
    >
      <template #actions>
        <AppButton size="sm" :disabled="saving || loading" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </AppButton>
      </template>
    </AppPageHeader>

    <AppLoader v-if="loading && !draftMenus" label="Loading navigation settings…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <AppCard v-for="section in sections" :key="section.id" class="p-5 space-y-4">
        <div>
          <AppTitle size="sm">{{ section.label }}</AppTitle>
          <p v-if="section.description" class="text-xs text-slate-500 mt-1">{{ section.description }}</p>
        </div>

        <ul class="divide-y divide-slate-100">
          <li
            v-for="item in section.items"
            :key="item.id"
            class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ item.label }}</p>
              <p class="text-xs text-slate-500 font-mono">{{ item.id }}</p>
            </div>
            <AppCheckbox
              :model-value="item.enabled"
              :label="item.enabled ? 'Visible' : 'Hidden'"
              @update:model-value="(value) => setItemEnabled(section.kind, item.id, value)"
            />
          </li>
        </ul>
      </AppCard>

      <p class="text-xs text-slate-500">
        Disabled menus are hidden from the sidebar and blocked via direct URL. The Navigation settings page itself always remains available to admins.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { AppAlert, AppButton, AppCard, AppCheckbox, AppLoader, AppTitle } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';
import { useNavigationSettingsStore } from '../../stores/navigationSettings';

const navigation = useNavigationSettingsStore();
const draftMenus = ref(null);
const draftFeatures = ref(null);
const loading = ref(true);
const saving = ref(false);
const error = ref(null);

const SECTION_META = {
  connect: { label: 'Connect', description: 'Integration and credential management.' },
  content: { label: 'Content', description: 'Curator, campaigns, scheduling, and content library.' },
  insights: { label: 'Insights', description: 'Analytics, inbox, and notifications.' },
  administration: { label: 'Administration', description: 'Admin-only tools and operations.' },
  features: { label: 'Features', description: 'In-page capabilities that are not sidebar links.' },
};

const sections = computed(() => {
  if (!draftMenus.value || !draftFeatures.value) return [];

  const registry = navigation.registry || { menus: {}, features: {} };
  const grouped = {};

  Object.values(registry.menus || {}).forEach((item) => {
    const sectionId = item.section || 'other';
    if (!grouped[sectionId]) {
      grouped[sectionId] = { id: sectionId, kind: 'menus', ...SECTION_META[sectionId], items: [] };
    }
    grouped[sectionId].items.push({
      id: item.id,
      label: item.label,
      enabled: Boolean(draftMenus.value[item.id]),
    });
  });

  const featuresSection = {
    id: 'features',
    kind: 'features',
    ...SECTION_META.features,
    items: Object.values(registry.features || {}).map((item) => ({
      id: item.id,
      label: item.label,
      enabled: Boolean(draftFeatures.value[item.id]),
    })),
  };

  const order = ['connect', 'content', 'insights', 'administration', 'features'];
  return [...order.filter((id) => grouped[id]).map((id) => grouped[id]), featuresSection].filter((section) => section.items.length);
});

function setItemEnabled(kind, id, enabled) {
  if (kind === 'menus') {
    draftMenus.value = { ...draftMenus.value, [id]: enabled };
  } else {
    draftFeatures.value = { ...draftFeatures.value, [id]: enabled };
  }
}

async function load() {
  loading.value = true;
  error.value = null;
  try {
    const data = await navigation.fetchAdmin();
    draftMenus.value = { ...data.menus };
    draftFeatures.value = { ...data.features };
  } catch (e) {
    error.value = e.response?.data?.message || navigation.error || 'Failed to load navigation settings';
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!draftMenus.value || !draftFeatures.value) return;
  saving.value = true;
  error.value = null;
  try {
    const data = await navigation.update({
      menus: draftMenus.value,
      features: draftFeatures.value,
    });
    draftMenus.value = { ...data.menus };
    draftFeatures.value = { ...data.features };
  } catch (e) {
    error.value = e.response?.data?.message || navigation.error || 'Failed to save navigation settings';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
