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
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
          @click="closable && $emit('close')"
        />

        <!-- Panel -->
        <div
          :class="[
            'relative flex w-full max-h-[calc(100vh-2rem)] flex-col bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-100',
            sizeClasses[size],
          ]"
        >
          <!-- Header -->
          <div
            v-if="title || closable"
            class="flex shrink-0 items-center justify-between px-6 py-4 border-b border-slate-100"
          >
            <h3 v-if="title" class="text-base font-semibold text-slate-800">{{ title }}</h3>
            <button
              v-if="closable"
              class="ml-auto p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
              @click="$emit('close')"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="min-h-0 flex-1 overflow-y-auto p-6">
            <slot />
          </div>

          <!-- Footer -->
          <div
            v-if="$slots.footer"
            class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4"
          >
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active {
  transition: opacity 0.2s ease;
}
.modal-leave-active {
  transition: opacity 0.15s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-active .relative,
.modal-leave-active .relative {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.modal-enter-from .relative {
  transform: translateY(-8px) scale(0.98);
  opacity: 0;
}
.modal-leave-to .relative {
  transform: translateY(4px) scale(0.98);
  opacity: 0;
}
</style>
