<template>
  <div class="space-y-4">
    <AppPageHeader
      title="Sync Operations"
      subtitle="Monitor the background scheduler, view sync history, and manage broken credentials."
      :breadcrumb="['Admin', 'Sync Operations']"
    />

    <!-- Scheduler status card -->
    <AppCard class="p-5">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-6">
          <div>
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-400 mb-0.5">Last run</div>
            <AppLoader v-if="syncOps.loading.status" size="sm" label="Loading…" />
            <div v-else class="text-sm-pro font-medium text-slate-700">
              {{ syncOps.status?.last_run_at ? formatRelative(syncOps.status.last_run_at) : 'No runs yet' }}
            </div>
          </div>
          <div>
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-400 mb-0.5">Next scheduled</div>
            <div class="text-sm-pro font-medium text-slate-700">
              {{ syncOps.status?.next_run_at ? formatRelative(syncOps.status.next_run_at) : '—' }}
            </div>
          </div>
          <div>
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-400 mb-0.5">Total feeds</div>
            <div class="text-sm-pro font-medium text-slate-700">{{ syncOps.status?.total_feeds ?? '—' }}</div>
          </div>
          <div>
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-400 mb-0.5">Logs today</div>
            <div class="text-sm-pro font-medium text-slate-700">{{ syncOps.status?.logs_today ?? '—' }}</div>
          </div>
          <div v-if="syncOps.status?.broken_count > 0">
            <div class="text-2xs font-semibold uppercase tracking-wide text-rose-400 mb-0.5">Broken</div>
            <div class="text-sm-pro font-medium text-rose-600">{{ syncOps.status.broken_count }}</div>
          </div>
        </div>
        <AppButton
          size="sm"
          class="!w-auto !py-1.5 !px-4 text-sm-pro inline-flex items-center gap-2"
          :disabled="syncOps.loading.runAll"
          @click="doRunAll"
        >
          {{ syncOps.loading.runAll ? 'Starting…' : 'Run All Feeds Now' }}
        </AppButton>
      </div>
    </AppCard>

    <!-- Broken credentials -->
    <div v-if="syncOps.brokenCredentials.length > 0" class="space-y-3">
      <h2 class="text-sm-pro font-semibold text-rose-700 flex items-center gap-2">
        <AppIcon name="alert" class="w-4 h-4" />
        Broken Credentials
        <span class="ml-1 text-2xs bg-rose-100 text-rose-700 rounded-full px-2 py-0.5">{{ syncOps.brokenCredentials.length }}</span>
      </h2>
      <AppLoader v-if="syncOps.loading.broken" size="sm" label="Loading…" />
      <div v-else class="table-shell border border-rose-200/70">
        <AppTable :columns="brokenColumns" :rows="syncOps.brokenCredentials" row-key="id">
          <template #cell-user="{ row: cred }"><div class="font-medium text-slate-800 text-sm-pro">{{ cred.user?.name || '—' }}</div><div class="text-2xs text-slate-500">{{ cred.user?.email || '' }}</div></template>
          <template #cell-provider="{ row: cred }"><span class="text-sm-pro text-slate-700 capitalize">{{ cred.provider }}</span></template>
          <template #cell-account="{ row: cred }"><span class="text-sm-pro text-slate-600">{{ cred.account_label || cred.account_id || '—' }}</span></template>
          <template #cell-status="{ row: cred }"><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium bg-rose-50 text-rose-700 border border-rose-200"><span class="w-1.5 h-1.5 rounded-full bg-rose-400" />{{ cred.status }}</span></template>
          <template #cell-updated_at="{ row: cred }"><span class="text-sm-pro text-slate-500">{{ formatDate(cred.updated_at) }}</span></template>
          <template #cell-actions="{ row: cred }"><AppButton variant="ghost" class="inline-flex items-center gap-1" :disabled="syncOps.actionLoading[cred.id]" @click="doResync(cred)">Resync</AppButton></template>
        </AppTable>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <AppSelect v-model="logFilters.provider" wrapper-class="!w-auto shrink-0" select-class="!w-auto" :show-placeholder="false" @update:modelValue="applyFilters">
        <option value="">All providers</option>
        <option value="youtube">YouTube</option>
        <option value="facebook">Facebook</option>
        <option value="instagram">Instagram</option>
        <option value="twitter">Twitter / X</option>
        <option value="tiktok">TikTok</option>
        <option value="threads">Threads</option>
        <option value="rss">RSS</option>
      </AppSelect>
      <AppSelect v-model="logFilters.status" wrapper-class="!w-auto shrink-0" select-class="!w-auto" :show-placeholder="false" @update:modelValue="applyFilters">
        <option value="">All statuses</option>
        <option value="success">Success</option>
        <option value="error">Error</option>
        <option value="disconnected">Disconnected</option>
        <option value="skipped">Skipped</option>
      </AppSelect>
      <AppSelect v-model="logFilters.triggered_by" wrapper-class="!w-auto shrink-0" select-class="!w-auto" :show-placeholder="false" @update:modelValue="applyFilters">
        <option value="">All sources</option>
        <option value="scheduler">Scheduler</option>
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </AppSelect>
    </div>

    <!-- Loading -->
    <AppLoader v-if="syncOps.loading.logs" label="Loading logs…" />

    <!-- Empty -->
    <AppCard v-else-if="!syncOps.logs.length" class="p-8 text-center text-sm-pro text-slate-500">
      No sync logs yet. Logs will appear here after feeds are synced.
    </AppCard>

    <!-- Table -->
    <div v-else class="table-shell">
      <AppTable :columns="logColumns" :rows="syncOps.logs" row-key="id">
        <template #cell-created_at="{ row: log }"><span class="text-2xs text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</span></template>
        <template #cell-user="{ row: log }"><span class="text-sm-pro text-slate-600">{{ log.user?.name || '—' }}</span></template>
        <template #cell-feed_name="{ row: log }"><span class="text-sm-pro text-slate-700 max-w-[10rem] truncate" :title="log.feed_name">{{ log.feed_name || '—' }}</span></template>
        <template #cell-provider="{ row: log }"><span class="text-sm-pro text-slate-600 capitalize">{{ log.provider || '—' }}</span></template>
        <template #cell-status="{ row: log }"><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium border cursor-default" :class="statusBadge(log.status)" :title="log.error_message || undefined"><span class="w-1.5 h-1.5 rounded-full" :class="statusDot(log.status)" />{{ log.status }}</span></template>
        <template #cell-posts_synced="{ row: log }"><span v-if="log.posts_synced > 0" class="text-emerald-700 font-medium">+{{ log.posts_synced }}</span><span v-else class="text-slate-400">0</span></template>
        <template #cell-duration_ms="{ row: log }"><span class="text-2xs text-slate-500">{{ formatDuration(log.duration_ms) }}</span></template>
        <template #cell-triggered_by="{ row: log }"><span class="inline-flex items-center rounded px-1.5 py-0.5 text-2xs font-medium" :class="sourceBadge(log.triggered_by)">{{ log.triggered_by }}</span></template>
        <template #cell-actions="{ row: log }"><AppButton v-if="log.feed_id" variant="ghost" class="inline-flex items-center gap-1" :disabled="syncOps.actionLoading[`feed_${log.feed_id}`]" @click="doSyncFeed(log)">Sync now</AppButton></template>
      </AppTable>
    </div>

      <!-- Pagination -->
      <div v-if="syncOps.logsPagination && syncOps.logsPagination.last_page > 1" class="flex items-center justify-between pt-1">
        <div class="text-2xs text-slate-500">
          Showing {{ syncOps.logsPagination.from }}–{{ syncOps.logsPagination.to }} of {{ syncOps.logsPagination.total }}
        </div>
        <div class="flex items-center gap-1">
          <AppButton
            variant="ghost"
            class="inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="syncOps.logsPage === 1"
            @click="goToPage(syncOps.logsPage - 1)"
          >
            <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
            Prev
          </AppButton>
          <span class="text-2xs text-slate-600 px-2">Page {{ syncOps.logsPage }} of {{ syncOps.logsPagination.last_page }}</span>
          <AppButton
            variant="ghost"
            class="inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="syncOps.logsPage === syncOps.logsPagination.last_page"
            @click="goToPage(syncOps.logsPage + 1)"
          >
            Next
            <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
          </AppButton>
        </div>
      </div>

    <!-- Toast -->
    <div
      v-if="toast"
      class="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-xl border shadow-panel px-4 py-3 text-sm-pro font-medium transition-all"
      :class="toast.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useSyncOpsStore } from '../../stores/syncOps';
