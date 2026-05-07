<script setup>
import { useAttrs } from 'vue'

defineOptions({ inheritAttrs: false })

defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  id: { type: String, default: '' },
  min: { type: [String, Number], default: null },
  max: { type: [String, Number], default: null },
  step: { type: [String, Number], default: null },
  rows: { type: Number, default: 4 },
  inputClass: { type: String, default: '' },
})

defineEmits(['update:modelValue'])

const attrs = useAttrs()
</script>

<template>
  <div class="w-full">
    <textarea
      v-if="type === 'textarea'"
      :id="id"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="disabled"
      v-bind="attrs"
      :class="[
        'input-pro',
        error ? 'border-red-400 focus:ring-red-300 focus:border-red-400' : '',
        disabled ? 'opacity-60 cursor-not-allowed bg-slate-50' : '',
        inputClass,
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <input
      v-else
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :min="min"
      :max="max"
      :step="step"
      v-bind="attrs"
      :class="[
        'input-pro',
        error ? 'border-red-400 focus:ring-red-300 focus:border-red-400' : '',
        disabled ? 'opacity-60 cursor-not-allowed bg-slate-50' : '',
        inputClass,
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>
