<template>
  <WizardPageLayout
    current="publish"
    title="Publish"
    description="Publish approved posts, customize how the embed looks, and copy the embed snippet."
    :workspaceId="workspaceId"
    :breadcrumb="['Workspaces', workspaceName || 'Workspace', 'Publish']"
    no-sticky
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <BrandKitBadge v-if="workspaceId" :workspace-id="workspaceId" @applied="onBrandKitAppliedOrReverted" />
      <AppButton
        variant="secondary"
        size="sm"
        :disabled="publish.loading"
        title="Discard unsaved edits and reload from the server"
        @click="refresh"
      >
        <AppIcon name="sync" class="w-4 h-4" />
        Refresh
      </AppButton>
    </template>

    <!-- Publish success banner -->
    <div v-if="publishedCount !== null" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-emerald-300 bg-emerald-50 text-sm-pro text-emerald-800 mb-2">
      <span class="inline-flex items-center gap-2">
        <AppIcon name="check" class="w-4 h-4 shrink-0" />
        <span><strong>{{ publishedCount }} post{{ publishedCount !== 1 ? 's' : '' }}</strong> published and live in your embed.</span>
      </span>
      <div class="flex items-center gap-2">
        <AppButton variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="showEmbedPreview = true">Test embed</AppButton>
        <AppButton variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="openCode">Get code</AppButton>
        <AppButton variant="ghost" size="sm" class="text-emerald-500 hover:text-emerald-700" @click="publishedCount = null" title="Dismiss">
          <AppIcon name="close" class="w-4 h-4" />
        </AppButton>
      </div>
    </div>

    <BrandKitDriftBanner
      v-if="workspaceId"
      :workspace-id="workspaceId"
      class="mb-2"
      @reverted="onBrandKitAppliedOrReverted"
    />

    <div v-if="publish.loading && !publish.stats" class="grid grid-cols-1 lg:grid-cols-[minmax(0,550px)_minmax(0,1fr)] gap-6 items-start">
      <AppCard class="p-6 space-y-4">
        <AppSkeleton variant="line" :lines="2" />
        <AppSkeleton variant="block" />
        <AppSkeleton variant="block" />
        <AppSkeleton variant="block" />
      </AppCard>
      <AppCard class="p-6 space-y-4">
        <AppSkeleton variant="line" :lines="2" />
        <AppSkeleton variant="block" />
        <AppSkeleton variant="block" />
      </AppCard>
    </div>

    <AppCard v-else-if="!workspaceId" class="p-6 text-sm-pro text-slate-600">
      <div class="flex items-center gap-2 mb-3">
        <AppSelect v-model="workspaceId" select-class="!py-1.5 !px-2.5 !text-sm-pro flex-1" :show-placeholder="false">
          <option value="">Select workspace</option>
          <option v-for="w in workspaces.list" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
        </AppSelect>
      </div>
      Pick a workspace to manage publishing.
    </AppCard>

    <div v-else class="space-y-4">
      <PublishStatusBar
        :stats="publish.stats"
        :publishing="publish.publishing"
        :embed-key="embedPublicKey"
        @publish="publishNow"
        @get-code="openCode"
        @test-embed="showEmbedPreview = true"
      />

      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,550px)_minmax(0,1fr)] gap-6 items-start">
        <div class="space-y-3">
          <AppCard v-if="appearance" padding="none">
            <AppTabs
              v-model="activeAppearanceTab"
              :tabs="appearanceTabs"
              aria-label="Feed appearance"
            />

            <div class="px-5 py-5">
              <AppearanceLayoutPanel
                v-if="activeAppearanceTab === 'layout'"
                v-model:settings="appearance"
                :caps="caps"
                :feed-style-options="feedStyleOptions"
              />

              <AppearancePostsPanel
                v-else-if="activeAppearanceTab === 'posts'"
                v-model:settings="appearance"
                :caps="caps"
              />

              <AppearanceColorsPanel
                v-else-if="activeAppearanceTab === 'colors'"
                v-model:settings="appearance"
                :caps="caps"
              />

              <AppearanceWidgetPanel
                v-else-if="activeAppearanceTab === 'widget'"
                v-model:settings="appearance"
                :caps="caps"
                :platform-filter-options="platformFilterOptions"
                :content-type-filter-options="contentTypeFilterOptions"
              />

              <AppearanceBrandingPanel
                v-else-if="activeAppearanceTab === 'branding'"
                v-model:settings="appearance"
                :caps="caps"
              />
            </div>
          </AppCard>

          <PublishSaveBar
            :count="dirtyCount"
            :saving="publish.savingSettings"
            @save="saveAppearance"
            @discard="discardAppearanceChanges"
          />
        </div>

        <div class="lg:sticky lg:top-5 self-start max-h-[calc(100vh-140px)] overflow-y-auto pr-1">

          <PublishPreviewFrame
            v-model="previewDevice"
            :style-label="activeFeedStyleLabel"
            :json-url="previewPostsJsonUrl"
            :site-label="previewSiteLabel"
            :theme="resolvedPreviewTheme"
          >
        <div
          v-if="isPreviewSectionLoading"
          class="publish-preview-skeleton"
          aria-busy="true"
          aria-label="Loading embed preview"
        >
          <div class="publish-preview-skeleton__grid">
            <div
              v-for="n in previewSkeletonCount"
              :key="n"
              class="publish-preview-skeleton__card"
            >
              <div class="publish-preview-skeleton__media" />
              <div class="publish-preview-skeleton__body">
                <div class="publish-preview-skeleton__row">
                  <div class="publish-preview-skeleton__avatar" />
                  <div class="publish-preview-skeleton__line publish-preview-skeleton__line--sm" />
                </div>
                <div class="publish-preview-skeleton__line publish-preview-skeleton__line--full" />
                <div class="publish-preview-skeleton__line publish-preview-skeleton__line--lg" />
                <div class="publish-preview-skeleton__line publish-preview-skeleton__line--xs" />
              </div>
            </div>
          </div>
          <p class="publish-preview-skeleton__caption">Loading published posts preview…</p>
        </div>
        <div v-else-if="!previewPosts.length" class="text-sm-pro text-slate-600 space-y-2">
          <p>No published posts in this preview yet.</p>
          <p class="text-2xs text-slate-500">
            Approving in <strong class="font-medium text-slate-600">Curate</strong> only marks posts as approved.
            Click <strong class="font-medium text-slate-600">Publish changes</strong> above so approved items get a
            <code class="text-slate-700">published_at</code> time—then they show here and in the public embed.
          </p>
        </div>
        <!--
          The preview renders the real embed runtime in an iframe rather than a
          second Vue implementation of the cards. The old copy silently drifted
          (click_action, stagger animation, layers width), which is why settings
          appeared to "do nothing" once published.
        -->
        <EmbedLivePreview
          v-else-if="appearance && embedPublicKey"
          :public-key="embedPublicKey"
          :settings="appearance"
          :theme="resolvedPreviewTheme"
          :version="embedPreviewVersion"
          :css-url="publish.code?.embed_css_url || ''"
          :js-url="publish.code?.embed_js_url || ''"
        />
          </PublishPreviewFrame>
        </div>
      </div>
    </div>

    <div v-if="showCode" class="fixed inset-0 bg-black/30 flex items-center justify-center p-4 z-50">
      <AppCard class="w-full max-w-2xl overflow-hidden" padding="none">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <div class="text-sm-pro font-medium text-slate-800">Embed code</div>
          <AppButton variant="secondary" size="sm" @click="showCode = false">Close</AppButton>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-2xs text-slate-500">
            Paste this into your website where you want the feed to appear.
          </div>
          <AppInput type="textarea" input-class="font-mono text-2xs !h-40" readonly :model-value="publish.code?.embed_html || ''" />
          <div class="flex items-center gap-2">
            <AppButton size="sm" @click="copyCode">Copy</AppButton>
            <div v-if="copied" class="text-2xs text-slate-500">Copied</div>
          </div>
        </div>
      </AppCard>
    </div>

    <!-- Embed iframe preview modal -->
    <div v-if="showEmbedPreview" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
      <AppCard class="w-full max-w-4xl overflow-hidden" padding="none">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between shrink-0">
          <div class="text-sm-pro font-medium text-slate-800">Live embed preview</div>
          <AppButton variant="secondary" size="sm" @click="showEmbedPreview = false">Close</AppButton>
        </div>
        <div class="p-4 overflow-y-auto max-h-[calc(100vh-120px)]">
          <EmbedLivePreview
            v-if="embedPublicKey"
            :public-key="embedPublicKey"
            :settings="savedAppearance"
            :theme="resolvedPreviewTheme"
            :version="embedLivePreviewNonce"
            :css-url="publish.code?.embed_css_url || ''"
            :js-url="publish.code?.embed_js_url || ''"
            :min-height="600"
          />
        </div>
      </AppCard>
    </div>

    <template #footer>
      <AppButton :to="`/workspaces/${workspaceId}/curate`" variant="secondary" size="sm" title="Go back">←</AppButton>
      <AppButton
        size="sm"
        :disabled="publish.publishing"
        @click="publishNow"
        title="Publish and finish"
      >
        {{ publish.publishing ? '⏳' : '✓' }}
      </AppButton>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import { usePublishStore } from '../stores/publish';
