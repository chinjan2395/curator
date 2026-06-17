<template>
  <div class="flex h-screen overflow-hidden" style="background: linear-gradient(160deg, #1e3a8a 0%, #172554 100%)">

    <!-- Mobile backdrop -->
    <div
      v-if="mobileSidebarOpen"
      class="fixed inset-0 bg-slate-900/50 z-30 md:hidden"
      @click="mobileSidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="h-full flex-shrink-0 flex flex-col transition-all duration-200 z-40"
      :style="mobileSidebarOpen ? 'background: linear-gradient(160deg, #1e3a8a 0%, #172554 100%)' : 'background: transparent'"
      :class="[
        sidebarCollapsed ? 'w-16' : 'w-64',
        mobileSidebarOpen ? 'fixed inset-y-0 left-0 flex' : 'hidden md:flex'
      ]"
    >
      <!-- Logo area -->
      <div class="flex items-center gap-2.5 px-3 py-4 border-b border-white/10 shrink-0">
        <img src="/icons.svg" alt="Curator" class="w-8 h-8 rounded-md flex-shrink-0" />
        <div v-if="!sidebarCollapsed" class="min-w-0">
          <div class="text-sm font-semibold text-white tracking-tight">Curator</div>
          <div class="text-xs text-blue-200/50">Admin workspace</div>
        </div>
        <button
          type="button"
          class="ml-auto p-1.5 rounded-md text-blue-200/50 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0"
          :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          @click="toggleSidebar"
        >
          <AppIcon :name="sidebarCollapsed ? 'chevron-right' : 'chevron-left'" class="w-4 h-4" />
        </button>
      </div>

      <!-- Main navigation -->
      <nav class="flex-1 px-2 py-3 overflow-y-auto">
        <template v-for="(section, sectionIndex) in mainNavSections" :key="section.id">
          <div
            v-if="!sidebarCollapsed"
            class="sidebar-section-label"
            :class="{ 'sidebar-section-label-first': sectionIndex === 0 }"
          >
            {{ section.label }}
          </div>
          <div v-else-if="sectionIndex > 0" class="my-3 mx-2 border-t border-white/10" />

          <ul class="space-y-0.5">
            <li v-for="item in section.items" :key="item.id">
              <router-link
                :to="item.to"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': isMainNavActive(item) },
                  sidebarCollapsed ? 'justify-center px-2' : '',
                ]"
                :title="sidebarCollapsed ? item.label : ''"
              >
                <AppIcon :name="item.icon" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">{{ item.label }}</span>
              </router-link>

              <!-- Workspace quick links when expanded -->
              <ul
                v-if="item.id === 'workspaces' && $route.path.startsWith('/workspaces') && !sidebarCollapsed"
                class="mt-0.5 space-y-0.5"
              >
                <li v-for="w in workspaces.list" :key="w.id">
                  <router-link
                    :to="`/workspaces/${w.id}/feeds`"
                    class="sidebar-nav-item pl-9 text-xs"
                    :class="{ 'sidebar-nav-item-active': Number($route.params.workspaceId) === w.id }"
                  >
                    <AppIcon name="feeds" class="w-3.5 h-3.5 flex-shrink-0" />
                    <span class="truncate">{{ w.name }}</span>
                  </router-link>
                </li>
                <li v-if="!workspaces.list.length">
                  <div class="px-9 py-1.5 text-xs text-blue-200/40">No workspaces</div>
                </li>
              </ul>
            </li>
          </ul>
        </template>
      </nav>

      <!-- Sidebar footer -->
      <div class="px-2 pb-3 pt-2 border-t border-white/10 shrink-0">
        <!-- Admin section -->
        <div v-if="auth.user?.role === 'admin' || auth.user?.role === 'superadmin'" class="sidebar-admin-section mb-2">
          <div v-if="!sidebarCollapsed" class="sidebar-section-label">Administration</div>
          <div v-else class="my-3 mx-2 border-t border-white/10" />
          <ul class="mt-0.5 space-y-0.5">
            <li>
              <router-link
                to="/oauth-apps"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/oauth-apps') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'OAuth Apps' : ''"
              >
                <AppIcon name="oauth" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">OAuth Apps</span>
              </router-link>
            </li>
            <li>
              <router-link
                to="/admin/users"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/users') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Users' : ''"
              >
                <AppIcon name="users" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Users</span>
              </router-link>
            </li>
            <li>
              <router-link
                to="/admin/sync-ops"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/sync-ops') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Sync Ops' : ''"
              >
                <AppIcon name="sync" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Sync Ops</span>
              </router-link>
            </li>
            <li v-if="!isSidebarItemHidden('admin-system')">
              <router-link
                to="/admin/system"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/system') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'System' : ''"
              >
                <AppIcon name="settings" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">System</span>
              </router-link>
            </li>
            <li v-if="!isSidebarItemHidden('admin-trends')">
              <router-link
                to="/admin/trends"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/trends') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Trends' : ''"
              >
                <AppIcon name="trending" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Trends</span>
              </router-link>
            </li>
            <li v-if="!isSidebarItemHidden('admin-moderation')">
              <router-link
                to="/admin/moderation"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/moderation') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Moderation' : ''"
              >
                <AppIcon name="shield" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Moderation</span>
              </router-link>
            </li>
            <li>
              <router-link
                to="/admin/activity"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/activity') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Activity' : ''"
              >
                <AppIcon name="activity" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Activity</span>
              </router-link>
            </li>
            <li>
              <router-link
                to="/admin/dev-tools"
                class="sidebar-nav-item"
                :class="[
                  { 'sidebar-nav-item-active': $route.path.startsWith('/admin/dev-tools') },
                  sidebarCollapsed ? 'justify-center px-2' : ''
                ]"
                :title="sidebarCollapsed ? 'Dev Tools' : ''"
              >
                <AppIcon name="terminal" class="w-5 h-5 flex-shrink-0" />
                <span v-if="!sidebarCollapsed">Dev Tools</span>
              </router-link>
            </li>
          </ul>
        </div>

        <!-- Profile dropdown -->
        <div class="relative" ref="profileDropdownRef">
          <button
            type="button"
            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white/10 transition-colors"
            :class="sidebarCollapsed ? 'justify-center px-2' : ''"
            @click="showProfileDropdown = !showProfileDropdown"
            :title="sidebarCollapsed ? (auth.user?.name || 'User') : ''"
          >
            <div class="h-8 w-8 rounded-full bg-blue-500 text-white text-sm font-semibold flex items-center justify-center flex-shrink-0">
              {{ userInitials }}
            </div>
            <div v-if="!sidebarCollapsed" class="min-w-0 text-left flex-1">
              <div class="text-sm font-medium text-white truncate">{{ auth.user?.name || 'User' }}</div>
              <div class="text-xs text-blue-200/50 truncate">{{ auth.user?.email || '' }}</div>
            </div>
            <AppIcon v-if="!sidebarCollapsed" name="chevron-down" class="w-3.5 h-3.5 flex-shrink-0 text-slate-400 ml-auto" />
          </button>
          <div
            v-if="showProfileDropdown"
            class="absolute left-0 bottom-full mb-1.5 rounded-lg border border-slate-200 bg-white shadow-panel py-1 z-20"
            :class="sidebarCollapsed ? 'w-48' : 'w-full'"
          >
            <div class="px-3 py-2 border-b border-slate-100">
              <div class="text-sm font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
              <div class="text-xs text-slate-500 truncate">{{ auth.user?.email || '' }}</div>
            </div>
            <button
              type="button"
              class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
              @click="showProfileDropdown = false; activityLog.togglePanel()"
            >
              <AppIcon name="activity" class="w-4 h-4 flex-shrink-0 text-slate-400" />
              My Activity
            </button>
            <div class="border-t border-slate-100" />
            <button
              type="button"
              class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 transition-colors"
              @click="logoutFromProfile"
            >
              <AppIcon name="logout" class="w-4 h-4 flex-shrink-0" />
              Logout
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main content area — white card with rounded left corners -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden rounded-2xl my-3 mr-3 bg-white" style="box-shadow: 0 0 0 1px rgba(255,255,255,0.06), 0 8px 32px rgba(0,0,0,0.18)">
      <!-- Top header bar -->
      <header class="h-14 bg-white flex items-center px-6 gap-3 flex-shrink-0 z-10" style="border-bottom:1px solid #e6ebf2">
        <!-- Mobile hamburger -->
        <button
          type="button"
          class="md:hidden p-2 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          @click="mobileSidebarOpen = !mobileSidebarOpen"
        >
          <AppIcon name="menu" class="w-5 h-5" />
        </button>

        <!-- Breadcrumb -->
        <nav class="flex-1 flex items-center gap-1 text-sm text-slate-500 min-w-0">
          <span v-for="(crumb, i) in headerBreadcrumbs" :key="i" class="flex items-center gap-1 min-w-0">
            <span v-if="i > 0" class="text-slate-300 flex-shrink-0">
              <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
            </span>
            <span :class="i === headerBreadcrumbs.length - 1 ? 'text-slate-800 font-medium truncate' : 'text-slate-400 truncate'">{{ crumb }}</span>
          </span>
        </nav>

        <!-- Right actions -->
        <div class="flex items-center gap-1 flex-shrink-0">
          <!-- Activity toggle -->
          <button
            type="button"
            class="p-2 rounded-lg transition-colors"
            :class="activityLog.panelOpen ? 'text-blue-600 bg-blue-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'"
            title="My activity"
            @click="activityLog.togglePanel()"
          >
            <AppIcon name="activity" class="w-5 h-5" />
          </button>

          <!-- Notification bell -->
          <button
            type="button"
            class="relative p-2 rounded-lg transition-colors"
            :class="syncUnreadCount > 0 ? 'text-blue-600 bg-blue-50 hover:bg-blue-100' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100'"
            title="Sync notifications"
            @click="openSyncNotifications"
          >
            <AppIcon name="bell" class="w-5 h-5" />
            <span
              v-if="syncUnreadCount > 0"
              class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-blue-600 text-white text-[10px] leading-[1.1rem] font-semibold text-center"
            >
              {{ syncUnreadCount > 99 ? '99+' : syncUnreadCount }}
            </span>
          </button>

          <!-- User avatar -->
          <div class="h-8 w-8 rounded-full bg-blue-600 text-white text-sm font-semibold flex items-center justify-center select-none ml-1 ring-2 ring-blue-100">
            {{ userInitials }}
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6 bg-slate-50">
        <div
          v-if="auth.user?.email_verification_required && !auth.user.email_verified_at"
          class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          <span>Please verify your email to unlock all features.</span>
          <button type="button" class="text-amber-800 underline font-medium" @click="resendVerification">Resend verification email</button>
        </div>
        <router-view />
      </main>
    </div>

    <!-- Activity panel (fixed right drawer) -->
    <Transition name="activity-panel">
      <aside
        v-if="activityLog.panelOpen"
        class="fixed top-0 right-0 h-full w-96 z-30 flex flex-col bg-white border-l border-slate-200 shadow-floating"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 shrink-0 bg-white">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center">
              <AppIcon name="activity" class="w-4 h-4 text-blue-600" />
            </div>
            <div>
              <div class="text-sm font-semibold text-slate-800">Activity</div>
              <div class="text-xs text-slate-400">Your recent actions</div>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
              title="Refresh"
              @click="activityLog.fetchMyLogs()"
            >
              <AppIcon name="sync" class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              title="Close"
              @click="activityLog.togglePanel()"
            >
              <AppIcon name="close" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-5 py-4">
          <div v-if="activityLog.loading" class="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
            <span class="inline-block w-6 h-6 border-2 border-slate-200 border-t-blue-500 rounded-full animate-spin" />
            <span class="text-sm">Loading activity…</span>
          </div>

          <div v-else-if="activityLog.logs.length === 0" class="flex flex-col items-center justify-center py-16 gap-2 text-slate-400">
            <AppIcon name="activity" class="w-8 h-8 text-slate-200" />
            <span class="text-sm">No activity yet</span>
          </div>

          <div v-else class="relative">
            <div class="absolute left-[18px] top-2 bottom-2 w-px bg-slate-100" aria-hidden="true" />
            <div class="space-y-1">
              <div
                v-for="log in activityLog.logs"
                :key="log.id"
                class="relative flex gap-4 group"
              >
                <div class="relative z-10 shrink-0 w-9 flex justify-center pt-2.5">
                  <div
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs ring-2 ring-white transition-transform group-hover:scale-110"
                    :class="activityDotClass(log.action)"
                  >
                    <AppIcon :name="activityPanelIcon(log.action)" class="w-3.5 h-3.5" />
                  </div>
                </div>
                <div class="flex-1 min-w-0 pb-4">
                  <div class="rounded-lg border border-slate-100 bg-slate-50 px-3.5 py-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="activityBadgeClass(log.action)">
                        {{ activityActionLabel(log.action) }}
                      </span>
                      <span class="text-xs text-slate-400 whitespace-nowrap shrink-0">{{ formatActivityDate(log.created_at) }}</span>
                    </div>
                    <div class="text-sm text-slate-700 leading-snug">{{ log.description }}</div>
                    <div v-if="log.entity_name" class="mt-1.5 flex items-center gap-1 text-xs text-slate-400">
                      <AppIcon name="feeds" class="w-3 h-3 shrink-0" />
                      <span class="truncate">{{ log.entity_name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="shrink-0 px-5 py-3 border-t border-slate-200 bg-slate-50">
          <div class="text-xs text-slate-400 text-center">Showing last {{ activityLog.logs.length }} actions</div>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, provide, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router';
import { AppIcon } from '../components/ui';
import { useAuthStore } from '../stores/auth';
import { useWorkspacesStore } from '../stores/workspaces';
import { useActivityLogStore } from '../stores/activityLog';
import { useToastStore } from '../stores/toast';

// Temporarily hidden sidebar items — remove IDs from this set to show them again.
const HIDDEN_SIDEBAR_ITEM_IDS = new Set([
  'inbox',
]);

function isSidebarItemHidden(id) {
  return HIDDEN_SIDEBAR_ITEM_IDS.has(id);
}

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const route = useRoute();
const workspaces = useWorkspacesStore();
const activityLog = useActivityLogStore();
const showProfileDropdown = ref(false);
const profileDropdownRef = ref(null);
const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const headerBreadcrumbs = ref([]);
const syncUnreadCount = computed(() => Number(auth.syncSummary?.scheduler_unread_count || 0));
provide('setHeaderBreadcrumbs', (crumbs) => { headerBreadcrumbs.value = crumbs; });

watch(() => route.path, () => {
  mobileSidebarOpen.value = false;
});

function activityPanelIcon(action) {
  if (action?.startsWith('auth')) return 'logout'
  if (action?.startsWith('workspace')) return 'workspaces'
  if (action?.startsWith('feed')) return 'feeds'
  if (action?.startsWith('post')) return 'check'
  if (action?.startsWith('credential')) return 'credentials'
  return 'circle'
}

function activityActionLabel(action) {
  const labels = {
    'auth.login': 'Login',
    'auth.logout': 'Logout',
    'workspace.created': 'Workspace created',
    'workspace.updated': 'Workspace updated',
    'workspace.deleted': 'Workspace deleted',
    'feed.created': 'Feed created',
    'feed.updated': 'Feed updated',
    'feed.deleted': 'Feed deleted',
    'feed.synced': 'Feed synced',
    'feed.sync_all': 'Sync all',
    'feed.resync_credential': 'Re-synced',
    'post.approved': 'Post approved',
    'post.rejected': 'Post rejected',
    'post.pinned': 'Post pinned',
    'post.unpinned': 'Post unpinned',
    'post.deleted': 'Post deleted',
    'credential.connected': 'Connected',
    'credential.disconnected': 'Disconnected',
  }
  return labels[action] ?? action
}

function activityDotClass(action) {
  if (action?.startsWith('auth')) return 'bg-violet-100 text-violet-600'
  if (action?.startsWith('workspace')) return 'bg-blue-100 text-blue-600'
  if (action?.startsWith('feed')) return 'bg-sky-100 text-sky-600'
  if (action?.startsWith('post')) return 'bg-amber-100 text-amber-600'
  if (action?.startsWith('credential')) return 'bg-emerald-100 text-emerald-600'
  return 'bg-slate-100 text-slate-500'
}

function activityBadgeClass(action) {
  if (action?.startsWith('auth')) return 'bg-violet-50 text-violet-700'
  if (action?.startsWith('workspace')) return 'bg-blue-50 text-blue-700'
  if (action?.startsWith('feed')) return 'bg-sky-50 text-sky-700'
  if (action?.startsWith('post')) return 'bg-amber-50 text-amber-700'
  if (action?.startsWith('credential')) return 'bg-emerald-50 text-emerald-700'
  return 'bg-slate-100 text-slate-600'
}

function formatActivityDate(iso) {
  if (!iso) return ''
  try {
    const diff = Date.now() - new Date(iso).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24) return `${hrs}h ago`
    return `${Math.floor(hrs / 24)}d ago`
  } catch { return iso }
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value;
  localStorage.setItem('curator_sidebar_collapsed', sidebarCollapsed.value ? '1' : '0');
}

const userInitials = computed(() => {
  const name = String(auth.user?.name || '').trim();
  if (!name) return 'U';
  const parts = name.split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((p) => p[0]?.toUpperCase() || '').join('') || 'U';
});

const MAIN_NAV_SECTIONS = [
  {
    id: 'overview',
    label: 'Overview',
    items: [
      { id: 'dashboard', to: '/', label: 'Dashboard', icon: 'dashboard', match: 'exact' },
    ],
  },
  {
    id: 'workspace',
    label: 'Workspace',
    items: [
      { id: 'workspaces', to: '/workspaces', label: 'Workspaces', icon: 'workspaces', match: 'workspaces-root' },
    ],
  },
  {
    id: 'connect',
    label: 'Connect',
    items: [
      { id: 'integrations', to: '/credentials', label: 'Integrations', icon: 'link', match: 'integrations' },
    ],
  },
  {
    id: 'content',
    label: 'Content',
    items: [
      { id: 'curator', to: '/curator', label: 'Curator', icon: 'grid', match: 'curator' },
      { id: 'campaigns', to: '/campaigns', label: 'Campaigns', icon: 'megaphone', match: 'campaigns' },
      { id: 'schedule', to: '/calendar', label: 'Schedule', icon: 'calendar', match: 'schedule' },
      { id: 'content-library', to: '/content-library', label: 'Content Library', icon: 'library', match: 'content' },
    ],
  },
  {
    id: 'insights',
    label: 'Insights',
    items: [
      { id: 'analytics', to: '/analytics', label: 'Analytics', icon: 'chart', match: 'analytics' },
      { id: 'inbox', to: '/inbox', label: 'Inbox', icon: 'inbox', match: 'inbox' },
      { id: 'notifications', to: '/notifications', label: 'Notifications', icon: 'bell', match: 'notifications' },
    ],
  },
];

const mainNavSections = computed(() =>
  MAIN_NAV_SECTIONS
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => !isSidebarItemHidden(item.id)),
    }))
    .filter((section) => section.items.length > 0),
);

