<template>
  <div class="dashboard-layout min-h-screen">
    <div class="mx-auto px-4 py-4 md:px-6 md:py-5">
      <div class="min-h-[calc(100vh-2.5rem)] rounded-[26px] border border-slate-200/70 bg-white/70 p-2 shadow-floating backdrop-blur-sm md:p-3">
        <div class="flex min-h-[calc(100vh-4rem)] overflow-hidden rounded-[20px] border border-slate-200/80 bg-white/88">
          <aside class="w-64 flex-shrink-0 flex flex-col border-r border-slate-200/80 bg-gradient-to-b from-white/95 via-indigo-50/30 to-cyan-50/20 backdrop-blur-sm">
            <div class="flex items-center gap-2 px-4 py-4 border-b border-slate-200/80">
              <img src="/icons.svg" alt="Curator" class="w-7 h-7 rounded-md ring-1 ring-slate-200/80" />
              <div>
                <div class="text-sm-pro font-semibold text-slate-900 tracking-tight">Curator</div>
                <div class="text-2xs text-slate-500">Admin workspace</div>
              </div>
            </div>
            <nav class="flex-1 px-3 py-3.5 overflow-y-auto">
              <ul class="space-y-1">
                <li>
                  <router-link to="/" class="nav-item" :class="{ 'nav-item-active': $route.path === '/' }">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v1.5a.75.75 0 0 0 1.5 0v-1.5ZM4.03 4.03a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 1 0 1.06-1.06L4.03 4.03ZM2.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM4.03 15.97a.75.75 0 0 0 1.06 0l1.06-1.06a.75.75 0 0 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 0 1.06ZM10 6.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM15.97 4.03a.75.75 0 0 0-1.06 0l-1.06 1.06a.75.75 0 1 0 1.06 1.06l1.06-1.06a.75.75 0 0 0 0-1.06ZM15.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM14.91 13.85a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 0 0 1.06-1.06l-1.06-1.06ZM9.25 15.75a.75.75 0 0 0 1.5 0v1.5a.75.75 0 0 0-1.5 0v-1.5Z" />
                    </svg>
                    Dashboard
                  </router-link>
                </li>
                <li>
                  <router-link
                    to="/publish"
                    class="nav-item"
                    :class="{ 'nav-item-active': $route.path.includes('/publish') }"
                  >
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3.5 3.5A1.5 1.5 0 0 1 5 2h7a1.5 1.5 0 0 1 1.5 1.5V6h1.75A1.75 1.75 0 0 1 17 7.75v8.5A1.75 1.75 0 0 1 15.25 18h-10.5A1.75 1.75 0 0 1 3 16.25v-8.5A1.75 1.75 0 0 1 4.75 6H6.5V3.5Zm1.5 0V6h7V3.5h-7Zm4.22 6.97a.75.75 0 0 0-1.06 1.06l1.09 1.09H7.75a.75.75 0 0 0 0 1.5h1.5l-1.09 1.09a.75.75 0 1 0 1.06 1.06l2.37-2.37a.75.75 0 0 0 0-1.06l-2.37-2.37Z" />
                    </svg>
                    Publish &amp; embed
                  </router-link>
                </li>
                <li>
                  <router-link to="/workspaces" class="nav-item" :class="{ 'nav-item-active': $route.path.startsWith('/workspaces') && !$route.params.workspaceId }">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
                    </svg>
                    Workspaces
                  </router-link>
                </li>
                <li class="pt-1" v-if="$route.path.startsWith('/workspaces')">
                  <ul class="space-y-1 mt-1">
                    <li v-for="w in workspaces.list" :key="w.id">
                      <router-link
                        :to="`/workspaces/${w.id}/feeds`"
                        class="nav-item nav-item-nested"
                        :class="{ 'nav-item-active': Number($route.params.workspaceId) === w.id }"
                      >
                        <svg class="nav-icon-sm" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path d="M4 5.75A1.75 1.75 0 0 1 5.75 4h8.5A1.75 1.75 0 0 1 16 5.75v8.5A1.75 1.75 0 0 1 14.25 16h-8.5A1.75 1.75 0 0 1 4 14.25v-8.5ZM7 8.75a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Zm0 3a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5H7Z" />
                        </svg>
                        <span class="truncate">{{ w.name }}</span>
                        <span class="ml-auto text-2xs text-slate-400">Feeds</span>
                      </router-link>
                    </li>
                    <li v-if="!workspaces.list.length">
                      <div class="px-3 py-2 text-sm-pro text-slate-400">No workspaces</div>
                    </li>
                  </ul>
                </li>
                <li>
                  <router-link to="/credentials" class="nav-item" :class="{ 'nav-item-active': $route.path.startsWith('/credentials') }">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                    </svg>
                    Credentials
                  </router-link>
                </li>
              </ul>
            </nav>
            <div class="px-3 pb-3 pt-2 border-t border-slate-200/70">
              <div class="relative" ref="profileDropdownRef">
                <button
                  type="button"
                  class="w-full rounded-lg border border-indigo-100/90 bg-gradient-to-r from-indigo-50/70 to-cyan-50/60 px-3 py-2.5 text-left hover:from-indigo-50/90 hover:to-cyan-50/75 transition-colors"
                  @click="showProfileDropdown = !showProfileDropdown"
                >
                  <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-700 text-sm-pro font-semibold flex items-center justify-center">
                      {{ userInitials }}
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm-pro font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
                      <div class="text-2xs text-slate-500 truncate">{{ auth.user?.email || 'No email' }}</div>
                    </div>
                    <span class="ml-auto text-slate-400">▾</span>
                  </div>
                </button>
                <div
                  v-if="showProfileDropdown"
                  class="absolute left-0 bottom-full mb-1.5 w-full rounded-lg border border-slate-200/90 bg-white shadow-panel py-1 z-20"
                >
                  <div class="px-3 py-2 border-b border-slate-100">
                    <div class="text-sm-pro font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
                    <div class="text-2xs text-slate-500 truncate">{{ auth.user?.email || 'No email' }}</div>
                  </div>
                  <button
                    type="button"
                    class="w-full text-left px-3 py-2 text-sm-pro text-rose-700 hover:bg-rose-50/80 transition-colors"
                    @click="logoutFromProfile"
                  >
                    Logout
                  </button>
                </div>
              </div>
            </div>
          </aside>
          <div class="flex-1 flex flex-col min-w-0">
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
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useWorkspacesStore } from '../stores/workspaces';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const workspaces = useWorkspacesStore();
const showProfileDropdown = ref(false);
const profileDropdownRef = ref(null);
const userInitials = computed(() => {
  const name = String(auth.user?.name || '').trim();
  if (!name) return 'U';
  const parts = name.split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
});

