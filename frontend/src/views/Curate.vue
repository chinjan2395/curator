<template>
  <WizardPageLayout
    current="curate"
    title="Curate"
    description="Review and approve posts from all feeds."
    :workspace-id="workspaceId"
    :breadcrumb="['Workspaces', workspaceName || 'Workspace', 'Curate']"
    no-sticky
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span aria-hidden="true">/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <AppButton :to="`/workspaces/${workspaceId}/publish`" size="sm" title="Continue to publish">
        <AppIcon name="forward" class="w-4 h-4" />
        <span class="sr-only">Continue to publish</span>
      </AppButton>
    </template>

    <div class="curate-toolbar mb-3">
      <div class="curate-toolbar__filters">
        <div class="curate-toolbar__field">
          <span class="curate-toolbar__label">Status</span>
          <AppSelect v-model="filterStatus" select-class="!py-1.5 !px-2.5 !text-sm-pro !w-full" :show-placeholder="false">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </AppSelect>
        </div>
        <div class="curate-toolbar__field">
          <span class="curate-toolbar__label">Platform</span>
          <AppSelect v-model="filterPlatform" select-class="!py-1.5 !px-2.5 !text-sm-pro !w-full" :show-placeholder="false">
            <option value="">All platforms</option>
            <option v-for="platform in platformOptions" :key="platform" :value="platform">
              {{ feedTypeLabel(platform) }}
            </option>
          </AppSelect>
        </div>
      </div>

      <div class="curate-toolbar__actions">
        <label class="curate-select-all-label" title="Select / deselect all posts">
          <input
            type="checkbox"
            :checked="allVisibleSelected"
            :indeterminate.prop="!allVisibleSelected && hasSelection"
            class="post-checkbox-input"
            @change="toggleSelectAll"
          >
          <span class="post-checkbox-mark" :class="{ 'post-checkbox-mark--checked': allVisibleSelected }" />
          <span class="curate-select-all-text">All</span>
        </label>
        <div class="curate-view-toggle" role="group" aria-label="Post view mode">
          <button
            type="button"
            class="curate-view-toggle__btn"
            :class="{ 'curate-view-toggle__btn--active': viewMode === 'cards' }"
            @click="viewMode = 'cards'"
          >
            Cards
          </button>
          <button
            type="button"
            class="curate-view-toggle__btn"
            :class="{ 'curate-view-toggle__btn--active': viewMode === 'compact' }"
            @click="viewMode = 'compact'"
          >
            Compact
          </button>
        </div>
        <AppButton variant="secondary" size="sm" class="curate-toolbar__btn" :disabled="feeds.syncing" @click="syncNow">
          {{ feeds.syncing ? 'Syncing…' : 'Sync now' }}
        </AppButton>
      </div>

      <div v-if="lastSyncedAt" class="curate-toolbar__meta">
        Last sync: <span class="text-slate-500">{{ formatDate(lastSyncedAt) }}</span>
      </div>
    </div>

    <div v-if="hasSelection" class="curate-bulk-bar mb-3">
      <div class="curate-bulk-bar__info">
        <input
          type="checkbox"
          :checked="allVisibleSelected"
          :indeterminate.prop="!allVisibleSelected && hasSelection"
          class="w-3.5 h-3.5 rounded accent-blue-500 cursor-pointer"
          @change="toggleSelectAll"
        >
        <span class="text-sm font-medium text-slate-700">{{ selectedPostIds.size }} selected</span>
      </div>
      <div class="curate-bulk-bar__actions">
        <AppButton variant="success" size="sm" :disabled="!canBulkApprove" @click="bulkApprove">
          <AppIcon name="check" class="w-3.5 h-3.5" /> Approve
        </AppButton>
        <AppButton variant="danger" size="sm" :disabled="!canBulkReject" @click="bulkReject">
          <AppIcon name="close" class="w-3.5 h-3.5" /> Reject
        </AppButton>
        <AppButton variant="secondary" size="sm" @click="clearSelection">Clear</AppButton>
      </div>
    </div>

    <div class="curate-status-strip mb-3">
      <AppCard class="curate-status-card" padding="none">
        <div class="curate-status-card__inner">
          <AppBadge variant="warning">Remaining</AppBadge>
          <span class="curate-status-card__value">{{ statusTotals.pending }}</span>
        </div>
      </AppCard>
      <AppCard class="curate-status-card" padding="none">
        <div class="curate-status-card__inner">
          <AppBadge variant="success">Approved</AppBadge>
          <span class="curate-status-card__value">{{ statusTotals.approved }}</span>
        </div>
      </AppCard>
      <AppCard class="curate-status-card" padding="none">
        <div class="curate-status-card__inner">
          <AppBadge variant="danger">Rejected</AppBadge>
          <span class="curate-status-card__value">{{ statusTotals.rejected }}</span>
        </div>
      </AppCard>
      <AppCard class="curate-status-card" padding="none">
        <div class="curate-status-card__inner">
          <AppBadge variant="info">Total shown</AppBadge>
          <span class="curate-status-card__value">{{ statusTotals.total }}</span>
        </div>
      </AppCard>
    </div>

    <!-- Duplicate groups banner -->
    <div
      v-if="duplicateGroups.list.length > 0"
      class="dup-banner rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 mb-3 flex flex-wrap items-center gap-2"
    >
      <span class="text-sm-pro font-semibold text-amber-800 flex-1">
        {{ duplicateGroups.list.length }} possible duplicate group{{ duplicateGroups.list.length !== 1 ? 's' : '' }} found
      </span>
      <AppButton variant="secondary" size="sm" :disabled="duplicateGroups.loading" @click="scanDuplicates">
        {{ duplicateGroups.loading ? 'Scanning…' : 'Scan for duplicates' }}
      </AppButton>
      <AppButton
        variant="ghost"
        size="sm"
        class="text-amber-600 hover:text-amber-800 font-medium"
        @click="showDuplicates = !showDuplicates"
      >
        {{ showDuplicates ? '▲ Hide' : '▼ Show' }}
      </AppButton>
    </div>

    <div v-if="duplicateGroups.list.length === 0" class="mb-3">
      <AppButton variant="secondary" size="sm" :disabled="duplicateGroups.loading" @click="scanDuplicates">
        {{ duplicateGroups.loading ? 'Scanning…' : 'Scan for duplicates' }}
      </AppButton>
    </div>

    <DuplicateGroupPanel
      v-if="showDuplicates && duplicateGroups.list.length > 0"
      :groups="duplicateGroups.list"
      :feeds-by-id="feedsById"
      :keeping-group-id="keepingGroupId"
      class="mb-4"
      @keep="keepPost"
      @dismiss="dismissGroup"
    />

    <div v-if="posts.loading && !posts.list.length" class="space-y-4">
      <div class="curate-toolbar">
        <div class="curate-toolbar__filters">
          <AppSkeleton variant="line" />
          <AppSkeleton variant="line" />
        </div>
        <div class="flex gap-3">
          <AppSkeleton variant="block" />
          <AppSkeleton variant="block" />
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <AppCard v-for="n in 4" :key="`status-${n}`" class="p-4">
          <AppSkeleton variant="line" :lines="2" />
        </AppCard>
      </div>
      <div class="curate-posts-grid gap-3">
        <AppCard v-for="n in 6" :key="n" class="p-3 space-y-3">
          <AppSkeleton variant="block" />
          <AppSkeleton variant="line" :lines="3" />
        </AppCard>
      </div>
    </div>
    <div v-else-if="posts.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <AppLoader size="sm" label="Refreshing posts..." />
    </div>
    <div v-else-if="posts.error" class="text-sm-pro text-red-600">{{ posts.error }}</div>
    <AppCard v-else-if="!posts.list.length" class="p-6 text-center text-sm-pro text-slate-500">
      No posts yet. Click <span class="font-medium text-slate-700">Sync now</span> to pull content from your feeds.
    </AppCard>
    <AppCard v-else-if="!visiblePosts.length" class="p-6 text-center text-sm-pro text-slate-500">
      No posts match the current filters.
    </AppCard>
    <div v-else class="space-y-6">
      <section v-for="[platform, platformPosts] in postsByPlatform" :key="platform">
        <!-- Platform section header -->
        <div class="flex items-center gap-2 mb-3">
          <SocialPlatformLabel :type="platform" size="md" :label-class="platformLabelClass(platform)" />
          <span class="text-2xs text-slate-400 font-medium">{{ platformPosts.length }} post{{ platformPosts.length !== 1 ? 's' : '' }}</span>
          <div class="flex-1 h-px" :class="platformDividerClass(platform)" />
        </div>

        <!-- Posts grid -->
        <div :class="['grid gap-3', sectionGridClass()]">
          <AppCard
            v-for="p in platformPosts"
            :key="p.id"
            class="rounded-xl p-0 flex flex-col overflow-hidden transition-colors"
            :class="[cardBorderClass(p.status), { 'curate-post-card--compact': viewMode === 'compact' }]"
          >
            <!-- Thumbnail (always shown; placeholder when no image) -->
            <component
              :is="p.video_url ? 'a' : 'div'"
              v-bind="p.video_url ? { href: p.video_url, target: '_blank', rel: 'noreferrer' } : {}"
              class="block group relative aspect-video w-full overflow-hidden flex-shrink-0"
              :class="[
                p.thumbnail_url ? '' : platformPlaceholderBg(platform),
                viewMode === 'compact' ? 'curate-post-media--compact' : '',
              ]"
            >
              <img
                v-if="hasUsableThumbnail(p)"
                :src="p.thumbnail_url"
                :alt="p.title || 'Post image'"
                class="w-full h-full object-cover group-hover:opacity-90 transition"
                @error="markThumbnailBroken(p)"
              >
              <div v-else class="w-full h-full flex items-center justify-center">
                <div class="text-center px-3">
                  <div class="w-8 h-8 opacity-40 mx-auto mb-1" :class="platformIconClass(platform)">
                    <SocialIcon :type="platform" />
                  </div>
                  <div class="text-[10px] font-medium text-slate-500">No preview available</div>
                </div>
              </div>
              <!-- Play overlay for videos -->
              <div v-if="p.video_url" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-7 h-7 rounded-full bg-black/40 flex items-center justify-center">
                  <svg class="w-3.5 h-3.5 text-white ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                </div>
              </div>
              <!-- Checkbox overlay -->
              <label class="post-checkbox-overlay" @click.stop>
                <input
                  type="checkbox"
                  :checked="selectedPostIds.has(p.id)"
                  class="post-checkbox-input"
                  @change="toggleSelect(p)"
                >
                <span class="post-checkbox-mark" :class="{ 'post-checkbox-mark--checked': selectedPostIds.has(p.id) }" />
              </label>
            </component>

            <!-- Body -->
            <div class="flex flex-col flex-1 p-2.5 gap-1.5" :class="{ 'curate-post-body--compact': viewMode === 'compact' }">
              <!-- Meta row -->
              <div class="flex items-center gap-1.5">
                <span class="text-2xs text-slate-400 truncate flex-1">{{ formatDate(p.posted_at) }}</span>
                <span
                  class="inline-flex items-center px-1.5 py-0.5 rounded-full text-2xs font-semibold uppercase tracking-wider shrink-0"
                  :class="statusBadgeClass(p.status)"
                >{{ statusInitial(p.status) }}</span>
                <span
                  v-if="duplicateGroups.duplicatedPostIds.has(p.id)"
                  class="inline-flex items-center px-1 py-0.5 rounded text-2xs font-bold uppercase tracking-wider text-amber-700 bg-amber-100 border border-amber-200 shrink-0"
                >Dup</span>
                <button
                  class="p-0.5 rounded transition-colors"
                  :class="p.pinned ? 'text-amber-500' : 'text-slate-300 hover:text-slate-500'"
                  :title="p.pinned ? 'Unpin' : 'Pin'"
                  @click="togglePin(p)"
                >
                  <AppIcon name="pin" class="w-3 h-3" />
                </button>
              </div>

              <!-- Content -->
              <button
                class="text-left text-2xs text-slate-700 hover:text-blue-700 transition-colors flex-1 leading-relaxed"
                :class="viewMode === 'compact' ? 'line-clamp-2' : 'line-clamp-4'"
                title="Click to preview"
                @click="previewPostId = p.id"
              >
                <strong v-if="p.title" class="block text-slate-800 font-semibold mb-0.5 line-clamp-1">{{ p.title }}</strong>
                {{ p.content }}
              </button>

              <!-- Actions -->
              <div class="post-action-row">
                <AppButton
                  v-if="p.status !== 'approved'"
                  variant="success"
                  size="sm"
                  class="post-action-btn post-action-btn--icon"
                  title="Approve"
                  aria-label="Approve"
                  @click="setStatus(p, 'approved')"
                >
                  <AppIcon name="check" class="w-3.5 h-3.5" />
                </AppButton>
                <AppButton
                  v-if="p.status !== 'rejected'"
                  variant="danger"
                  size="sm"
                  class="post-action-btn post-action-btn--icon"
                  title="Reject"
                  aria-label="Reject"
                  @click="setStatus(p, 'rejected')"
                >
                  <AppIcon name="close" class="w-3.5 h-3.5" />
                </AppButton>
              </div>
            </div>
          </AppCard>
        </div>
      </section>
    </div>

    <!-- Post preview modal -->
    <AppModal v-if="previewPost" :open="true" :closable="true" size="lg" @close="previewPostId = null">
      <div class="w-full">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <span
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider"
            :class="statusBadgeClass(previewPost.status)"
          >{{ previewPost.status }}</span>
          <AppButton variant="secondary" size="sm" aria-label="Close preview" @click="previewPostId = null">
            <AppIcon name="close" class="w-4 h-4" />
          </AppButton>
        </div>
        <div class="p-4 space-y-3 max-h-[70vh] overflow-y-auto">
          <div v-if="previewPost.thumbnail_url" class="aspect-video rounded-md overflow-hidden bg-slate-100">
            <img :src="previewPost.thumbnail_url" :alt="previewPost.title || 'Post'" class="w-full h-full object-cover">
          </div>
          <div v-if="previewPost.title" class="text-sm-pro font-semibold text-slate-800">{{ previewPost.title }}</div>
          <div class="text-sm-pro text-slate-700 whitespace-pre-wrap">{{ previewPost.content }}</div>
          <div class="text-2xs text-slate-400">{{ formatDate(previewPost.posted_at) }}</div>
          <AppButton
            v-if="previewPost.video_url"
            :href="previewPost.video_url"
            target="_blank"
            rel="noreferrer"
            variant="ghost"
            size="sm"
            class="self-start !px-0 !py-0 text-blue-600 hover:!bg-transparent hover:!text-blue-700"
          >
            Open original ↗
          </AppButton>
        </div>
        <div class="px-4 py-3 border-t border-slate-200 flex items-center gap-2">
          <AppButton
            v-if="previewPost.status !== 'approved'"
            variant="success"
            size="sm"
            @click="setStatus(previewPost, 'approved')"
          >
            <AppIcon name="check" class="w-4 h-4" />
            Approve
          </AppButton>
          <AppButton
            v-if="previewPost.status !== 'rejected'"
            variant="danger"
            size="sm"
            @click="setStatus(previewPost, 'rejected')"
          >
            <AppIcon name="close" class="w-4 h-4" />
            Reject
          </AppButton>
        </div>
      </div>
    </AppModal>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { usePostsStore } from '../stores/posts';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useToastStore } from '../stores/toast';
