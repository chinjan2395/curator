<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <label :class="['inline-flex items-center gap-2.5 cursor-pointer select-none', disabled ? 'opacity-60 cursor-not-allowed' : '']">
    <input
      :id="id"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      class="w-4 h-4 rounded-md border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-200 focus:ring-offset-0 transition"
      @change="$emit('update:modelValue', $event.target.checked)"
    />
    <span v-if="label" class="text-sm text-slate-700 font-medium">{{ label }}</span>
    <slot v-else />
  </label>
</template>
