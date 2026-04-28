<template>
  <WizardPageLayout
    current="publish"
    title="Publish"
    description="Publish approved posts, customize how the embed looks, and copy the embed snippet."
    :workspaceId="workspaceId"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <button
        type="button"
        class="btn-primary !w-auto !px-3 !py-2"
        :disabled="publish.publishing"
        @click="publishNow"
        title="Publish and finish"
      >
        {{ publish.publishing ? '⏳' : '✓' }}
      </button>
    </template>

    <!-- Publish success banner -->
    <div v-if="publishedCount !== null" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-emerald-300 bg-emerald-50 text-sm-pro text-emerald-800 mb-2">
      <span>✓ <strong>{{ publishedCount }} post{{ publishedCount !== 1 ? 's' : '' }}</strong> published and live in your embed.</span>
      <div class="flex items-center gap-2">
        <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="showEmbedPreview = true">Test embed</button>
        <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="openCode">Get code</button>
        <button type="button" class="text-emerald-500 hover:text-emerald-700 text-lg leading-none" @click="publishedCount = null" title="Dismiss">✕</button>
      </div>
    </div>

    <div v-if="publish.loading && !publish.stats" class="surface-card-soft flex items-center gap-2 text-sm-pro text-one-sub px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-one-divider border-t-one-primary rounded-full animate-spin" />
      Loading…
    </div>

    <div v-else-if="!workspaceId" class="surface-card p-6 text-sm-pro text-one-sub">
      <div class="flex items-center gap-2 mb-3">
        <select v-model="workspaceId" class="input-pro !py-1.5 !px-2.5 !text-sm-pro flex-1">
          <option value="">Select workspace</option>
          <option v-for="w in workspaces.list" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
        </select>
      </div>
      Pick a workspace to manage publishing.
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-6 items-start">
        <div class="space-y-6">
          <div v-if="appearance" class="surface-card p-4 space-y-6">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm-pro font-semibold text-one-text">Feed appearance (embed)</h2>
              <button
                type="button"
                class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro"
                :disabled="publish.savingSettings"
                @click="saveAppearance"
              >
                {{ publish.savingSettings ? 'Saving…' : 'Save appearance' }}
              </button>
            </div>

            <div class="space-y-4">
              <h3 class="text-2xs font-semibold text-one-sub uppercase tracking-wider">Feed style</h3>
              <select v-model="appearance.feed_style" class="input-pro !py-2 !text-sm-pro max-w-md">
                <option v-for="opt in feedStyleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <p class="text-2xs text-one-sub max-w-2xl">
                The preview below updates immediately. Click <strong class="font-medium text-one-sub">Save appearance</strong> so exported embed code and external sites use the new layout.
              </p>
            </div>

        <div class="space-y-3 border-t border-one-divider pt-4">
          <h3 class="text-2xs font-semibold text-one-sub uppercase tracking-wider">Feed</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.feed.lazy_load" type="checkbox" class="rounded border-one-divider" />
              Lazy load
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.feed.show_load_more" type="checkbox" class="rounded border-one-divider" />
              Show load more button
            </label>
            <div>
              <div class="text-2xs text-one-sub mb-1">Posts per page</div>
              <input v-model.number="appearance.feed.posts_per_page" type="number" min="1" max="100" class="input-pro !py-1.5 !text-sm-pro w-full" />
            </div>
            <div>
              <div class="text-2xs text-one-sub mb-1">Post min width (px)</div>
              <input v-model.number="appearance.feed.post_min_width" type="number" min="120" max="600" class="input-pro !py-1.5 !text-sm-pro w-full" />
            </div>
          </div>
          <p class="text-2xs text-one-sub max-w-2xl">
            Lazy load auto-fetches when visitors scroll. If both lazy load and load more are off, only the first page of posts is shown.
          </p>
        </div>

        <div class="space-y-3 border-t border-one-divider pt-4">
          <h3 class="text-2xs font-semibold text-one-sub uppercase tracking-wider">Post</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-2xl">
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.post.show_titles" type="checkbox" class="rounded border-one-divider" />
              Show titles
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.post.show_share_icons" type="checkbox" class="rounded border-one-divider" />
              Show share icons
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.post.show_comments" type="checkbox" class="rounded border-one-divider" />
              Show comments
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <input v-model="appearance.post.show_likes" type="checkbox" class="rounded border-one-divider" />
              Show likes
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text sm:col-span-2">
              <input v-model="appearance.post.autoplay_videos" type="checkbox" class="rounded border-one-divider" />
              Autoplay videos (YouTube embed, muted)
            </label>
          </div>
          <p class="text-2xs text-one-sub max-w-2xl">
            Like/comment rows are visual hints on the card; live counts require a future social integration.
          </p>
        </div>

        <div class="space-y-3 border-t border-one-divider pt-4">
          <h3 class="text-2xs font-semibold text-one-sub uppercase tracking-wider">Colors</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-full">
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <span class="w-28 shrink-0 text-2xs text-one-sub">Post icon</span>
              <input v-model="appearance.colors.post_icon" type="color" class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_icon" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <span class="w-28 shrink-0 text-2xs text-one-sub">Post text</span>
              <input v-model="appearance.colors.post_text" type="color" class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_text" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <span class="w-28 shrink-0 text-2xs text-one-sub">Post date</span>
              <input v-model="appearance.colors.post_date" type="color" class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_date" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <span class="w-28 shrink-0 text-2xs text-one-sub">Post link</span>
              <input v-model="appearance.colors.post_link" type="color" class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_link" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-one-text">
              <span class="w-28 shrink-0 text-2xs text-one-sub">Post button</span>
              <input v-model="appearance.colors.post_button" type="color" class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_button" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl pt-2">
            <div class="flex flex-wrap items-center gap-2">
              <label class="flex items-center gap-2 text-sm-pro text-one-text">
                <input v-model="appearance.colors.post_border.enabled" type="checkbox" class="rounded border-one-divider" />
                Post border
              </label>
              <input
                v-model="appearance.colors.post_border.color"
                type="color"
                class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0"
                :disabled="!appearance.colors.post_border.enabled"
              />
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="flex items-center gap-2 text-sm-pro text-one-text">
                <input v-model="appearance.colors.post_bg.enabled" type="checkbox" class="rounded border-one-divider" />
                Post background
              </label>
              <input
                v-model="appearance.colors.post_bg.color"
                type="color"
                class="h-9 w-14 rounded border border-one-divider cursor-pointer bg-white p-0"
                :disabled="!appearance.colors.post_bg.enabled"
              />
            </div>
          </div>
        </div>
      </div>

        </div>

        <div class="lg:sticky lg:top-5 self-start max-h-[calc(100vh-140px)] overflow-y-auto pr-1">
          <div class="surface-card p-4">
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="text-sm-pro text-one-text">
                Publishing will make all <span class="font-medium">approved</span> posts live in the public feed.
              </div>
              <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                <button
                  type="button"
                  class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="refresh"
                >
                  Refresh
                </button>
                <button
                  type="button"
                  class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  :disabled="publish.publishing"
                  @click="publishNow"
                >
                  {{ publish.publishing ? 'Publishing…' : 'Publish changes' }}
                </button>
                <button
                  type="button"
                  class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="openCode"
                >
                  Get code
                </button>
                <button
                  type="button"
                  class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="showEmbedPreview = true"
                  :disabled="!publish.code?.public_key"
                  title="Test embed in iframe"
                >
                  Test embed
                </button>
              </div>
            </div>
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-sm-pro font-medium text-one-text">Preview (published)</div>
            <div class="text-2xs text-one-sub mt-0.5">
              Uses the same layout CSS as your embed. Changes as you adjust options; save so live sites load the updated script.
            </div>
          </div>
          <a
            v-if="publish.code?.public_posts_url"
            :href="publish.code.public_posts_url"
            target="_blank"
            rel="noreferrer"
            class="text-sm-pro text-one-sub hover:text-one-text underline underline-offset-2 shrink-0"
          >
            Open JSON
          </a>
        </div>

        <Teleport to="head">
          <link
            v-if="embedCssHref"
            rel="stylesheet"
            :href="embedCssHref"
            :key="embedCssHref"
          />
        </Teleport>

        <div v-if="previewLoading" class="text-sm-pro text-one-sub">Loading preview…</div>
        <div v-else-if="!previewPosts.length" class="text-sm-pro text-one-sub space-y-2">
          <p>No published posts in this preview yet.</p>
          <p class="text-2xs text-one-sub">
            Approving in <strong class="font-medium text-one-sub">Curate</strong> only marks posts as approved.
            Click <strong class="font-medium text-one-sub">Publish changes</strong> above so approved items get a
            <code class="text-one-text">published_at</code> time—then they show here and in the public embed.
          </p>
        </div>
        <div
          v-else-if="appearance && embedCssHref"
          class="rounded-lg border border-one-divider/90 bg-one-bg/90 p-3 overflow-x-auto curator-embed-preview"
        >
          <div class="crt-wrap" :style="previewColorCssVars">
            <div :class="['crt-inner', previewLayoutClass]" :style="previewInnerStyle">
              <a
                v-for="(p, idx) in previewPosts"
                :key="p.id"
                :href="p.video_url || '#'"
                target="_blank"
                rel="noreferrer"
                class="crt-card crt-link"
                :style="previewCardStyle(idx)"
              >
                <div v-if="p.thumbnail_url" class="crt-media">
                  <img :src="p.thumbnail_url" :alt="p.title || 'Post'" loading="lazy" />
                </div>
                <div class="crt-body">
                  <div v-if="appearance.post.show_titles !== false" class="crt-title">
                    {{ p.title || 'Untitled' }}
                  </div>
                  <div class="crt-text">{{ clampPreview(p.content, 220) }}</div>
                  <div class="crt-meta">
                    <span class="crt-meta-date">{{ formatPreviewDate(p.posted_at) }}</span>
                    <span
                      v-if="appearance.post.show_likes || appearance.post.show_comments"
                      class="crt-meta-social"
                    >
                      <template v-if="appearance.post.show_likes">♥</template>
                      <template v-if="appearance.post.show_likes && appearance.post.show_comments"> </template>
                      <template v-if="appearance.post.show_comments">💬</template>
                    </span>
                  </div>
                  <div v-if="appearance.post.show_share_icons && p.video_url" class="crt-share">
                    <a
                      :href="'https://twitter.com/intent/tweet?url=' + encodeURIComponent(p.video_url)"
                      target="_blank"
                      rel="noreferrer"
                      class="crt-share-link"
                      title="Share"
                    >𝕏</a>
                    <a
                      :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(p.video_url)"
                      target="_blank"
                      rel="noreferrer"
                      class="crt-share-link"
                      title="Share"
                    >f</a>
                  </div>
                </div>
              </a>
            </div>
          </div>
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <a
            v-for="p in previewPosts"
            :key="p.id"
            :href="p.video_url || '#'"
            target="_blank"
            rel="noreferrer"
            class="block bg-one-surface rounded-sm-card border border-one-divider hover:shadow-card transition overflow-hidden"
          >
            <div v-if="p.thumbnail_url" class="aspect-video bg-one-bg overflow-hidden">
              <img :src="p.thumbnail_url" class="w-full h-full object-cover" :alt="p.title || 'Post'" />
            </div>
            <div class="p-3">
              <div class="text-sm-pro font-medium text-one-text line-clamp-2">{{ p.title || 'Untitled' }}</div>
              <div class="text-2xs text-one-sub mt-1 line-clamp-3">{{ p.content }}</div>
            </div>
          </a>
        </div>
      </div>
    </div>
    </div>

    <div v-if="showCode" class="fixed inset-0 bg-black/30 flex items-center justify-center p-4 z-50">
      <div class="w-full max-w-2xl surface-card overflow-hidden">
        <div class="px-4 py-3 border-b border-one-divider flex items-center justify-between">
          <div class="text-sm-pro font-medium text-one-text">Embed code</div>
          <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro" @click="showCode = false">Close</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-2xs text-one-sub">
            Paste this into your website where you want the feed to appear.
          </div>
          <textarea class="input-pro font-mono text-2xs !h-40" readonly :value="publish.code?.embed_html || ''" />
          <div class="flex items-center gap-2">
            <button type="button" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro" @click="copyCode">
              Copy
            </button>
            <div v-if="copied" class="text-2xs text-one-sub">Copied</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Embed iframe preview modal -->
    <div v-if="showEmbedPreview" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
      <div class="w-full max-w-3xl surface-card overflow-hidden flex flex-col" style="height: 80vh">
        <div class="px-4 py-3 border-b border-one-divider flex items-center justify-between shrink-0">
          <div class="text-sm-pro font-medium text-one-text">Live embed preview</div>
          <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro" @click="showEmbedPreview = false">Close</button>
        </div>
        <iframe
          v-if="publish.code?.public_key"
          :srcdoc="embedIframeHtml"
          class="flex-1 w-full border-0"
          title="Embed preview"
          sandbox="allow-scripts allow-same-origin"
        />
      </div>
    </div>

    <template #footer>
      <router-link :to="`/workspaces/${workspaceId}/curate`" class="btn-secondary !w-auto" title="Go back">←</router-link>
      <button
        type="button"
        class="btn-primary !w-auto !px-3 !py-2"
        :disabled="publish.publishing"
        @click="publishNow"
        title="Publish and finish"
      >
        {{ publish.publishing ? '⏳' : '✓' }}
      </button>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import { usePublishStore } from '../stores/publish';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import WizardPageLayout from '../components/WizardPageLayout.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const workspaces = useWorkspacesStore();
