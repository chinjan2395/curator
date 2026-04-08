<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="page-title truncate flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M4.75 3A1.75 1.75 0 0 0 3 4.75v10.5C3 16.216 3.784 17 4.75 17h10.5A1.75 1.75 0 0 0 17 15.25V7.75a1.75 1.75 0 0 0-.513-1.237l-3-3A1.75 1.75 0 0 0 12.25 3h-7.5Zm5.22 4.47a.75.75 0 0 0-1.06 1.06l.59.59H7.75a.75.75 0 0 0 0 1.5H9.5l-.59.59a.75.75 0 1 0 1.06 1.06l1.88-1.88a.75.75 0 0 0 0-1.06L9.97 7.47Z" />
            </svg>
            Publish
          </h1>
          <span
            v-if="selectedFeedType"
            class="inline-flex items-center px-2 py-0.5 rounded-md text-2xs font-semibold uppercase tracking-wider border bg-slate-50 text-slate-700 border-slate-200"
            :title="`Source: ${feedTypeLabel(selectedFeedType)}`"
          >
            {{ feedTypeLabel(selectedFeedType) }}
          </span>
        </div>
        <div class="text-2xs text-slate-500 mt-0.5">
          Choose a workspace and feed, customize <strong class="font-medium text-slate-600">how the embed looks</strong> on your site,
          publish approved posts, and copy the embed snippet.
        </div>
      </div>
      <div class="surface-card-soft flex items-center gap-2 px-2 py-2">
        <router-link
          v-if="route.name === 'feed-publish' && workspaceId"
          :to="`/workspaces/${workspaceId}/feeds/${feedId || ''}/curate`"
          class="btn-secondary !w-auto !py-1.5 !px-3 text-sm-pro"
        >
          ← Curate
        </router-link>
        <select v-model="workspaceId" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto">
          <option value="">Select workspace</option>
          <option v-for="w in workspaces.list" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
        </select>
        <select v-model="feedId" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto" :disabled="!workspaceId">
          <option value="">Select feed</option>
          <option v-for="f in feedsInWorkspace" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
        </select>
      </div>
    </div>

    <div v-if="publish.loading && !publish.stats" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>

    <div v-else-if="!workspaceId || !feedId" class="surface-card p-6 text-sm-pro text-slate-600">
      Pick a workspace and feed to manage publishing.
    </div>

    <div v-else class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="surface-card p-4 publish-widget">
          <div class="text-2xs text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <span class="metric-chip">APR</span> Approved
          </div>
          <div class="text-xl-pro font-semibold text-slate-900 mt-1">{{ publish.stats?.approved ?? '—' }}</div>
          <div class="text-2xs text-slate-500 mt-1">Ready to publish</div>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-emerald-300/85 to-cyan-300/75" :style="{ width: `${Math.min(100, (publish.stats?.approved || 0) * 8)}%` }" />
          </div>
        </div>
        <div class="surface-card p-4 publish-widget">
          <div class="text-2xs text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <span class="metric-chip">PUB</span> Published
          </div>
          <div class="text-xl-pro font-semibold text-slate-900 mt-1">{{ publish.stats?.published ?? '—' }}</div>
          <div class="text-2xs text-slate-500 mt-1">
            Last: <span class="text-slate-700">{{ publish.stats?.last_published_at ? formatDate(publish.stats.last_published_at) : '—' }}</span>
          </div>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-indigo-300/85 to-violet-300/75" :style="{ width: `${Math.min(100, (publish.stats?.published || 0) * 8)}%` }" />
          </div>
        </div>
        <div class="surface-card p-4 publish-widget">
          <div class="text-2xs text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
            <span class="metric-chip">PDG</span> Pending
          </div>
          <div class="text-xl-pro font-semibold text-slate-900 mt-1">{{ publish.stats?.pending ?? '—' }}</div>
          <div class="text-2xs text-slate-500 mt-1">Awaiting review</div>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-amber-300/85 to-orange-300/75" :style="{ width: `${Math.min(100, (publish.stats?.pending || 0) * 8)}%` }" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-6 items-start">
        <div class="space-y-6">
          <div v-if="appearance" class="surface-card p-4 space-y-6">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm-pro font-semibold text-slate-800">Feed appearance (embed)</h2>
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
              <h3 class="text-2xs font-semibold text-slate-500 uppercase tracking-wider">Feed style</h3>
              <select v-model="appearance.feed_style" class="input-pro !py-2 !text-sm-pro max-w-md">
                <option v-for="opt in feedStyleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <p class="text-2xs text-slate-500 max-w-2xl">
                The preview below updates immediately. Click <strong class="font-medium text-slate-600">Save appearance</strong> so exported embed code and external sites use the new layout.
              </p>
            </div>

        <div class="space-y-3 border-t border-slate-100 pt-4">
          <h3 class="text-2xs font-semibold text-slate-500 uppercase tracking-wider">Feed</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.feed.lazy_load" type="checkbox" class="rounded border-slate-300" />
              Lazy load
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.feed.show_load_more" type="checkbox" class="rounded border-slate-300" />
              Show load more button
            </label>
            <div>
              <div class="text-2xs text-slate-500 mb-1">Posts per page</div>
              <input v-model.number="appearance.feed.posts_per_page" type="number" min="1" max="100" class="input-pro !py-1.5 !text-sm-pro w-full" />
            </div>
            <div>
              <div class="text-2xs text-slate-500 mb-1">Post min width (px)</div>
              <input v-model.number="appearance.feed.post_min_width" type="number" min="120" max="600" class="input-pro !py-1.5 !text-sm-pro w-full" />
            </div>
          </div>
          <p class="text-2xs text-slate-500 max-w-2xl">
            Lazy load auto-fetches when visitors scroll. If both lazy load and load more are off, only the first page of posts is shown.
          </p>
        </div>

        <div class="space-y-3 border-t border-slate-100 pt-4">
          <h3 class="text-2xs font-semibold text-slate-500 uppercase tracking-wider">Post</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-w-2xl">
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.post.show_titles" type="checkbox" class="rounded border-slate-300" />
              Show titles
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.post.show_share_icons" type="checkbox" class="rounded border-slate-300" />
              Show share icons
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.post.show_comments" type="checkbox" class="rounded border-slate-300" />
              Show comments
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <input v-model="appearance.post.show_likes" type="checkbox" class="rounded border-slate-300" />
              Show likes
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700 sm:col-span-2">
              <input v-model="appearance.post.autoplay_videos" type="checkbox" class="rounded border-slate-300" />
              Autoplay videos (YouTube embed, muted)
            </label>
          </div>
          <p class="text-2xs text-slate-500 max-w-2xl">
            Like/comment rows are visual hints on the card; live counts require a future social integration.
          </p>
        </div>

        <div class="space-y-3 border-t border-slate-100 pt-4">
          <h3 class="text-2xs font-semibold text-slate-500 uppercase tracking-wider">Colors</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-full">
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <span class="w-28 shrink-0 text-2xs text-slate-500">Post icon</span>
              <input v-model="appearance.colors.post_icon" type="color" class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_icon" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <span class="w-28 shrink-0 text-2xs text-slate-500">Post text</span>
              <input v-model="appearance.colors.post_text" type="color" class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_text" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <span class="w-28 shrink-0 text-2xs text-slate-500">Post date</span>
              <input v-model="appearance.colors.post_date" type="color" class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_date" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <span class="w-28 shrink-0 text-2xs text-slate-500">Post link</span>
              <input v-model="appearance.colors.post_link" type="color" class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_link" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
            <label class="flex items-center gap-2 text-sm-pro text-slate-700">
              <span class="w-28 shrink-0 text-2xs text-slate-500">Post button</span>
              <input v-model="appearance.colors.post_button" type="color" class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0" />
              <input v-model="appearance.colors.post_button" type="text" class="input-pro !py-1 !text-xs font-mono flex-1 min-w-0" />
            </label>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl pt-2">
            <div class="flex flex-wrap items-center gap-2">
              <label class="flex items-center gap-2 text-sm-pro text-slate-700">
                <input v-model="appearance.colors.post_border.enabled" type="checkbox" class="rounded border-slate-300" />
                Post border
              </label>
              <input
                v-model="appearance.colors.post_border.color"
                type="color"
                class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0"
                :disabled="!appearance.colors.post_border.enabled"
              />
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="flex items-center gap-2 text-sm-pro text-slate-700">
                <input v-model="appearance.colors.post_bg.enabled" type="checkbox" class="rounded border-slate-300" />
                Post background
              </label>
              <input
                v-model="appearance.colors.post_bg.color"
                type="color"
                class="h-9 w-14 rounded border border-slate-200 cursor-pointer bg-white p-0"
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
              <div class="text-sm-pro text-slate-700">
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
              </div>
            </div>
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-sm-pro font-medium text-slate-800">Preview (published)</div>
            <div class="text-2xs text-slate-500 mt-0.5">
              Uses the same layout CSS as your embed. Changes as you adjust options; save so live sites load the updated script.
            </div>
          </div>
          <a
            v-if="publish.code?.public_posts_url"
            :href="publish.code.public_posts_url"
            target="_blank"
            rel="noreferrer"
            class="text-sm-pro text-slate-600 hover:text-slate-800 underline underline-offset-2 shrink-0"
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

        <div v-if="previewLoading" class="text-sm-pro text-slate-500">Loading preview…</div>
        <div v-else-if="!previewPosts.length" class="text-sm-pro text-slate-600 space-y-2">
          <p>No published posts in this preview yet.</p>
          <p class="text-2xs text-slate-500">
            Approving in <strong class="font-medium text-slate-600">Curate</strong> only marks posts as approved.
            Click <strong class="font-medium text-slate-600">Publish changes</strong> above so approved items get a
            <code class="text-slate-700">published_at</code> time—then they show here and in the public embed.
          </p>
        </div>
        <div
          v-else-if="appearance && embedCssHref"
          class="rounded-lg border border-slate-200/90 bg-slate-50/90 p-3 overflow-x-auto curator-embed-preview"
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
            class="block bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-card transition overflow-hidden"
          >
            <div v-if="p.thumbnail_url" class="aspect-video bg-slate-100 overflow-hidden">
              <img :src="p.thumbnail_url" class="w-full h-full object-cover" :alt="p.title || 'Post'" />
            </div>
            <div class="p-3">
              <div class="text-sm-pro font-medium text-slate-800 line-clamp-2">{{ p.title || 'Untitled' }}</div>
              <div class="text-2xs text-slate-500 mt-1 line-clamp-3">{{ p.content }}</div>
            </div>
          </a>
        </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showCode" class="fixed inset-0 bg-black/30 flex items-center justify-center p-4 z-50">
      <div class="w-full max-w-2xl surface-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <div class="text-sm-pro font-medium text-slate-800">Embed code</div>
          <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro" @click="showCode = false">Close</button>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-2xs text-slate-500">
            Paste this into your website where you want the feed to appear.
          </div>
          <textarea class="input-pro font-mono text-2xs !h-40" readonly :value="publish.code?.embed_html || ''" />
          <div class="flex items-center gap-2">
            <button type="button" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro" @click="copyCode">
              Copy
            </button>
            <div v-if="copied" class="text-2xs text-slate-500">Copied</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import { useFeedsStore } from '../stores/feeds';
