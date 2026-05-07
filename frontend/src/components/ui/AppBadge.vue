<script setup>
defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'success', 'danger', 'warning', 'info', 'purple'].includes(v),
  },
})

const variantClasses = {
  default: 'bg-slate-100 text-slate-600',
  success: 'bg-emerald-50 text-emerald-700',
  danger:  'bg-red-50 text-red-700',
  warning: 'bg-amber-50 text-amber-700',
  info:    'bg-sky-50 text-sky-700',
  purple:  'bg-violet-50 text-violet-700',
}
</script>

<template>
  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', variantClasses[variant]]">
    <slot />
  </span>
</template>