import { useToastStore } from '../stores/toast';
import { useNavigationSettingsStore } from '../stores/navigationSettings';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import AppearanceBrandingPanel from '../components/publish/AppearanceBrandingPanel.vue';
import AppearanceColorsPanel from '../components/publish/AppearanceColorsPanel.vue';
import AppearanceLayoutPanel from '../components/publish/AppearanceLayoutPanel.vue';
import AppearancePostsPanel from '../components/publish/AppearancePostsPanel.vue';
import AppearanceWidgetPanel from '../components/publish/AppearanceWidgetPanel.vue';
import BrandKitBadge from '../components/publish/BrandKitBadge.vue';
import BrandKitDriftBanner from '../components/publish/BrandKitDriftBanner.vue';
import EmbedLivePreview from '../components/publish/EmbedLivePreview.vue';
import PublishPreviewFrame from '../components/publish/PublishPreviewFrame.vue';
import PublishSaveBar from '../components/publish/PublishSaveBar.vue';
import PublishStatusBar from '../components/publish/PublishStatusBar.vue';
import { AppButton, AppCard, AppIcon, AppInput, AppSelect, AppSkeleton, AppTabs } from '../components/ui';
import { usePublishAppearanceDraft } from '../composables/usePublishAppearanceDraft';
import { EMBED_TABS, normalizeLayout, resolveCapabilities, visibleTabKeys } from '../constants/embedCapabilities';
import { fetchPreviewPosts } from '../composables/usePublishApi';
import { apiUrlFromAny } from '../config/api.js';

