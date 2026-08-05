<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="kit?.name || 'Brand kit'"
      :subtitle="isRoot ? 'Master brand kit — every field below is directly editable.' : `Child kit — inherits every field from ${kit?.parent?.name || 'its Master'} until you override it.`"
      icon="sparkles"
      :breadcrumb="breadcrumb"
    >
      <template #actions>
        <AppButton
          v-if="!isRoot && overriddenPaths.length"
          size="sm"
          variant="ghost"
          :disabled="resettingAll"
          @click="resetAllToMaster"
        >
          {{ resettingAll ? 'Resetting…' : 'Reset all to Master' }}
        </AppButton>
        <AppButton
          variant="secondary"
          size="sm"
          @click="router.push('/brand-kits')"
        >
          Back to Brand Kits
        </AppButton>
      </template>
    </AppPageHeader>

    <div
      v-if="loading"
      class="space-y-4"
    >
      <AppSkeleton variant="block" />
      <AppSkeleton variant="card" />
    </div>

    <div
      v-else-if="kit"
      class="bk-editor"
    >
      <!-- One continuous rail: kit identity, grouped section nav, override meter. -->
      <aside class="bk-rail">
        <div class="bk-rail__kit">
          <div
            class="bk-logo bk-logo--md"
            :class="kit.logo_url ? '' : 'bk-logo--empty'"
          >
            <img
              v-if="kit.logo_url"
              :src="kit.logo_url"
              alt=""
              referrerpolicy="no-referrer"
            >
            <span v-else>{{ initials(kit.name) }}</span>
          </div>
          <div class="min-w-0">
            <p class="bk-rail__name">
              {{ kit.name }}
            </p>
            <p class="bk-rail__parent">
              {{ isRoot ? 'Master kit' : `Child of ${kit.parent?.name || 'Master'}` }}
              <template v-if="kit.is_default">
                · Default
              </template>
            </p>
          </div>
        </div>

        <nav class="bk-rail__nav">
          <template
            v-for="section in NAV_SECTIONS"
            :key="section.label"
          >
            <p class="bk-rail__label">
              {{ section.label }}
            </p>
            <AppButton
              v-for="g in section.groups"
              :key="g.id"
              variant="ghost"
              size="sm"
              class="bk-rail__item"
              :class="activeGroup === g.id ? 'bk-rail__item--active' : ''"
              @click="activeGroup = g.id"
            >
              <span class="truncate">{{ g.label }}</span>
              <span
                v-if="!isRoot && overrideCountFor(g)"
                class="bk-rail__count"
              >{{ overrideCountFor(g) }}</span>
            </AppButton>
          </template>
        </nav>

        <div
          v-if="!isRoot"
          class="bk-rail__foot"
        >
          <p class="bk-rail__cap">
            <b>{{ overriddenPaths.length }}</b> of <b>{{ totalFieldCount }}</b> fields overridden
          </p>
          <div class="bk-meter">
            <i :style="{ width: `${overridePercent}%` }" />
          </div>
        </div>
      </aside>

      <section class="bk-panel">
        <div class="bk-panel__head">
          <div class="min-w-0">
            <h2 class="bk-panel__title">
              {{ currentGroup.label }}
            </h2>
            <p class="bk-panel__desc">
              {{ currentGroup.description }}
            </p>
          </div>
          <AppButton
            v-if="activeGroup === 'colors' && draft"
            size="sm"
            variant="ghost"
            :disabled="syncingBrandColors"
            title="Overwrite the embed palette with these brand colours"
            @click="pushIdentityColorsToEmbed"
          >
            {{ syncingBrandColors ? 'Applying…' : 'Apply to embed appearance' }}
          </AppButton>
          <AppButton
            v-if="!isRoot && overrideCountFor(currentGroup)"
            size="sm"
            variant="ghost"
            :disabled="resettingAll"
            @click="resetSection"
          >
            Reset section
          </AppButton>
        </div>

        <div class="bk-rows">
          <!-- Identity keeps two bespoke controls (name, logo upload) that don't
               fit the resolved-value/override model, laid out on the same grid
               so the whole column still aligns. -->
          <template v-if="activeGroup === 'identity'">
            <div class="bk-rows__item">
              <div class="bk-inline-row">
                <div>
                  <label class="bk-inline-row__name">Kit name</label>
                  <code class="bk-inline-row__path">name</code>
                </div>
                <AppInput
                  v-model="nameDraft"
                  placeholder="Acme Global"
                  input-class="!h-9 !text-xs"
                  @change="saveName"
                />
                <div class="bk-inline-row__aside">
                  <AppButton
                    v-if="!kit.is_default"
                    size="sm"
                    variant="ghost"
                    @click="setDefault"
                  >
                    Set as default
                  </AppButton>
                </div>
              </div>
            </div>

            <div class="bk-rows__item">
              <div class="bk-inline-row">
                <div>
                  <label class="bk-inline-row__name">Logo</label>
                  <code class="bk-inline-row__path">logo_asset_id</code>
                  <p
                    v-if="!isRoot"
                    class="bk-inline-row__hint"
                  >
                    Leave empty to use the Master's logo.
                  </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                  <div
                    class="bk-logo bk-logo--lg"
                    :class="kit.logo_url ? '' : 'bk-logo--empty'"
                  >
                    <img
                      v-if="kit.logo_url"
                      :src="kit.logo_url"
                      alt=""
                      referrerpolicy="no-referrer"
                    >
                    <span v-else>{{ initials(kit.name) }}</span>
                  </div>
                  <AppInput
                    type="file"
                    accept="image/*"
                    input-class="!h-9 !text-xs !py-1.5"
                    @change="onLogoUpload"
                  />
                </div>
                <div class="bk-inline-row__aside">
                  <AppButton
                    v-if="kit.logo_url || kit.logo_asset_id"
                    size="sm"
                    variant="ghost"
                    @click="clearLogo"
                  >
                    Clear
                  </AppButton>
                </div>
              </div>
            </div>
          </template>

          <div
            v-for="f in currentGroup.fields"
            :key="f.path"
            class="bk-rows__item"
          >
            <BrandKitFieldRow
              :label="f.label"
              :path="f.path"
              :type="f.type"
              :options="f.options"
              :min="f.min"
              :max="f.max"
              :step="f.step"
              :disabled="f.disabledWhen ? !getAtPath(resolved, f.disabledWhen) : false"
              :resolved-value="getAtPath(resolved, f.path)"
              :is-overridden="isOverridden(f.path)"
              :is-root="isRoot"
              @update="updateField"
              @reset="resetField"
            />
          </div>

          <!-- Embed appearance reuses the Publish panels verbatim, so a kit can
               never offer fewer options than the Publish page does. -->
          <div
            v-if="currentGroup.tab && draft"
            class="bk-appearance"
          >
            <AppearanceLayoutPanel
              v-if="currentGroup.tab === 'layout'"
              v-model:settings="draft"
              :caps="caps"
              :feed-style-options="feedStyleOptions"
            />
            <AppearancePostsPanel
              v-else-if="currentGroup.tab === 'posts'"
              v-model:settings="draft"
              :caps="caps"
            />
            <AppearanceColorsPanel
              v-else-if="currentGroup.tab === 'colors'"
              v-model:settings="draft"
              :caps="caps"
            />
            <AppearanceWidgetPanel
              v-else-if="currentGroup.tab === 'widget'"
              v-model:settings="draft"
              :caps="caps"
              :platform-filter-options="PLATFORM_FILTER_OPTIONS"
              :content-type-filter-options="CONTENT_TYPE_FILTER_OPTIONS"
            />
            <AppearanceBrandingPanel
              v-else-if="currentGroup.tab === 'branding'"
              v-model:settings="draft"
              :caps="caps"
            />

            <p
              v-if="hiddenForLayoutCount"
              class="bk-appearance__note"
            >
              {{ hiddenForLayoutCount }} more option{{ hiddenForLayoutCount === 1 ? '' : 's' }} in this tab
              apply to other feed layouts. Switch <b>Feed style</b> on the Layout tab to edit them — the
              stored values are kept either way.
            </p>
          </div>
        </div>

        <!-- Same runtime the embed serves, so what a kit produces is visible
             before it is applied to any workspace. -->
        <div
          v-if="currentGroup.tab && draft"
          class="bk-preview"
        >
          <div class="bk-preview__head">
            <span class="bk-preview__title">Live preview</span>
            <AppSelect
              v-if="previewWorkspaceOptions.length"
              v-model="previewWorkspaceId"
              :show-placeholder="false"
              select-class="!h-8 !text-xs !py-0"
              aria-label="Preview against workspace"
            >
              <option
                v-for="w in previewWorkspaceOptions"
                :key="w.id"
                :value="String(w.id)"
              >
                {{ w.name }}
              </option>
            </AppSelect>
          </div>
          <EmbedLivePreview
            v-if="previewPublicKey"
            :public-key="previewPublicKey"
            :settings="draft"
            :theme="resolvedPreviewTheme"
            :min-height="360"
          />
          <p
            v-else
            class="bk-preview__empty"
          >
            Publish a workspace once to get a public feed, then this kit can be previewed against it.
          </p>
        </div>
      </section>
    </div>

    <AppEmptyState
      v-else
      title="Brand kit not found"
      description="It may have been deleted."
      icon="alert"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBrandKitsStore } from '../stores/brandKits';
