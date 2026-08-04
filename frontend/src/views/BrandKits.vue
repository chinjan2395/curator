<template>
  <div class="space-y-5">
    <AppPageHeader
      title="Brand Kits"
      subtitle="Build one Master brand kit per brand, then create per-workspace child kits that inherit everything until you explicitly override a field."
      icon="sparkles"
      :breadcrumb="['Brand Kits']"
    >
      <template #actions>
        <AppInput
          v-model="search"
          type="search"
          placeholder="Search kits…"
          wrapper-class="!w-48"
          input-class="!h-9 !text-xs"
        />
        <AppButton
          size="sm"
          @click="openCreateMaster"
        >
          + New Master Kit
        </AppButton>
      </template>
    </AppPageHeader>

    <div
      v-if="store.loading && !store.kits.length"
      class="space-y-4"
    >
      <AppSkeleton variant="card" />
      <AppSkeleton variant="card" />
    </div>

    <AppEmptyState
      v-else-if="!store.kits.length"
      title="No brand kits yet"
      description="Create a Master kit with your logo, colors, and Publish-page defaults. Then create child kits per workspace that inherit everything until overridden."
      icon="sparkles"
    >
      <AppButton
        size="sm"
        @click="openCreateMaster"
      >
        Create a Master kit
      </AppButton>
    </AppEmptyState>

    <template v-else>
      <dl class="bk-strip">
        <div>
          <dt>Brand families</dt>
          <dd>{{ store.groupedByMaster.length }}</dd>
        </div>
        <div>
          <dt>Child kits</dt>
          <dd>{{ childCount }}</dd>
        </div>
        <div>
          <dt>Fields overridden</dt>
          <dd>{{ totalOverrides }}</dd>
        </div>
        <div>
          <dt>Default kit</dt>
          <dd class="bk-strip__text">
            {{ defaultKitName }}
          </dd>
        </div>
      </dl>

      <AppEmptyState
        v-if="!visibleGroups.length"
        title="No kits match that search"
        :description="`Nothing named like “${search}”. Clear the search to see all brand kits.`"
        icon="search"
      >
        <AppButton
          size="sm"
          variant="secondary"
          @click="search = ''"
        >
          Clear search
        </AppButton>
      </AppEmptyState>

      <div
        v-else
        class="space-y-4"
      >
        <article
          v-for="group in visibleGroups"
          :key="group.master.id"
          class="bk-family"
        >
          <header class="bk-family__head">
            <div
              class="bk-logo bk-logo--md"
              :class="group.master.logo_url ? '' : 'bk-logo--empty'"
            >
              <img
                v-if="group.master.logo_url"
                :src="group.master.logo_url"
                alt=""
                referrerpolicy="no-referrer"
              >
              <span v-else>{{ initials(group.master.name) }}</span>
            </div>

            <div class="min-w-0 flex-1">
              <h3 class="bk-family__name">
                {{ group.master.name }}
                <AppBadge variant="info">
                  Master
                </AppBadge>
                <AppBadge
                  v-if="group.master.is_default"
                  variant="success"
                >
                  Default
                </AppBadge>
              </h3>
              <div class="bk-family__meta">
                <span
                  v-if="swatches(group.master).length"
                  class="bk-swatches"
                >
                  <span
                    v-for="sw in swatches(group.master)"
                    :key="sw.key"
                    class="bk-sw"
                    :title="`${sw.key}: ${sw.hex}`"
                    :style="{ backgroundColor: sw.hex }"
                  />
                </span>
                <span class="bk-dot" />
                <span>{{ group.children.length ? `${group.children.length} child kit${group.children.length === 1 ? '' : 's'}` : 'No child kits yet' }}</span>
                <template v-if="fontSummary(group.master)">
                  <span class="bk-dot" />
                  <span>{{ fontSummary(group.master) }}</span>
                </template>
              </div>
            </div>

            <div class="bk-actions">
              <AppButton
                size="sm"
                variant="secondary"
                @click="router.push(`/brand-kits/${group.master.id}/edit`)"
              >
                Edit
              </AppButton>
              <AppButton
                variant="ghost"
                size="sm"
                class="bk-icon-btn"
                title="Duplicate kit"
                @click="duplicate(group.master)"
              >
                <AppIcon
                  name="copy"
                  class="w-4 h-4"
                />
              </AppButton>
              <AppButton
                variant="ghost"
                tone="destructive"
                size="sm"
                class="bk-icon-btn"
                title="Delete kit"
                @click="remove(group.master, group.children.length)"
              >
                <AppIcon
                  name="delete"
                  class="w-4 h-4"
                />
              </AppButton>
            </div>
          </header>

          <div
            v-if="group.children.length"
            class="bk-kids"
          >
            <div
              v-for="child in group.children"
              :key="child.id"
              class="bk-kid"
            >
              <div
                class="bk-logo bk-logo--sm"
                :class="child.logo_url ? '' : 'bk-logo--empty'"
              >
                <img
                  v-if="child.logo_url"
                  :src="child.logo_url"
                  alt=""
                  referrerpolicy="no-referrer"
                >
                <span v-else>{{ initials(child.name) }}</span>
              </div>

              <div class="min-w-0 flex-1">
                <p class="bk-kid__name">
                  {{ child.name }}
                  <AppBadge
                    v-if="overridePaths(child).length"
                    variant="purple"
                  >
                    {{ overridePaths(child).length }} override{{ overridePaths(child).length === 1 ? '' : 's' }}
                  </AppBadge>
                  <AppBadge
                    v-else
                    variant="default"
                  >
                    Fully synced
                  </AppBadge>
                </p>
                <p class="bk-kid__sub">
                  {{ overrideSummary(child, group.master) }}
                </p>
              </div>

              <span
                v-if="swatches(child).length"
                class="bk-swatches bk-swatches--sm"
              >
                <span
                  v-for="sw in swatches(child)"
                  :key="sw.key"
                  class="bk-sw"
                  :title="`${sw.key}: ${sw.hex}`"
                  :style="{ backgroundColor: sw.hex }"
                />
              </span>

              <div class="bk-actions">
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="bk-icon-btn"
                  title="Edit kit"
                  @click="router.push(`/brand-kits/${child.id}/edit`)"
                >
                  <AppIcon
                    name="edit"
                    class="w-4 h-4"
                  />
                </AppButton>
                <AppButton
                  variant="ghost"
                  size="sm"
                  class="bk-icon-btn"
                  title="Duplicate kit"
                  @click="duplicate(child)"
                >
                  <AppIcon
                    name="copy"
                    class="w-4 h-4"
                  />
                </AppButton>
                <AppButton
                  variant="ghost"
                  tone="destructive"
                  size="sm"
                  class="bk-icon-btn"
                  title="Delete kit"
                  @click="remove(child, 0)"
                >
                  <AppIcon
                    name="delete"
                    class="w-4 h-4"
                  />
                </AppButton>
              </div>
            </div>
          </div>

          <footer class="bk-family__foot">
            <AppButton
              variant="ghost"
              size="sm"
              class="bk-link"
              @click="openCreateChild(group.master)"
            >
              <AppIcon
                name="add"
                class="w-3.5 h-3.5"
              />
              Create child kit under {{ group.master.name }}
            </AppButton>
          </footer>
        </article>
      </div>
    </template>

    <AppModal
      :open="createMasterOpen"
      title="New Master brand kit"
      size="md"
      @close="createMasterOpen = false"
    >
      <div class="space-y-4">
        <AppFormField
          label="Name"
          required
        >
          <AppInput
            v-model="masterForm.name"
            placeholder="Acme Global"
          />
        </AppFormField>
        <AppFormField
          label="Starter colors"
          hint="Optional — you can fine-tune every setting in the editor after creating the kit."
        >
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div
              v-for="key in colorKeys"
              :key="key"
              class="flex items-center gap-2"
            >
              <AppInput
                v-model="masterForm.colors[key]"
                type="color"
                wrapper-class="!w-auto shrink-0"
                input-class="h-9 w-11 rounded-lg border border-slate-300 cursor-pointer bg-white p-0"
              />
              <span class="text-xs text-slate-500 capitalize">{{ key }}</span>
            </div>
          </div>
        </AppFormField>
      </div>
      <template #footer>
        <AppButton
          variant="secondary"
          @click="createMasterOpen = false"
        >
          Cancel
        </AppButton>
        <AppButton
          :disabled="!masterForm.name.trim() || creating"
          @click="submitCreateMaster"
        >
          {{ creating ? 'Creating…' : 'Create kit' }}
        </AppButton>
      </template>
    </AppModal>

    <AppModal
      :open="createChildOpen"
      title="Create child kit"
      size="sm"
      @close="createChildOpen = false"
    >
      <AppFormField
        label="Name"
        required
        :hint="childTargetMaster ? `Inherits from ${childTargetMaster.name}` : ''"
      >
        <AppInput
          v-model="childForm.name"
          placeholder="e.g. Acme — France"
        />
      </AppFormField>
      <template #footer>
        <AppButton
          variant="secondary"
          @click="createChildOpen = false"
        >
          Cancel
        </AppButton>
        <AppButton
          :disabled="!childForm.name.trim() || creating"
          @click="submitCreateChild"
        >
          {{ creating ? 'Creating…' : 'Create child kit' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useBrandKitsStore } from '../stores/brandKits';
import { AppPageHeader } from '../components/layout';
import { AppBadge, AppButton, AppEmptyState, AppFormField, AppIcon, AppInput, AppModal, AppSkeleton } from '../components/ui';

defineOptions({ name: 'BrandKitsView' });

const router = useRouter();
const store = useBrandKitsStore();
const { confirm } = inject('confirm');

const colorKeys = ['primary', 'secondary', 'accent', 'background', 'text'];
const DEFAULT_COLORS = {
  primary: '#2563eb',
  secondary: '#64748b',
  accent: '#0f172a',
  background: '#ffffff',
  text: '#0f172a',
};

const search = ref('');
const createMasterOpen = ref(false);
const createChildOpen = ref(false);
const childTargetMaster = ref(null);
const creating = ref(false);

const masterForm = reactive({ name: '', colors: { ...DEFAULT_COLORS } });
const childForm = reactive({ name: '' });

/** A family stays visible when the Master or any of its children matches. */
const visibleGroups = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return store.groupedByMaster;
  return store.groupedByMaster
    .map((group) => {
      if (group.master.name.toLowerCase().includes(q)) return group;
      const children = group.children.filter((c) => c.name.toLowerCase().includes(q));
      return children.length ? { ...group, children } : null;
    })
    .filter(Boolean);
});

