<script setup>
defineProps({
  padding: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
  },
})

const paddingClasses = {
  none: '',
  sm: 'p-3',
  md: 'p-5',
  lg: 'p-7',
}
</script>

<template>
  <div :class="['surface-card', paddingClasses[padding]]">
    <slot />
  </div>
</template>
