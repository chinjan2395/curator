<script setup>
/**
 * Shared tab strip. Listed in the architecture guidelines' required component
 * set but not previously implemented — pages that needed tabs had none to use.
 *
 * Each tab is `{ key, label, dot?, count? }`. `dot` renders the amber
 * unsaved-changes marker so a tab can advertise pending edits while its panel
 * is collapsed; `count` renders a neutral numeric chip.
 */
defineProps({
  modelValue: { type: String, default: '' },
  tabs: { type: Array, default: () => [] },
  ariaLabel: { type: String, default: 'Sections' },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div
    class="flex items-center gap-1 px-2 pt-2 border-b border-slate-200 overflow-x-auto app-tabs"
    role="tablist"
    :aria-label="ariaLabel"
  >
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      role="tab"
      :aria-selected="modelValue === tab.key"
      :class="[
        'relative inline-flex items-center gap-1.5 px-2.5 pt-2 pb-2.5 rounded-t-lg whitespace-nowrap',
        'text-2xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-blue-200',
        modelValue === tab.key ? 'text-blue-900' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50',
      ]"
      @click="$emit('update:modelValue', tab.key)"
    >
      {{ tab.label }}
      <span
        v-if="tab.count"
        class="inline-flex items-center justify-center min-w-[1.1rem] px-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold"
      >
        {{ tab.count }}
      </span>
      <span
        v-if="tab.dot"
        class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"
        title="Unsaved changes in this section"
      />
      <span
        v-if="modelValue === tab.key"
        class="absolute left-1.5 right-1.5 -bottom-px h-0.5 rounded-t bg-blue-900"
        aria-hidden="true"
      />
    </button>
  </div>
</template>

<style scoped>
.app-tabs {
  scrollbar-width: none;
}
.app-tabs::-webkit-scrollbar {
  display: none;
}
</style>
