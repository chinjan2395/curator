<template>
  <WizardPageLayout
    current="workspace"
    :title="isEdit ? 'Edit workspace' : 'New workspace'"
    description="Name the workspace, then continue through Feed → Curate → Publish."
    :workspaceId="route.params.id"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
    </template>

    <template #actions>
      <AppButton to="/workspaces" variant="secondary" size="sm" title="Go back">←</AppButton>
      <AppButton type="submit" form="workspace-form" variant="primary" size="sm" :loading="saving">
        {{ saving ? '' : '→' }}
      </AppButton>
    </template>

    <AppCard padding="md" class="max-w-2xl">
      <form id="workspace-form" @submit.prevent="submit" class="space-y-4">
        <AppFormField label="Name" id="workspace-name" :required="true">
          <AppInput
            id="workspace-name"
            v-model="name"
            type="text"
            placeholder="Workspace name"
          />
        </AppFormField>

        <AppAlert v-if="workspaces.error" variant="danger">{{ workspaces.error }}</AppAlert>
      </form>
    </AppCard>

    <template #footer>
      <AppButton to="/workspaces" variant="secondary" size="sm">Back</AppButton>
      <AppButton type="submit" form="workspace-form" variant="primary" size="sm" :loading="saving">
        {{ saving ? '' : 'Next' }}
      </AppButton>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkspacesStore } from '../stores/workspaces'
import WizardPageLayout from '../components/WizardPageLayout.vue'
import { AppAlert, AppButton, AppCard, AppFormField, AppInput } from '../components/ui/index.js'

const route = useRoute()
const router = useRouter()
const workspaces = useWorkspacesStore()

const name = ref('')
const saving = ref(false)

const isEdit = computed(() => !!route.params.id)

onMounted(async () => {
  if (isEdit.value) {
    await workspaces.fetchAll()
    const w = workspaces.list.find((x) => x.id === Number(route.params.id))
    if (w) name.value = w.name
  }
})

async function submit() {
  saving.value = true
  try {
    if (isEdit.value) {
      await workspaces.update(Number(route.params.id), name.value)
      await router.push(`/workspaces/${route.params.id}/feeds`)
    } else {
      const created = await workspaces.create(name.value)
      await router.push(`/workspaces/${created.id}/feeds/new`)
    }
  } catch {
    // error set in store
  } finally {
    saving.value = false
  }
}
</script>
