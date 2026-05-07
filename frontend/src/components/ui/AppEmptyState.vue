<script setup>
defineProps({
  title: { type: String, default: 'Nothing here yet' },
  description: { type: String, default: '' },
  icon: { type: String, default: '📭' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <span class="text-4xl mb-4">{{ icon }}</span>
    <h3 class="text-sm font-semibold text-slate-700 mb-1">{{ title }}</h3>
    <p v-if="description" class="text-xs text-slate-400 max-w-xs">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>
