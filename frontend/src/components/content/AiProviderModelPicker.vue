<template>
  <div class="flex flex-wrap items-center gap-2">
    <AppSelect
      :model-value="provider"
      placeholder="Use my default"
      select-class="!h-8 !text-xs !py-1"
      wrapper-class="!w-auto min-w-[9rem]"
      @update:model-value="onProviderChange"
    >
      <option value="">Use my default</option>
      <option
        v-for="option in providerOptions"
        :key="option.value"
        :value="option.value"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </option>
    </AppSelect>

    <AppSelect
      :model-value="model"
      placeholder="Use my default"
      select-class="!h-8 !text-xs !py-1"
      wrapper-class="!w-auto min-w-[9rem]"
      @update:model-value="onModelChange"
    >
      <option value="">Use my default</option>
      <option
        v-for="option in modelOptions"
        :key="option.value"
        :value="option.value"
        :disabled="option.disabled"
      >
        {{ option.label }}
      </option>
    </AppSelect>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAiSettingsStore } from '../../stores/aiSettings';
import { AppSelect } from '../ui';

const props = defineProps({
  kind: { type: String, required: true },
  modelValue: { type: Object, default: () => ({ provider: '', model: '' }) },
  /** Image kind only: disable providers that cannot use a reference image. */
  requireReference: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const aiSettings = useAiSettingsStore();

const provider = computed(() => props.modelValue?.provider || '');
const model = computed(() => props.modelValue?.model || '');

const providerOptions = computed(() => {
  if (props.kind === 'text') return aiSettings.contentProviderOptions;
  return props.requireReference ? aiSettings.providerOptionsForReference : aiSettings.providerOptions;
});

const modelOptions = computed(() => {
  if (props.kind === 'text') {
    return provider.value
      ? aiSettings.contentModelOptionsFor(provider.value, model.value)
      : aiSettings.allContentModelOptions(model.value);
  }
  return provider.value
    ? aiSettings.modelOptionsFor(provider.value, model.value)
    : aiSettings.allModelOptions(model.value);
});

function onProviderChange(value) {
  emit('update:modelValue', { provider: value, model: '' });
}

function onModelChange(value) {
  emit('update:modelValue', { provider: provider.value, model: value });
}
</script>