import { usePublishStore } from '../stores/publish';
import { useToastStore } from '../stores/toast';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const workspaces = useWorkspacesStore();
const feeds = useFeedsStore();
const publish = usePublishStore();

const workspaceId = ref('');
const feedId = ref('');

const showCode = ref(false);
const copied = ref(false);

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

const feedsInWorkspace = computed(() =>
  feeds.list.filter((f) => String(f.workspace_id) === String(workspaceId.value)),
);

const selectedFeedType = computed(() => {
  const f = feedsInWorkspace.value.find((x) => String(x.id) === String(feedId.value));
  return f?.type || '';
});

function feedTypeLabel(type) {
  if (type === 'rss') return 'RSS / Atom';
  if (type === 'twitter') return 'X / Twitter';
  if (type === 'tiktok') return 'TikTok';
  if (type === 'youtube') return 'YouTube';
  if (type === 'facebook') return 'Facebook';
  if (type === 'instagram') return 'Instagram';
  return type || '—';
}

/** Same stylesheet as the public embed (including ?v= cache bust). */
const embedCssHref = computed(() => publish.code?.embed_css_url || '');

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
  // If routed as /workspaces/:workspaceId/feeds/:feedId/publish, auto-select.
  if (route.params.workspaceId) {
    workspaceId.value = String(route.params.workspaceId);
    await feeds.fetchAll(workspaceId.value);
  } else if (!workspaceId.value && workspaces.list.length) {
    // Dashboard-style default: first workspace.
    workspaceId.value = String(workspaces.list[0].id);
    await feeds.fetchAll(workspaceId.value);
  }
  if (route.params.feedId) {
    feedId.value = String(route.params.feedId);
  } else if (!feedId.value && feeds.list.length) {
    // Dashboard-style default: first feed in selected workspace.
    feedId.value = String(feeds.list[0].id);
  }
});