defineOptions({ name: 'PublishView' });

const toast = useToastStore();
const navigationSettings = useNavigationSettingsStore();
const route = useRoute();
const router = useRouter();
const workspaces = useWorkspacesStore();
const publish = usePublishStore();


const workspaceId = ref('');

/** Mirrors backend PublishSettings branding defaults for nested form + preview */
const BRANDING_DEFAULTS = {
  media_badge: {
    show: true,
    image_source: 'platform',
    custom_url: '',
    position: 'center',
  },
  source_icon: {
    show: true,
    image_source: 'platform',
    custom_url: '',
    position: 'before_name',
  },
  account_avatar: {
    show: true,
    image_source: 'connected',
    custom_url: '',
    position: 'footer_start',
  },
};

const POST_DEFAULTS = {
  show_titles: true,
  show_share_icons: false,
  show_comments: false,
  show_likes: false,
  autoplay_videos: false,
  show_platform_icon: true,
  show_feed_name: true,
  source_row_layout: 'stacked',
  source_row_alignment: 'center',
  showcase_content_alignment: 'start',
  showcase_share_icon: 'upload_share',
  showcase_share_icon_color_mode: 'post_icon',
  showcase_share_icon_color: '#e2e8f0',
  platform_icon_color_mode: 'brand',
  platform_icon_color: '#64748b',
};

const WIDGET_DEFAULTS = {
  theme: 'light',
  columns: 3,
  gap: 16,
  border_radius: 12,
  font_family: 'inherit',
  animation: 'fade',
  click_action: 'new_tab',
  auto_refresh: false,
  platform_filters: [],
  content_type_filters: [],
};

function mergePublishAppearance(raw) {
  const clone = JSON.parse(JSON.stringify(raw));
  clone.post = { ...POST_DEFAULTS, ...(clone.post || {}) };
  clone.widget = { ...WIDGET_DEFAULTS, ...(clone.widget || {}) };
  const b = clone.branding || {};
  clone.branding = {
    media_badge: { ...BRANDING_DEFAULTS.media_badge, ...(b.media_badge || {}) },
    source_icon: { ...BRANDING_DEFAULTS.source_icon, ...(b.source_icon || {}) },
    account_avatar: { ...BRANDING_DEFAULTS.account_avatar, ...(b.account_avatar || {}) },
  };
  return clone;
}

