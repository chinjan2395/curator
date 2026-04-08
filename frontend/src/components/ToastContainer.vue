<template>
  <div class="toast-container fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="t in toast.items"
        :key="t.id"
        class="pointer-events-auto rounded-md border px-3 py-2.5 text-sm-pro shadow-card"
        :class="toastClass(t.type)"
      >
        <div class="flex items-center justify-between gap-2">
          <span>{{ t.message }}</span>
          <button type="button" class="shrink-0 text-slate-400 hover:text-slate-600" @click="toast.remove(t.id)" aria-label="Dismiss">×</button>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToastStore } from '../stores/toast';

const toast = useToastStore();

function toastClass(type) {
  switch (type) {
    case 'success':
      return 'bg-emerald-50 border-emerald-200 text-emerald-800';
    case 'error':
      return 'bg-red-50 border-red-200 text-red-800';
    default:
      return 'bg-white border-slate-200 text-slate-800';
  }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(1rem);
}
</style>
