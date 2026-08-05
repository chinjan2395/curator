<script setup>
import { AppCheckbox } from '../ui';

/**
 * Checkbox row used throughout the appearance panels — one row, one setting,
 * optional supporting line. Replaces the hand-rolled `<label class="flex …">`
 * blocks that were repeated a dozen times in the old Publish template.
 */
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <label
    class="flex items-center gap-3 -mx-2 px-2 py-2 rounded-lg cursor-pointer transition-colors hover:bg-slate-50"
    :class="disabled ? 'opacity-60 cursor-not-allowed' : ''"
  >
    <AppCheckbox
      :model-value="modelValue"
      :disabled="disabled"
      @update:model-value="$emit('update:modelValue', $event)"
    />
    <span class="min-w-0">
      <span class="block text-2xs font-medium text-slate-800">{{ title }}</span>
      <span v-if="description" class="block text-xs text-slate-400">{{ description }}</span>
    </span>
  </label>
</template>