import { AppButton, AppCard, AppIcon, AppLoader, AppSelect, AppTable } from '../../components/ui';
import { AppPageHeader } from '../../components/layout/index.js';

const syncOps = useSyncOpsStore();
const toast = ref(null);
const logFilters = reactive({ provider: '', status: '', triggered_by: '' });
const brokenColumns = [
  { key: 'user', label: 'User' },
  { key: 'provider', label: 'Provider' },
  { key: 'account', label: 'Account' },
  { key: 'status', label: 'Status' },
  { key: 'updated_at', label: 'Last updated' },
  { key: 'actions', label: 'Actions' },
];
const logColumns = [
  { key: 'created_at', label: 'Time' },
  { key: 'user', label: 'User' },
  { key: 'feed_name', label: 'Feed' },
  { key: 'provider', label: 'Provider' },
  { key: 'status', label: 'Status' },
  { key: 'posts_synced', label: 'Posts' },
  { key: 'duration_ms', label: 'Duration' },
  { key: 'triggered_by', label: 'Source' },
  { key: 'actions', label: 'Actions' },
];

onMounted(() => {
  syncOps.fetchStatus();
  syncOps.fetchLogs(1);
  syncOps.fetchBrokenCredentials();
});

function applyFilters() {
  syncOps.logFilters = { ...logFilters };
  syncOps.fetchLogs(1);
}

