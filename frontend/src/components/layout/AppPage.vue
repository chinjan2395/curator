<script setup>
defineProps({
  maxWidth: {
    type: String,
    default: '7xl',
    validator: (v) => ['4xl', '5xl', '6xl', '7xl', 'full'].includes(v),
  },
})
</script>

<template>
  <div class="min-h-screen">
    <div :class="['mx-auto px-6 py-6', maxWidth !== 'full' ? `max-w-${maxWidth}` : '']">
      <slot />
    </div>
  </div>
</template>
