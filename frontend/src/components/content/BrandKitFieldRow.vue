<template>
  <div
    class="bk-row"
    :class="{ 'bk-row--overridden': isOverridden && !isRoot }"
  >
    <div class="bk-row__label">
      <label class="bk-row__name">{{ label }}</label>
      <code class="bk-row__path">{{ path }}</code>
      <p
        v-if="hint"
        class="bk-row__hint"
      >
        {{ hint }}
      </p>
    </div>

    <div class="bk-row__control">
      <AppCheckbox
        v-if="type === 'checkbox'"
        :model-value="Boolean(draft)"
        :disabled="disabled"
        @update:model-value="onImmediate"
      />

      <AppSelect
        v-else-if="type === 'select'"
        :model-value="draft"
        :show-placeholder="false"
        :disabled="disabled"
        select-class="!h-9 !text-xs"
        @update:model-value="onImmediate"
      >
        <option
          v-for="opt in options"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </AppSelect>

      <div
        v-else-if="type === 'color'"
        class="flex items-center gap-2"
      >
        <AppInput
          :model-value="draft"
          type="color"
          wrapper-class="!w-auto shrink-0"
          input-class="!h-9 !w-9 !p-0 rounded-lg border border-slate-300 cursor-pointer bg-white"
          @update:model-value="onDebounced"
        />
        <AppInput
          :model-value="draft"
          type="text"
          wrapper-class="flex-1 min-w-0"
          input-class="!h-9 !py-0 !text-xs font-mono uppercase max-w-[11rem]"
          @update:model-value="onDebounced"
        />
      </div>

      <AppInput
        v-else-if="type === 'number'"
        :model-value="draft"
        type="number"
        :min="min"
        :max="max"
        :step="step"
        input-class="!h-9 !text-xs max-w-[9rem]"
        :disabled="disabled"
        @update:model-value="onDebouncedNumber"
      />

      <AppInput
        v-else
        :model-value="draft"
        type="text"
        input-class="!h-9 !text-xs"
        :disabled="disabled"
        @update:model-value="onDebounced"
      />
    </div>

    <div
      v-if="!isRoot"
      class="bk-row__state"
    >
      <span
        v-if="isOverridden"
        class="bk-state bk-state--over"
      >
        <span class="bk-state__dot" />
        Overridden
      </span>
      <span
        v-else
        class="bk-state bk-state--inherit"
      >
        <span class="bk-state__dot" />
        Inherited
      </span>
      <AppButton
        v-if="isOverridden"
        variant="ghost"
        size="sm"
        class="bk-reset !px-1.5 !py-0.5"
        @click="$emit('reset', path)"
      >
        Reset
      </AppButton>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { AppButton, AppCheckbox, AppInput, AppSelect } from '../ui';

const props = defineProps({
  label: { type: String, required: true },
  path: { type: String, required: true },
  resolvedValue: { type: [String, Number, Boolean, null], default: null },
  isOverridden: { type: Boolean, default: false },
  isRoot: { type: Boolean, default: false },
  type: {
    type: String,
    default: 'text',
    validator: (v) => ['color', 'select', 'checkbox', 'number', 'text'].includes(v),
  },
  options: { type: Array, default: () => [] },
  min: { type: [String, Number], default: null },
  max: { type: [String, Number], default: null },
  step: { type: [String, Number], default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'reset']);

const draft = ref(props.resolvedValue);

watch(
  () => props.resolvedValue,
  (v) => {
    draft.value = v;
  },
);

let timer = null;

function onImmediate(value) {
  draft.value = value;
  emit('update', props.path, value);
}

function onDebounced(value) {
  draft.value = value;
  if (timer) clearTimeout(timer);
  timer = setTimeout(() => emit('update', props.path, value), 450);
}

function onDebouncedNumber(value) {
  const num = value === '' ? 0 : Number(value);
  draft.value = value;
  if (timer) clearTimeout(timer);
  timer = setTimeout(() => emit('update', props.path, num), 450);
}
</script>

<style scoped>
/*
 * Three-column field row: label + dot-path, control, inheritance state.
 * Deliberately borderless — the surrounding panel supplies the hairline and
 * zebra tint so a group of fields scans as one column rather than as a stack
 * of individually boxed tiles.
 */
.bk-row {
  display: grid;
  gap: 0.4rem;
  align-items: center;
  padding: 0.7rem 1rem;
}
.bk-row__label {
  min-width: 0;
}
.bk-row__name {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
  line-height: 1.3;
}
.bk-row__path {
  display: block;
  margin-top: 0.1rem;
  font-size: 0.66rem;
  color: #94a3b8;
  word-break: break-all;
}
.bk-row__hint {
  margin-top: 0.2rem;
  font-size: 0.7rem;
  color: #94a3b8;
}
.bk-row__control {
  min-width: 0;
}
.bk-row__state {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.bk-state {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.7rem;
  white-space: nowrap;
}
.bk-state__dot {
  width: 0.32rem;
  height: 0.32rem;
  border-radius: 999px;
  background: currentColor;
  flex: none;
}
.bk-state--inherit {
  color: #94a3b8;
}
.bk-state--over {
  color: #6d28d9;
  font-weight: 650;
}
.bk-reset {
  font-size: 0.68rem;
  color: #2f4da1 !important;
}
@media (min-width: 640px) {
  .bk-row {
    grid-template-columns: minmax(0, 10rem) minmax(0, 1fr) minmax(0, 7.5rem);
    gap: 1rem;
  }
  .bk-row__state {
    justify-content: flex-end;
  }
}
</style>
