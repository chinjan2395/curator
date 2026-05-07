<script setup>
defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (v) => ['info', 'success', 'warning', 'danger'].includes(v),
  },
  title: { type: String, default: '' },
  dismissible: { type: Boolean, default: false },
})

defineEmits(['dismiss'])

const variantClasses = {
  info:    'bg-sky-50 border-sky-200 text-sky-800',
  success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
  warning: 'bg-amber-50 border-amber-200 text-amber-800',
  danger:  'bg-red-50 border-red-200 text-red-800',
}

const iconMap = {
  info:    'ℹ️',
  success: '✅',
  warning: '⚠️',
  danger:  '❌',
}
</script>

<template>
  <div :class="['flex gap-3 border rounded-lg p-4 text-sm', variantClasses[variant]]">
    <span class="shrink-0">{{ iconMap[variant] }}</span>
    <div class="flex-1">
      <p v-if="title" class="font-semibold mb-0.5">{{ title }}</p>
      <slot />
    </div>
    <button
      v-if="dismissible"
      class="shrink-0 text-current opacity-60 hover:opacity-100"
      @click="$emit('dismiss')"
    >
      ✕
    </button>
  </div>
</template>