const publish = usePublishStore();

const workspaceId = ref('');

const showCode = ref(false);
const copied = ref(false);
const publishedCount = ref(null);
const showEmbedPreview = ref(false);

const previewLoading = ref(false);
const previewPosts = ref([]);

/** Local copy of publish_settings for the appearance form */
const appearance = ref(null);

const feedStyleOptions = [
  { value: 'waterfall', label: 'Waterfall' },
  { value: 'grid', label: 'Grid' },
  { value: 'grid_carousel', label: 'Grid carousel' },
  { value: 'carousel', label: 'Carousel' },
  { value: 'mosaic', label: 'Mosaic' },
  { value: 'tetris', label: 'Tetris' },
  { value: 'select', label: 'Select' },
  { value: 'cover_flow', label: 'Cover flow' },
  { value: 'list', label: 'List' },
  { value: 'stagger', label: 'Stagger' },
  { value: 'layers', label: 'Layers' },
];

const selectedFeedType = computed(() => '');

const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

function feedTypeLabel(type) {
  if (type === 'rss') return 'RSS / Atom';
  if (type === 'twitter') return 'X / Twitter';
  if (type === 'tiktok') return 'TikTok';
  if (type === 'threads') return 'Threads';
  if (type === 'youtube') return 'YouTube';
  if (type === 'facebook') return 'Facebook';
  if (type === 'instagram') return 'Instagram';
  return type || '—';
}

