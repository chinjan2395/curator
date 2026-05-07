<template>
  <div class="space-y-4">
    <AppPageHeader
      title="Activity Logs"
      subtitle="Full audit trail of every user action across the platform."
      :breadcrumb="['Admin', 'Activity Logs']"
    />

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <AppInput
        v-model="filters.user_id"
        type="number"
        placeholder="User ID"
        input-class="!w-28"
        @input="onFilterChange"
      />
      <AppSelect v-model="filters.action" select-class="!w-auto" :show-placeholder="false" @update:modelValue="onFilterChange">
        <option value="">All actions</option>
        <option value="auth">Auth</option>
        <option value="workspace">Workspace</option>
        <option value="feed">Feed</option>
        <option value="post">Post</option>
        <option value="credential">Credential</option>
      </AppSelect>
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
      <AppTable :columns="columns" :rows="activityLog.adminLogs" row-key="id">
        <template #cell-created_at="{ row }">
          <span class="text-2xs text-slate-500 whitespace-nowrap">{{ formatDate(row.created_at) }}</span>
        </template>
        <template #cell-user="{ row }">
          <div class="font-medium text-slate-800 text-sm-pro">{{ row.user?.name || '—' }}</div>
          <div class="text-2xs text-slate-500">{{ row.user?.email || '' }}</div>
        </template>
        <template #cell-action="{ row }">
          <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-2xs font-medium border" :class="actionBadgeClass(row.action)">
            <span class="w-1.5 h-1.5 rounded-full" :class="actionDotClass(row.action)" />
            {{ actionLabel(row.action) }}
          </span>
        </template>
        <template #cell-description="{ row }">
          <span class="text-sm-pro text-slate-700 max-w-[16rem] truncate" :title="row.description">{{ row.description }}</span>
        </template>
        <template #cell-entity_name="{ row }">
          <span class="text-sm-pro text-slate-500 max-w-[10rem] truncate" :title="row.entity_name || undefined">{{ row.entity_name || '—' }}</span>
        </template>
      </AppTable>
    </div>

    <!-- Pagination -->
    <div v-if="activityLog.adminMeta && activityLog.adminMeta.last_page > 1" class="flex items-center justify-between pt-1">
      <div class="text-2xs text-slate-500">
        Showing {{ activityLog.adminMeta.from }}–{{ activityLog.adminMeta.to }} of {{ activityLog.adminMeta.total }}
      </div>
      <div class="flex items-center gap-1">
        <AppButton
          variant="ghost"
          class="inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage <= 1"
          @click="goToPage(currentPage - 1)"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
          Prev
        </AppButton>
        <span class="text-2xs text-slate-600 px-2">Page {{ currentPage }} of {{ activityLog.adminMeta.last_page }}</span>
        <AppButton
          variant="ghost"
          class="inline-flex items-center gap-1 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="currentPage >= activityLog.adminMeta.last_page"
          @click="goToPage(currentPage + 1)"
        >
          Next
          <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
        </AppButton>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useActivityLogStore } from '../../stores/activityLog'
import { AppButton, AppInput, AppSelect, AppTable } from '../../components/ui'
import { AppPageHeader } from '../../components/layout/index.js'

const activityLog = useActivityLogStore()
const filters = reactive({ user_id: '', action: '' })
const currentPage = ref(1)
const columns = [
  { key: 'created_at', label: 'Time' },
  { key: 'user', label: 'User' },
  { key: 'action', label: 'Action' },
  { key: 'description', label: 'Description' },
  { key: 'entity_name', label: 'Entity' },
]

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