const childCount = computed(() => store.kits.filter((k) => k.parent_id).length);

const totalOverrides = computed(
  () => store.kits.filter((k) => k.parent_id).reduce((n, k) => n + overridePaths(k).length, 0),
);

const defaultKitName = computed(() => store.kits.find((k) => k.is_default)?.name || 'None set');

function initials(name) {
  return String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function swatches(kit) {
  return Object.entries(kit?.colors || {})
    .filter(([, hex]) => typeof hex === 'string' && hex)
    .map(([key, hex]) => ({ key, hex }));
}

function fontSummary(kit) {
  const heading = kit?.fonts?.heading;
  const body = kit?.fonts?.body;
  if (!heading && !body) return '';
  if (heading && body && heading !== body) return `${heading} / ${body}`;
  return heading || body;
}

/**
 * Flattens a kit's sparse `overrides` object into the dot-paths the editor
 * shows, so the list can both count them and name the first few.
 */
function overridePaths(kit, node = kit?.overrides, prefix = '') {
  if (!node || typeof node !== 'object' || Array.isArray(node)) return [];
  const paths = [];
  for (const [key, value] of Object.entries(node)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0) {
      paths.push(...overridePaths(kit, value, path));
    } else {
      paths.push(path);
    }
  }
  return paths;
}

