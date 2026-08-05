<script setup>
import AppIcon from './AppIcon.vue'

/**
 * Compact single-choice control for 2–4 mutually exclusive view options
 * (viewport width, density, sort direction). Sits between AppTabs — which
 * switches whole panels — and AppSelect, which is for form values.
 *
 * Options are `{ value, label, icon? }`. `hideLabelsBelow` drops to icon-only
 * on narrow columns so the control never wraps.
 */
defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="app-segmented" role="group" :aria-label="ariaLabel">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :aria-pressed="modelValue === option.value"
      :title="option.label"
      :class="['app-segmented__btn', { 'app-segmented__btn--on': modelValue === option.value }]"
      @click="$emit('update:modelValue', option.value)"
    >
      <AppIcon v-if="option.icon" :name="option.icon" class="w-3.5 h-3.5" />
      <span :class="compact ? 'app-segmented__label' : ''">{{ option.label }}</span>
    </button>
  </div>
</template>

<style scoped>
.app-segmented {
  display: inline-flex;
  padding: 2px;
  border: 1px solid #e6ebf2;
  border-radius: 0.5rem;
  background: #f1f5f9;
}

.app-segmented__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.25rem 0.55rem;
  border: 0;
  border-radius: 0.375rem;
  background: none;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  transition:
    background-color 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.app-segmented__btn:hover {
  color: #334155;
}

.app-segmented__btn:focus-visible {
  outline: 2px solid #7389ca;
  outline-offset: 1px;
}

.app-segmented__btn--on {
  background: #fff;
  color: #1e3a8a;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

@media (max-width: 1279px) {
  .app-segmented__label {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .app-segmented__btn {
    transition: none;
  }
}
</style>
