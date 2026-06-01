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

    <AppLoader v-if="workspaces.loading" label="Loading…" />

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
      <template #cell-name="{ value }">
        <span class="font-medium text-slate-800">{{ value }}</span>
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
import { inject, onMounted } from 'vue'
import { useWorkspacesStore } from '../stores/workspaces'
import { AppAlert, AppEmptyState, AppButton, AppIcon, AppLoader, AppTable } from '../components/ui/index.js'
import { AppPageHeader, AppSection, AppStack } from '../components/layout/index.js'

const workspaces = useWorkspacesStore()
const { confirm } = inject('confirm')

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'actions', label: 'Actions', class: 'w-48' },
]

onMounted(async () => {
  await workspaces.fetchAll()
})

async function confirmDelete(w) {
  if (await confirm({ title: 'Delete workspace?', message: `Delete workspace "${w.name}"?`, confirmLabel: 'Delete' })) {
    await workspaces.remove(w.id)
  }
}
</script>
