<script setup>
defineProps({
  spacing: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
})

const spacingClasses = { sm: 'space-y-3', md: 'space-y-5', lg: 'space-y-8' }
</script>

<template>
  <section :class="spacingClasses[spacing]">
    <slot />
  </section>
</template>