const platformFilterOptions = ['youtube', 'facebook', 'instagram', 'twitter', 'tiktok', 'threads', 'rss'];
const contentTypeFilterOptions = ['video', 'image', 'post', 'article'];

const showCode = ref(false);
const copied = ref(false);
const publishedCount = ref(null);
const showEmbedPreview = ref(false);

const previewLoading = ref(false);
const hasLoadedPreviewOnce = ref(false);
const previewPosts = ref([]);
const embedPreviewVersion = ref(0);
const embedLivePreviewNonce = ref(0);
const autoPublishInFlight = ref(false);

/** Local copy of publish_settings for the appearance form */
const appearance = ref(null);

/**
 * Deep clone of `appearance` as the server last accepted it. Everything the
 * unsaved-changes UI shows (save bar count, per-tab dots) is a diff against
 * this, and Discard restores from it without a round trip.
 */
const savedAppearance = ref(null);

const activeAppearanceTab = ref('layout');

const previewDevice = ref('desktop');

const feedStyleOptions = [
  { value: 'waterfall', label: 'Waterfall' },
  { value: 'grid', label: 'Grid' },
  { value: 'grid_carousel', label: 'Grid carousel' },
  { value: 'carousel', label: 'Carousel' },
  {
    value: 'showcase_carousel',
    label: 'Showcase carousel',
  },
  { value: 'mosaic', label: 'Mosaic' },
  { value: 'tetris', label: 'Tetris' },
  { value: 'select', label: 'Select' },
  { value: 'cover_flow', label: 'Cover flow' },
  { value: 'list', label: 'List' },
  { value: 'stagger', label: 'Stagger' },
  { value: 'layers', label: 'Layers' },
];

const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

/** Workspace public key from embed code response or publish stats (either may load first). */
const embedPublicKey = computed(
  () => publish.code?.public_key || publish.stats?.public_key || '',
);

const isPreviewSectionLoading = computed(
  () => previewLoading.value || (!!workspaceId.value && !hasLoadedPreviewOnce.value),
);

const previewPostsJsonUrl = computed(() => {
  const key = embedPublicKey.value;
  if (!key) return '';
  if (publish.code?.public_posts_url) return apiUrlFromAny(publish.code.public_posts_url);
  return apiUrlFromAny(`/api/public/feeds/${encodeURIComponent(key)}/posts`);
});

const activeFeedStyle = computed(() => normalizeLayout(appearance.value?.feed_style));

const previewIsShowcase = computed(() => activeFeedStyle.value === 'showcase_carousel');

/**
 * Resolved widget theme for the preview chrome. `auto` mirrors the editor's own
 * device so what you see matches what a visitor on that device would get.
 */
const resolvedPreviewTheme = computed(() => {
  const theme = String(appearance.value?.widget?.theme || 'light');
  if (theme !== 'auto') return theme === 'dark' ? 'dark' : 'light';
  if (typeof window === 'undefined' || !window.matchMedia) return 'light';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
});

/**
 * Which controls this layout actually honours. `EMBED_CAPABILITIES` is the one
 * place that knows; panels take this map and never re-derive visibility.
 */
const caps = computed(() => resolveCapabilities(activeFeedStyle.value, appearance.value));

const visibleAppearanceTabKeys = computed(() =>
  appearance.value ? visibleTabKeys(caps.value) : ['layout'],
);

const { dirtyCount, dirtySections } = usePublishAppearanceDraft(appearance, savedAppearance);

const appearanceTabs = computed(() =>
  EMBED_TABS.filter((tab) => visibleAppearanceTabKeys.value.includes(tab.key)).map((tab) => ({
    ...tab,
    // Amber marker so edits are never hidden behind a collapsed tab.
    dot: dirtySections.value.has(tab.key),
  })),
);

watch(visibleAppearanceTabKeys, (keys) => {
  if (!keys.includes(activeAppearanceTab.value)) {
    activeAppearanceTab.value = keys[0];
  }
});

const activeFeedStyleLabel = computed(
  () => feedStyleOptions.find((option) => option.value === appearance.value?.feed_style)?.label || '',
);

const previewSiteLabel = computed(() => {
  const name = String(workspaceName.value || '').trim();
  return name && name !== '…' ? `${name} — embed` : 'Embed preview';
});

const previewSkeletonCount = computed(() => (previewIsShowcase.value ? 3 : 6));


const appearanceHydratedFor = ref(null);

/**
 * Points the editable draft and the unsaved-changes baseline at the same
 * server state. Both sides are the *merged* shape so defaults the API omits
 * never register as an unsaved change.
 */