/** Same stylesheet as the public embed (including ?v= cache bust). */
const embedCssHref = computed(() => publish.code?.embed_css_url || '');

const embedIframeHtml = computed(() => {
  const code = publish.code;
  if (!code?.embed_html) return '<p style="font-family:sans-serif;padding:1rem;color:#64748b">No embed code yet — publish first.</p>';
  const base = window.location.origin;
  const css = code.embed_css_url ? `<link rel="stylesheet" href="${base}${code.embed_css_url.startsWith('/') ? '' : '/api/'}${code.embed_css_url}">` : '';
  const js = code.embed_js_url ? `<script src="${base}${code.embed_js_url.startsWith('/') ? '' : '/api/'}${code.embed_js_url}"><\/script>` : '';
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${css}</head><body style="margin:0;padding:16px;background:#f8fafc">${code.embed_html}${js}</body></html>`;
});

const previewLayoutClass = computed(() => {
  const st = String(appearance.value?.feed_style || 'grid').replace(/-/g, '_');
  return `crt-layout--${st}`;
});

const previewColorCssVars = computed(() => {
  const a = appearance.value;
  if (!a?.colors) return {};
  const c = a.colors;
  const feed = a.feed || {};
  const minW = Math.max(120, Math.min(Number(feed.post_min_width) || 260, 600));
  const b = c.post_border || {};
  const g = c.post_bg || {};
  return {
    '--crt-icon': c.post_icon,
    '--crt-text': c.post_text,
    '--crt-date': c.post_date,
    '--crt-link': c.post_link,
    '--crt-btn': c.post_button,
    '--crt-post-min': `${minW}px`,
    '--crt-border': b.enabled !== false ? b.color || '#e2e8f0' : 'transparent',
    '--crt-card-bg': g.enabled !== false ? g.color || '#ffffff' : 'transparent',
  };
});

const previewInnerStyle = computed(() => {
  if (!appearance.value) return {};
  const st = String(appearance.value.feed_style || 'grid').replace(/-/g, '_');
  if (st === 'layers') {
    const n = previewPosts.value.length;
    return {
      position: 'relative',
      minHeight: `${Math.min(520, 140 + Math.max(0, n - 1) * 26)}px`,
    };
  }
  return {};
});

function previewCardStyle(idx) {
  if (!appearance.value) return {};
  const st = String(appearance.value.feed_style || 'grid').replace(/-/g, '_');
  if (st === 'stagger') return { animationDelay: `${idx * 0.07}s` };
  if (st === 'layers') {
    const baseW = 300;
    return {
      position: 'absolute',
      width: `${baseW}px`,
      left: '50%',
      marginLeft: `${-baseW / 2 + idx * 14}px`,
      top: `${24 + idx * 20}px`,
      zIndex: 10 + idx,
    };
  }
  return {};
}

function clampPreview(text, n) {
  const s = String(text || '');
  return s.length > n ? `${s.slice(0, n - 1)}…` : s;
}

function formatPreviewDate(v) {
  try {
    return new Date(v).toLocaleDateString();
  } catch {
    return '';
  }
}

watch(
  () => publish.publishSettings,
  (s) => {
    appearance.value = s ? JSON.parse(JSON.stringify(s)) : null;
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
  if (workspaceId.value) {
    await publish.fetchStats(workspaceId.value);
    await publish.fetchCode(workspaceId.value);
    await loadPreview();
  }
});

watch(workspaceId, async (id) => {
  publish.clear();
  previewPosts.value = [];
  if (id) {
    await publish.fetchStats(id);
    await publish.fetchCode(id);
    await loadPreview();
  }
  if (route.name === 'workspace-publish' && id) {
    router.replace(`/workspaces/${id}/publish`);
  }
});

async function saveAppearance() {
  if (!workspaceId.value || !appearance.value) return;
  await publish.savePublishSettings(workspaceId.value, appearance.value);
  await publish.fetchCode(workspaceId.value);
}

async function refresh() {
  if (!workspaceId.value) return;
  await publish.fetchStats(workspaceId.value);
  await loadPreview();
}

async function publishNow() {
  if (!workspaceId.value) return;
  const result = await publish.publish(workspaceId.value);
  publishedCount.value = result?.published ?? 0;
  await publish.fetchCode(workspaceId.value);
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
  const key = publish.code?.public_key;
  if (!key) return;
  // Same-origin `/api/...` matches other stores (and Vite proxy) so preview works when APP_URL
  // differs from the SPA origin or CORS blocks absolute public_posts_url.
  const url = `/api/public/feeds/${encodeURIComponent(key)}/posts`;
  previewLoading.value = true;
  try {
    const { data } = await axios.get(url, { params: { limit: 9 } });
    previewPosts.value = data.posts || [];
  } catch {
    previewPosts.value = [];
  } finally {
    previewLoading.value = false;
  }
}

function formatDate(v) {
  try {
    return new Date(v).toLocaleString();
  } catch {
    return String(v);
  }
}
</script>

<style scoped>
.curator-embed-preview {
  border-radius: 14px;
  border: 1px solid #E5E5EA;
  background: #F4F4F6;
}
</style>