import { useRealtimeWithFallback } from '../composables/useRealtimeWithFallback';
import { useDuplicateGroupsStore } from '../stores/duplicateGroups';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import SocialIcon from '../components/SocialIcon.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import DuplicateGroupPanel from '../components/curate/DuplicateGroupPanel.vue';
import { getPlatformLabel } from '../constants/socialPlatforms';
import { AppBadge, AppButton, AppCard, AppIcon, AppLoader, AppModal, AppSelect, AppSkeleton } from '../components/ui';

defineOptions({ name: 'CurateView' });

const toast = useToastStore();

const route = useRoute();
const posts = usePostsStore();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const duplicateGroups = useDuplicateGroupsStore();
const showDuplicates = ref(true);
const keepingGroupId = ref(null);

const workspaceId = computed(() => route.params.workspaceId);
const feedId = computed(() => route.params.feedId);

const filterStatus = ref('');
const filterPlatform = ref('');
const previewPostId = ref(null);
const previewPost = computed(() => {
  if (!previewPostId.value) return null;
  return posts.list.find((post) => post.id === previewPostId.value) ?? null;
});
const viewMode = ref('cards');
const brokenThumbnails = ref(new Set());
const selectedPostIds = ref(new Set());

const hasSelection = computed(() => selectedPostIds.value.size > 0);

