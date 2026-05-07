<script setup>
defineProps({
  as: { type: String, default: 'h2' },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
  },
})

const sizeClasses = {
  sm: 'text-sm font-semibold text-slate-800',
  md: 'text-base font-semibold text-slate-800',
  lg: 'text-lg font-bold text-slate-900',
  xl: 'text-xl font-bold text-slate-900',
}
</script>

<template>
  <component :is="as" :class="sizeClasses[size]">
    <slot />
  </component>
</template>