import { useToastStore } from '../stores/toast';
import { useWorkspacesStore } from '../stores/workspaces';
import { AppPageHeader } from '../components/layout';
import { AppButton, AppEmptyState, AppInput, AppSelect, AppSkeleton } from '../components/ui';
import BrandKitFieldRow from '../components/content/BrandKitFieldRow.vue';
import AppearanceBrandingPanel from '../components/publish/AppearanceBrandingPanel.vue';
import AppearanceColorsPanel from '../components/publish/AppearanceColorsPanel.vue';
import AppearanceLayoutPanel from '../components/publish/AppearanceLayoutPanel.vue';
import AppearancePostsPanel from '../components/publish/AppearancePostsPanel.vue';
import AppearanceWidgetPanel from '../components/publish/AppearanceWidgetPanel.vue';
import EmbedLivePreview from '../components/publish/EmbedLivePreview.vue';
import {
  EMBED_CAPABILITIES,
  EMBED_TABS,
  normalizeLayout,
  resolveCapabilities,
} from '../constants/embedCapabilities';
import { provideAppearanceOverrides } from '../composables/useAppearanceOverrides';
import { applyIdentityColors } from '../constants/brandIdentityColors';

defineOptions({ name: 'BrandKitEditorView' });

const route = useRoute();
const router = useRouter();
const store = useBrandKitsStore();
const toast = useToastStore();
const workspaces = useWorkspacesStore();