const lastSyncedAt = computed(() => {
  if (!feeds.list.length) return null;
  const times = feeds.list.map(f => new Date(f.last_synced_at)).filter(t => !isNaN(t));
  return times.length ? new Date(Math.max(...times)) : null;
});

const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

const feedsById = computed(() => {
  const map = {};
  for (const f of feeds.list) map[f.id] = f;
  return map;
});

const platformOptions = computed(() => {
  const platforms = new Set();
  posts.list.forEach(p => {
    const feedType = getPostFeedType(p);
    if (feedType) platforms.add(feedType);
  });
  return Array.from(platforms).sort();
});

const filteredPosts = computed(() => {
  return posts.list.filter(p => {
    const statusMatch = !filterStatus.value || p.status === filterStatus.value;
    const platformMatch = !filterPlatform.value || getPostFeedType(p) === filterPlatform.value;
    return statusMatch && platformMatch;
  });
});

const platformOrder = ['youtube', 'instagram', 'facebook', 'twitter', 'tiktok', 'threads', 'rss'];

/** Posts shown in the grid and bulk actions; hide rejected only on the default "all statuses" view. */
const visiblePosts = computed(() => {
  if (filterStatus.value) return filteredPosts.value;
  return filteredPosts.value.filter((p) => p.status !== 'rejected');
});

