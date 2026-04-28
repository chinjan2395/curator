<template>
  <WizardPageLayout
    current="curate"
    title="Curate"
    description="Review and approve posts from all feeds."
    :workspaceId="workspaceId"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span aria-hidden="true">/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <router-link :to="`/workspaces/${workspaceId}/publish`" class="btn-primary !w-auto !px-3 !py-2 inline-flex items-center justify-center" title="Continue to publish">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Continue to publish</span>
      </router-link>
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
      <button type="button" class="btn-secondary !py-1.5 !px-3 text-sm-pro text-emerald-700 border-emerald-200 hover:bg-emerald-50 inline-flex items-center gap-2" @click="approveAllPending" title="Approve all pending posts">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
        Approve All
      </button>
      <button type="button" class="btn-secondary !py-1.5 !px-3 text-sm-pro text-rose-700 border-rose-200 hover:bg-rose-50 inline-flex items-center gap-2" @click="rejectAllPending" title="Reject all pending posts">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
        Reject All
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
            <span class="text-2xs text-slate-600 mt-0.5 w-full">{{ formatDate(p.posted_at) }}</span>
          </div>
          <button
            type="button"
            class="text-2xs font-medium px-2 py-1 rounded-md border shrink-0 inline-flex items-center justify-center"
            :class="p.pinned ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'"
            @click="togglePin(p)"
            :title="p.pinned ? 'Unpin this post' : 'Pin this post'"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" clip-rule="evenodd" /></svg>
            <span class="sr-only">{{ p.pinned ? 'Unpin' : 'Pin' }}</span>
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
            class="curate-btn curate-btn-approve inline-flex items-center justify-center"
            :class="{ 'curate-btn-active': p.status === 'approved' }"
            @click="setStatus(p, 'approved')"
            title="Approve this post"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            <span class="sr-only">Approve</span>
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-reject inline-flex items-center justify-center"
            :class="{ 'curate-btn-active': p.status === 'rejected' }"
            @click="setStatus(p, 'rejected')"
            title="Reject this post"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            <span class="sr-only">Reject</span>
          </button>
          <button
            type="button"
            class="curate-btn curate-btn-delete ml-auto inline-flex items-center justify-center"
            @click="deletePost(p)"
            title="Delete this post"
          >
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" /></svg>
            <span class="sr-only">Delete post</span>
          </button>
        </div>
      </article>
    </div>

    <template #footer>
      <router-link :to="`/workspaces/${workspaceId}/feeds`" class="btn-secondary !w-auto inline-flex items-center justify-center" title="Go back">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 0-.75-.75H5.612l4.158-3.96a.75.75 0 1 0-1.04-1.08l-5.5 5.25a.75.75 0 0 0 0 1.08l5.5 5.25a.75.75 0 1 0 1.04-1.08L5.612 10.75H16.25A.75.75 0 0 0 17 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Go back</span>
      </router-link>
      <router-link :to="`/workspaces/${workspaceId}/publish`" class="btn-primary !w-auto !px-3 !py-2 inline-flex items-center justify-center" title="Continue to publish">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        <span class="sr-only">Continue to publish</span>
      </router-link>
    </template>

    <!-- Post preview modal -->
    <div v-if="previewPost" class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50" @click.self="previewPost = null">
      <div class="w-full max-w-lg surface-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
            :class="statusBadgeClass(previewPost.status)"
          >{{ previewPost.status }}</span>
          <button type="button" class="btn-secondary !w-auto !py-1 !px-2 inline-flex items-center justify-center" @click="previewPost = null" aria-label="Close preview">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
          </button>
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
          <button type="button" class="curate-btn curate-btn-approve inline-flex items-center justify-center" :class="{ 'curate-btn-active': previewPost.status === 'approved' }" @click="setStatus(previewPost, 'approved')" title="Approve">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
            <span class="sr-only">Approve</span>
          </button>
          <button type="button" class="curate-btn curate-btn-reject inline-flex items-center justify-center" :class="{ 'curate-btn-active': previewPost.status === 'rejected' }" @click="setStatus(previewPost, 'rejected')" title="Reject">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
            <span class="sr-only">Reject</span>
          </button>
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
const feedId = computed(() => route.params.feedId);

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
  if (type === 'threads') return 'Threads';
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
  if (type === 'threads') return '@';
  if (type === 'rss') return '◉';
  if (type === 'twitter') return '𝕏';
  return '•';
}

async function refresh() {
  posts.clearList();
  const allPosts = [];
  const feediesToLoad = feedId.value ? feeds.list.filter(f => f.id === Number(feedId.value)) : feeds.list;
  for (const feed of feediesToLoad) {
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
  const feediesToSync = feedId.value ? feeds.list.filter(f => f.id === Number(feedId.value)) : feeds.list;
  for (const feed of feediesToSync) {
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
  font-size: 0.7rem;
  font-weight: 700;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}

.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--threads { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
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
  font-size: 0.8rem;
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
  font-size: 0.8rem;
  color: rgb(71 85 105);
}
</style>
