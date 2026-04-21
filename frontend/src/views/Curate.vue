<template>
  <div class="space-y-4">
    <div class="curate-hero surface-card p-4 md:p-5 mb-4">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="text-2xs text-slate-500 uppercase tracking-wider mb-2">Step 2 of 3</div>
          <h1 class="page-title truncate flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M4.75 3A1.75 1.75 0 0 0 3 4.75v10.5C3 16.216 3.784 17 4.75 17h10.5A1.75 1.75 0 0 0 17 15.25V4.75A1.75 1.75 0 0 0 15.25 3H4.75Zm2.5 3.75a.75.75 0 0 0 0 1.5h5.5a.75.75 0 0 0 0-1.5h-5.5Zm0 3a.75.75 0 0 0 0 1.5h3.25a.75.75 0 0 0 0-1.5H7.25Zm0 3a.75.75 0 0 0 0 1.5h5.5a.75.75 0 0 0 0-1.5h-5.5Z" />
            </svg>
            Curate · {{ feedName }}
          </h1>
          <span
            v-if="feedType"
            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-2xs font-semibold uppercase tracking-wider border bg-slate-50 text-slate-700 border-slate-200 shrink-0"
            :title="`Source: ${feedTypeLabel(feedType)}`"
          >
            <span class="type-dot" :class="`type-dot--${String(feedType || 'other')}`">{{ feedPlatformIcon(feedType) }}</span>
            {{ feedTypeLabel(feedType) }}
          </span>
          <div class="text-2xs text-slate-500 mt-1">Review feed posts before moving to publish.</div>
        </div>
        <div class="surface-card-soft flex items-center gap-2 px-2 py-2">
          <router-link :to="`/workspaces/${workspaceId}`" class="btn-secondary !py-1.5 !px-3 text-sm-pro">
            Back
          </router-link>
          <select v-model="filterStatus" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
          <div class="text-2xs text-slate-500 hidden sm:block" v-if="lastSyncedAt">
            Last sync: <span class="text-slate-600">{{ formatDate(lastSyncedAt) }}</span>
          </div>
          <router-link :to="`/workspaces/${workspaceId}/publish`" class="btn-primary !py-1.5 !px-3 text-sm-pro">
            Continue to Publish
          </router-link>
          <button
            type="button"
            class="btn-secondary !py-1.5 !px-3 text-sm-pro"
            @click="syncNow"
            :disabled="feeds.syncing"
          >
            {{ feeds.syncing ? 'Syncing…' : 'Sync now' }}
          </button>
          <button type="button" class="btn-secondary !py-1.5 !px-3 text-sm-pro" @click="refresh" :disabled="posts.loading">
            {{ posts.loading ? 'Refreshing…' : 'Refresh' }}
          </button>
        </div>
      </div>
      <div class="wizard-stepper mt-4">
        <div class="wizard-step wizard-step--done">
          <span class="wizard-step__index">1</span>
          <div>
            <div class="wizard-step__label">Organize / Edit</div>
            <div class="wizard-step__meta">Workspace details</div>
          </div>
        </div>
        <div class="wizard-step wizard-step--active">
          <span class="wizard-step__index">2</span>
          <div>
            <div class="wizard-step__label">Curate</div>
            <div class="wizard-step__meta">Approve and reject posts</div>
          </div>
        </div>
        <div class="wizard-step">
          <span class="wizard-step__index">3</span>
          <div>
            <div class="wizard-step__label">Publish</div>
            <div class="wizard-step__meta">Embed and publish</div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="posts.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading posts…
    </div>
    <div v-else-if="posts.error" class="text-sm-pro text-red-600">{{ posts.error }}</div>
    <div v-else-if="!posts.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      <template v-if="feedType === 'rss'">
        No posts yet. Click <span class="font-medium text-slate-700">Sync now</span> to pull entries from your RSS or Atom URL.
      </template>
      <template v-else>
        No posts yet. Use “Sync now” or add sources to populate this feed.
      </template>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <article
        v-for="p in posts.list"
        :key="p.id"
        class="surface-card rounded-xl border-2 p-4 flex flex-col transition-colors"
        :class="cardBorderClass(p.status)"
      >
        <div class="flex items-start justify-between gap-2 mb-2">
          <div class="min-w-0 flex items-center gap-2 flex-wrap">
            <span
              class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider shrink-0"
              :class="statusBadgeClass(p.status)"
            >
              {{ p.status }}
            </span>
            <span v-if="p.pinned" class="inline-flex items-center px-2 py-0.5 rounded-md text-2xs font-medium text-amber-800 bg-amber-100 border border-amber-300">
              Pinned
            </span>
            <span class="text-2xs text-slate-400 mt-0.5 w-full">{{ formatDate(p.posted_at) }}</span>
          </div>
          <button
            type="button"
            class="text-2xs font-medium px-2 py-1 rounded-md border shrink-0"
            :class="p.pinned ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'"
            @click="togglePin(p)"
          >
            {{ p.pinned ? 'Unpin' : 'Pin' }}
          </button>
        </div>

        <div class="mb-2" v-if="p.thumbnail_url || p.video_url">
          <a
            v-if="p.video_url"
            :href="p.video_url"
            target="_blank"
            rel="noreferrer"
            class="block group"
          >
            <div class="aspect-video w-full rounded-md overflow-hidden bg-slate-100 flex items-center justify-center">
              <img
                v-if="p.thumbnail_url"
                :src="p.thumbnail_url"
                :alt="p.title || 'Video thumbnail'"
                class="w-full h-full object-cover group-hover:opacity-95 transition"
              />
              <div v-else class="text-2xs text-slate-400">{{ feedType === 'rss' ? 'Open article' : 'Open on YouTube' }}</div>
            </div>
          </a>
        </div>

        <div class="text-sm-pro text-slate-800 whitespace-pre-wrap line-clamp-[10]">
          <strong v-if="p.title" class="block mb-1">{{ p.title }}</strong>
          {{ p.content }}
        </div>

        <div class="flex items-center gap-2 pt-3 mt-auto border-t border-slate-100">
          <button
            type="button"
            class="curate-btn curate-btn-approve"
            :class="{ 'curate-btn-active': p.status === 'approved' }"
            @click="setStatus(p, 'approved')"
          >
            Approve
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-reject"
            :class="{ 'curate-btn-active': p.status === 'rejected' }"
            @click="setStatus(p, 'rejected')"
          >
            Reject
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-delete ml-auto"
            @click="deletePost(p)"
          >
            Delete
          </button>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { usePostsStore } from '../stores/posts';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';

