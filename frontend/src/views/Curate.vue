<template>
  <WizardPageLayout
    current="curate"
    title="Curate"
    description="Review and approve posts from all feeds."
    :workspaceId="workspaceId"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <router-link :to="`/workspaces/${workspaceId}/publish`" class="btn-primary !w-auto !px-3 !py-2" title="Continue to publish">→</router-link>
    </template>

    <div class="flex items-center gap-2 flex-wrap mb-4">
      <select v-model="filterStatus" class="input-pro !py-1.5 !px-2.5 !text-sm-pro !w-auto">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      <div class="text-2xs text-slate-500 hidden sm:block" v-if="lastSyncedAt">
        Last sync: <span class="text-slate-600">{{ formatDate(lastSyncedAt) }}</span>
      </div>
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
      <div class="h-4 w-px bg-slate-200 hidden sm:block" />
      <button type="button" class="btn-secondary !py-1.5 !px-3 text-sm-pro text-emerald-700 border-emerald-200 hover:bg-emerald-50" @click="approveAllPending" title="Approve all pending posts">
        ✓ All
      </button>
      <button type="button" class="btn-secondary !py-1.5 !px-3 text-sm-pro text-rose-700 border-rose-200 hover:bg-rose-50" @click="rejectAllPending" title="Reject all pending posts">
        ✗ All
      </button>
    </div>

    <div v-if="posts.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading posts…
    </div>
    <div v-else-if="posts.error" class="text-sm-pro text-red-600">{{ posts.error }}</div>
    <div v-else-if="!posts.list.length" class="surface-card p-6 text-center text-sm-pro text-slate-500">
      No posts yet. Click <span class="font-medium text-slate-700">Sync now</span> to pull content from your feeds.
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
            :title="p.pinned ? 'Unpin this post' : 'Pin this post'"
          >
            {{ p.pinned ? '📌' : '📍' }}
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
              <div v-else class="text-2xs text-slate-400">Open post</div>
            </div>
          </a>
        </div>

        <button
          type="button"
          class="text-left w-full text-sm-pro text-slate-800 whitespace-pre-wrap line-clamp-[10] hover:text-indigo-700 transition-colors"
          @click="previewPost = p"
          title="Click to preview"
        >
          <strong v-if="p.title" class="block mb-1">{{ p.title }}</strong>
          {{ p.content }}
        </button>

        <div class="flex items-center gap-2 pt-3 mt-auto border-t border-slate-100">
          <button
            type="button"
            class="curate-btn curate-btn-approve"
            :class="{ 'curate-btn-active': p.status === 'approved' }"
            @click="setStatus(p, 'approved')"
            title="Approve this post"
          >
            ✓
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-reject"
            :class="{ 'curate-btn-active': p.status === 'rejected' }"
            @click="setStatus(p, 'rejected')"
            title="Reject this post"
          >
            ✗
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-delete ml-auto"
            @click="deletePost(p)"
            title="Delete this post"
          >
            🗑
          </button>
        </div>
      </article>
    </div>

    <template #footer>
      <router-link :to="`/workspaces/${workspaceId}/feeds`" class="btn-secondary !w-auto" title="Go back">←</router-link>
      <router-link :to="`/workspaces/${workspaceId}/publish`" class="btn-primary !w-auto !px-3 !py-2" title="Continue to publish">→</router-link>
    </template>

    <!-- Post preview modal -->
    <div v-if="previewPost" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50" @click.self="previewPost = null">
      <div class="w-full max-w-lg surface-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
            :class="statusBadgeClass(previewPost.status)"
          >{{ previewPost.status }}</span>
          <button type="button" class="btn-secondary !w-auto !py-1 !px-2 text-xs-pro" @click="previewPost = null">✕</button>
        </div>
        <div class="p-4 space-y-3 max-h-[70vh] overflow-y-auto">
          <div v-if="previewPost.thumbnail_url" class="aspect-video rounded-md overflow-hidden bg-slate-100">
            <img :src="previewPost.thumbnail_url" :alt="previewPost.title || 'Post'" class="w-full h-full object-cover" />
          </div>
          <div v-if="previewPost.title" class="text-sm-pro font-semibold text-slate-800">{{ previewPost.title }}</div>
          <div class="text-sm-pro text-slate-700 whitespace-pre-wrap">{{ previewPost.content }}</div>
          <div class="text-2xs text-slate-400">{{ formatDate(previewPost.posted_at) }}</div>
          <a v-if="previewPost.video_url" :href="previewPost.video_url" target="_blank" rel="noreferrer" class="inline-block text-sm-pro text-indigo-600 hover:underline">
            Open original ↗
          </a>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 flex items-center gap-2">
          <button type="button" class="curate-btn curate-btn-approve" :class="{ 'curate-btn-active': previewPost.status === 'approved' }" @click="setStatus(previewPost, 'approved')" title="Approve">✓</button>
          <button type="button" class="curate-btn curate-btn-reject" :class="{ 'curate-btn-active': previewPost.status === 'rejected' }" @click="setStatus(previewPost, 'rejected')" title="Reject">✗</button>
        </div>
      </div>
    </div>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { usePostsStore } from '../stores/posts';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';