const APPEARANCE_TAB_DESCRIPTIONS = {
  layout: 'Feed style, loading behaviour and thumbnail sizing.',
  posts: 'What each post card shows, and how the source row is laid out.',
  colors: 'Element-level colours for the embedded feed.',
  widget: 'Theme, grid metrics, click behaviour and content filters.',
  branding: 'Badges, feed icons and the showcase footer avatar.',
};

const kitId = computed(() => route.params.id);
const kit = ref(null);
const resolved = ref(null);
const overriddenPaths = ref([]);
const loading = ref(true);
const activeGroup = ref('identity');
const nameDraft = ref('');
const resettingAll = ref(false);
const syncingBrandColors = ref(false);

const isRoot = computed(() => !kit.value?.parent_id);

const breadcrumb = computed(() => {
  if (!kit.value) return ['Brand Kits'];
  if (kit.value.parent_id) {
    return ['Brand Kits', `${kit.value.parent?.name || 'Master'} (Master)`, kit.value.name];
  }
  return ['Brand Kits', kit.value.name];
});

function humanize(v) {
  return String(v).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function initials(name) {
  return String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

const FEED_STYLES = ['waterfall', 'grid', 'grid_carousel', 'carousel', 'showcase_carousel', 'mosaic', 'tetris', 'select', 'cover_flow', 'list', 'stagger', 'layers'];
const opt = (v) => ({ value: v, label: humanize(v) });

/** Same tiles as Publish's layout picker. */
const EMBED_LAYOUT_LABELS = FEED_STYLES.map(opt);

const GROUPS = [
  {
    id: 'identity',
    label: 'Identity & watermark',
    description: 'Name, logo, and the watermark stamped onto AI-generated images.',
    fields: [
      { label: 'Enable watermark', path: 'watermark.enabled', type: 'checkbox' },
      { label: 'Watermark URL', path: 'watermark.url', type: 'text', disabledWhen: 'watermark.enabled' },
      { label: 'Position', path: 'watermark.position', type: 'select', disabledWhen: 'watermark.enabled', options: ['top_left', 'top_right', 'bottom_left', 'bottom_right', 'center'].map(opt) },
      { label: 'Opacity (0–1)', path: 'watermark.opacity', type: 'number', min: 0, max: 1, step: 0.05, disabledWhen: 'watermark.enabled' },
    ],
  },
  {
    id: 'colors',
    label: 'Colors',
    description: 'Brand palette used by AI generation and as the source for widget colors.',
    fields: ['primary', 'secondary', 'accent', 'background', 'text'].map((k) => ({
      label: humanize(k), path: `colors.${k}`, type: 'color',
    })),
  },
  {
    id: 'typography',
    label: 'Typography',
    description: 'Font families applied to headings and body copy.',
    fields: [
      { label: 'Heading font', path: 'fonts.heading', type: 'text' },
      { label: 'Body font', path: 'fonts.body', type: 'text' },
    ],
  },
];

const groupById = Object.fromEntries(GROUPS.map((g) => [g.id, g]));

// Grouping the rail by what a field actually controls — who the brand is,
// what it looks like, how the embedded widget behaves.
/**
 * The embed-appearance half of a kit is edited with the very same panels the
 * Publish page uses, so a brand kit can never drift behind Publish on which
 * options it offers. Each tab is presented as a pseudo-group in the rail.
 */
const APPEARANCE_GROUPS = EMBED_TABS.map((tab) => ({
  id: `appearance:${tab.key}`,
  tab: tab.key,
  label: tab.label,
  description: APPEARANCE_TAB_DESCRIPTIONS[tab.key],
  fields: [],
}));

const groupByIdAll = { ...groupById, ...Object.fromEntries(APPEARANCE_GROUPS.map((g) => [g.id, g])) };

const NAV_SECTIONS = [
  { label: 'Brand identity', groups: [groupById.identity, groupById.colors, groupById.typography] },
  { label: 'Embed appearance', groups: APPEARANCE_GROUPS },
];

const totalFieldCount = computed(
  () => GROUPS.reduce((n, g) => n + g.fields.length, 0) + Object.keys(EMBED_CAPABILITIES).length,
);

const currentGroup = computed(() => groupByIdAll[activeGroup.value] || GROUPS[0]);

const overridePercent = computed(() => {
  if (!totalFieldCount.value) return 0;
  return Math.min(100, Math.round((overriddenPaths.value.length / totalFieldCount.value) * 100));
});

function overrideCountFor(group) {
  if (group.tab) {
    return Object.entries(EMBED_CAPABILITIES).filter(
      ([path, def]) => def.tab === group.tab && isOverridden(path),
    ).length;
  }
  return group.fields.filter((f) => overriddenPaths.value.includes(f.path)).length;
}

function getAtPath(obj, path) {
  if (!obj) return undefined;
  return path.split('.').reduce((acc, k) => (acc == null ? undefined : acc[k]), obj);
}

function buildPatch(path, value) {
  const keys = path.split('.');
  const root = {};
  let cur = root;
  keys.forEach((k, i) => {
    if (i === keys.length - 1) {
      cur[k] = value;
    } else {
      cur[k] = {};
      cur = cur[k];
    }
  });
  return root;
}

/**
 * The kit stores widget colours under `feed_colors` while the Publish panels
 * write plain `colors`. Translating at this boundary is what lets the panels be
 * reused verbatim on both pages.
 */
function toKitPath(panelPath) {
  return panelPath.startsWith('colors.') ? `feed_${panelPath}` : panelPath;
}

/**
 * A capability key may name a leaf (`widget.gap`) or a subtree
 * (`colors.post_border`), so a subtree counts as overridden when any leaf below
 * it is.
 */
function isOverridden(path) {
  const kitPath = toKitPath(path);
  return overriddenPaths.value.some((p) => p === kitPath || p.startsWith(`${kitPath}.`));
}

/** Panel-space capability keys currently overridden — drives the group chips. */
const overriddenCapabilityPaths = computed(() => {
  const out = new Set();
  Object.keys(EMBED_CAPABILITIES).forEach((path) => {
    if (isOverridden(path)) out.add(path);
  });
  return out;
});

const canInherit = computed(() => !isRoot.value);

provideAppearanceOverrides({
  enabled: canInherit,
  overriddenPaths: overriddenCapabilityPaths,
  reset: (path) => resetSubtree(path),
});

/**
 * Editable copy of the resolved tree in `PublishSettings` shape. The panels
 * mutate it in place; `syncDraft` turns each mutation into a sparse override
 * patch so inheritance from the Master is preserved.
 */
const draft = ref(null);
let draftBaseline = null;
let applyingRemote = false;

function toPanelSettings(res) {
  if (!res) return null;
  return JSON.parse(
    JSON.stringify({
      feed_style: res.feed_style,
      feed: res.feed,
      post: res.post,
      colors: res.feed_colors,
      widget: res.widget,
      branding: res.branding,
    }),
  );
}

function rebuildDraft() {
  applyingRemote = true;
  draft.value = toPanelSettings(resolved.value);
  draftBaseline = JSON.parse(JSON.stringify(draft.value ?? null));
  // Let the deep watcher observe the swap before edits count as user input.
  nextTick(() => {
    applyingRemote = false;
  });
}

/** Collects every changed leaf as a dot-path, so patches stay sparse. */
function diffLeaves(next, prev, prefix, out) {
  if (next === null || typeof next !== 'object' || Array.isArray(next)) {
    if (JSON.stringify(next) !== JSON.stringify(prev)) out.push([prefix, next]);
    return;
  }
  Object.keys(next).forEach((k) => {
    diffLeaves(next[k], prev == null ? undefined : prev[k], prefix ? `${prefix}.${k}` : k, out);
  });
}

let syncTimer = null;
function scheduleDraftSync() {
  if (applyingRemote || !draft.value || !draftBaseline) return;
  if (syncTimer) clearTimeout(syncTimer);
  syncTimer = setTimeout(syncDraft, 400);
}

async function syncDraft() {
  syncTimer = null;
  if (!kit.value || !draft.value || !draftBaseline) return;
  const changes = [];
  diffLeaves(draft.value, draftBaseline, '', changes);
  if (!changes.length) return;

  const patch = {};
  changes.forEach(([path, value]) => deepAssign(patch, toKitPath(path), value));
  draftBaseline = JSON.parse(JSON.stringify(draft.value));

  try {
    const result = await store.patchOverrides(kit.value.id, patch);
    applyResult(result, { keepDraft: true });
  } catch {
    // toast handled in store
  }
}

function deepAssign(target, path, value) {
  const keys = path.split('.');
  let cur = target;
  keys.forEach((k, i) => {
    if (i === keys.length - 1) cur[k] = value;
    else cur = cur[k] = cur[k] || {};
  });
}

/**
 * `keepDraft` avoids stomping in-flight typing after a patch round-trip; the
 * server echo only has to refresh the inheritance markers.
 */
function applyResult(result, { keepDraft = false } = {}) {
  resolved.value = result.resolved;
  overriddenPaths.value = result.overridden_paths;
  if (!keepDraft) rebuildDraft();
}

/** Resets every override under a capability key (leaf or subtree). */
async function resetSubtree(panelPath) {
  if (!kit.value) return;
  const kitPath = toKitPath(panelPath);
  const paths = overriddenPaths.value.filter((p) => p === kitPath || p.startsWith(`${kitPath}.`));
  if (!paths.length) return;
  try {
    for (const path of paths) {
      applyResult(await store.resetOverride(kit.value.id, path), { keepDraft: true });
    }
    rebuildDraft();
  } catch {
    // toast handled in store
  }
}

async function updateField(path, value) {
  if (!kit.value) return;
  try {
    const result = await store.patchOverrides(kit.value.id, buildPatch(path, value));
    resolved.value = result.resolved;
    overriddenPaths.value = result.overridden_paths;
  } catch {
    // toast handled in store
  }
}

async function resetField(path) {
  if (!kit.value) return;
  try {
    const result = await store.resetOverride(kit.value.id, path);
    resolved.value = result.resolved;
    overriddenPaths.value = result.overridden_paths;
  } catch {
    // toast handled in store
  }
}

async function resetPaths(paths, message) {
  if (!kit.value || !paths.length) return;
  resettingAll.value = true;
  try {
    for (const path of paths) {

      const result = await store.resetOverride(kit.value.id, path);
      resolved.value = result.resolved;
      overriddenPaths.value = result.overridden_paths;
    }
    toast.success(message);
  } catch {
    // toast handled in store
  } finally {
    resettingAll.value = false;
  }
}

function resetAllToMaster() {
  return resetPaths([...overriddenPaths.value], 'Reset to Master').then(rebuildDraft);
}

function resetSection() {
  const group = currentGroup.value;
  // Appearance tabs have no static field list — their paths come from the
  // capability matrix, and each may name a subtree rather than a leaf.
  const prefixes = group.tab
    ? Object.entries(EMBED_CAPABILITIES)
      .filter(([, def]) => def.tab === group.tab)
      .map(([path]) => toKitPath(path))
    : group.fields.map((f) => f.path);
  const paths = overriddenPaths.value.filter((p) =>
    prefixes.some((prefix) => p === prefix || p.startsWith(`${prefix}.`)),
  );
  return resetPaths(paths, `${group.label} reset to Master`).then(rebuildDraft);
}

/**
 * Re-seeds the embed palette from the identity palette. Creation does this
 * automatically; this is the manual re-sync for when the brand colours change
 * afterwards. It writes into `draft`, so the usual diff/patch path persists it
 * and every touched field is recorded as a normal override.
 */
async function pushIdentityColorsToEmbed() {
  if (!draft.value || !resolved.value || syncingBrandColors.value) return;
  syncingBrandColors.value = true;
  try {
    applyIdentityColors(draft.value.colors, resolved.value.colors);
    if (syncTimer) clearTimeout(syncTimer);
    await syncDraft();
    toast.success('Embed colours updated from the brand palette.');
  } finally {
    syncingBrandColors.value = false;
  }
}

async function saveName() {
  if (!kit.value || !nameDraft.value.trim() || nameDraft.value === kit.value.name) return;
  try {
    const updated = await store.updateKit(kit.value.id, { name: nameDraft.value.trim() });
    kit.value = { ...kit.value, name: updated.name };
  } catch {
    nameDraft.value = kit.value.name;
  }
}

async function setDefault() {
  if (!kit.value) return;
  try {
    const updated = await store.updateKit(kit.value.id, { is_default: true });
    kit.value = { ...kit.value, is_default: updated.is_default };
  } catch {
    // toast handled in store
  }
}

async function clearLogo() {
  if (!kit.value) return;
  try {
    const updated = await store.updateKit(kit.value.id, { logo_url: null, logo_asset_id: null });
    kit.value = { ...kit.value, logo_url: updated.logo_url, logo_asset_id: updated.logo_asset_id };
  } catch {
    // toast handled in store
  }
}

async function onLogoUpload(e) {
  const file = e.target.files?.[0];
  if (!file || !kit.value) return;
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', 'image');
  try {
    const { data } = await store.uploadLogoAsset(formData);
    const asset = data?.data || data;
    if (asset?.id) {
      const updated = await store.updateKit(kit.value.id, { logo_asset_id: asset.id, logo_url: null });
      kit.value = { ...kit.value, logo_url: updated.logo_url, logo_asset_id: updated.logo_asset_id };
      toast.success('Logo uploaded');
    }
  } catch {
    // toast handled in store
  } finally {
    e.target.value = '';
  }
}

const feedStyleOptions = EMBED_LAYOUT_LABELS;
const PLATFORM_FILTER_OPTIONS = ['youtube', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads', 'rss'];
const CONTENT_TYPE_FILTER_OPTIONS = ['video', 'image', 'post', 'article'];

const caps = computed(() => resolveCapabilities(normalizeLayout(draft.value?.feed_style), draft.value));

/**
 * Non-applicable options are counted rather than hidden silently: a kit is
 * pushed to workspaces whose layout may differ, so the values still matter.
 */
const hiddenForLayoutCount = computed(() => {
  const tab = currentGroup.value?.tab;
  if (!tab) return 0;
  return Object.entries(EMBED_CAPABILITIES).filter(([path, def]) => def.tab === tab && !caps.value[path])
    .length;
});

const resolvedPreviewTheme = computed(() =>
  String(draft.value?.widget?.theme || 'light') === 'dark' ? 'dark' : 'light',
);

const previewWorkspaceId = ref('');
const previewWorkspaceOptions = computed(() => workspaces.list.filter((w) => w.public_key));
const previewPublicKey = computed(
  () =>
    previewWorkspaceOptions.value.find((w) => String(w.id) === previewWorkspaceId.value)?.public_key || '',
);

watch(previewWorkspaceOptions, (list) => {
  if (!previewWorkspaceId.value && list.length) previewWorkspaceId.value = String(list[0].id);
});

watch(draft, scheduleDraftSync, { deep: true });

async function load() {
  loading.value = true;
  try {
    const [kitData, resolvedData] = await Promise.all([
      store.fetchKit(kitId.value),
      store.getResolved(kitId.value, { force: true }),
    ]);
    kit.value = kitData;
    nameDraft.value = kitData.name;
    resolved.value = resolvedData.resolved;
    overriddenPaths.value = resolvedData.overridden_paths;
    rebuildDraft();
  } catch {
    kit.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  load();
  workspaces.fetchAll();
});
</script>

<style scoped>
.bk-appearance {
  padding: 0.9rem 1rem 1.1rem;
}

.bk-appearance__note {
  margin-top: 1rem;
  padding-top: 0.75rem;
  border-top: 1px solid #f1f5f9;
  font-size: 0.7rem;
  color: #94a3b8;
  line-height: 1.5;
}

.bk-preview {
  border-top: 1px solid #e6ebf2;
  background: #fbfcfe;
}

.bk-preview__head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 1rem;
  border-bottom: 1px solid #eef2f7;
}

.bk-preview__title {
  font-size: 0.78rem;
  font-weight: 650;
  color: #334155;
}

.bk-preview__head :deep(select) {
  margin-left: auto;
  max-width: 14rem;
}

.bk-preview__empty {
  padding: 1.1rem 1rem;
  font-size: 0.75rem;
  color: #94a3b8;
}

.bk-editor {
  display: grid;
  gap: 0;
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 22px rgba(15, 23, 42, 0.05);
}

/* Rail */
.bk-rail {
  background: #fbfcfe;
  border-bottom: 1px solid #e6ebf2;
  padding: 0.9rem 0 1rem;
}
.bk-rail__kit {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0 0.9rem 0.8rem;
  border-bottom: 1px solid #eef2f7;
}
.bk-rail__name {
  font-size: 0.86rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.01em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bk-rail__parent {
  font-size: 0.7rem;
  color: #64748b;
  margin-top: 0.05rem;
}
.bk-rail__nav {
  display: flex;
  flex-direction: column;
}
.bk-rail__label {
  padding: 0.85rem 0.9rem 0.3rem;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #94a3b8;
}
/*
 * Rail items are AppButtons so they keep the app's focus/hover behaviour, but
 * the active marker is a pseudo-element bar rather than a border — AppButton
 * owns its own border colour and would fight a border-left here.
 */
.bk-rail__item {
  position: relative;
  width: 100%;
  justify-content: space-between !important;
  gap: 0.5rem;
  padding: 0.35rem 0.85rem 0.35rem 0.75rem !important;
  border-radius: 0 !important;
  border-color: transparent !important;
  box-shadow: none !important;
  font-size: 0.8rem;
  font-weight: 500 !important;
  color: #64748b !important;
  text-align: left;
}
.bk-rail__item--active {
  background: #fff !important;
  color: #0f172a !important;
  font-weight: 650 !important;
}
.bk-rail__item--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #7389ca;
}
.bk-rail__count {
  flex: none;
  font-size: 0.62rem;
  font-weight: 700;
  color: #6d28d9;
  background: #f3f0ff;
  border-radius: 999px;
  padding: 0.05rem 0.35rem;
  font-variant-numeric: tabular-nums;
}
.bk-rail__foot {
  display: grid;
  gap: 0.4rem;
  margin: 0.8rem 0.9rem 0;
  padding-top: 0.7rem;
  border-top: 1px solid #eef2f7;
}
.bk-rail__cap {
  font-size: 0.7rem;
  color: #64748b;
}
.bk-rail__cap b {
  color: #0f172a;
  font-variant-numeric: tabular-nums;
}
.bk-meter {
  height: 4px;
  border-radius: 999px;
  background: #eef2f7;
  overflow: hidden;
}
.bk-meter i {
  display: block;
  height: 100%;
  background: #6d28d9;
  border-radius: 999px;
  transition: width 0.2s ease;
}

/* Panel */
.bk-panel {
  min-width: 0;
}
.bk-panel__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.9rem 1rem;
  border-bottom: 1px solid #e6ebf2;
}
.bk-panel__title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: -0.01em;
}
.bk-panel__desc {
  margin-top: 0.15rem;
  font-size: 0.74rem;
  color: #64748b;
}
.bk-rows__item {
  border-bottom: 1px solid #eef2f7;
}
.bk-rows__item:last-child {
  border-bottom: none;
}
.bk-rows__item:nth-child(even) {
  background: #fcfdff;
}

