<template>
  <div class="admin-navigation space-y-5">
    <AppPageHeader
      title="Navigation"
      subtitle="Control which sidebar menus and publish features are visible platform-wide."
      icon="grid"
      :breadcrumb="['Admin', 'Navigation']"
    >
      <template #actions>
        <AppButton variant="secondary" size="sm" :disabled="loading || saving" @click="reload">
          <AppIcon name="sync" class="h-4 w-4" />
          <span>Reload</span>
        </AppButton>
        <AppButton size="sm" :disabled="saving || loading" @click="save">
          <AppIcon name="save" class="h-4 w-4" />
          <span>{{ saving ? 'Saving…' : 'Save changes' }}</span>
        </AppButton>
      </template>
    </AppPageHeader>

    <AppLoader v-if="loading && !draftMenus" label="Loading navigation settings…" />
    <AppAlert v-else-if="error" variant="danger">{{ error }}</AppAlert>

    <template v-else>
      <AppCard class="admin-navigation-hero overflow-hidden border-slate-200/80 p-0" variant="panel">
        <div class="relative isolate overflow-hidden px-5 py-5 md:px-6 md:py-6">
          <div class="admin-navigation-hero__glow admin-navigation-hero__glow--one" />
          <div class="admin-navigation-hero__glow admin-navigation-hero__glow--two" />
          <div class="relative grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)]">
            <div class="space-y-4">
              <div class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/55 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-700 backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-blue-500 shadow-[0_0_0_4px_rgba(59,130,246,0.16)]" />
                Navigation matrix
              </div>
              <div class="space-y-2">
                <h2 class="max-w-2xl text-2xl font-semibold tracking-tight text-slate-950 md:text-3xl">
                  Shape the product surface from one place.
                </h2>
                <p class="max-w-2xl text-sm leading-6 text-slate-600 md:text-[15px]">
                  Hide unfinished areas, trim the sidebar for specific deployments, and keep feature access aligned with rollout state.
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <AppBadge variant="success">{{ enabledMenusCount }} menus live</AppBadge>
                <AppBadge variant="info">{{ enabledFeaturesCount }} features live</AppBadge>
                <AppBadge variant="warning">{{ disabledCount }} hidden</AppBadge>
                <AppBadge variant="purple">{{ sections.length }} sections</AppBadge>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sidebar</span>
                  <AppIcon name="grid" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ enabledMenusCount }}</div>
                <div class="mt-1 text-xs text-slate-500">Visible navigation entries</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Features</span>
                  <AppIcon name="sparkles" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ enabledFeaturesCount }}</div>
                <div class="mt-1 text-xs text-slate-500">Page-level capabilities</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Locked</span>
                  <AppIcon name="lock" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-3xl font-semibold text-slate-950">{{ disabledMenusCount }}</div>
                <div class="mt-1 text-xs text-slate-500">Sidebar routes hidden</div>
              </div>
              <div class="rounded-2xl border border-white/50 bg-white/65 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Session</span>
                  <AppIcon name="shield" class="h-4 w-4 text-slate-500" />
                </div>
                <div class="mt-3 text-lg font-semibold text-slate-950">Admin-only</div>
                <div class="mt-1 text-xs text-slate-500">This page always stays available</div>
              </div>
            </div>
          </div>
        </div>
      </AppCard>

      <div class="grid gap-4 xl:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
        <AppCard class="space-y-4 p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-semibold text-slate-950">Rollout map</div>
              <div class="text-xs text-slate-500">Section summary</div>
            </div>
            <AppBadge variant="default">{{ visibleItems.length }}</AppBadge>
          </div>

          <div class="space-y-2">
            <AppButton
              v-for="section in sections"
              :key="section.id"
              variant="ghost"
              size="sm"
              class="group flex w-full items-start justify-between rounded-2xl border px-3 py-2.5 text-left !whitespace-normal transition-all duration-200"
              :class="activeSection === section.id
                ? 'border-slate-950 bg-slate-950 text-white shadow-lg shadow-slate-950/10'
                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
              @click="activeSection = section.id"
            >
              <span class="min-w-0 pr-3">
                <span class="block text-sm font-semibold leading-5 break-words">{{ section.label }}</span>
                <span class="block text-xs leading-5 opacity-80 break-words">{{ section.description }}</span>
              </span>
              <span class="shrink-0 text-sm font-semibold leading-5 opacity-80">{{ section.enabledCount }}/{{ section.items.length }}</span>
            </AppButton>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-xs leading-6 text-slate-600">
            Disable unfinished menus to keep the sidebar clean. Feature toggles hide page-level controls without removing the page route.
          </div>
        </AppCard>

        <div class="space-y-4">
          <AppCard
            v-for="section in visibleSections"
            :key="section.id"
            class="overflow-hidden p-0"
            variant="panel"
          >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 md:px-6">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-base font-semibold text-slate-950">{{ section.label }}</h3>
                  <AppBadge :variant="section.kind === 'menus' ? 'info' : 'purple'">
                    {{ section.enabledCount }}/{{ section.items.length }} live
                  </AppBadge>
                </div>
                <p v-if="section.description" class="mt-1 text-xs text-slate-500">
                  {{ section.description }}
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <AppBadge variant="success">{{ section.enabledCount }} on</AppBadge>
                <AppBadge variant="warning">{{ section.disabledCount }} off</AppBadge>
              </div>
            </div>

            <div class="grid gap-3 px-5 py-5 md:px-6 lg:grid-cols-2">
              <div
                v-for="item in section.items"
                :key="item.id"
                class="rounded-2xl border p-4 transition-all duration-200"
                :class="item.enabled
                  ? 'border-emerald-200 bg-emerald-50/60 shadow-sm'
                  : 'border-slate-200 bg-white'"
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="text-sm font-semibold text-slate-950">{{ item.label }}</p>
                      <AppBadge :variant="item.enabled ? 'success' : 'default'">
                        {{ item.enabled ? 'Visible' : 'Hidden' }}
                      </AppBadge>
                    </div>
                    <p class="mt-1 break-all font-mono text-[11px] text-slate-500">{{ item.id }}</p>
                  </div>
                  <AppCheckbox
                    :model-value="item.enabled"
                    :label="item.enabled ? 'Visible' : 'Hidden'"
                    @update:model-value="(value) => setItemEnabled(section.kind, item.id, value)"
                  />
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                  <span>{{ section.kind === 'menus' ? 'Sidebar route' : 'Feature flag' }}</span>
                  <span>{{ item.enabled ? 'Shown to all admins' : 'Removed from navigation' }}</span>
                </div>
              </div>
            </div>
          </AppCard>

          <AppAlert variant="info">
            Disabled menus are hidden from the sidebar and blocked via direct URL. The Navigation settings page itself always remains available to admins.
          </AppAlert>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { AppAlert, AppBadge, AppButton, AppCard, AppCheckbox, AppIcon, AppLoader } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';