const selectedPosts = computed(() =>
  visiblePosts.value.filter((post) => selectedPostIds.value.has(post.id)),
);

const canBulkApprove = computed(() =>
  selectedPosts.value.some((post) => post.status !== 'approved'),
);

const canBulkReject = computed(() =>
  selectedPosts.value.some((post) => post.status !== 'rejected'),
);

const allVisibleSelected = computed(() => {
  const ids = visiblePosts.value.map(p => p.id);
  return ids.length > 0 && ids.every(id => selectedPostIds.value.has(id));
});

const postsByPlatform = computed(() => {
  const groups = {};
  for (const p of visiblePosts.value) {
    const type = getPostFeedType(p) || 'unknown';
    if (!groups[type]) groups[type] = [];
    groups[type].push(p);
  }
  return Object.entries(groups).sort(([a], [b]) => {
    const ai = platformOrder.indexOf(a);
    const bi = platformOrder.indexOf(b);
    if (ai === -1 && bi === -1) return feedTypeLabel(a).localeCompare(feedTypeLabel(b));
    if (ai === -1) return 1;
    if (bi === -1) return -1;
    return ai - bi;
  });
});


const statusTotals = computed(() => {
  let approved = 0;
  let rejected = 0;
  let pending = 0;
  for (const post of filteredPosts.value) {
    if (post.status === 'approved') approved += 1;
    else if (post.status === 'rejected') rejected += 1;
    else pending += 1;
  }
  return {
    approved,
    rejected,
    pending,
    total: approved + pending,
  };
});

