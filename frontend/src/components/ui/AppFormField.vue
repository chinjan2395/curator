<script setup>
defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" :for="id" class="label-pro">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-0.5">*</span>
    </label>
    <slot />
    <p v-if="hint && !error" class="text-xs text-slate-400">{{ hint }}</p>
    <p v-if="error" class="text-xs text-red-500">{{ error }}</p>
  </div>
</template>
