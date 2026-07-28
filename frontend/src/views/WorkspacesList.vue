<template>
  <AppSection>
    <AppPageHeader
      title="Workspaces"
      subtitle="Manage workspace setup from Feed to Curate to Publish."
      icon="folder"
      :breadcrumb="['Curator', 'Workspaces']"
    >
      <template #actions>
        <AppButton to="/workspaces/new" size="sm">
          <AppIcon name="add" class="w-3.5 h-3.5 shrink-0" />
          New workspace
        </AppButton>
      </template>
    </AppPageHeader>

    <div v-if="workspaces.loading && !workspaces.list.length" class="space-y-3">
      <AppCard class="p-4">
        <div class="space-y-3">
          <AppSkeleton variant="line" />
          <AppSkeleton variant="line" />
          <AppSkeleton variant="line" />
        </div>
      </AppCard>
    </div>

    <AppAlert v-else-if="workspaces.error" variant="danger">{{ workspaces.error }}</AppAlert>

    <AppEmptyState
      v-else-if="!workspaces.list.length"
      icon="rocket"
      title="Welcome to Curator"
      description="Get your first feed live in 4 steps."
    >
      <AppButton to="/workspaces/new" size="lg">
        <AppIcon name="add" class="w-4 h-4 shrink-0" />
        Create your first workspace
      </AppButton>
    </AppEmptyState>

    <AppTable
      v-else
      :columns="columns"
      :rows="workspaces.list"
    >
      <template #cell-name="{ value, row }">
        <span class="font-medium text-slate-800">{{ value }}</span>
        <span
          v-if="row.is_owner === false"
          class="ml-2 inline-flex items-center gap-1 rounded-full bg-violet-50 text-violet-700 border border-violet-200 px-2 py-0.5 text-2xs"
        >
          <AppIcon name="shield" class="w-3 h-3" />
          Not yours
        </span>
      </template>

      <template #cell-owner="{ row }">
        <span class="text-slate-500">{{ row.owner_name || row.owner_email || '—' }}</span>
      </template>

      <template #cell-actions="{ row }">
        <AppStack direction="horizontal" spacing="xs" align="center">
          <AppButton :to="`/workspaces/${row.id}/feeds`" variant="ghost" size="sm" class="gap-1.5">
            <AppIcon name="feeds" class="w-3.5 h-3.5 shrink-0" />
            Feeds
          </AppButton>
          <AppButton :to="`/workspaces/${row.id}/edit`" variant="ghost" size="sm" class="gap-1.5">
            <AppIcon name="edit" class="w-3.5 h-3.5 shrink-0" />
            Edit
          </AppButton>
          <AppButton variant="ghost" tone="destructive" size="sm" class="gap-1.5" @click="confirmDelete(row)">
            <AppIcon name="delete" class="w-3.5 h-3.5 shrink-0" />
            Delete
          </AppButton>
        </AppStack>
      </template>
    </AppTable>
  </AppSection>
</template>

<script setup>
import { computed, inject, onMounted } from 'vue'
import { useWorkspacesStore } from '../stores/workspaces'
import { useAuthStore } from '../stores/auth'
import { AppAlert, AppEmptyState, AppButton, AppCard, AppIcon, AppSkeleton, AppTable } from '../components/ui/index.js'
import { AppPageHeader, AppSection, AppStack } from '../components/layout/index.js'

const workspaces = useWorkspacesStore()
const auth = useAuthStore()
const { confirm } = inject('confirm')

const isSuperAdmin = computed(() => auth.user?.role === 'superadmin')

const columns = computed(() => [
  { key: 'name', label: 'Name' },
  ...(isSuperAdmin.value ? [{ key: 'owner', label: 'Owner' }] : []),
  { key: 'actions', label: 'Actions', class: 'w-48' },
])

onMounted(async () => {
  await workspaces.fetchAll()
})

async function confirmDelete(w) {
  if (await confirm({ title: 'Delete workspace?', message: `Delete workspace "${w.name}"?`, confirmLabel: 'Delete' })) {
    await workspaces.remove(w.id)
  }
}
</script>
