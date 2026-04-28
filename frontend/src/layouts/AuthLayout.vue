<template>
  <div class="min-h-screen flex flex-col bg-one-bg font-samsung">
    <header class="flex items-center justify-between px-6 py-4 bg-one-surface border-b border-one-divider">
      <router-link to="/" class="flex items-center gap-2.5">
        <img src="/icons.svg" alt="Curator" class="w-8 h-8 rounded-xs-card" />
        <div>
          <div class="text-base-pro font-bold text-one-text tracking-tight">Curator</div>
          <div class="text-2xs text-one-sub">Admin console</div>
        </div>
      </router-link>
      <nav class="text-sm-pro text-one-sub">
        <template v-if="isLogin">
          Don't have an account?
          <router-link to="/register" class="ml-1.5 font-semibold text-one-primary hover:text-[#0F4BA8] transition-colors">Sign up</router-link>
        </template>
        <template v-else>
          Already have an account?
          <router-link to="/login" class="ml-1.5 font-semibold text-one-primary hover:text-[#0F4BA8] transition-colors">Sign in</router-link>
        </template>
      </nav>
    </header>
    <main class="flex-1 flex items-center justify-center px-4 py-16">
      <div class="w-full max-w-[420px] bg-one-surface rounded-card shadow-card px-8 py-10">
        <h1 class="text-lg-pro font-bold text-one-text tracking-tight mb-1">{{ title }}</h1>
        <p class="text-sm-pro text-one-sub mb-8">Secure access to your workspace.</p>
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  isLogin: { type: Boolean, default: false },
});
</script>
