<script setup>
defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  label: { type: String, default: '' },
})

const sizeClasses = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-10 w-10' }
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-10 text-slate-400">
    <svg
      :class="['animate-spin', sizeClasses[size]]"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
    </svg>
    <span v-if="label" class="text-xs">{{ label }}</span>
  </div>
</template>
