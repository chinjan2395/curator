<template>
  <div class="min-h-screen flex">
    <!-- Left panel — navy gradient, hidden on small screens -->
    <div class="hidden lg:flex lg:w-[44%] xl:w-[40%] flex-col justify-between p-10 relative overflow-hidden" style="background: linear-gradient(160deg, #1e3a8a 0%, #172554 100%)">
      <!-- Background decoration -->
      <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%)" />
        <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full" style="background: radial-gradient(circle, rgba(147,197,253,0.08) 0%, transparent 70%)" />
      </div>

      <!-- Logo -->
      <div class="relative flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center backdrop-blur-sm">
          <img src="/icons.svg" alt="Curator" class="w-6 h-6" />
        </div>
        <div>
          <div class="text-base font-bold text-white tracking-tight">Curator</div>
          <div class="text-xs text-blue-200/60">Social content platform</div>
        </div>
      </div>

      <!-- Tagline -->
      <div class="relative">
        <h2 class="text-2xl font-bold text-white leading-snug mb-3">
          Aggregate, curate,<br />and publish — effortlessly.
        </h2>
        <p class="text-sm text-blue-200/70 leading-relaxed max-w-xs">
          Connect your social accounts, sync posts, and embed a beautiful feed on any website.
        </p>

        <!-- Feature bullets -->
        <div class="mt-8 space-y-3">
          <div v-for="item in features" :key="item" class="flex items-center gap-3 text-sm text-blue-100/80">
            <div class="w-5 h-5 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3 text-blue-200" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
              </svg>
            </div>
            {{ item }}
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="relative text-xs text-blue-200/40">© {{ new Date().getFullYear() }} Curator</div>
    </div>

    <!-- Right panel — form -->
    <div class="flex-1 flex flex-col bg-slate-50">
      <!-- Mobile header -->
      <header class="lg:hidden flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-white">
        <router-link to="/" class="flex items-center gap-2.5">
          <img src="/icons.svg" alt="Curator" class="w-7 h-7 rounded-md" />
          <div class="text-sm font-semibold text-slate-900">Curator</div>
        </router-link>
        <nav class="text-sm text-slate-500">
          <template v-if="isLogin">
            <router-link to="/register" class="font-medium text-blue-600 hover:text-blue-700">Sign up</router-link>
          </template>
          <template v-else>
            <router-link to="/login" class="font-medium text-blue-600 hover:text-blue-700">Sign in</router-link>
          </template>
        </nav>
      </header>

      <!-- Form area -->
      <div class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
          <!-- Top nav (desktop) -->
          <div class="hidden lg:flex justify-end mb-8 text-sm text-slate-500">
            <template v-if="isLogin">
              Don't have an account?
              <router-link to="/register" class="ml-1.5 font-semibold text-blue-600 hover:text-blue-700">Sign up</router-link>
            </template>
            <template v-else>
              Already have an account?
              <router-link to="/login" class="ml-1.5 font-semibold text-blue-600 hover:text-blue-700">Sign in</router-link>
            </template>
          </div>

          <div class="bg-white rounded-2xl border px-8 py-8" style="border-color: #e6ebf2; box-shadow: 0 1px 2px rgba(15,23,42,0.04), 0 8px 22px rgba(15,23,42,0.06);">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight mb-1">{{ title }}</h1>
            <p class="text-sm text-slate-500 mb-6">Secure access to your workspace.</p>
            <slot />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  isLogin: { type: Boolean, default: false },
});

const features = [
  'Connect YouTube, Instagram, Facebook & more',
  'Curate and approve posts with one click',
  'Embed a live feed on any website',
];
</script>