function isMainNavActive(item) {
  const path = route.path;
  switch (item.match) {
    case 'exact':
      return path === '/';
    case 'workspaces-root':
      return path.startsWith('/workspaces') && !route.params.workspaceId;
    case 'integrations':
      return path.startsWith('/credentials') || path.startsWith('/integrations');
    case 'curator':
      return path.startsWith('/curator') && !path.includes('embed-builder');
    case 'campaigns':
      return path.startsWith('/campaigns');
    case 'schedule':
      return path.startsWith('/calendar') || path.startsWith('/publisher');
    case 'content':
      return path.startsWith('/content-library') || path === '/content';
    case 'analytics':
      return path.startsWith('/analytics');
    case 'inbox':
      return path.startsWith('/inbox');
    case 'notifications':
      return path.startsWith('/notifications');
    default:
      return path.startsWith(item.to);
  }
}

onMounted(async () => {
  await workspaces.fetchAll();
  await auth.fetchSyncSummary();
  localStorage.removeItem('curator_nav_mode');
  const savedCollapse = localStorage.getItem('curator_sidebar_collapsed');
  if (savedCollapse === null) {
    sidebarCollapsed.value = window.innerWidth < 768;
  } else {
    sidebarCollapsed.value = savedCollapse === '1';
  }
  if (activityLog.panelOpen) {
    activityLog.fetchMyLogs();
  }
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

async function resendVerification() {
  try {
    await axios.post('/api/auth/resend-verification');
    toast.info('Verification email sent.');
  } catch (err) {
    toast.error(err.response?.data?.message || 'Could not send verification email.');
  }
}

async function openSyncNotifications() {
  const unread = syncUnreadCount.value;
  const totalSinceLogin = Number(auth.syncSummary?.scheduler_synced_post_count || 0);
  if (unread > 0) {
    toast.info(`${unread} new post${unread !== 1 ? 's' : ''} synced by scheduler/job.`);
    await auth.acknowledgeSyncNotifications();
    return;
  }
  toast.info(`No new scheduler/job sync posts right now. ${totalSinceLogin} synced since your last login.`);
}
</script>

<style scoped>
.activity-panel-enter-active,
.activity-panel-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.activity-panel-enter-from,
.activity-panel-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
