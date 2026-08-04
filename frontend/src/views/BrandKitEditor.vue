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
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useBrandKitsStore } from '../stores/brandKits';
import { useToastStore } from '../stores/toast';
import { AppPageHeader } from '../components/layout';
import { AppButton, AppEmptyState, AppInput, AppSkeleton } from '../components/ui';
import BrandKitFieldRow from '../components/content/BrandKitFieldRow.vue';

defineOptions({ name: 'BrandKitEditorView' });

const route = useRoute();
const router = useRouter();
const store = useBrandKitsStore();
const toast = useToastStore();

const kitId = computed(() => route.params.id);
const kit = ref(null);
const resolved = ref(null);
const overriddenPaths = ref([]);
const loading = ref(true);
const activeGroup = ref('identity');
const nameDraft = ref('');
const resettingAll = ref(false);

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
  {
    id: 'feed_colors',
    label: 'Widget colors',
    description: 'Element-level colors for the embedded feed. Defaults derive from the brand palette.',
    fields: [
      { label: 'Icon color', path: 'feed_colors.post_icon', type: 'color' },
      { label: 'Text color', path: 'feed_colors.post_text', type: 'color' },
      { label: 'Date color', path: 'feed_colors.post_date', type: 'color' },
      { label: 'Link color', path: 'feed_colors.post_link', type: 'color' },
      { label: 'Button color', path: 'feed_colors.post_button', type: 'color' },
      { label: 'Post border enabled', path: 'feed_colors.post_border.enabled', type: 'checkbox' },
      { label: 'Post border color', path: 'feed_colors.post_border.color', type: 'color', disabledWhen: 'feed_colors.post_border.enabled' },
      { label: 'Post border width (px)', path: 'feed_colors.post_border.width', type: 'number', min: 0, max: 8, disabledWhen: 'feed_colors.post_border.enabled' },
      { label: 'Post background enabled', path: 'feed_colors.post_bg.enabled', type: 'checkbox' },
      { label: 'Post background color', path: 'feed_colors.post_bg.color', type: 'color', disabledWhen: 'feed_colors.post_bg.enabled' },
    ],
  },
  {
    id: 'feed',
    label: 'Feed layout',
    description: 'How posts are arranged and paginated in the embedded widget.',
    fields: [
      { label: 'Feed style', path: 'feed_style', type: 'select', options: FEED_STYLES.map(opt) },
      { label: 'Lazy load images', path: 'feed.lazy_load', type: 'checkbox' },
      { label: 'Posts per page', path: 'feed.posts_per_page', type: 'number', min: 1, max: 100 },
      { label: 'Post min width (px)', path: 'feed.post_min_width', type: 'number', min: 120, max: 600 },
      { label: 'Show "Load more"', path: 'feed.show_load_more', type: 'checkbox' },
      { label: 'Media size mode', path: 'feed.media_size_mode', type: 'select', options: ['auto', 'aspect', 'fixed'].map(opt) },
      { label: 'Media aspect ratio', path: 'feed.media_aspect_ratio', type: 'select', options: ['1:1', '4:3', '16:9', '3:4', '9:16'].map((v) => ({ value: v, label: v })) },
      { label: 'Media height (px)', path: 'feed.media_height', type: 'number', min: 80, max: 800 },
      { label: 'Media fit', path: 'feed.media_fit', type: 'select', options: ['cover', 'contain'].map(opt) },
    ],
  },
  {
    id: 'post',
    label: 'Post display',
    description: 'Which parts of each post the widget renders, and how the source row is laid out.',
    fields: [
      { label: 'Show titles', path: 'post.show_titles', type: 'checkbox' },
      { label: 'Show share icons', path: 'post.show_share_icons', type: 'checkbox' },
      { label: 'Show comments', path: 'post.show_comments', type: 'checkbox' },
      { label: 'Show likes', path: 'post.show_likes', type: 'checkbox' },
      { label: 'Autoplay videos', path: 'post.autoplay_videos', type: 'checkbox' },
      { label: 'Show platform icon', path: 'post.show_platform_icon', type: 'checkbox' },
      { label: 'Show feed / account name', path: 'post.show_feed_name', type: 'checkbox' },
      { label: 'Source row layout', path: 'post.source_row_layout', type: 'select', options: ['stacked', 'inline'].map(opt) },
      { label: 'Source row alignment', path: 'post.source_row_alignment', type: 'select', options: ['center', 'start'].map(opt) },
      { label: 'Showcase content alignment', path: 'post.showcase_content_alignment', type: 'select', options: ['start', 'center'].map(opt) },
      { label: 'Showcase share icon', path: 'post.showcase_share_icon', type: 'select', options: ['upload_share', 'arrow', 'none'].map(opt) },
      { label: 'Showcase share icon color mode', path: 'post.showcase_share_icon_color_mode', type: 'select', options: ['post_icon', 'post_text', 'post_button', 'custom'].map(opt) },
      { label: 'Showcase share icon color', path: 'post.showcase_share_icon_color', type: 'color' },
      { label: 'Platform icon color mode', path: 'post.platform_icon_color_mode', type: 'select', options: ['brand', 'custom'].map(opt) },
      { label: 'Platform icon color', path: 'post.platform_icon_color', type: 'color' },
    ],
  },
  {
    id: 'branding',
    label: 'Branding & badges',
    description: 'Media badge, source icon, and account avatar shown on each post.',
    fields: [
      { label: 'Show media badge', path: 'branding.media_badge.show', type: 'checkbox' },
      { label: 'Media badge source', path: 'branding.media_badge.image_source', type: 'select', options: ['platform', 'custom', 'none'].map(opt) },
      { label: 'Media badge custom URL', path: 'branding.media_badge.custom_url', type: 'text' },
      { label: 'Media badge position', path: 'branding.media_badge.position', type: 'select', options: ['center', 'top_left', 'top_right', 'bottom_left', 'bottom_right'].map(opt) },
      { label: 'Show source icon', path: 'branding.source_icon.show', type: 'checkbox' },
      { label: 'Source icon source', path: 'branding.source_icon.image_source', type: 'select', options: ['platform', 'custom', 'none'].map(opt) },
      { label: 'Source icon custom URL', path: 'branding.source_icon.custom_url', type: 'text' },
      { label: 'Source icon position', path: 'branding.source_icon.position', type: 'select', options: ['before_name', 'after_name'].map(opt) },
      { label: 'Show account avatar', path: 'branding.account_avatar.show', type: 'checkbox' },
      { label: 'Account avatar source', path: 'branding.account_avatar.image_source', type: 'select', options: ['connected', 'initial', 'custom', 'none'].map(opt) },
      { label: 'Account avatar custom URL', path: 'branding.account_avatar.custom_url', type: 'text' },
      { label: 'Account avatar position', path: 'branding.account_avatar.position', type: 'select', options: ['footer_start', 'footer_end'].map(opt) },
    ],
  },
  {
    id: 'widget',
    label: 'Widget behavior',
    description: 'Theme, grid metrics, and how the widget reacts to a click.',
    fields: [
      { label: 'Theme', path: 'widget.theme', type: 'select', options: ['light', 'dark', 'auto', 'custom'].map(opt) },
      { label: 'Columns', path: 'widget.columns', type: 'select', options: [2, 3, 4, 5].map((v) => ({ value: v, label: String(v) })) },
      { label: 'Gap (px)', path: 'widget.gap', type: 'number', min: 0, max: 64 },
      { label: 'Border radius (px)', path: 'widget.border_radius', type: 'number', min: 0, max: 48 },
      { label: 'Font family', path: 'widget.font_family', type: 'text' },
      { label: 'Animation', path: 'widget.animation', type: 'select', options: ['fade', 'slide', 'none'].map(opt) },
      { label: 'Click action', path: 'widget.click_action', type: 'select', options: ['modal', 'new_tab', 'none'].map(opt) },
      { label: 'Auto refresh', path: 'widget.auto_refresh', type: 'checkbox' },
    ],
  },
];

