<template>
  <aside class="w-60 flex-shrink-0 flex flex-col bg-one-surface border-r border-one-divider">
    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 py-4 border-b border-one-divider">
      <img src="/icons.svg" alt="Curator" class="w-8 h-8 rounded-xs-card" />
      <span class="text-base-pro font-bold text-one-text tracking-tight">Curator</span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto">
      <ul class="space-y-0.5">
        <li>
          <router-link to="/" class="nav-item" :class="{ 'nav-item-active': $route.path === '/' }">
            <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v1.5a.75.75 0 0 0 1.5 0v-1.5ZM4.03 4.03a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 1 0 1.06-1.06L4.03 4.03ZM2.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM4.03 15.97a.75.75 0 0 0 1.06 0l1.06-1.06a.75.75 0 0 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 0 1.06ZM10 6.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM15.97 4.03a.75.75 0 0 0-1.06 0l-1.06 1.06a.75.75 0 1 0 1.06 1.06l1.06-1.06a.75.75 0 0 0 0-1.06ZM15.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM14.91 13.85a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 0 0 1.06-1.06l-1.06-1.06ZM9.25 15.75a.75.75 0 0 0 1.5 0v1.5a.75.75 0 0 0-1.5 0v-1.5Z" />
            </svg>
            Dashboard
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
        <li v-if="$route.path.startsWith('/workspaces')">
          <ul class="space-y-0.5 mt-0.5 ml-3 pl-3 border-l border-one-divider">
            <li v-for="w in workspaces.list" :key="w.id">
              <router-link
                :to="`/workspaces/${w.id}/feeds`"
                class="nav-item text-xs-pro"
                :class="{ 'nav-item-active': Number($route.params.workspaceId) === w.id }"
              >
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M4 5.75A1.75 1.75 0 0 1 5.75 4h8.5A1.75 1.75 0 0 1 16 5.75v8.5A1.75 1.75 0 0 1 14.25 16h-8.5A1.75 1.75 0 0 1 4 14.25v-8.5ZM7 8.75a.75.75 0 0 0 0 1.5h6a.75.75 0 0 0 0-1.5H7Zm0 3a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5H7Z" />
                </svg>
                <span class="truncate">{{ w.name }}</span>
              </router-link>
            </li>
            <li v-if="!workspaces.list.length">
              <div class="px-3 py-1.5 text-xs-pro text-one-muted">No workspaces</div>
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

    <!-- Bottom -->
    <div class="px-3 pb-3 pt-2 border-t border-one-divider space-y-1">
      <button
        type="button"
        class="w-full flex items-center gap-2 px-3 py-2 rounded-xs-card text-sm-pro text-one-sub hover:bg-one-bg hover:text-one-text transition-colors"
        @click="emit('switch-nav')"
      >
        <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M2 3.75A.75.75 0 012.75 3h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 3.75zm0 4.5A.75.75 0 012.75 7.5h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 8.25zm0 4.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75z" clip-rule="evenodd"/>
        </svg>
        Switch to top bar
      </button>

      <div class="relative" ref="profileDropdownRef">
        <button
          type="button"
          class="w-full rounded-sm-card bg-one-bg px-3 py-2.5 text-left hover:bg-[#E8E8EA] transition-colors"
          @click="showProfileDropdown = !showProfileDropdown"
        >
          <div class="flex items-center gap-2.5">
            <div class="h-8 w-8 rounded-full bg-one-primary text-white text-sm-pro font-bold flex items-center justify-center shrink-0">
              {{ userInitials }}
            </div>
            <div class="min-w-0">
              <div class="text-sm-pro font-semibold text-one-text truncate">{{ auth.user?.name || 'User' }}</div>
              <div class="text-2xs text-one-sub truncate">{{ auth.user?.email || 'No email' }}</div>
            </div>
            <svg class="w-3.5 h-3.5 shrink-0 text-one-muted ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
          </div>
        </button>
        <div
          v-if="showProfileDropdown"
          class="absolute left-0 bottom-full mb-2 w-full rounded-sm-card bg-one-surface shadow-panel py-1.5 z-20"
        >
          <div class="px-3 py-2 border-b border-one-divider">
            <div class="text-sm-pro font-semibold text-one-text truncate">{{ auth.user?.name || 'User' }}</div>
            <div class="text-2xs text-one-sub truncate">{{ auth.user?.email || 'No email' }}</div>
          </div>
          <button
            type="button"
            class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm-pro text-rose-600 hover:bg-rose-50 transition-colors"
            @click="emit('logout'); showProfileDropdown = false"
          >
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Zm9.47 4.97a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06l-2.25 2.25a.75.75 0 1 1-1.06-1.06l.97-.97H8.75a.75.75 0 0 1 0-1.5h4.69l-.97-.97a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
            Logout
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useWorkspacesStore } from '../stores/workspaces';

const auth = useAuthStore();
const workspaces = useWorkspacesStore();
const emit = defineEmits(['switch-nav', 'logout']);

const showProfileDropdown = ref(false);
const profileDropdownRef = ref(null);

const userInitials = computed(() => {
  const name = String(auth.user?.name || '').trim();
  if (!name) return 'U';
  const parts = name.split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
});

function onClickOutside(e) {
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(e.target)) {
    showProfileDropdown.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<style scoped>
.nav-icon {
  @apply w-4 h-4 shrink-0;
}
.nav-item {
  @apply flex items-center gap-2.5 px-3 py-2 rounded-xs-card text-sm-pro font-medium text-one-sub hover:bg-one-bg hover:text-one-text transition-colors;
}
.nav-item-active {
  @apply bg-one-tint text-one-primary font-semibold border-l-2 border-one-primary pl-[10px];
}
</style>
