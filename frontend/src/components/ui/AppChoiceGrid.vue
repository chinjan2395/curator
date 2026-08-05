<script setup>
/**
 * Radio group rendered as a grid of selectable tiles, for choices that are
 * easier to recognise by sight than by name. The tile's visual is supplied by
 * the caller through the `option` slot (`{ option, selected }`); this component
 * owns only selection, focus and the selected state ring.
 */
defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: '' },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="app-choice-grid" role="radiogroup" :aria-label="ariaLabel">
    <button
      v-for="option in options"
      :key="option[valueKey]"
      type="button"
      role="radio"
      :aria-checked="modelValue === option[valueKey]"
      :title="option[labelKey]"
      :class="[
        'app-choice-grid__item',
        { 'app-choice-grid__item--active': modelValue === option[valueKey] },
      ]"
      @click="$emit('update:modelValue', option[valueKey])"
    >
      <slot name="option" :option="option" :selected="modelValue === option[valueKey]" />
      <span class="app-choice-grid__label">{{ option[labelKey] }}</span>
    </button>
  </div>
</template>

<style scoped>
.app-choice-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.5rem;
}

@media (min-width: 640px) {
  .app-choice-grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }
}

.app-choice-grid__item {
  border: 1px solid #e6ebf2;
  border-radius: 0.625rem;
  background: #fff;
  padding: 0.4rem 0.35rem 0.35rem;
  cursor: pointer;
  text-align: center;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease,
    background-color 0.15s ease;
}

.app-choice-grid__item:hover {
  border-color: rgba(30, 58, 138, 0.3);
  background: #f8fafc;
}

.app-choice-grid__item:focus-visible {
  outline: 2px solid #7389ca;
  outline-offset: 2px;
}

.app-choice-grid__item--active {
  border-color: #1e3a8a;
  background: rgba(239, 246, 255, 0.75);
  box-shadow: 0 0 0 1px #1e3a8a;
}

.app-choice-grid__label {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.2;
  color: #475569;
}

.app-choice-grid__item--active .app-choice-grid__label {
  color: #1e3a8a;
}

@media (prefers-reduced-motion: reduce) {
  .app-choice-grid__item {
    transition: none;
  }
}
</style>
