<template>
  <div class="space-y-4">
    <nav class="page-breadcrumb">
      <span>Admin</span>
      <span class="mx-1 text-slate-300">/</span>
      <span>Activity Logs</span>
    </nav>

    <div class="flex items-start justify-between">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
          </svg>
          Activity Logs
        </h1>
        <p class="page-kicker">Full audit trail of every user action across the platform.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <input
        v-model="filters.user_id"
        type="number"
        placeholder="User ID"
        class="input-pro !w-28"
        @input="onFilterChange"
      />
      <select v-model="filters.action" class="input-pro !w-auto" @change="onFilterChange">
        <option value="">All actions</option>
        <option value="auth">Auth</option>
        <option value="workspace">Workspace</option>
        <option value="feed">Feed</option>
        <option value="post">Post</option>
        <option value="credential">Credential</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="activityLog.adminLoading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading logs…
    </div>

    <!-- Empty -->
    <div v-else-if="!activityLog.adminLogs.length" class="surface-card p-8 text-center text-sm-pro text-slate-500">
      No activity logs found.
    </div>

    <!-- Table -->
    <div v-else class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">Time</th>
            <th class="table-th">User</th>
            <th class="table-th">Action</th>
            <th class="table-th">Description</th>
            <th class="table-th">Entity</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="log in activityLog.adminLogs" :key="log.id" class="table-tr">
            <td class="table-td text-2xs text-slate-500 whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
            <td class="table-td">
              <div class="font-medium text-slate-800 text-sm-pro">{{ log.user?.name || '—' }}</div>
              <div class="text-2xs text-slate-500">{{ log.user?.email || '' }}</div>
            </td>
            <td class="table-td">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-2xs font-medium border"
                :class="actionBadgeClass(log.action)"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="actionDotClass(log.action)" />
                {{ actionLabel(log.action) }}
              </span>
            </td>
            <td class="table-td text-sm-pro text-slate-700 max-w-[16rem] truncate" :title="log.description">
              {{ log.description }}
            </td>
            <td class="table-td text-sm-pro text-slate-500 max-w-[10rem] truncate" :title="log.entity_name || undefined">
              {{ log.entity_name || '—' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="activityLog.adminMeta && activityLog.adminMeta.last_page > 1" class="flex items-center justify-between pt-1">
      <div class="text-2xs text-slate-500">
        Showing {{ activityLog.adminMeta.from }}–{{ activityLog.adminMeta.to }} of {{ activityLog.adminMeta.total }}
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage <= 1"
          @click="goToPage(currentPage - 1)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
          Prev
        </button>
        <span class="text-2xs text-slate-600 px-2">Page {{ currentPage }} of {{ activityLog.adminMeta.last_page }}</span>
        <button
          type="button"
          class="action-link action-link--premium inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage >= activityLog.adminMeta.last_page"
          @click="goToPage(currentPage + 1)"
        >
          Next
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useActivityLogStore } from '../../stores/activityLog'

const activityLog = useActivityLogStore()
const filters = reactive({ user_id: '', action: '' })
const currentPage = ref(1)

function buildParams() {
  return {
    page: currentPage.value,
    ...(filters.user_id ? { user_id: filters.user_id } : {}),
    ...(filters.action  ? { action: filters.action }   : {}),
  }
}

function load() {
  activityLog.fetchAdminLogs(buildParams())
}

function onFilterChange() {
  currentPage.value = 1
  load()
}

function goToPage(page) {
  currentPage.value = page
  load()
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function actionLabel(action) {
  const labels = {
    'auth.login':               'Login',
    'auth.logout':              'Logout',
    'workspace.created':        'Workspace created',
    'workspace.updated':        'Workspace updated',
    'workspace.deleted':        'Workspace deleted',
    'feed.created':             'Feed created',
    'feed.updated':             'Feed updated',
    'feed.deleted':             'Feed deleted',
    'feed.synced':              'Feed synced',
    'feed.sync_all':            'Sync all',
    'feed.resync_credential':   'Re-synced',
    'post.approved':            'Post approved',
    'post.rejected':            'Post rejected',
    'post.pinned':              'Post pinned',
    'post.unpinned':            'Post unpinned',
    'post.deleted':             'Post deleted',
    'credential.connected':     'Connected',
    'credential.disconnected':  'Disconnected',
  }
  return labels[action] ?? action
}

function actionBadgeClass(action) {
  if (action?.startsWith('auth'))       return 'bg-violet-50 text-violet-700 border-violet-200'
  if (action?.startsWith('workspace')) return 'bg-indigo-50 text-indigo-700 border-indigo-200'
  if (action?.startsWith('feed'))      return 'bg-sky-50 text-sky-700 border-sky-200'
  if (action?.startsWith('post'))      return 'bg-amber-50 text-amber-700 border-amber-200'
  if (action?.startsWith('credential'))return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  return 'bg-slate-50 text-slate-600 border-slate-200'
}

function actionDotClass(action) {
  if (action?.startsWith('auth'))       return 'bg-violet-400'
  if (action?.startsWith('workspace')) return 'bg-indigo-400'
  if (action?.startsWith('feed'))      return 'bg-sky-400'
  if (action?.startsWith('post'))      return 'bg-amber-400'
  if (action?.startsWith('credential'))return 'bg-emerald-400'
  return 'bg-slate-400'
}

onMounted(load)
</script>

<style scoped>
.action-link--premium {
  border-color: rgba(203, 213, 225, 0.95);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}
</style>
