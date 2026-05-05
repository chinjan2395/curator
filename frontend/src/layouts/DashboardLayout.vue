<template>
  <div class="dashboard-layout min-h-screen">
    <div class="mx-auto px-1.5 py-1.5 sm:px-3 sm:py-3 md:px-6 md:py-5">
      <div class="min-h-[calc(100vh-0.75rem)] sm:min-h-[calc(100vh-1.5rem)] md:min-h-[calc(100vh-2.5rem)] rounded-[14px] sm:rounded-[20px] md:rounded-[26px] border border-slate-200/70 bg-white/70 p-1 sm:p-2 md:p-3 shadow-floating backdrop-blur-sm">

        <!-- Topbar layout -->
        <div v-if="navMode === 'topbar'" class="flex flex-col min-h-[calc(100vh-1.5rem)] sm:min-h-[calc(100vh-2.5rem)] md:min-h-[calc(100vh-4rem)] overflow-hidden rounded-[12px] sm:rounded-[16px] md:rounded-[20px] border border-slate-200/80 bg-white/88">
          <header class="flex flex-wrap md:flex-nowrap items-center gap-3 px-3 md:px-5 py-3 border-b border-slate-200/80 bg-gradient-to-r from-white/95 via-indigo-50/30 to-cyan-50/20 backdrop-blur-sm shrink-0">
            <div class="flex items-center gap-2 mr-1 md:mr-2 shrink-0">
              <img src="/icons.svg" alt="Curator" class="w-7 h-7 rounded-md ring-1 ring-slate-200/80" />
              <div class="text-sm-pro font-semibold text-slate-900 tracking-tight">Curator</div>
            </div>
            <div class="order-3 md:order-none basis-full md:basis-auto md:flex-1 overflow-x-auto overflow-y-visible topnav-scroll">
            <nav class="flex items-center gap-1 min-w-max md:min-w-0 md:w-auto">
              <router-link to="/" class="topnav-item" :class="{ 'topnav-item-active': $route.path === '/' }">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v1.5a.75.75 0 0 0 1.5 0v-1.5ZM4.03 4.03a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 1 0 1.06-1.06L4.03 4.03ZM2.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM4.03 15.97a.75.75 0 0 0 1.06 0l1.06-1.06a.75.75 0 0 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 0 1.06ZM10 6.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM15.97 4.03a.75.75 0 0 0-1.06 0l-1.06 1.06a.75.75 0 1 0 1.06 1.06l1.06-1.06a.75.75 0 0 0 0-1.06ZM15.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM14.91 13.85a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 0 0 1.06-1.06l-1.06-1.06ZM9.25 15.75a.75.75 0 0 0 1.5 0v1.5a.75.75 0 0 0-1.5 0v-1.5Z" />
                </svg>
                Dashboard
              </router-link>
              <!-- <router-link to="/publish" class="topnav-item" :class="{ 'topnav-item-active': $route.path.includes('/publish') }">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M3.5 3.5A1.5 1.5 0 0 1 5 2h7a1.5 1.5 0 0 1 1.5 1.5V6h1.75A1.75 1.75 0 0 1 17 7.75v8.5A1.75 1.75 0 0 1 15.25 18h-10.5A1.75 1.75 0 0 1 3 16.25v-8.5A1.75 1.75 0 0 1 4.75 6H6.5V3.5Zm1.5 0V6h7V3.5h-7Zm4.22 6.97a.75.75 0 0 0-1.06 1.06l1.09 1.09H7.75a.75.75 0 0 0 0 1.5h1.5l-1.09 1.09a.75.75 0 1 0 1.06 1.06l2.37-2.37a.75.75 0 0 0 0-1.06l-2.37-2.37Z" />
                </svg>
                Publish &amp; embed
              </router-link> -->
              <router-link to="/workspaces" class="topnav-item" :class="{ 'topnav-item-active': $route.path.startsWith('/workspaces') }">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
                </svg>
                Workspaces
              </router-link>
              <router-link to="/credentials" class="topnav-item" :class="{ 'topnav-item-active': $route.path.startsWith('/credentials') }">
                <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                </svg>
                Credentials
              </router-link>
              <!-- Top bar: floating “ops rail” capsule (distinct from sidebar deck) -->
              <div
                v-if="auth.user?.role === 'admin'"
                class="topnav-admin-rail shrink-0"
                role="group"
                aria-label="Admin shortcuts"
              >
                <span class="topnav-admin-rail__pulse" aria-hidden="true" />
                <span class="topnav-admin-rail__badge">
                  <svg class="topnav-admin-rail__badge-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9.661 2.237a.75.75 0 0 1 .678 0l6.332 3.166a.75.75 0 0 1 .579.73v5.534a2.25 2.25 0 0 1-1.248 2.02l-6 3.048a.75.75 0 0 1-.684 0l-6-3.048A2.25 2.25 0 0 1 2.25 11.667V6.133a.75.75 0 0 1 .579-.729l6.332-3.167Zm6.225 4.469-5.886 2.943-5.886-2.943v4.961c0 .431.225.83.593 1.053l5.25 2.667 5.25-2.667a1.25 1.25 0 0 0 .593-1.053V6.706Z" clip-rule="evenodd" />
                  </svg>
                  <span class="topnav-admin-rail__badge-text">Ops</span>
                </span>
                <router-link
                  to="/oauth-apps"
                  class="topnav-admin-pill"
                  :class="{ 'topnav-admin-pill--active': $route.path.startsWith('/oauth-apps') }"
                  title="OAuth apps (admin)"
                >
                  <svg class="topnav-admin-pill__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a2 2 0 0 1 2 2v1.07a5.002 5.002 0 0 1 2.93 2.93H16a2 2 0 1 1 0 4h-1.07a5.002 5.002 0 0 1-2.93 2.93V16a2 2 0 1 1-4 0v-1.07a5.002 5.002 0 0 1-2.93-2.93H4a2 2 0 1 1 0-4h1.07A5.002 5.002 0 0 1 8 5.07V4a2 2 0 0 1 2-2Zm0 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
                  </svg>
                  OAuth
                </router-link>
                <router-link
                  to="/admin/users"
                  class="topnav-admin-pill"
                  :class="{ 'topnav-admin-pill--active': $route.path.startsWith('/admin/users') }"
                  title="Users (admin)"
                >
                  <svg class="topnav-admin-pill__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z" />
                  </svg>
                  Users
                </router-link>
                <router-link
                  to="/admin/sync-ops"
                  class="topnav-admin-pill"
                  :class="{ 'topnav-admin-pill--active': $route.path.startsWith('/admin/sync-ops') }"
                  title="Sync operations (admin)"
                >
                  <svg class="topnav-admin-pill__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                  </svg>
                  Sync
                </router-link>
                <router-link
                  to="/admin/activity"
                  class="topnav-admin-pill"
                  :class="{ 'topnav-admin-pill--active': $route.path.startsWith('/admin/activity') }"
                  title="Activity logs (admin)"
                >
                  <svg class="topnav-admin-pill__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                  </svg>
                  Activity
                </router-link>
              </div>
            </nav>
            </div>
            <div class="flex items-center gap-2 ml-auto order-2 md:order-none shrink-0">
              <!-- Activity panel toggle -->
              <button
                type="button"
                class="p-1.5 rounded-md transition-colors"
                :class="activityLog.panelOpen ? 'text-indigo-600 bg-indigo-50' : 'text-slate-400 hover:text-slate-600 hover:bg-slate-100/70'"
                title="My activity"
                @click="activityLog.togglePanel()"
              >
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                </svg>
              </button>
              <!-- Nav mode toggle -->
              <button
                type="button"
                class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100/70 transition-colors"
                title="Switch to sidebar navigation"
                @click="setNavMode('sidebar')"
              >
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5A.75.75 0 012 10z" clip-rule="evenodd"/>
                </svg>
              </button>
              <!-- Profile -->
              <div class="relative" ref="profileDropdownRef">
                <button
                  type="button"
                  class="flex items-center gap-2 rounded-lg border border-indigo-100/90 bg-gradient-to-r from-indigo-50/70 to-cyan-50/60 px-2.5 py-1.5 hover:from-indigo-50/90 hover:to-cyan-50/75 transition-colors"
                  @click="showProfileDropdown = !showProfileDropdown"
                >
                  <div class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold flex items-center justify-center">
                    {{ userInitials }}
                  </div>
                  <span class="text-sm-pro font-medium text-slate-800 max-w-[120px] truncate">{{ auth.user?.name || 'User' }}</span>
                  <svg class="w-3.5 h-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                </button>
                <div
                  v-if="showProfileDropdown"
                  class="absolute right-0 top-full mt-1.5 w-48 rounded-lg border border-slate-200/90 bg-white shadow-panel py-1 z-20"
                >
                  <div class="px-3 py-2 border-b border-slate-100">
                    <div class="text-sm-pro font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
                    <div class="text-2xs text-slate-600 truncate">{{ auth.user?.email || 'No email' }}</div>
                  </div>
                  <button
                    type="button"
                    class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm-pro text-rose-700 hover:bg-rose-50/80 transition-colors"
                    @click="logoutFromProfile"
                  >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Zm9.47 4.97a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06l-2.25 2.25a.75.75 0 1 1-1.06-1.06l.97-.97H8.75a.75.75 0 0 1 0-1.5h4.69l-.97-.97a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    Logout
                  </button>
                </div>
              </div>
            </div>
          </header>
          <main class="flex-1 p-3 sm:p-4 md:p-5 overflow-y-auto bg-gradient-to-b from-slate-50/40 via-white to-white">
            <router-view />
          </main>
        </div>

        <!-- Sidebar layout -->
        <div v-else class="flex h-[calc(100vh-1.5rem)] sm:h-[calc(100vh-2.5rem)] md:h-[calc(100vh-4rem)] overflow-hidden rounded-[12px] sm:rounded-[16px] md:rounded-[20px] border border-slate-200/80 bg-white/88">
          <aside
            class="h-full flex-shrink-0 flex flex-col border-r border-slate-200/80 bg-gradient-to-b from-white/95 via-indigo-50/30 to-cyan-50/20 backdrop-blur-sm transition-all duration-200"
            :class="sidebarCollapsed ? 'w-16' : 'w-64'"
          >
            <div class="flex items-center gap-2 px-3 py-4 border-b border-slate-200/80">
              <img src="/icons.svg" alt="Curator" class="w-7 h-7 rounded-md ring-1 ring-slate-200/80" />
              <div v-if="!sidebarCollapsed">
                <div class="text-sm-pro font-semibold text-slate-900 tracking-tight">Curator</div>
                <div class="text-2xs text-slate-500">Admin workspace</div>
              </div>
              <button
                type="button"
                class="ml-auto p-1.5 rounded-md text-slate-500 hover:text-slate-700 hover:bg-slate-100/70 transition-colors"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                @click="toggleSidebar"
              >
                <svg v-if="sidebarCollapsed" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M12.78 4.22a.75.75 0 0 1 0 1.06L8.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M7.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L11.94 10 7.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            <nav class="flex-1 px-3 py-3.5 overflow-y-auto">
              <ul class="space-y-1">
                <li>
                  <router-link to="/" class="nav-item" :class="[{ 'nav-item-active': $route.path === '/' }, sidebarCollapsed ? 'nav-item-collapsed' : '']" :title="sidebarCollapsed ? 'Dashboard' : ''">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v1.5a.75.75 0 0 0 1.5 0v-1.5ZM4.03 4.03a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 1 0 1.06-1.06L4.03 4.03ZM2.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM4.03 15.97a.75.75 0 0 0 1.06 0l1.06-1.06a.75.75 0 0 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 0 1.06ZM10 6.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5ZM15.97 4.03a.75.75 0 0 0-1.06 0l-1.06 1.06a.75.75 0 1 0 1.06 1.06l1.06-1.06a.75.75 0 0 0 0-1.06ZM15.75 9.25a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5h-1.5ZM14.91 13.85a.75.75 0 0 0-1.06 1.06l1.06 1.06a.75.75 0 0 0 1.06-1.06l-1.06-1.06ZM9.25 15.75a.75.75 0 0 0 1.5 0v1.5a.75.75 0 0 0-1.5 0v-1.5Z" />
                    </svg>
                    <span v-if="!sidebarCollapsed">Dashboard</span>
                  </router-link>
                </li>
                <!-- <li>
                  <router-link
                    to="/publish"
                    class="nav-item"
                    :class="[{ 'nav-item-active': $route.path.includes('/publish') }, sidebarCollapsed ? 'nav-item-collapsed' : '']"
                    :title="sidebarCollapsed ? 'Publish & embed' : ''"
                  >
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3.5 3.5A1.5 1.5 0 0 1 5 2h7a1.5 1.5 0 0 1 1.5 1.5V6h1.75A1.75 1.75 0 0 1 17 7.75v8.5A1.75 1.75 0 0 1 15.25 18h-10.5A1.75 1.75 0 0 1 3 16.25v-8.5A1.75 1.75 0 0 1 4.75 6H6.5V3.5Zm1.5 0V6h7V3.5h-7Zm4.22 6.97a.75.75 0 0 0-1.06 1.06l1.09 1.09H7.75a.75.75 0 0 0 0 1.5h1.5l-1.09 1.09a.75.75 0 1 0 1.06 1.06l2.37-2.37a.75.75 0 0 0 0-1.06l-2.37-2.37Z" />
                    </svg>
                    <span v-if="!sidebarCollapsed">Publish &amp; embed</span>
                  </router-link>
                </li> -->
                <li>
                  <router-link to="/workspaces" class="nav-item" :class="[{ 'nav-item-active': $route.path.startsWith('/workspaces') && !$route.params.workspaceId }, sidebarCollapsed ? 'nav-item-collapsed' : '']" :title="sidebarCollapsed ? 'Workspaces' : ''">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3 4.75A1.75 1.75 0 0 1 4.75 3h4.5A1.75 1.75 0 0 1 11 4.75v4.5A1.75 1.75 0 0 1 9.25 11h-4.5A1.75 1.75 0 0 1 3 9.25v-4.5ZM12 4.75A1.75 1.75 0 0 1 13.75 3h1.5A1.75 1.75 0 0 1 17 4.75v1.5A1.75 1.75 0 0 1 15.25 8h-1.5A1.75 1.75 0 0 1 12 6.25v-1.5ZM12 13.75A1.75 1.75 0 0 1 13.75 12h1.5A1.75 1.75 0 0 1 17 13.75v1.5A1.75 1.75 0 0 1 15.25 17h-1.5A1.75 1.75 0 0 1 12 15.25v-1.5ZM3 13.75A1.75 1.75 0 0 1 4.75 12h4.5A1.75 1.75 0 0 1 11 13.75v1.5A1.75 1.75 0 0 1 9.25 17h-4.5A1.75 1.75 0 0 1 3 15.25v-1.5Z" />
                    </svg>
                    <span v-if="!sidebarCollapsed">Workspaces</span>
                  </router-link>
                </li>
                <li class="pt-1" v-if="$route.path.startsWith('/workspaces') && !sidebarCollapsed">
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
                        <span class="ml-auto text-2xs text-slate-500">Setup</span>
                      </router-link>
                    </li>
                    <li v-if="!workspaces.list.length">
                      <div class="px-3 py-2 text-sm-pro text-slate-400">No workspaces</div>
                    </li>
                  </ul>
                </li>
                <li>
                  <router-link to="/credentials" class="nav-item" :class="[{ 'nav-item-active': $route.path.startsWith('/credentials') }, sidebarCollapsed ? 'nav-item-collapsed' : '']" :title="sidebarCollapsed ? 'Credentials' : ''">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M11.49 2.17a1.75 1.75 0 0 0-2.98 0l-.62 1.08a1.75 1.75 0 0 1-1.25.85l-1.22.22a1.75 1.75 0 0 0-.92 2.95l.84.86a1.75 1.75 0 0 1 .48 1.48l-.14 1.3a1.75 1.75 0 0 0 2.4 1.83l1.13-.48a1.75 1.75 0 0 1 1.38 0l1.13.48a1.75 1.75 0 0 0 2.4-1.83l-.14-1.3a1.75 1.75 0 0 1 .48-1.48l.84-.86a1.75 1.75 0 0 0-.92-2.95l-1.22-.22a1.75 1.75 0 0 1-1.25-.85l-.62-1.08ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
                    </svg>
                    <span v-if="!sidebarCollapsed">Credentials</span>
                  </router-link>
                </li>
              </ul>
            </nav>
            <div class="px-3 pb-3 pt-2 border-t border-slate-200/70 shrink-0">
              <!-- Operator deck: pinned above “Switch to top bar” -->
              <div v-if="auth.user?.role === 'admin'" class="sidebar-admin-footer">
                <div
                  class="sidebar-admin-deck"
                  :class="{ 'sidebar-admin-deck--collapsed': sidebarCollapsed }"
                  :title="sidebarCollapsed ? 'Admin: OAuth apps & Users' : ''"
                >
                  <div class="sidebar-admin-deck__aurora" aria-hidden="true" />
                  <div v-if="!sidebarCollapsed" class="sidebar-admin-deck__head">
                    <span class="sidebar-admin-deck__orb">
                      <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M11.983 1.907a.75.75 0 0 0-1.466 0l-.056.229a5.978 5.978 0 0 1-.713 1.516l-.141.191c-.282.383-.646.687-1.048.887l-.378.182a5.978 5.978 0 0 1-1.878.398l-.247.017a.75.75 0 0 0-.441 1.287l.185.154c.336.28.592.633.745 1.022l.097.268a5.978 5.978 0 0 1-.024 1.97l-.056.229a.75.75 0 0 0 1.052.848l.209-.112c.368-.197.784-.304 1.208-.311h.278c.424.007.84.114 1.208.311l.209.112a.75.75 0 0 0 1.052-.848l-.056-.229a5.978 5.978 0 0 1-.024-1.97l.097-.268c.153-.389.409-.742.745-1.022l.185-.154a.75.75 0 0 0-.441-1.287l-.247-.017a5.978 5.978 0 0 1-1.878-.398l-.378-.182a4.49 4.49 0 0 1-1.048-.887l-.141-.191a5.978 5.978 0 0 1-.713-1.516l-.056-.229Z" />
                      </svg>
                    </span>
                    <div class="sidebar-admin-deck__head-text">
                      <p class="sidebar-admin-deck__title">Operator deck</p>
                      <p class="sidebar-admin-deck__sub">Apps · roster · sync</p>
                    </div>
                  </div>
                  <ul class="sidebar-admin-list space-y-1">
                    <li>
                      <router-link
                        to="/oauth-apps"
                        class="nav-item nav-item-admin-deck"
                        :class="[{ 'nav-item-admin-deck--active': $route.path.startsWith('/oauth-apps') }, sidebarCollapsed ? 'nav-item-collapsed' : '']"
                        :title="sidebarCollapsed ? 'OAuth apps (admin)' : ''"
                      >
                        <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path d="M10 2a2 2 0 0 1 2 2v1.07a5.002 5.002 0 0 1 2.93 2.93H16a2 2 0 1 1 0 4h-1.07a5.002 5.002 0 0 1-2.93 2.93V16a2 2 0 1 1-4 0v-1.07a5.002 5.002 0 0 1-2.93-2.93H4a2 2 0 1 1 0-4h1.07A5.002 5.002 0 0 1 8 5.07V4a2 2 0 0 1 2-2Zm0 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
                        </svg>
                        <span v-if="!sidebarCollapsed">OAuth apps</span>
                        <span v-if="!sidebarCollapsed" class="sidebar-admin-deck__hint">keys</span>
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        to="/admin/users"
                        class="nav-item nav-item-admin-deck"
                        :class="[{ 'nav-item-admin-deck--active': $route.path.startsWith('/admin/users') }, sidebarCollapsed ? 'nav-item-collapsed' : '']"
                        :title="sidebarCollapsed ? 'Users (admin)' : ''"
                      >
                        <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z" />
                        </svg>
                        <span v-if="!sidebarCollapsed">Users</span>
                        <span v-if="!sidebarCollapsed" class="sidebar-admin-deck__hint">roster</span>
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        to="/admin/sync-ops"
                        class="nav-item nav-item-admin-deck"
                        :class="[{ 'nav-item-admin-deck--active': $route.path.startsWith('/admin/sync-ops') }, sidebarCollapsed ? 'nav-item-collapsed' : '']"
                        :title="sidebarCollapsed ? 'Sync operations (admin)' : ''"
                      >
                        <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
                        </svg>
                        <span v-if="!sidebarCollapsed">Sync</span>
                        <span v-if="!sidebarCollapsed" class="sidebar-admin-deck__hint">ops</span>
                      </router-link>
                    </li>
                    <li>
                      <router-link
                        to="/admin/activity"
                        class="nav-item nav-item-admin-deck"
                        :class="[{ 'nav-item-admin-deck--active': $route.path.startsWith('/admin/activity') }, sidebarCollapsed ? 'nav-item-collapsed' : '']"
                        :title="sidebarCollapsed ? 'Activity logs (admin)' : ''"
                      >
                        <svg class="nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                        </svg>
                        <span v-if="!sidebarCollapsed">Activity</span>
                        <span v-if="!sidebarCollapsed" class="sidebar-admin-deck__hint">logs</span>
                      </router-link>
                    </li>
                  </ul>
                </div>
              </div>
              <!-- Nav mode toggle -->
              <button
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2 mb-2 rounded-md text-sm-pro text-slate-500 hover:bg-slate-100/70 hover:text-slate-700 transition-colors"
                :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                @click="setNavMode('topbar')"
                :title="sidebarCollapsed ? 'Switch to top bar' : ''"
              >
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M2 3.75A.75.75 0 012.75 3h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 3.75zm0 4.5A.75.75 0 012.75 7.5h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 8.25zm0 4.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75z" clip-rule="evenodd"/>
                </svg>
                <span v-if="!sidebarCollapsed">Switch to top bar</span>
              </button>
              <!-- Activity panel toggle -->
              <button
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2 mb-1 rounded-md text-sm-pro transition-colors"
                :class="[sidebarCollapsed ? 'justify-center px-2' : '', activityLog.panelOpen ? 'text-indigo-600 bg-indigo-50' : 'text-slate-500 hover:bg-slate-100/70 hover:text-slate-700']"
                :title="sidebarCollapsed ? 'My activity' : ''"
                @click="activityLog.togglePanel()"
              >
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
                </svg>
                <span v-if="!sidebarCollapsed">My Activity</span>
              </button>
              <div class="relative" ref="profileDropdownRef">
                <button
                  type="button"
                  class="w-full rounded-lg border border-indigo-100/90 bg-gradient-to-r from-indigo-50/70 to-cyan-50/60 px-3 py-2.5 text-left hover:from-indigo-50/90 hover:to-cyan-50/75 transition-colors"
                  :class="sidebarCollapsed ? 'px-2 py-2' : ''"
                  @click="showProfileDropdown = !showProfileDropdown"
                  :title="sidebarCollapsed ? (auth.user?.name || 'User') : ''"
                >
                  <div class="flex items-center gap-2.5" :class="sidebarCollapsed ? 'justify-center' : ''">
                    <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-700 text-sm-pro font-semibold flex items-center justify-center">
                      {{ userInitials }}
                    </div>
                    <div v-if="!sidebarCollapsed" class="min-w-0">
                      <div class="text-sm-pro font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
                      <div class="text-2xs text-slate-600 truncate">{{ auth.user?.email || 'No email' }}</div>
                    </div>
                    <svg v-if="!sidebarCollapsed" class="w-3.5 h-3.5 shrink-0 text-slate-400 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                  </div>
                </button>
                <div
                  v-if="showProfileDropdown"
                  class="absolute left-0 bottom-full mb-1.5 rounded-lg border border-slate-200/90 bg-white shadow-panel py-1 z-20"
                  :class="sidebarCollapsed ? 'w-48' : 'w-full'"
                >
                  <div class="px-3 py-2 border-b border-slate-100">
                    <div class="text-sm-pro font-medium text-slate-800 truncate">{{ auth.user?.name || 'User' }}</div>
                    <div class="text-2xs text-slate-600 truncate">{{ auth.user?.email || 'No email' }}</div>
                  </div>
                  <button
                    type="button"
                    class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm-pro text-rose-700 hover:bg-rose-50/80 transition-colors"
                    @click="logoutFromProfile"
                  >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Zm9.47 4.97a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06l-2.25 2.25a.75.75 0 1 1-1.06-1.06l.97-.97H8.75a.75.75 0 0 1 0-1.5h4.69l-.97-.97a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                    Logout
                  </button>
                </div>
              </div>
            </div>
          </aside>
          <div class="flex-1 h-full flex flex-col min-w-0 overflow-hidden">
            <main class="flex-1 p-3 sm:p-4 md:p-5 overflow-y-auto bg-gradient-to-b from-slate-50/40 via-white to-white">
              <router-view />
            </main>
          </div>
        </div>

      </div>
    </div>

    <!-- Activity panel (fixed right drawer, works in both nav modes) -->
    <Transition name="activity-panel">
      <aside
        v-if="activityLog.panelOpen"
        class="fixed top-0 right-0 h-full w-96 z-30 flex flex-col bg-white border-l border-slate-200/80 shadow-2xl"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0 bg-gradient-to-r from-slate-50/80 to-white">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center">
              <svg class="w-4 h-4 text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <div class="text-sm-pro font-semibold text-slate-800">Activity</div>
              <div class="text-2xs text-slate-400">Your recent actions</div>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
              title="Refresh"
              @click="activityLog.fetchMyLogs()"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd" />
              </svg>
            </button>
            <button
              type="button"
              class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
              title="Close"
              @click="activityLog.togglePanel()"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-5 py-4">
          <!-- Loading -->
          <div v-if="activityLog.loading" class="flex flex-col items-center justify-center py-16 gap-3 text-slate-400">
            <span class="inline-block w-6 h-6 border-2 border-slate-200 border-t-indigo-500 rounded-full animate-spin" />
            <span class="text-sm-pro">Loading activity…</span>
          </div>

          <!-- Empty -->
          <div v-else-if="activityLog.logs.length === 0" class="flex flex-col items-center justify-center py-16 gap-2 text-slate-400">
            <svg class="w-8 h-8 text-slate-200" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd" />
            </svg>
            <span class="text-sm-pro">No activity yet</span>
          </div>

          <!-- Timeline -->
          <div v-else class="relative">
            <!-- Vertical line -->
            <div class="absolute left-[18px] top-2 bottom-2 w-px bg-slate-100" aria-hidden="true" />

            <div class="space-y-1">
              <div
                v-for="(log, index) in activityLog.logs"
                :key="log.id"
                class="relative flex gap-4 group"
              >
                <!-- Timeline dot -->
                <div class="relative z-10 shrink-0 w-9 flex justify-center pt-2.5">
                  <div
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs ring-2 ring-white transition-transform group-hover:scale-110"
                    :class="activityDotClass(log.action)"
                  >
                    {{ activityPanelIcon(log.action) }}
                  </div>
                </div>

                <!-- Content card -->
                <div class="flex-1 min-w-0 pb-4">
                  <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-3.5 py-3 hover:bg-white hover:border-slate-200 hover:shadow-sm transition-all">
                    <!-- Action badge + time -->
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-semibold" :class="activityBadgeClass(log.action)">
                        {{ activityActionLabel(log.action) }}
                      </span>
                      <span class="text-2xs text-slate-400 whitespace-nowrap shrink-0">{{ formatActivityDate(log.created_at) }}</span>
                    </div>
                    <!-- Description -->
                    <div class="text-sm-pro text-slate-700 leading-snug">{{ log.description }}</div>
                    <!-- Entity name if present -->
                    <div v-if="log.entity_name" class="mt-1.5 flex items-center gap-1 text-2xs text-slate-400">
                      <svg class="w-3 h-3 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                      <span class="truncate">{{ log.entity_name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="shrink-0 px-5 py-3 border-t border-slate-100 bg-slate-50/50">
          <div class="text-2xs text-slate-400 text-center">Showing last {{ activityLog.logs.length }} actions</div>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useWorkspacesStore } from '../stores/workspaces';
