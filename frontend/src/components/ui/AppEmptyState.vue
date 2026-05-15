<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
  title: { type: String, default: 'Nothing here yet' },
  description: { type: String, default: '' },
  icon: { type: String, default: 'circle' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center py-14 text-center">
    <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center text-2xl mb-4">
      <AppIcon :name="icon" class="w-7 h-7 text-slate-500" />
    </div>
    <h3 class="text-sm font-semibold text-slate-700 mb-1">{{ title }}</h3>
    <p v-if="description" class="text-xs text-slate-400 max-w-xs leading-relaxed">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>
