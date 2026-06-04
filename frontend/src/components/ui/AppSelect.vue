<script setup>
defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select an option' },
  showPlaceholder: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  selectClass: { type: String, default: '' },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
  id: { type: String, default: '' },
  wrapperClass: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'change'])

function onChange(event) {
  const value = event.target.value
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <div :class="['w-full', wrapperClass]">
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      :class="[
        'input-pro',
        error ? 'border-red-400 focus:ring-red-300' : '',
        disabled ? 'opacity-60 cursor-not-allowed bg-slate-50' : '',
        selectClass,
      ]"
      @change="onChange"
    >
      <option v-if="showPlaceholder" value="" disabled>{{ placeholder }}</option>
      <slot>
        <option
          v-for="option in options"
          :key="option[valueKey]"
          :value="option[valueKey]"
        >
          {{ option[labelKey] }}
        </option>
      </slot>
    </select>
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>
