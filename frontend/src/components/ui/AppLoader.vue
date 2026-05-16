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
const borderClasses = { sm: 'border-2', md: 'border-2', lg: 'border-[3px]' }
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-10 text-slate-400">
    <div
      :class="[
        'animate-spin rounded-full border-slate-200',
        sizeClasses[size],
        borderClasses[size],
      ]"
      style="border-top-color: #2563eb"
    />
    <span v-if="label" class="text-xs text-slate-400">{{ label }}</span>
  </div>
</template>