import { useNavigationSettingsStore } from '../../stores/navigationSettings';

const navigation = useNavigationSettingsStore();
const draftMenus = ref(null);
const draftFeatures = ref(null);
const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const activeSection = ref('connect');

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
      grouped[sectionId] = {
        id: sectionId,
        kind: 'menus',
        ...SECTION_META[sectionId],
        items: [],
      };
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
  return [...order.filter((id) => grouped[id]).map((id) => grouped[id]), featuresSection]
    .filter((section) => section.items.length)
    .map((section) => ({
      ...section,
      enabledCount: section.items.filter((item) => item.enabled).length,
      disabledCount: section.items.filter((item) => !item.enabled).length,
    }));
});

const visibleSections = computed(() => {
  if (!sections.value.length) return [];
  return sections.value.filter((section) => section.id === activeSection.value) || sections.value;
});

const enabledMenusCount = computed(() => sections.value.filter((section) => section.kind === 'menus').reduce((sum, section) => sum + section.enabledCount, 0));
const disabledMenusCount = computed(() => sections.value.filter((section) => section.kind === 'menus').reduce((sum, section) => sum + section.disabledCount, 0));
const enabledFeaturesCount = computed(() => sections.value.filter((section) => section.kind === 'features').reduce((sum, section) => sum + section.enabledCount, 0));
const disabledCount = computed(() => sections.value.reduce((sum, section) => sum + section.disabledCount, 0));
const visibleItems = computed(() => sections.value.flatMap((section) => section.items));

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
    activeSection.value = sections.value[0]?.id || 'connect';
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

function reload() {
  load();
}

onMounted(load);
</script>

<style scoped>
.admin-navigation {
  position: relative;
}

.admin-navigation-hero {
  background:
    radial-gradient(circle at top left, rgba(59, 130, 246, 0.15), transparent 34%),
    radial-gradient(circle at top right, rgba(168, 85, 247, 0.1), transparent 30%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.92));
}

.admin-navigation-hero__glow {
  position: absolute;
  border-radius: 9999px;
  filter: blur(42px);
  opacity: 0.8;
  pointer-events: none;
}

.admin-navigation-hero__glow--one {
  top: -1.5rem;
  right: 8%;
  width: 11rem;
  height: 11rem;
  background: rgba(56, 189, 248, 0.16);
}

.admin-navigation-hero__glow--two {
  bottom: -2rem;
  left: 26%;
  width: 14rem;
  height: 14rem;
  background: rgba(139, 92, 246, 0.14);
}
</style>
