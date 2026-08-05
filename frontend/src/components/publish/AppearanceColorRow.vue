<script setup>
import { AppInput } from '../ui';

/**
 * Swatch + hex pair for a single embed colour. Both inputs write the same
 * value, so picking and pasting stay in sync.
 */
defineProps({
  modelValue: { type: String, default: '#000000' },
  label: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div class="flex items-center gap-3 -mx-2 px-2 py-1.5 rounded-lg transition-colors hover:bg-slate-50">
    <span class="w-20 shrink-0 text-xs font-semibold text-slate-600">{{ label }}</span>
    <AppInput
      :model-value="modelValue"
      type="color"
      :aria-label="`${label} colour picker`"
      wrapper-class="!w-auto shrink-0"
      input-class="!h-8 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
      @update:model-value="$emit('update:modelValue', $event)"
    />
    <AppInput
      :model-value="modelValue"
      type="text"
      :aria-label="`${label} hex value`"
      input-class="!h-8 !py-1 !text-xs font-mono uppercase"
      @update:model-value="$emit('update:modelValue', $event)"
    />
  </div>
</template>
