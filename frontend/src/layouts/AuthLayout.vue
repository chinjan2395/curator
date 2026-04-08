<template>
  <div class="auth-layout min-h-screen flex flex-col">
    <header class="flex items-center justify-between px-6 py-4 border-b border-slate-200/80 bg-white/70 backdrop-blur-sm">
      <router-link to="/" class="flex items-center gap-2.5">
        <img src="/icons.svg" alt="Curator" class="w-8 h-8 rounded-md ring-1 ring-slate-200/80" />
        <div>
          <div class="text-base-pro font-semibold text-slate-900 tracking-tight">Curator</div>
          <div class="text-2xs text-slate-500">Admin console</div>
        </div>
      </router-link>
      <nav class="text-sm-pro text-slate-500">
        <template v-if="isLogin">
          Don't have an account?
          <router-link to="/register" class="ml-1.5 font-medium text-slate-700 hover:text-slate-900">Sign up</router-link>
        </template>
        <template v-else>
          Already have an account?
          <router-link to="/login" class="ml-1.5 font-medium text-slate-700 hover:text-slate-900">Sign in</router-link>
        </template>
      </nav>
    </header>
    <main class="flex-1 flex items-center justify-center px-4 py-12">
      <div class="w-full max-w-[920px]">
        <div class="mx-auto w-full max-w-[430px] rounded-[24px] border border-slate-200/75 bg-white/55 p-2.5 shadow-floating backdrop-blur-sm">
          <div class="auth-card w-full bg-white/92 rounded-[18px] border border-slate-200/90 shadow-panel py-9 px-7">
            <h1 class="text-lg-pro font-semibold text-slate-900 tracking-tight mb-1">
              {{ title }}
            </h1>
            <p class="text-2xs text-slate-500 mb-6">
              Secure access to your team workspace.
            </p>
            <slot />
          </div>
        </div>
      </div>
      <div class="auth-glow" />
      <div class="auth-glow auth-glow-alt" />
    </main>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  isLogin: { type: Boolean, default: false },
});
</script>

<style scoped>
.auth-layout {
  background-image:
    radial-gradient(1100px 540px at -10% -20%, rgba(148, 163, 184, 0.2), transparent 56%),
    radial-gradient(980px 460px at 110% 0%, rgba(99, 102, 241, 0.12), transparent 56%);
}
.auth-glow {
  position: fixed;
  width: 360px;
  height: 360px;
  border-radius: 9999px;
  right: -90px;
  bottom: -110px;
  background: radial-gradient(circle at 30% 30%, rgba(56, 189, 248, 0.2), rgba(56, 189, 248, 0));
  pointer-events: none;
  z-index: 0;
}
.auth-glow-alt {
  left: -120px;
  top: 110px;
  right: auto;
  bottom: auto;
  background: radial-gradient(circle at 70% 60%, rgba(148, 163, 184, 0.2), rgba(148, 163, 184, 0));
}
</style>