function getPostFeedType(post) {
  const postFeedId = getPostFeedId(post);
  if (!postFeedId) return null;
  const feed = feeds.list.find((f) => Number(f.id) === Number(postFeedId));
  return feed ? feed.type : null;
}

function getPostFeedId(post) {
  return post?._feedId ?? post?.feed_id ?? post?.feedId ?? null;
}

function feedTypeLabel(type) {
  return getPlatformLabel(type) || '—';
}

function clearSelection() {
  selectedPostIds.value = new Set();
}

function toggleSelect(p) {
  const next = new Set(selectedPostIds.value);
  if (next.has(p.id)) next.delete(p.id);
  else next.add(p.id);
  selectedPostIds.value = next;
}

function toggleSelectAll() {
  if (allVisibleSelected.value) {
    selectedPostIds.value = new Set();
  } else {
    selectedPostIds.value = new Set(visiblePosts.value.map(p => p.id));
  }
}

async function bulkApprove() {
  const toUpdate = visiblePosts.value.filter(
    (p) => selectedPostIds.value.has(p.id) && p.status !== 'approved',
  );
  if (!toUpdate.length) return;
  await posts.bulkUpdate(workspaceId.value, toUpdate.map(p => p.id), { status: 'approved' });
  toast.success(`Approved ${toUpdate.length} post${toUpdate.length !== 1 ? 's' : ''}`);
  clearSelection();
}

