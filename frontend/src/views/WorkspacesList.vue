<template>
  <AppSection>
    <AppPageHeader
      title="Workspaces"
      subtitle="Manage workspace setup from Feed to Curate to Publish."
      icon="folder"
    >
      <template #actions>
        <router-link to="/workspaces/new">
          <AppButton size="sm" class="!w-auto !py-1.5 !px-3 text-sm-pro">+ New workspace</AppButton>
        </router-link>
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
      <router-link to="/workspaces/new">
        <AppButton size="sm" class="!w-auto !py-2 !px-5 text-sm-pro inline-flex">Create your first workspace →</AppButton>
      </router-link>
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
          <router-link :to="`/workspaces/${row.id}/feeds`">
            <AppButton variant="secondary" size="sm" class="!w-auto inline-flex items-center gap-1.5 text-sm-pro">Feeds</AppButton>
          </router-link>
          <router-link :to="`/workspaces/${row.id}/edit`">
            <AppButton variant="secondary" size="sm" class="!w-auto inline-flex items-center gap-1.5 text-sm-pro">Edit</AppButton>
          </router-link>
          <AppButton variant="danger" size="sm" @click="confirmDelete(row)">
            Delete
          </AppButton>
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

