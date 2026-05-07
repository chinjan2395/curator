<script setup>
defineProps({
  as: { type: String, default: 'p' },
  size: {
    type: String,
    default: 'sm',
    validator: (v) => ['xs', 'sm', 'base'].includes(v),
  },
  muted: { type: Boolean, default: false },
})

const sizeClasses = { xs: 'text-xs', sm: 'text-sm', base: 'text-base' }
</script>

<template>
  <component
    :is="as"
    :class="[sizeClasses[size], muted ? 'text-slate-500' : 'text-slate-700']"
  >
    <slot />
  </component>
</template>