async function bulkReject() {
  const toUpdate = visiblePosts.value.filter(
    (p) => selectedPostIds.value.has(p.id) && p.status !== 'rejected',
  );
  if (!toUpdate.length) return;
  await posts.bulkUpdate(workspaceId.value, toUpdate.map(p => p.id), { status: 'rejected' });
  toast.success(`Rejected ${toUpdate.length} post${toUpdate.length !== 1 ? 's' : ''}`);
  clearSelection();
}

function getLatestUpdatedAt() {
  let latest = null;
  for (const post of posts.list) {
    const at = post.updated_at || post.posted_at;
    if (!at) continue;
    const date = new Date(at);
    if (Number.isNaN(date.getTime())) continue;
    if (!latest || date > latest) latest = date;
  }
  return latest ? latest.toISOString() : null;
}

async function refresh({ incremental = false, force = false } = {}) {
  if (!incremental && !posts.list.length) {
    clearSelection();
  }

  const fetched = await posts.fetchWorkspace(workspaceId.value, {
    feedId: feedId.value ? Number(feedId.value) : null,
    since: incremental ? getLatestUpdatedAt() : null,
    silent: incremental || posts.list.length > 0,
    force,
  });

  if (incremental) {
    posts.mergePosts(fetched, workspaceId.value, feedId.value ? Number(feedId.value) : null);
    return;
  }

  posts.list = fetched;
}

async function syncNow() {
  let totalCreated = 0;
  const feediesToSync = feedId.value ? feeds.list.filter(f => f.id === Number(feedId.value)) : feeds.list;
  for (const feed of feediesToSync) {
    try {
      const result = await feeds.sync(workspaceId.value, Number(feed.id), { silent: true });
      totalCreated += result?.created || 0;
    } catch {
      // Continue syncing other feeds
    }
  }
  toast.success(`Sync complete — ${totalCreated} new post${totalCreated !== 1 ? 's' : ''} found`);
  await refresh({ force: true });
  await duplicateGroups.fetch(workspaceId.value, { force: true, background: false });
}


async function handleFeedSync(payload) {
  if (Number(payload.workspace_id) !== Number(workspaceId.value)) return;
  if (payload.status === 'started') return;
  if (document.hidden || posts.loading || feeds.syncing) return;
  try {
    if (payload.status === 'success' && payload.posts_synced > 0) {
      toast.info(`${payload.posts_synced} new post${payload.posts_synced !== 1 ? 's' : ''} synced`);
    }
    await refresh({ incremental: payload.status === 'success' });
    if (payload.status === 'success') {
      await duplicateGroups.fetch(workspaceId.value);
    }
    if (payload.last_synced_at) {
      const i = feeds.list.findIndex((f) => f.id === payload.feed_id);
      if (i !== -1) {
        feeds.list[i] = { ...feeds.list[i], last_synced_at: payload.last_synced_at };
      }
    }
  } catch {
    // Background refresh should not interrupt curation.
  } finally {
    feeds.syncing = false;
  }
}

async function handleDuplicateScan(payload) {
  if (Number(payload.workspace_id) !== Number(workspaceId.value)) return;
  await duplicateGroups.fetch(workspaceId.value);
  if (payload.triggered_by === 'scan') {
    const count = payload.group_count ?? 0;
    toast.success(`Duplicate scan complete — ${count} group${count === 1 ? '' : 's'} found`);
  }
}

useRealtimeWithFallback({
  event: 'feedSync',
  onEvent: handleFeedSync,
  poll: () => refresh({ incremental: true }),
});

useRealtimeWithFallback({
  event: 'duplicateScan',
  onEvent: handleDuplicateScan,
});

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (!feeds.list.length && workspaceId.value) await feeds.fetchAll(workspaceId.value);
  await refresh();
  await duplicateGroups.fetch(workspaceId.value);
});

watch([filterStatus, filterPlatform], () => {
  clearSelection();
});

watch(
  () => posts.list.map((p) => thumbnailKey(p)),
  (keys) => {
    const keep = new Set(keys);
    const next = new Set();
    for (const key of brokenThumbnails.value) {
      if (keep.has(key)) next.add(key);
    }
    brokenThumbnails.value = next;
  },
);