watch(workspaceId, async (id) => {
  feedId.value = '';
  publish.clear();
  previewPosts.value = [];
  if (id) await feeds.fetchAll(id);
  // Dashboard-style default: first feed for selected workspace.
  if (id && !feedId.value && feeds.list.length) {
    feedId.value = String(feeds.list[0].id);
  }
  if (route.name === 'feed-publish' && id) {
    router.replace(`/workspaces/${id}/feeds/${route.params.feedId || ''}/publish`);
  }
});

watch(feedId, async (id) => {
  publish.clear();
  previewPosts.value = [];
  if (workspaceId.value && id) {
    await publish.fetchStats(workspaceId.value, id);
    await publish.fetchCode(workspaceId.value, id);
    await loadPreview();
  }
  if (route.name === 'feed-publish' && workspaceId.value && id) {
    router.replace(`/workspaces/${workspaceId.value}/feeds/${id}/publish`);
  }
});

async function saveAppearance() {
  if (!workspaceId.value || !feedId.value || !appearance.value) return;
  await publish.savePublishSettings(workspaceId.value, feedId.value, appearance.value);
  await publish.fetchCode(workspaceId.value, feedId.value);
}

async function refresh() {
  if (!workspaceId.value || !feedId.value) return;
  await publish.fetchStats(workspaceId.value, feedId.value);
  await loadPreview();
}

async function publishNow() {
  if (!workspaceId.value || !feedId.value) return;
  await publish.publish(workspaceId.value, feedId.value);
  await publish.fetchCode(workspaceId.value, feedId.value);
  await loadPreview();
}

async function openCode() {
  if (!workspaceId.value || !feedId.value) return;
  await publish.fetchCode(workspaceId.value, feedId.value);
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
.publish-widget {
  position: relative;
  overflow: hidden;
  background: linear-gradient(150deg, #ffffff 0%, #f8fbff 100%);
  border-color: rgba(199, 210, 254, 0.7);
}
.publish-widget::before {
  content: '';
  position: absolute;
  width: 140px;
  height: 140px;
  right: -64px;
  top: -72px;
  border-radius: 9999px;
  background: radial-gradient(circle, rgba(129, 140, 248, 0.16), rgba(129, 140, 248, 0));
  pointer-events: none;
}
.publish-widget::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, rgba(99, 102, 241, 0.7), rgba(34, 211, 238, 0.45));
}
.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  border: 1px solid rgba(165, 180, 252, 0.7);
  background: rgba(238, 242, 255, 0.9);
  color: rgb(79, 70, 229);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
}
</style>