onMounted(async () => {
  await workspaces.fetchAll();
  document.addEventListener('click', onClickOutside);
});
onUnmounted(() => {
  document.removeEventListener('click', onClickOutside);
});

async function logoutFromProfile() {
  showProfileDropdown.value = false;
  await auth.logout();
  router.push('/login');
}

function onClickOutside(e) {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(e.target)) {
    showProfileDropdown.value = false;
  }
}
</script>

<style scoped>
.nav-item {
  @apply relative flex items-center gap-2 px-3 py-2 rounded-md text-sm-pro text-slate-600 hover:bg-indigo-50/80 hover:text-indigo-700 transition-all duration-150;
}
.nav-item-nested {
  @apply text-sm-pro pl-4;
}
.nav-icon {
  @apply w-4 h-4 shrink-0 text-slate-400;
}
.nav-icon-sm {
  @apply w-3.5 h-3.5 shrink-0 text-slate-400;
}
.nav-item-active .nav-icon,
.nav-item-active .nav-icon-sm {
  @apply text-indigo-600;
}
.nav-item-active {
  @apply border border-indigo-200/75 font-medium text-indigo-700;
  background:
    linear-gradient(90deg, rgba(99, 102, 241, 0.18) 0%, rgba(168, 85, 247, 0.12) 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.55),
    0 10px 18px -16px rgba(79, 70, 229, 0.85);
}
.nav-item-active::after {
  content: '';
  position: absolute;
  inset: -1px;
  border-radius: 0.45rem;
  background: linear-gradient(90deg, rgba(129, 140, 248, 0.2), rgba(192, 132, 252, 0.1));
  filter: blur(12px);
  opacity: 0.55;
  z-index: -1;
}
.dashboard-layout {
  background-image:
    radial-gradient(1300px 480px at -8% -15%, rgba(226, 232, 240, 0.36), transparent 60%),
    radial-gradient(1080px 460px at 108% -10%, rgba(226, 232, 240, 0.26), transparent 60%);
}
</style>
