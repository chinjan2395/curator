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
  <label :class="['inline-flex items-center gap-2 cursor-pointer', disabled ? 'opacity-60 cursor-not-allowed' : '']">
    <input
      :id="id"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-300 transition"
      @change="$emit('update:modelValue', $event.target.checked)"
    />
    <span v-if="label" class="text-sm text-slate-700">{{ label }}</span>
    <slot v-else />
  </label>
</template>
