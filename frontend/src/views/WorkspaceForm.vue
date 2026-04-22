<template>
  <div class="space-y-4 max-w-3xl">
    <nav class="page-breadcrumb">
      <router-link to="/workspaces">Workspaces</router-link>
    </nav>
    <div>
      <h1 class="page-title">{{ isEdit ? 'Edit workspace' : 'New workspace' }}</h1>
      <p class="page-kicker">Name the workspace, then continue through Feed → Curate → Publish.</p>
    </div>
    <WorkspaceWizardStepper current="workspace" />
    <form @submit.prevent="submit" class="surface-card p-5 space-y-4">
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
      <div class="flex items-center gap-2">
        <button type="submit" class="btn-primary !w-auto !px-4" :disabled="saving">
          {{ saving ? 'Saving…' : (isEdit ? 'Save and go to Feed' : 'Create and add Feed') }}
        </button>
        <router-link to="/workspaces" class="btn-secondary !w-auto">Cancel</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import WorkspaceWizardStepper from '../components/WorkspaceWizardStepper.vue';

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

