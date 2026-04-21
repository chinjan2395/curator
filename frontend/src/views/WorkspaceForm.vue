<template>
  <div class="space-y-4 max-w-3xl">
    <div class="surface-card p-4 md:p-5 wizard-hero">
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit workspace' : 'New workspace' }}</h1>
        <p class="page-kicker">Workspace settings for organizing feeds and publishing.</p>
      </div>
      <div class="wizard-stepper mt-4">
        <div class="wizard-step wizard-step--active">
          <span class="wizard-step__index">1</span>
          <div>
            <div class="wizard-step__label">Organize / Edit</div>
            <div class="wizard-step__meta">Name the workspace</div>
          </div>
        </div>
        <div class="wizard-step" :class="isEdit ? 'wizard-step--ready' : ''">
          <span class="wizard-step__index">2</span>
          <div>
            <div class="wizard-step__label">Curate</div>
            <div class="wizard-step__meta">Review feed posts</div>
          </div>
        </div>
        <div class="wizard-step">
          <span class="wizard-step__index">3</span>
          <div>
            <div class="wizard-step__label">Publish</div>
            <div class="wizard-step__meta">Configure embed and publish</div>
          </div>
        </div>
      </div>
    </div>
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
          {{ saving ? 'Saving…' : (isEdit ? 'Save and continue' : 'Create and continue') }}
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
      await router.push(`/workspaces/${route.params.id}/curate`);
    } else {
      const created = await workspaces.create(name.value);
      await router.push(`/workspaces/${created.id}/curate`);
    }
  } catch {
    // error set in store
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.wizard-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(56, 189, 248, 0.12), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(99, 102, 241, 0.14), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
}

.wizard-stepper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex: 1 1 180px;
  padding: 0.7rem 0.8rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  background: rgba(248, 250, 252, 0.82);
}

.wizard-step--active {
  border-color: rgba(99, 102, 241, 0.45);
  background: rgba(238, 242, 255, 0.72);
}

.wizard-step--done,
.wizard-step--ready {
  border-color: rgba(191, 219, 254, 0.8);
}

.wizard-step__index {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgb(51 65 85);
  background: rgba(226, 232, 240, 0.95);
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: rgb(67 56 202);
  background: rgba(199, 210, 254, 0.95);
}

.wizard-step__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(30 41 59);
}

.wizard-step__meta {
  font-size: 0.7rem;
  color: rgb(100 116 139);
}
</style>
