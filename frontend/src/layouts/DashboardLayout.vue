<template>
  <div class="dashboard-layout min-h-screen">
    <div class="mx-auto px-4 py-4 md:px-6 md:py-5">
      <div class="min-h-[calc(100vh-2.5rem)] rounded-[26px] border border-slate-200/70 bg-white/70 p-2 shadow-floating backdrop-blur-sm md:p-3">

        <!-- Topbar layout -->
        <div v-if="navMode === 'topbar'" class="flex flex-col min-h-[calc(100vh-4rem)] overflow-hidden rounded-[20px] border border-slate-200/80 bg-white/88">
          <TopBar @switch-nav="setNavMode('sidebar')" @logout="handleLogout" />
          <WorkspaceContextBar v-if="hasWorkspaceContext" />
          <main class="flex-1 p-5 overflow-y-auto bg-gradient-to-b from-slate-50/40 via-white to-white">
            <router-view />
          </main>
        </div>

        <!-- Sidebar layout -->
        <div v-else class="flex min-h-[calc(100vh-4rem)] overflow-hidden rounded-[20px] border border-slate-200/80 bg-white/88">
          <SideBar @switch-nav="setNavMode('topbar')" @logout="handleLogout" />
          <div class="flex-1 flex flex-col min-w-0">
            <WorkspaceContextBar v-if="hasWorkspaceContext" />
            <main class="flex-1 p-5 overflow-y-auto bg-gradient-to-b from-slate-50/40 via-white to-white">
              <router-view />
            </main>
          </div>
        </div>

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

<style scoped>
.dashboard-layout {
  background-image:
    radial-gradient(1300px 480px at -8% -15%, rgba(226, 232, 240, 0.36), transparent 60%),
    radial-gradient(1080px 460px at 108% -10%, rgba(226, 232, 240, 0.26), transparent 60%);
}
</style>