function hydrateAppearance(settings) {
  const merged = settings ? mergePublishAppearance(settings) : null;
  appearance.value = merged;
  savedAppearance.value = merged ? JSON.parse(JSON.stringify(merged)) : null;
}

watch(
  () => publish.publishSettings,
  (s) => {
    // Background stats revalidation (see stores/publish.js fetchStats) can reassign
    // publishSettings after this workspace's appearance was already hydrated once; only
    // resync here on the first load for a given workspace so it doesn't clobber unsaved
    // edits the user is actively making (e.g. mid-toggle checkboxes) with stale server data.
    if (appearanceHydratedFor.value === workspaceId.value) return;
    hydrateAppearance(s);
    appearanceHydratedFor.value = workspaceId.value;
  },
  { immediate: true },
);

onMounted(async () => {
  await workspaces.fetchAll();
  if (route.params.workspaceId) {
    workspaceId.value = String(route.params.workspaceId);
  } else if (!workspaceId.value && workspaces.list.length) {
    workspaceId.value = String(workspaces.list[0].id);
  }
  if (!navigationSettings.loaded) {
    await navigationSettings.fetch();
  }
});

/**
 * The brand-kit toolbar badge (apply) and drift banner (revert) both change
 * `workspace.publish_settings` on the server without going through
 * `saveAppearance()`. `fetchStats`'s stale-while-revalidate reassignment of
 * `publish.publishSettings` is intentionally ignored after first hydration
 * (see the watcher above) so it doesn't clobber in-progress edits — so a
 * successful apply/revert must explicitly re-hydrate the local `appearance`
 * draft and refresh the embed code/preview, mirroring `refresh()` below.
 */
async function onBrandKitAppliedOrReverted() {
  appearanceHydratedFor.value = null;
  hydrateAppearance(publish.publishSettings);
  appearanceHydratedFor.value = workspaceId.value;
  await publish.fetchCode(workspaceId.value, { force: true, background: false });
  embedPreviewVersion.value += 1;
  await loadPreview();
}

onBeforeUnmount(() => {
});

watch(showEmbedPreview, (open) => {
  // Force a fresh runtime + CSS fetch for each modal open so the "Test embed"
  // view never shows a cached build of the embed.
  if (open) embedLivePreviewNonce.value = Date.now();
});

watch(workspaceId, async (id) => {
  previewPosts.value = [];
  hasLoadedPreviewOnce.value = false;
  if (id) {
    await publish.fetchStats(id);
    await publish.fetchCode(id);
    await loadPreview();
    await autoPublishIfNeeded();
  }
  if (route.name === 'workspace-publish' && id) {
    router.replace(`/workspaces/${id}/publish`);
  }
});

async function saveAppearance() {
  if (!workspaceId.value || !appearance.value) return;
  const normalized = await publish.savePublishSettings(workspaceId.value, appearance.value);
  // Re-seed from what the server actually stored so the unsaved-changes count
  // clears and any server-side clamping (e.g. posts-per-page) is reflected.
  hydrateAppearance(normalized ?? appearance.value);
  await publish.fetchCode(workspaceId.value, { force: true, background: false });
  embedPreviewVersion.value += 1;
  await loadPreview();
}

/** Restores the last-saved appearance without a round trip. */
function discardAppearanceChanges() {
  if (!savedAppearance.value) return;
  appearance.value = JSON.parse(JSON.stringify(savedAppearance.value));
}

async function refresh() {
  if (!workspaceId.value) return;
  previewLoading.value = true;
  try {
    // Explicit refresh should discard any unsaved local edits and resync from the server.
    appearanceHydratedFor.value = null;
    await publish.fetchStats(workspaceId.value, { force: true, background: false });
    await publish.fetchCode(workspaceId.value, { force: true, background: false });
    await loadPreview();
  } catch {
    previewLoading.value = false;
  }
}

async function autoPublishIfNeeded() {
  if (!workspaceId.value || autoPublishInFlight.value || publish.publishing) return;
  const stats = publish.stats;
  if (!stats) return;

  const approved = Number(stats.approved || 0);
  const published = Number(stats.published || 0);
  if (approved <= published) return;

  autoPublishInFlight.value = true;
  try {
    const result = await publish.publish(workspaceId.value);
    publishedCount.value = result?.published ?? 0;
    await publish.fetchCode(workspaceId.value, { force: true, background: false });
    await loadPreview();
  } finally {
    autoPublishInFlight.value = false;
  }
}