import WizardPageLayout from '../components/WizardPageLayout.vue';

const toast = useToastStore();

const route = useRoute();
const posts = usePostsStore();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();

const workspaceId = computed(() => route.params.workspaceId);

const filterStatus = ref('');
const previewPost = ref(null);

const lastSyncedAt = computed(() => {
  if (!feeds.list.length) return null;
  const times = feeds.list.map(f => new Date(f.last_synced_at)).filter(t => !isNaN(t));
  return times.length ? new Date(Math.max(...times)) : null;
});

const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
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
  posts.clearList();
  const allPosts = [];
  for (const feed of feeds.list) {
    try {
      const feedPosts = await posts.fetchAll(workspaceId.value, feed.id, { status: filterStatus.value || null });
      feedPosts.forEach(p => p._feedId = feed.id);
      allPosts.push(...feedPosts);
    } catch (err) {
      // Continue loading from other feeds
    }
  }
  posts.list = allPosts.sort((a, b) => new Date(b.posted_at) - new Date(a.posted_at));
}

async function syncNow() {
  let totalCreated = 0;
  for (const feed of feeds.list) {
    try {
      const result = await feeds.sync(workspaceId.value, Number(feed.id), { silent: true });
      totalCreated += result?.created || 0;
    } catch (err) {
      // Continue syncing other feeds
    }
  }
  toast.success(`Sync complete — ${totalCreated} new post${totalCreated !== 1 ? 's' : ''} found`);
  await refresh();
}

async function approveAllPending() {
  const pending = posts.list.filter((p) => p.status === 'pending');
  if (!pending.length) { toast.info('No pending posts'); return; }
  await Promise.all(pending.map((p) => posts.update(workspaceId.value, p._feedId, p.id, { status: 'approved' })));
  toast.success(`Approved ${pending.length} post${pending.length !== 1 ? 's' : ''}`);
  await refresh();
}

async function rejectAllPending() {
  const pending = posts.list.filter((p) => p.status === 'pending');
  if (!pending.length) { toast.info('No pending posts'); return; }
  await Promise.all(pending.map((p) => posts.update(workspaceId.value, p._feedId, p.id, { status: 'rejected' })));
  toast.success(`Rejected ${pending.length} post${pending.length !== 1 ? 's' : ''}`);
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
  await posts.update(workspaceId.value, p._feedId, p.id, { status });
}

async function togglePin(p) {
  await posts.update(workspaceId.value, p._feedId, p.id, { pinned: !p.pinned });
}

async function deletePost(p) {
  if (window.confirm('Delete this post?')) {
    await posts.remove(workspaceId.value, p._feedId, p.id);
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