import { useActivityLogStore } from '../stores/activityLog';

const auth = useAuthStore();
const router = useRouter();
const workspaces = useWorkspacesStore();
const activityLog = useActivityLogStore();
const showProfileDropdown = ref(false);
const profileDropdownRef = ref(null);
const sidebarCollapsed = ref(false);

const navMode = ref(localStorage.getItem('curator_nav_mode') || 'topbar');

function setNavMode(mode) {
  navMode.value = mode;
  localStorage.setItem('curator_nav_mode', mode);
}

function activityPanelIcon(action) {
  if (action?.startsWith('auth')) return '🔑'
  if (action?.startsWith('workspace')) return '🗂'
  if (action?.startsWith('feed')) return '📡'
  if (action?.startsWith('post')) return '📝'
  if (action?.startsWith('credential')) return '🔗'
  return '·'
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
  if (action?.startsWith('workspace')) return 'bg-indigo-100 text-indigo-600'
  if (action?.startsWith('feed')) return 'bg-sky-100 text-sky-600'
  if (action?.startsWith('post')) return 'bg-amber-100 text-amber-600'
  if (action?.startsWith('credential')) return 'bg-emerald-100 text-emerald-600'
  return 'bg-slate-100 text-slate-500'
}

function activityBadgeClass(action) {
  if (action?.startsWith('auth')) return 'bg-violet-50 text-violet-700'
  if (action?.startsWith('workspace')) return 'bg-indigo-50 text-indigo-700'
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

onMounted(async () => {
  await workspaces.fetchAll();
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
</script>

<style scoped>
.nav-item {
  @apply relative flex items-center gap-2 px-3 py-2 rounded-md text-sm-pro text-slate-600 hover:bg-indigo-50/80 hover:text-indigo-700 transition-all duration-150;
}
.nav-item-nested {
  @apply text-sm-pro pl-4;
}
.nav-item-collapsed {
  justify-content: center;
  padding-left: 0.5rem;
  padding-right: 0.5rem;
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
.topnav-item {
  @apply relative flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm-pro text-slate-600 hover:bg-indigo-50/80 hover:text-indigo-700 transition-all duration-150;
  white-space: nowrap;
  flex-shrink: 0;
}
.topnav-item-active {
  @apply border border-indigo-200/75 font-medium text-indigo-700;
  background:
    linear-gradient(90deg, rgba(99, 102, 241, 0.18) 0%, rgba(168, 85, 247, 0.12) 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.55),
    0 10px 18px -16px rgba(79, 70, 229, 0.85);
}
.topnav-item-active .nav-icon {
  @apply text-indigo-600;
}

@keyframes topnav-admin-dot {
  0%,
  100% {
    opacity: 0.45;
    transform: scale(1);
    box-shadow: 0 0 0 0 rgba(168, 85, 247, 0.35);
  }
  50% {
    opacity: 1;
    transform: scale(1.15);
    box-shadow: 0 0 0 6px rgba(34, 211, 238, 0.12);
  }
}

@keyframes sidebar-admin-aurora-spin {
  to {
    transform: rotate(360deg);
  }
}

/* Top bar: glass “ops rail” capsule — fast telemetry strip */
.topnav-admin-rail {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  margin-left: 0.45rem;
  padding: 0.18rem 0.4rem 0.18rem 0.35rem;
  border-radius: 9999px;
  border: 1px solid rgba(167, 139, 250, 0.38);
  background:
    linear-gradient(125deg, rgba(255, 255, 255, 0.94), rgba(237, 233, 254, 0.58)),
    linear-gradient(135deg, rgba(244, 114, 182, 0.07), rgba(34, 211, 238, 0.06));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.82),
    0 10px 32px -20px rgba(91, 33, 182, 0.55);
  backdrop-filter: blur(12px);
}

.topnav-admin-rail__pulse {
  width: 6px;
  height: 6px;
  border-radius: 9999px;
  flex-shrink: 0;
  margin-left: 2px;
  background: linear-gradient(145deg, #c084fc, #22d3ee);
  animation: topnav-admin-dot 2.4s ease-in-out infinite;
}

.topnav-admin-rail__badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
  padding: 0.12rem 0.45rem 0.12rem 0.35rem;
  border-radius: 9999px;
  border: 1px solid rgba(139, 92, 246, 0.28);
  background: linear-gradient(120deg, rgba(109, 40, 217, 0.11), rgba(14, 165, 233, 0.08));
}

.topnav-admin-rail__badge-icon {
  width: 0.65rem;
  height: 0.65rem;
  color: rgb(109 40 217 / 0.9);
}

.topnav-admin-rail__badge-text {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgb(76 29 149 / 0.92);
}

.topnav-admin-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
  padding: 0.2rem 0.45rem;
  border-radius: 0.5rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: rgb(59 7 100 / 0.92);
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease,
    border-color 0.15s ease;
}

.topnav-admin-pill:hover {
  background: rgba(255, 255, 255, 0.92);
  color: rgb(49 0 92);
  box-shadow: 0 6px 18px -14px rgba(91, 33, 182, 0.55);
}

.topnav-admin-pill__icon {
  width: 0.85rem;
  height: 0.85rem;
  color: rgb(139 92 246 / 0.88);
}

.topnav-admin-pill--active {
  border: 1px solid rgba(167, 139, 250, 0.65);
  background: rgba(255, 255, 255, 0.96);
  color: rgb(30 0 58);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.85),
    0 10px 26px -18px rgba(91, 33, 182, 0.55);
}

.topnav-admin-pill--active .topnav-admin-pill__icon {
  color: rgb(109 40 217);
}

/* Sidebar: frosted “operator deck” — pinned in footer above mode switch */
.sidebar-admin-footer {
  margin-bottom: 0.75rem;
}

.sidebar-admin-deck {
  position: relative;
  overflow: hidden;
  border-radius: 0.85rem;
  padding: 0.6rem 0.45rem 0.45rem;
  border: 1px solid rgba(167, 139, 250, 0.32);
  background:
    linear-gradient(165deg, rgba(255, 255, 255, 0.9), rgba(237, 233, 254, 0.42)),
    radial-gradient(120% 90% at 0% 0%, rgba(192, 132, 252, 0.26), transparent 56%),
    radial-gradient(95% 85% at 100% 100%, rgba(34, 211, 238, 0.16), transparent 52%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.78),
    0 18px 46px -34px rgba(76, 29, 149, 0.72);
  backdrop-filter: blur(14px);
}

.sidebar-admin-deck--collapsed {
  padding: 0.4rem 0.25rem;
}

.sidebar-admin-deck__aurora {
  position: absolute;
  inset: -55%;
  opacity: 0.55;
  pointer-events: none;
  background: conic-gradient(
    from 200deg,
    rgba(168, 85, 247, 0.14),
    rgba(34, 211, 238, 0.1),
    rgba(244, 114, 182, 0.11),
    rgba(129, 140, 248, 0.09),
    rgba(168, 85, 247, 0.14)
  );
  animation: sidebar-admin-aurora-spin 22s linear infinite;
}

.sidebar-admin-deck__head {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0 0.2rem 0.15rem;
  margin-bottom: 1rem;
}

.sidebar-admin-deck__orb {
  display: flex;
  height: 2.25rem;
  width: 2.25rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.65rem;
  color: white;
  background: linear-gradient(145deg, #6d28d9 0%, #a855f7 42%, #0891b2 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.38),
    0 12px 28px -14px rgba(76, 29, 149, 0.85);
}

.sidebar-admin-deck__orb svg {
  width: 1rem;
  height: 1rem;
  opacity: 0.96;
}

.sidebar-admin-deck__head-text {
  min-width: 0;
  flex: 1;
}

.sidebar-admin-deck__title {
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: rgb(46 16 101);
}

.sidebar-admin-deck__sub {
  margin-top: 0.1rem;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgb(109 40 217 / 0.62);
}

.sidebar-admin-list {
  list-style: none;
  margin: 0;
  padding: 0;
  position: relative;
  z-index: 1;
}

.sidebar-admin-deck__hint {
  margin-left: auto;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(139 92 246 / 0.72);
}

.nav-item-admin-deck {
  border-color: transparent;
  background: rgba(255, 255, 255, 0.28);
}

.nav-item-admin-deck:hover {
  background: rgba(255, 255, 255, 0.62);
  color: rgb(30 0 58);
}

.nav-item-admin-deck:hover .nav-icon {
  color: rgb(124 58 237);
}

.nav-item-admin-deck--active {
  border-color: rgba(167, 139, 250, 0.72);
  font-weight: 700;
  color: rgb(30 0 58);
  background: rgba(255, 255, 255, 0.88);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.75),
    0 12px 28px -18px rgba(91, 33, 182, 0.42);
}

.nav-item-admin-deck--active .nav-icon {
  color: rgb(109 40 217);
}

.nav-item-admin-deck--active::after {
  content: '';
  position: absolute;
  inset: -1px;
  border-radius: 0.45rem;
  background: linear-gradient(120deg, rgba(167, 139, 250, 0.28), rgba(34, 211, 238, 0.12));
  filter: blur(12px);
  opacity: 0.65;
  z-index: -1;
}

.dashboard-layout {
  background-image:
    radial-gradient(1300px 480px at -8% -15%, rgba(226, 232, 240, 0.36), transparent 60%),
    radial-gradient(1080px 460px at 108% -10%, rgba(226, 232, 240, 0.26), transparent 60%);
}

.topnav-scroll {
  scrollbar-width: thin;
}

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