const route = useRoute();
const posts = usePostsStore();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();

const workspaceId = computed(() => route.params.workspaceId);
const feedId = computed(() => route.params.feedId);

const filterStatus = ref('');

const feedName = computed(() => {
  const f = feeds.list.find((x) => x.id === Number(feedId.value));
  return f ? f.name : '…';
});

const lastSyncedAt = computed(() => {
  const f = feeds.list.find((x) => x.id === Number(feedId.value));
  return f ? f.last_synced_at : null;
});

const feedType = computed(() => {
  const f = feeds.list.find((x) => x.id === Number(feedId.value));
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

function feedPlatformIcon(type) {
  if (type === 'youtube') return '▶';
  if (type === 'facebook') return 'f';
  if (type === 'instagram') return '◎';
  if (type === 'tiktok') return '♪';
  if (type === 'rss') return '◉';
  if (type === 'twitter') return '𝕏';
  return '•';
}

async function refresh() {
  await posts.fetchAll(workspaceId.value, feedId.value, { status: filterStatus.value || null });
}

async function syncNow() {
  await feeds.sync(workspaceId.value, Number(feedId.value));
  await refresh();
}

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (!feeds.list.length && workspaceId.value) await feeds.fetchAll(workspaceId.value);
  await refresh();
});

watch(filterStatus, () => {
  refresh();
});

async function setStatus(p, status) {
  await posts.update(workspaceId.value, feedId.value, p.id, { status });
}

async function togglePin(p) {
  await posts.update(workspaceId.value, feedId.value, p.id, { pinned: !p.pinned });
}

async function deletePost(p) {
  if (window.confirm('Delete this post?')) {
    await posts.remove(workspaceId.value, feedId.value, p.id);
  }
}

function formatDate(v) {
  if (!v) return '';
  try {
    const d = new Date(v);
    return d.toLocaleString();
  } catch {
    return String(v);
  }
}

function statusBadgeClass(status) {
  switch (status) {
    case 'approved':
      return 'bg-emerald-100 text-emerald-800 border border-emerald-300';
    case 'rejected':
      return 'bg-rose-100 text-rose-800 border border-rose-300';
    default:
      return 'bg-slate-100 text-slate-600 border border-slate-200';
  }
}

function cardBorderClass(status) {
  switch (status) {
    case 'approved':
      return 'border-emerald-300 bg-emerald-50/30';
    case 'rejected':
      return 'border-rose-200 bg-rose-50/20';
    default:
      return 'border-slate-200/90';
  }
}
</script>

<style scoped>
.curate-btn {
  /* Match app button language (like btn-secondary), with subtle semantic accents. */
  @apply py-1.5 px-3 text-sm-pro font-medium rounded-md transition;
  @apply border border-slate-200 bg-slate-100 text-slate-700;
  @apply hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2;
  transform: translateY(0);
  transition:
    transform 0.14s ease,
    box-shadow 0.18s ease,
    background-color 0.18s ease,
    border-color 0.18s ease;
}
.curate-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 12px 20px -18px rgba(30, 41, 59, 0.9);
}
.curate-btn:active:not(:disabled) {
  transform: translateY(0);
}
.curate-btn-approve {
  @apply border-emerald-200 text-emerald-800;
}
.curate-btn-approve.curate-btn-active {
  @apply bg-emerald-50 border-emerald-300 text-emerald-900;
  @apply focus:ring-emerald-200;
}
.curate-btn-reject {
  @apply border-rose-200 text-rose-700;
}
.curate-btn-reject.curate-btn-active {
  @apply bg-rose-50 border-rose-300 text-rose-900;
  @apply focus:ring-rose-200;
}
.curate-btn-delete {
  @apply bg-white text-rose-600 border-rose-200;
  @apply hover:bg-rose-50 hover:border-rose-300;
  @apply focus:ring-rose-200;
}

.curate-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(56, 189, 248, 0.12), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(99, 102, 241, 0.14), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
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

.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--rss { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }
.type-dot--twitter { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }

@media (prefers-reduced-motion: reduce) {
  .curate-btn {
    transition: none !important;
    transform: none !important;
  }
}

.wizard-stepper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex: 1 1 180px;
  padding: 0.7rem 0.8rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  background: rgba(248, 250, 252, 0.82);
}

.wizard-step--active {
  border-color: rgba(99, 102, 241, 0.45);
  background: rgba(238, 242, 255, 0.72);
}

.wizard-step--done,
.wizard-step--ready {
  border-color: rgba(191, 219, 254, 0.8);
}

.wizard-step__index {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgb(51 65 85);
  background: rgba(226, 232, 240, 0.95);
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: rgb(67 56 202);
  background: rgba(199, 210, 254, 0.95);
}

.wizard-step__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(30 41 59);
}

.wizard-step__meta {
  font-size: 0.7rem;
  color: rgb(100 116 139);
}
</style>