const groupById = Object.fromEntries(GROUPS.map((g) => [g.id, g]));

// Grouping the rail by what a field actually controls — who the brand is,
// what it looks like, how the embedded widget behaves.
const NAV_SECTIONS = [
  { label: 'Identity', groups: [groupById.identity] },
  { label: 'Appearance', groups: [groupById.colors, groupById.typography, groupById.feed_colors] },
  { label: 'Feed & widget', groups: [groupById.feed, groupById.post, groupById.branding, groupById.widget] },
];

const totalFieldCount = computed(() => GROUPS.reduce((n, g) => n + g.fields.length, 0));

const currentGroup = computed(() => groupById[activeGroup.value] || GROUPS[0]);

const overridePercent = computed(() => {
  if (!totalFieldCount.value) return 0;
  return Math.min(100, Math.round((overriddenPaths.value.length / totalFieldCount.value) * 100));
});

function overrideCountFor(group) {
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

function isOverridden(path) {
  return overriddenPaths.value.includes(path);
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
  return resetPaths([...overriddenPaths.value], 'Reset to Master');
}

function resetSection() {
  const paths = currentGroup.value.fields
    .map((f) => f.path)
    .filter((p) => overriddenPaths.value.includes(p));
  return resetPaths(paths, `${currentGroup.value.label} reset to Master`);
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
  } catch {
    kit.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
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
