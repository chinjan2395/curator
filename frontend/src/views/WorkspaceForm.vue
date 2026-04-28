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
      <router-link to="/workspaces" class="btn-secondary !w-auto" title="Go back">←</router-link>
      <button type="submit" form="workspace-form" class="btn-primary !w-auto !px-3 !py-2" :disabled="saving" title="Continue to next step">
        {{ saving ? '⏳' : '→' }}
      </button>
    </template>

    <form id="workspace-form" @submit.prevent="submit" class="surface-card p-5 space-y-4 max-w-2xl">
      <div>
        <label class="label-pro">Name</label>
        <input
          v-model="name"
          type="text"
          class="input-pro"
          placeholder="Workspace name"
          required
        />
      </div>
      <div v-if="workspaces.error" class="text-2xs text-red-600">{{ workspaces.error }}</div>
    </form>

    <template #footer>
      <router-link to="/workspaces" class="btn-secondary !w-auto">Back</router-link>
      <button type="submit" form="workspace-form" class="btn-primary !w-auto !px-3 !py-2" :disabled="saving">
        {{ saving ? 'Saving…' : 'Next' }}
      </button>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import WizardPageLayout from '../components/WizardPageLayout.vue';

const route = useRoute();
const router = useRouter();
const workspaces = useWorkspacesStore();

const name = ref('');
const saving = ref(false);

const isEdit = computed(() => !!route.params.id);

onMounted(async () => {
  if (isEdit.value) {
    await workspaces.fetchAll();
    const w = workspaces.list.find((x) => x.id === Number(route.params.id));
    if (w) name.value = w.name;
  }
});

async function submit() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await workspaces.update(Number(route.params.id), name.value);
      await router.push(`/workspaces/${route.params.id}/feeds`);
    } else {
      const created = await workspaces.create(name.value);
      await router.push(`/workspaces/${created.id}/feeds/new`);
    }
  } catch {
    // error set in store
  } finally {
    saving.value = false;
  }
}
</script>