function goToPage(page) {
  syncOps.fetchLogs(page);
}

async function doRunAll() {
  const result = await syncOps.runAll();
  if (result.success) {
    showToast('Sync started — logs will appear shortly.');
    setTimeout(() => {
      syncOps.fetchStatus();
      syncOps.fetchLogs(1);
    }, 3000);
  } else {
    showToast(result.message, 'error');
  }
}

async function doResync(cred) {
  const result = await syncOps.resyncCredential(cred.id);
  if (result.success) {
    showToast(`Resync complete — credential is now "${result.data.status}".`);
    syncOps.fetchStatus();
    syncOps.fetchLogs(1);
  } else {
    showToast(result.message, 'error');
  }
}

async function doSyncFeed(log) {
  const result = await syncOps.syncFeed(log.feed_id);
  if (result.success) {
    showToast(`Synced "${log.feed_name}".`);
    syncOps.fetchLogs(syncOps.logsPage);
  } else {
    showToast(result.message, 'error');
  }
}

function showToast(message, type = 'success') {
  toast.value = { message, type };
  setTimeout(() => { toast.value = null; }, 3500);
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatRelative(dateStr) {
  if (!dateStr) return '—';
  const diff = Date.now() - new Date(dateStr).getTime();
  if (diff < 0) {
    const abs = Math.abs(diff);
    if (abs < 60000) return 'in < 1 min';
    if (abs < 3600000) return `in ${Math.round(abs / 60000)} min`;
    return `in ${Math.round(abs / 3600000)}h`;
  }
  if (diff < 60000) return '< 1 min ago';
  if (diff < 3600000) return `${Math.round(diff / 60000)} min ago`;
  if (diff < 86400000) return `${Math.round(diff / 3600000)}h ago`;
  return formatDate(dateStr);
}

function formatDuration(ms) {
  if (ms == null) return '—';
  if (ms < 1000) return `${ms}ms`;
  return `${(ms / 1000).toFixed(1)}s`;
}

function statusBadge(status) {
  return {
    success:      'bg-emerald-50 text-emerald-700 border-emerald-200',
    error:        'bg-amber-50 text-amber-700 border-amber-200',
    disconnected: 'bg-rose-50 text-rose-700 border-rose-200',
    skipped:      'bg-slate-50 text-slate-600 border-slate-200',
  }[status] ?? 'bg-slate-50 text-slate-600 border-slate-200';
}

function statusDot(status) {
  return {
    success:      'bg-emerald-400',
    error:        'bg-amber-400',
    disconnected: 'bg-rose-400',
    skipped:      'bg-slate-400',
  }[status] ?? 'bg-slate-400';
}

function sourceBadge(source) {
  return {
    scheduler: 'bg-blue-50 text-blue-700',
    user:      'bg-sky-50 text-sky-700',
    admin:     'bg-purple-50 text-purple-700',
  }[source] ?? 'bg-slate-50 text-slate-600';
}
</script>