function overrideSummary(child, master) {
  const paths = overridePaths(child);
  if (!paths.length) return `Every field follows ${master.name}`;
  const shown = paths.slice(0, 3).join(' · ');
  return paths.length > 3 ? `${shown} +${paths.length - 3} more` : shown;
}

function openCreateMaster() {
  masterForm.name = '';
  Object.assign(masterForm.colors, DEFAULT_COLORS);
  createMasterOpen.value = true;
}

async function submitCreateMaster() {
  if (!masterForm.name.trim()) return;
  creating.value = true;
  try {
    await store.createKit({ name: masterForm.name.trim(), colors: { ...masterForm.colors } });
    createMasterOpen.value = false;
  } catch {
    // toast handled in store
  } finally {
    creating.value = false;
  }
}

function openCreateChild(master) {
  childTargetMaster.value = master;
  childForm.name = '';
  createChildOpen.value = true;
}

async function submitCreateChild() {
  if (!childForm.name.trim() || !childTargetMaster.value) return;
  creating.value = true;
  try {
    await store.createChildKit(childTargetMaster.value.id, childForm.name.trim());
    createChildOpen.value = false;
  } catch {
    // toast handled in store
  } finally {
    creating.value = false;
  }
}

async function duplicate(kit) {
  try {
    await store.duplicateKit(kit.id);
  } catch {
    // toast handled in store
  }
}

