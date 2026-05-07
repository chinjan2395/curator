<template>
  <AppSection>
    <AppPageHeader
      title="Workspaces"
      subtitle="Manage workspace setup from Feed to Curate to Publish."
      icon="folder"
    >
      <template #actions>
        <AppButton to="/workspaces/new" size="sm">+ New workspace</AppButton>
      </template>
    </AppPageHeader>

    <AppLoader v-if="workspaces.loading" label="Loading…" />

    <AppAlert v-else-if="workspaces.error" variant="danger">{{ workspaces.error }}</AppAlert>

    <AppEmptyState
      v-else-if="!workspaces.list.length"
      icon="🚀"
      title="Welcome to Curator"
      description="Get your first feed live in 4 steps."
    >
      <AppButton to="/workspaces/new" size="lg">Create your first workspace →</AppButton>
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
          <AppButton :to="`/workspaces/${row.id}/feeds`" variant="ghost" size="sm">Feeds</AppButton>
          <AppButton :to="`/workspaces/${row.id}/edit`" variant="ghost" size="sm">Edit</AppButton>
          <AppButton variant="ghost" tone="destructive" size="sm" @click="confirmDelete(row)">Delete</AppButton>
        </AppStack>
      </template>
    </AppTable>
  </AppSection>
</template>

<script setup>
import { onMounted } from 'vue'
import { useWorkspacesStore } from '../stores/workspaces'
import { AppAlert, AppEmptyState, AppButton, AppLoader, AppTable } from '../components/ui/index.js'
import { AppPageHeader, AppSection, AppStack } from '../components/layout/index.js'

const workspaces = useWorkspacesStore()

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'actions', label: 'Actions', class: 'w-48' },
]

onMounted(async () => {
  await workspaces.fetchAll()
})

async function confirmDelete(w) {
  if (window.confirm(`Delete workspace "${w.name}"?`)) {
    await workspaces.remove(w.id)
  }
}
</script>