async function publishNow() {
  if (!workspaceId.value) return;
  const result = await publish.publish(workspaceId.value);
  publishedCount.value = result?.published ?? 0;
  await publish.fetchCode(workspaceId.value, { force: true, background: false });
  await loadPreview();
}

async function openCode() {
  if (!workspaceId.value) return;
  await publish.fetchCode(workspaceId.value);
  showCode.value = true;
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(publish.code?.embed_html || '');
    copied.value = true;
    setTimeout(() => (copied.value = false), 1200);
  } catch {
    toast.error('Copy failed');
  }
}

async function loadPreview() {
  const key = publish.code?.public_key || publish.stats?.public_key;
  if (!key) {
    previewLoading.value = false;
    hasLoadedPreviewOnce.value = true;
    return;
  }
  const url = apiUrlFromAny(`/api/public/feeds/${encodeURIComponent(key)}/posts`);
  previewLoading.value = true;
  try {
    const data = await fetchPreviewPosts(url, 9);
    previewPosts.value = data.posts || [];
  } catch {
    previewPosts.value = [];
  } finally {
    previewLoading.value = false;
    hasLoadedPreviewOnce.value = true;
  }
}

</script>

<style scoped>
.publish-preview-skeleton__grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 0.75rem;
}

@media (min-width: 640px) {
  .publish-preview-skeleton__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .publish-preview-skeleton__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.publish-preview-skeleton__card {
  overflow: hidden;
  border-radius: 0.75rem;
  border: 1px solid rgb(226 232 240 / 0.9);
  background: #fff;
}

.publish-preview-skeleton__media {
  aspect-ratio: 16 / 9;
  background: linear-gradient(90deg, #e2e8f0 0%, #f1f5f9 45%, #e2e8f0 90%);
  background-size: 200% 100%;
  animation: publish-preview-shimmer 1.35s ease-in-out infinite;
}

.publish-preview-skeleton__body {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 0.75rem;
}

.publish-preview-skeleton__row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.publish-preview-skeleton__avatar {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 9999px;
  flex-shrink: 0;
  background: linear-gradient(90deg, #e2e8f0 0%, #f8fafc 45%, #e2e8f0 90%);
  background-size: 200% 100%;
  animation: publish-preview-shimmer 1.35s ease-in-out infinite;
}

.publish-preview-skeleton__line {
  height: 0.65rem;
  border-radius: 9999px;
  background: linear-gradient(90deg, #e2e8f0 0%, #f8fafc 45%, #e2e8f0 90%);
  background-size: 200% 100%;
  animation: publish-preview-shimmer 1.35s ease-in-out infinite;
}

.publish-preview-skeleton__line--sm {
  width: 35%;
}

.publish-preview-skeleton__line--full {
  width: 92%;
}

.publish-preview-skeleton__line--lg {
  width: 78%;
}

.publish-preview-skeleton__line--xs {
  width: 28%;
}

.publish-preview-skeleton__caption {
  margin-top: 0.85rem;
  text-align: center;
  font-size: 0.75rem;
  color: #64748b;
}

@keyframes publish-preview-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}

.publish-widget {
  position: relative;
  overflow: hidden;
  background: linear-gradient(150deg, #ffffff 0%, #f8fbff 100%);
  border-color: rgba(30, 58, 138, 0.12);
}
.publish-widget:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 30px -24px rgba(30, 41, 59, 0.88);
}
.publish-widget::before {
  content: '';
  position: absolute;
  width: 140px;
  height: 140px;
  right: -64px;
  top: -72px;
  border-radius: 9999px;
  background: radial-gradient(circle, rgba(30, 58, 138, 0.08), rgba(30, 58, 138, 0));
  pointer-events: none;
}
.publish-widget::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, rgba(30, 58, 138, 0.7), rgba(37, 99, 235, 0.45));
}
.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  border: 1px solid rgba(30, 58, 138, 0.25);
  background: rgba(239, 246, 255, 0.9);
  color: #1e3a8a;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.publish-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(30, 58, 138, 0.06), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(30, 58, 138, 0.05), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}

.type-dot {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.65rem;
  font-weight: 700;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}

.type-dot :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--threads { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--rss { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }
.type-dot--twitter { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }

@media (prefers-reduced-motion: reduce) {
  .publish-widget:hover {
    transform: none;
    box-shadow: none;
  }
}

</style>
