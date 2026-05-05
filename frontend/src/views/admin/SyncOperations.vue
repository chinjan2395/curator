<template>
  <div class="space-y-5">
    <nav class="page-breadcrumb">
      <span>Admin</span>
      <span class="mx-1 text-slate-300">/</span>
      <span>Sync Operations</span>
    </nav>

    <div class="flex items-start justify-between">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
          </svg>
          Sync Operations
        </h1>
        <p class="page-kicker">Monitor the background scheduler, view sync history, and manage broken credentials.</p>
      </div>
    </div>

    <!-- Scheduler status card -->
    <div class="surface-card p-5">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-6">
          <div>
            <div class="text-2xs font-semibold uppercase tracking-wide text-slate-400 mb-0.5">Last run</div>
            <div v-if="syncOps.loading.status" class="flex items-center gap-1.5 text-sm-pro text-slate-500">
              <span class="inline-block w-3 h-3 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
              Loading…
            </div>
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
        <button
          type="button"
          class="btn-primary !w-auto !py-1.5 !px-4 text-sm-pro inline-flex items-center gap-2"
          :disabled="syncOps.loading.runAll"
          @click="doRunAll"
        >
          <span v-if="syncOps.loading.runAll" class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin" />
          {{ syncOps.loading.runAll ? 'Starting…' : 'Run All Feeds Now' }}
        </button>
      </div>
    </div>

    <!-- Broken credentials -->
    <div v-if="syncOps.brokenCredentials.length > 0" class="surface-card p-5 border-rose-200/60 space-y-3">
      <h2 class="text-sm-pro font-semibold text-rose-700 border-b border-rose-100 pb-2 flex items-center gap-2">
        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
        </svg>
        Broken Credentials
        <span class="ml-1 text-2xs bg-rose-100 text-rose-700 rounded-full px-2 py-0.5">{{ syncOps.brokenCredentials.length }}</span>
      </h2>
      <div v-if="syncOps.loading.broken" class="flex items-center gap-2 text-sm-pro text-slate-500 py-1">
        <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
        Loading…
      </div>
      <div v-else class="table-shell">
        <table class="w-full text-left">
          <thead class="table-head">
            <tr>
              <th class="table-th">User</th>
              <th class="table-th">Provider</th>
              <th class="table-th">Account</th>
              <th class="table-th">Status</th>
              <th class="table-th">Last updated</th>
              <th class="table-th">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="cred in syncOps.brokenCredentials" :key="cred.id" class="table-tr">
              <td class="table-td">
                <div class="font-medium text-slate-800 text-sm-pro">{{ cred.user?.name || '—' }}</div>
                <div class="text-2xs text-slate-500">{{ cred.user?.email || '' }}</div>
              </td>
              <td class="table-td text-sm-pro text-slate-700 capitalize">{{ cred.provider }}</td>
              <td class="table-td text-sm-pro text-slate-600">{{ cred.account_label || cred.account_id || '—' }}</td>
              <td class="table-td">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                  <span class="w-1.5 h-1.5 rounded-full bg-rose-400" />
                  {{ cred.status }}
                </span>
              </td>
              <td class="table-td text-sm-pro text-slate-500">{{ formatDate(cred.updated_at) }}</td>
              <td class="table-td">
                <button
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1"
                  :disabled="syncOps.actionLoading[cred.id]"
                  @click="doResync(cred)"
                >
                  <span v-if="syncOps.actionLoading[cred.id]" class="inline-block w-3 h-3 border-2 border-indigo-300 border-t-indigo-600 rounded-full animate-spin" />
                  <svg v-else class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Z" clip-rule="evenodd" />
                  </svg>
                  Resync
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Sync logs -->
    <div class="surface-card p-5 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
        <h2 class="text-sm-pro font-semibold text-slate-700">Sync Logs</h2>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="logFilters.provider" class="input-pro !w-auto text-sm-pro" @change="applyFilters">
            <option value="">All providers</option>
            <option value="youtube">YouTube</option>
            <option value="facebook">Facebook</option>
            <option value="instagram">Instagram</option>
            <option value="twitter">Twitter / X</option>
            <option value="tiktok">TikTok</option>
            <option value="threads">Threads</option>
            <option value="rss">RSS</option>
          </select>
          <select v-model="logFilters.status" class="input-pro !w-auto text-sm-pro" @change="applyFilters">
            <option value="">All statuses</option>
            <option value="success">Success</option>
            <option value="error">Error</option>
            <option value="disconnected">Disconnected</option>
            <option value="skipped">Skipped</option>
          </select>
          <select v-model="logFilters.triggered_by" class="input-pro !w-auto text-sm-pro" @change="applyFilters">
            <option value="">All sources</option>
            <option value="scheduler">Scheduler</option>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>

      <div v-if="syncOps.loading.logs" class="flex items-center gap-2 text-sm-pro text-slate-500 py-2">
        <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
        Loading logs…
      </div>

      <div v-else-if="!syncOps.logs.length" class="py-6 text-center text-sm-pro text-slate-500">
        No sync logs yet. Logs will appear here after feeds are synced.
      </div>

      <div v-else class="table-shell">
        <table class="w-full text-left">
          <thead class="table-head">
            <tr>
              <th class="table-th">Time</th>
              <th class="table-th">User</th>
              <th class="table-th">Feed</th>
              <th class="table-th">Provider</th>
              <th class="table-th">Status</th>
              <th class="table-th">Posts</th>
              <th class="table-th">Duration</th>
              <th class="table-th">Source</th>
              <th class="table-th">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="log in syncOps.logs" :key="log.id" class="table-tr group">
              <td class="table-td text-2xs text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
              <td class="table-td text-sm-pro text-slate-600">{{ log.user?.name || '—' }}</td>
              <td class="table-td text-sm-pro text-slate-700 max-w-[10rem] truncate" :title="log.feed_name">{{ log.feed_name || '—' }}</td>
              <td class="table-td text-sm-pro text-slate-600 capitalize">{{ log.provider || '—' }}</td>
              <td class="table-td">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-medium border cursor-default"
                  :class="statusBadge(log.status)"
                  :title="log.error_message || undefined"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="statusDot(log.status)" />
                  {{ log.status }}
                </span>
              </td>
              <td class="table-td text-sm-pro text-slate-600">
                <span v-if="log.posts_synced > 0" class="text-emerald-700 font-medium">+{{ log.posts_synced }}</span>
                <span v-else class="text-slate-400">0</span>
              </td>
              <td class="table-td text-2xs text-slate-500">{{ formatDuration(log.duration_ms) }}</td>
              <td class="table-td">
                <span
                  class="inline-flex items-center rounded px-1.5 py-0.5 text-2xs font-medium"
                  :class="sourceBadge(log.triggered_by)"
                >{{ log.triggered_by }}</span>
              </td>
              <td class="table-td">
                <button
                  v-if="log.feed_id"
                  type="button"
                  class="action-link action-link--premium inline-flex items-center gap-1"
                  :disabled="syncOps.actionLoading[`feed_${log.feed_id}`]"
                  @click="doSyncFeed(log)"
                >
                  <span v-if="syncOps.actionLoading[`feed_${log.feed_id}`]" class="inline-block w-3 h-3 border-2 border-indigo-300 border-t-indigo-600 rounded-full animate-spin" />
                  <svg v-else class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Z" clip-rule="evenodd" />
                  </svg>
                  Sync now
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="syncOps.logsPagination && syncOps.logsPagination.last_page > 1" class="flex items-center justify-between pt-1">
        <div class="text-2xs text-slate-500">
          Showing {{ syncOps.logsPagination.from }}–{{ syncOps.logsPagination.to }} of {{ syncOps.logsPagination.total }}
        </div>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="syncOps.logsPage === 1"
            @click="goToPage(syncOps.logsPage - 1)"
          >
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
            Prev
          </button>
          <span class="text-2xs text-slate-600 px-2">Page {{ syncOps.logsPage }} of {{ syncOps.logsPagination.last_page }}</span>
          <button
            type="button"
            class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
            :disabled="syncOps.logsPage === syncOps.logsPagination.last_page"
            @click="goToPage(syncOps.logsPage + 1)"
          >
            Next
            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
          </button>
        </div>
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

const syncOps = useSyncOpsStore();
const toast = ref(null);
const logFilters = reactive({ provider: '', status: '', triggered_by: '' });

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
    scheduler: 'bg-indigo-50 text-indigo-700',
    user:      'bg-sky-50 text-sky-700',
    admin:     'bg-purple-50 text-purple-700',
  }[source] ?? 'bg-slate-50 text-slate-600';
}
</script>

<style scoped>
.action-link--premium {
  border-color: rgba(203, 213, 225, 0.95);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}
</style>
