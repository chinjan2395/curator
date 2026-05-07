<script setup>
defineProps({
  open: { type: Boolean, required: true },
  title: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
  },
  closable: { type: Boolean, default: true },
})

defineEmits(['close'])

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
      >
        <div
          class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
          @click="closable && $emit('close')"
        />

        <div :class="['relative w-full surface-card shadow-xl rounded-xl overflow-hidden', sizeClasses[size]]">
          <div v-if="title || closable" class="flex items-center justify-between px-5 py-4 border-b border-slate-200/80">
            <h3 v-if="title" class="text-sm font-semibold text-slate-800">{{ title }}</h3>
            <button
              v-if="closable"
              class="ml-auto text-slate-400 hover:text-slate-600 transition"
              @click="$emit('close')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-5">
            <slot />
          </div>

          <div v-if="$slots.footer" class="px-5 py-4 border-t border-slate-200/80 bg-slate-50/50 flex items-center justify-end gap-2">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