async function keepPost(groupId, postId) {
  keepingGroupId.value = groupId;
  try {
    await duplicateGroups.keepPost(workspaceId.value, groupId, postId);
    toast.success('Resolved — duplicates rejected.');
  } catch {
    toast.error('Failed to resolve group.');
  } finally {
    keepingGroupId.value = null;
  }
}

async function dismissGroup(groupId) {
  try {
    await duplicateGroups.dismiss(workspaceId.value, groupId);
    toast.success('Group dismissed.');
  } catch {
    toast.error('Failed to dismiss group.');
  }
}

async function scanDuplicates() {
  try {
    await duplicateGroups.scan(workspaceId.value);
    toast.info('Duplicate scan started…');
  } catch {
    toast.error('Failed to start duplicate scan.');
  }
}

async function setStatus(p, status) {
  await posts.update(workspaceId.value, getPostFeedId(p), p.id, { status });
}

async function togglePin(p) {
  await posts.update(workspaceId.value, getPostFeedId(p), p.id, { pinned: !p.pinned });
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

function thumbnailKey(post) {
  return `${post.id}:${post.thumbnail_url || ''}`;
}

function hasUsableThumbnail(post) {
  if (!post.thumbnail_url) return false;
  return !brokenThumbnails.value.has(thumbnailKey(post));
}

function markThumbnailBroken(post) {
  const next = new Set(brokenThumbnails.value);
  next.add(thumbnailKey(post));
  brokenThumbnails.value = next;
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

function statusInitial(status) {
  if (typeof status !== 'string' || !status.length) return '—';
  return status.charAt(0).toUpperCase();
}

const platformStyles = {
  youtube:   { icon: 'text-red-600',    label: 'text-red-700',    divider: 'bg-red-100',    placeholder: 'bg-red-50' },
  facebook:  { icon: 'text-blue-600',   label: 'text-blue-700',   divider: 'bg-blue-100',   placeholder: 'bg-blue-50' },
  instagram: { icon: 'text-pink-700',   label: 'text-pink-700',   divider: 'bg-pink-100',   placeholder: 'bg-pink-50' },
  twitter:   { icon: 'text-slate-800',  label: 'text-slate-700',  divider: 'bg-slate-200',  placeholder: 'bg-slate-100' },
  tiktok:    { icon: 'text-slate-900',  label: 'text-slate-700',  divider: 'bg-slate-200',  placeholder: 'bg-slate-100' },
  threads:   { icon: 'text-slate-900',  label: 'text-slate-700',  divider: 'bg-slate-200',  placeholder: 'bg-slate-100' },
  rss:       { icon: 'text-orange-600', label: 'text-orange-700', divider: 'bg-orange-100', placeholder: 'bg-orange-50' },
};

function platformIconClass(type) { return platformStyles[type]?.icon ?? 'text-slate-500'; }
function platformLabelClass(type) { return platformStyles[type]?.label ?? 'text-slate-700'; }
function platformDividerClass(type) { return platformStyles[type]?.divider ?? 'bg-slate-200'; }

function sectionGridClass() {
  if (viewMode.value === 'compact') return 'grid-cols-1';
  return 'curate-posts-grid';
}
function platformPlaceholderBg(type) { return platformStyles[type]?.placeholder ?? 'bg-slate-100'; }

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
.feed-icon-youtube { color: rgb(220 38 38); }
.feed-icon-facebook { color: rgb(37 99 235); }
.feed-icon-instagram { color: rgb(190 24 93); }
.feed-icon-tiktok { color: rgb(15 23 42); }
.feed-icon-threads { color: rgb(15 23 42); }
.feed-icon-rss { color: rgb(234 88 12); }
.feed-icon-twitter { color: rgb(15 23 42); }

/* Fixed card width: do not stretch 1–2 posts to full row width */
.curate-posts-grid {
  grid-template-columns: repeat(auto-fill, minmax(220px, 280px));
  justify-content: start;
}

.curate-toolbar {
  display: grid;
  gap: 0.75rem;
  padding: 0.85rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.95rem;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.curate-toolbar__filters {
  display: grid;
  gap: 0.6rem;
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.curate-toolbar__field {
  display: grid;
  gap: 0.3rem;
  min-width: 0;
}

.curate-toolbar__label {
  font-size: 0.67rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}

.curate-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.curate-view-toggle {
  display: inline-flex;
  align-items: center;
  padding: 0.08rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.62rem;
  background: #fff;
}

.curate-view-toggle__btn {
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 0.75rem;
  line-height: 1rem;
  font-weight: 700;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  cursor: pointer;
}

.curate-view-toggle__btn--active {
  background: #e2e8f0;
  color: #0f172a;
}

.curate-toolbar__btn {
  width: auto;
}

.curate-toolbar__meta {
  font-size: 0.68rem;
  color: #94a3b8;
}

.curate-bulk-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.6rem 0.85rem;
  border: 1px solid #bfdbfe;
  border-radius: 0.75rem;
  background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
}

.curate-bulk-bar__info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.curate-bulk-bar__actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.curate-status-strip {
  display: grid;
  gap: 0.55rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.curate-status-card {
  overflow: hidden;
}

.curate-status-card__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.55rem 0.7rem;
}

.curate-status-card__value {
  font-size: 0.95rem;
  line-height: 1;
  font-weight: 700;
  color: #0f172a;
}

@media (min-width: 900px) {
  .curate-toolbar {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
  }

  .curate-toolbar__filters {
    grid-template-columns: repeat(2, minmax(170px, 220px));
  }

  .curate-toolbar__meta {
    grid-column: 1 / -1;
    justify-self: end;
  }

  .curate-status-strip {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

/* ── Checkbox overlay on thumbnail ── */
.post-checkbox-overlay {
  position: absolute;
  top: 0.5rem;
  left: 0.5rem;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.post-checkbox-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}

.post-checkbox-mark {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 0.375rem;
  border: 2px solid rgba(255, 255, 255, 0.9);
  background: rgba(0, 0, 0, 0.35);
  backdrop-filter: blur(2px);
  transition: background 0.15s, border-color 0.15s;
  flex-shrink: 0;
}

.post-checkbox-mark::after {
  content: '';
  display: block;
  width: 0.35rem;
  height: 0.6rem;
  border: 2px solid transparent;
  border-top: none;
  border-left: none;
  transform: rotate(45deg) translateY(-1px);
  transition: border-color 0.1s;
}

.post-checkbox-mark--checked {
  background: #3b82f6;
  border-color: #3b82f6;
}

.post-checkbox-mark--checked::after {
  border-color: #fff;
}


/* Toolbar select-all label */
.curate-select-all-label {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.55rem;
  border: 1px solid #dbe3ef;
  border-radius: 0.5rem;
  background: #fff;
  cursor: pointer;
  user-select: none;
}

.curate-select-all-label .post-checkbox-mark {
  width: 1rem;
  height: 1rem;
  border: 2px solid #94a3b8;
  background: #fff;
  backdrop-filter: none;
  border-radius: 0.25rem;
}

.curate-select-all-label .post-checkbox-mark::after {
  width: 0.28rem;
  height: 0.5rem;
}

.curate-select-all-label .post-checkbox-mark--checked {
  background: #3b82f6;
  border-color: #3b82f6;
}

.curate-select-all-text {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
  line-height: 1;
}

.post-action-row {
  display: grid;
  grid-template-columns: repeat(3, auto);
  justify-content: start;
  gap: 0.38rem;
  padding-top: 0.45rem;
  border-top: 1px solid #f1f5f9;
}

.curate-post-card--compact :deep(.app-card-body) {
  display: grid;
  grid-template-columns: 140px minmax(0, 1fr);
  gap: 0;
}

.curate-post-media--compact {
  aspect-ratio: auto;
  height: 100%;
  min-height: 100%;
}

.curate-post-body--compact {
  gap: 0.5rem;
  padding: 0.6rem 0.7rem;
}

.post-action-btn {
  transition: transform 0.12s ease;
}

.post-action-btn:hover {
  transform: translateY(-1px);
}

.post-action-btn--icon {
  @apply !w-auto !px-2.5 !py-1.5;
  min-width: 2.05rem;
}

.post-action-active {
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
}

@media (max-width: 639px) {
  .curate-post-card--compact :deep(.app-card-body) {
    grid-template-columns: 1fr;
  }

  .curate-post-media--compact {
    aspect-ratio: 16 / 9;
    min-height: auto;
  }
}
</style>