async function remove(kit, childCountForKit) {
  const message = childCountForKit
    ? `“${kit.name}” has ${childCountForKit} child kit${childCountForKit === 1 ? '' : 's'} inheriting from it. Deleting it affects every one of them.`
    : `“${kit.name}” will be removed. Anything still pointing at this kit falls back to its defaults.`;
  if (!(await confirm({ title: 'Delete brand kit?', message, confirmLabel: 'Delete' }))) return;
  try {
    await store.deleteKit(kit.id);
  } catch {
    // toast handled in store
  }
}

onMounted(() => {
  store.loadKits();
});
</script>

<style scoped>
/* Summary strip — the counts that used to require reading every card. */
.bk-strip {
  display: flex;
  flex-wrap: wrap;
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.bk-strip > div {
  flex: 1 1 9rem;
  display: grid;
  gap: 0.15rem;
  padding: 0.7rem 1rem;
  border-right: 1px solid #eef2f7;
}
.bk-strip > div:last-child {
  border-right: none;
}
.bk-strip dt {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: #94a3b8;
}
.bk-strip dd {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.2;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
}
.bk-strip__text {
  font-size: 0.85rem !important;
  font-weight: 650 !important;
  padding-top: 0.2rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* One card per brand family: Master heads it, children hang off a rail. */
.bk-family {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 22px rgba(15, 23, 42, 0.05);
}
.bk-family__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.8rem;
  padding: 0.85rem 1rem;
  background: linear-gradient(180deg, #fbfcfe, #f4f7fc);
  border-bottom: 1px solid #e6ebf2;
}
.bk-family__name {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.95rem;
  font-weight: 680;
  color: #0f172a;
  letter-spacing: -0.01em;
}
.bk-family__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.25rem;
  font-size: 0.72rem;
  color: #64748b;
}
.bk-dot {
  width: 3px;
  height: 3px;
  border-radius: 999px;
  background: #cbd5e1;
  flex: none;
}
.bk-swatches {
  display: inline-flex;
  gap: 0.15rem;
  flex: none;
}
.bk-sw {
  width: 0.95rem;
  height: 0.95rem;
  border-radius: 0.3rem;
  border: 1px solid rgba(15, 23, 42, 0.12);
  flex: none;
}
.bk-swatches--sm .bk-sw {
  width: 0.78rem;
  height: 0.78rem;
  border-radius: 0.25rem;
}

/* Children: a hairline rail + tick replaces raw indentation. */
.bk-kids {
  padding-right: 1rem;
}
.bk-kid {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.7rem;
  padding: 0.65rem 0 0.65rem 2.75rem;
  border-bottom: 1px solid #eef2f7;
}
.bk-kid::before {
  content: '';
  position: absolute;
  left: 1.7rem;
  top: 0;
  bottom: 0;
  width: 1px;
  background: #e6ebf2;
}
.bk-kid:last-child::before {
  bottom: auto;
  height: 50%;
}
.bk-kid::after {
  content: '';
  position: absolute;
  left: 1.7rem;
  top: 50%;
  width: 0.6rem;
  height: 1px;
  background: #e6ebf2;
}
.bk-kid:hover {
  background: rgba(248, 250, 252, 0.9);
}
.bk-kid__name {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 640;
  color: #334155;
}
.bk-kid__sub {
  margin-top: 0.1rem;
  font-size: 0.68rem;
  color: #94a3b8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bk-family__foot {
  padding: 0.6rem 1rem 0.75rem 2.75rem;
  border-top: 1px solid #eef2f7;
  background: #fcfdff;
}

.bk-actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex: none;
}
/* Square icon-only affordances built on AppButton's ghost variant. */
.bk-icon-btn {
  width: 1.9rem;
  height: 1.9rem;
  padding: 0 !important;
  border-radius: 0.55rem !important;
}
.bk-link {
  gap: 0.35rem;
  padding: 0.2rem 0.4rem !important;
  font-size: 0.78rem;
  color: #2f4da1 !important;
}

/* Logo tiles */
.bk-logo {
  display: grid;
  place-items: center;
  flex: none;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 0.7rem;
  background: #fff;
}
.bk-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.bk-logo--md {
  width: 2.5rem;
  height: 2.5rem;
}
.bk-logo--sm {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 0.55rem;
}
.bk-logo--empty {
  background: #f1f5f9;
  color: #94a3b8;
  font-size: 0.7rem;
  font-weight: 700;
}
.bk-logo--sm.bk-logo--empty {
  font-size: 0.6rem;
}
</style>