/* Identity's bespoke rows sit on the same grid as BrandKitFieldRow. */
.bk-inline-row {
  display: grid;
  gap: 0.4rem;
  align-items: center;
  padding: 0.7rem 1rem;
}
.bk-inline-row__name {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
  line-height: 1.3;
}
.bk-inline-row__path {
  display: block;
  margin-top: 0.1rem;
  font-size: 0.66rem;
  color: #94a3b8;
}
.bk-inline-row__hint {
  margin-top: 0.2rem;
  font-size: 0.7rem;
  color: #94a3b8;
}
.bk-inline-row__aside {
  display: flex;
  align-items: center;
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
  width: 2.2rem;
  height: 2.2rem;
}
.bk-logo--lg {
  width: 2.6rem;
  height: 2.6rem;
}
.bk-logo--empty {
  background: #f1f5f9;
  color: #94a3b8;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

@media (min-width: 640px) {
  .bk-inline-row {
    grid-template-columns: minmax(0, 10rem) minmax(0, 1fr) minmax(0, 7.5rem);
    gap: 1rem;
  }
  .bk-inline-row__aside {
    justify-content: flex-end;
  }
}
@media (min-width: 1024px) {
  .bk-editor {
    grid-template-columns: minmax(13rem, 15rem) minmax(0, 1fr);
    align-items: start;
  }
  .bk-rail {
    border-bottom: none;
    border-right: 1px solid #e6ebf2;
    align-self: stretch;
  }
}
</style>
