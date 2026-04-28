<template>
  <div class="min-h-screen bg-one-bg font-samsung">

    <!-- Topbar layout -->
    <div v-if="navMode === 'topbar'" class="flex flex-col min-h-screen">
      <TopBar @switch-nav="setNavMode('sidebar')" @logout="handleLogout" />
      <WorkspaceContextBar v-if="hasWorkspaceContext" />
      <main class="flex-1 p-5 bg-one-bg">
        <router-view />
      </main>
    </div>

    <!-- Sidebar layout -->
    <div v-else class="flex min-h-screen">
      <SideBar @switch-nav="setNavMode('topbar')" @logout="handleLogout" />
      <div class="flex-1 flex flex-col min-w-0">
        <WorkspaceContextBar v-if="hasWorkspaceContext" />
        <main class="flex-1 p-5 bg-one-bg">
          <router-view />
        </main>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import TopBar from '../components/TopBar.vue';
import SideBar from '../components/SideBar.vue';
import WorkspaceContextBar from '../components/WorkspaceContextBar.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const navMode = ref(localStorage.getItem('curator_nav_mode') || 'topbar');

function setNavMode(mode) {
  navMode.value = mode;
  localStorage.setItem('curator_nav_mode', mode);
}

const hasWorkspaceContext = computed(() => Boolean(route.params.workspaceId));

async function handleLogout() {
  await auth.logout();
  router.push('/login');
}
</script>
