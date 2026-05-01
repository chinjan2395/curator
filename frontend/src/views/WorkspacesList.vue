<template>
  <div class="space-y-4">
    <nav class="page-breadcrumb">
      <span>Workspaces</span>
    </nav>
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="page-title flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
          </svg>
          Workspaces
        </h1>
        <p class="page-kicker">Manage workspace setup from Feed to Curate to Publish.</p>
      </div>
      <router-link to="/workspaces/new" class="btn-primary !w-auto !py-1.5 !px-3 text-sm-pro">
        + New workspace
      </router-link>
    </div>

    <div v-if="workspaces.loading" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>
    <div v-else-if="workspaces.error" class="text-sm-pro text-red-600">{{ workspaces.error }}</div>

    <!-- Onboarding empty state -->
    <div v-else-if="!workspaces.list.length" class="surface-card p-8 max-w-xl mx-auto text-center space-y-5">
      <div class="text-4xl">🚀</div>
      <div>
        <h2 class="text-lg-pro font-semibold text-slate-800 mb-1">Welcome to Curator</h2>
        <p class="text-sm-pro text-slate-500">Get your first feed live in 4 steps.</p>
      </div>
      <ol class="text-left space-y-3 text-sm-pro text-slate-700">
        <li class="flex items-start gap-3">
          <span class="onboard-step">1</span>
          <span><strong class="text-slate-800">Create a workspace</strong> — a container for one site or project.</span>
        </li>
        <li class="flex items-start gap-3">
          <span class="onboard-step">2</span>
          <span><strong class="text-slate-800">Add feeds</strong> — connect YouTube, Instagram, TikTok, RSS, and more.</span>
        </li>
        <li class="flex items-start gap-3">
          <span class="onboard-step">3</span>
          <span><strong class="text-slate-800">Curate</strong> — approve the posts you want shown publicly.</span>
        </li>
        <li class="flex items-start gap-3">
          <span class="onboard-step">4</span>
          <span><strong class="text-slate-800">Publish</strong> — copy the embed snippet and paste it into any website.</span>
        </li>
      </ol>
      <router-link to="/workspaces/new" class="btn-primary !w-auto !py-2 !px-5 text-sm-pro inline-flex">
        Create your first workspace →
      </router-link>
    </div>

    <div v-else class="table-shell">
      <table class="w-full text-left">
        <thead class="table-head">
          <tr>
            <th class="table-th">Name</th>
            <th class="table-th w-48">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="w in workspaces.list" :key="w.id" class="table-tr">
            <td class="table-td font-medium text-slate-800">{{ w.name }}</td>
            <td class="table-td">
              <div class="flex items-center gap-2">
                <router-link :to="`/workspaces/${w.id}/feeds`" class="action-link action-link--premium inline-flex items-center gap-1.5" title="Manage feeds">
                  <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
                  </svg>
                  Feeds
                </router-link>
                <!-- <router-link :to="`/workspaces/${w.id}/curate`" class="action-link" title="Curate posts">Curate</router-link> -->
                <!-- <router-link :to="`/workspaces/${w.id}/publish`" class="action-link" title="Publish & embed">Publish</router-link> -->
                <router-link :to="`/workspaces/${w.id}/edit`" class="action-link action-link--premium inline-flex items-center gap-1.5">
                  <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M2.695 14.763l-1.262 3.154a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.885L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z" />
                  </svg>
                  Edit
                </router-link>
                <button type="button" class="action-link action-link--premium inline-flex items-center gap-1.5 !text-rose-700 hover:!text-rose-800 hover:!bg-rose-50/75 hover:!border-rose-200/80" @click="confirmDelete(w)">
                  <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 3.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4ZM8.58 7.72a.75.75 0 0 0-1.5.06l.3 7.5a.75.75 0 1 0 1.5-.06l-.3-7.5Zm4.34.06a.75.75 0 1 0-1.5-.06l-.3 7.5a.75.75 0 1 0 1.5.06l.3-7.5Z" clip-rule="evenodd" />
                  </svg>
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useWorkspacesStore } from '../stores/workspaces';

const workspaces = useWorkspacesStore();

onMounted(async () => {
  await workspaces.fetchAll();
});

async function confirmDelete(w) {
  if (window.confirm(`Delete workspace "${w.name}"?`)) {
    await workspaces.remove(w.id);
  }
}
</script>

<style scoped>
.action-link--premium {
  border-color: rgba(203, 213, 225, 0.95);
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
}

.onboard-step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 9999px;
  background: rgba(238, 242, 255, 0.9);
  border: 1px solid rgba(165, 180, 252, 0.7);
  color: rgb(79, 70, 229);
  font-size: 0.72rem;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 1px;
}
</style>
