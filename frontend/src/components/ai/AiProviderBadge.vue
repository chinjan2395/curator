<script setup>
import { computed } from 'vue';
import AiProviderIcon from './AiProviderIcon.vue';
import { getAiProviderMeta } from '../../constants/aiProviders';

const props = defineProps({
  id: { type: String, required: true },
  /** sm | md */
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v),
  },
});

const meta = computed(() => getAiProviderMeta(props.id));

const tileStyle = computed(() => ({
  background: meta.value.tileBg || '#FFFFFF',
  color: meta.value.tileFg,
  border: meta.value.tileBorder || '1px solid #E6EBF2',
}));
</script>

<template>
  <div
    class="ai-provider-badge"
    :class="size === 'sm' ? 'ai-provider-badge--sm' : 'ai-provider-badge--md'"
    :style="tileStyle"
    :title="meta.label"
  >
    <AiProviderIcon :id="meta.icon" :letter="meta.letter" class="ai-provider-badge__icon" />
  </div>
</template>

<style scoped>
.ai-provider-badge {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 0.85rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.1);
}

.ai-provider-badge--md {
  width: 2.75rem;
  height: 2.75rem;
}

.ai-provider-badge--sm {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.7rem;
}

.ai-provider-badge__icon {
  width: 55%;
  height: 55%;
}

.ai-provider-badge--md .ai-provider-badge__icon.ai-provider-icon__letter {
  font-size: 1.15rem;
}

.ai-provider-badge--sm .ai-provider-badge__icon.ai-provider-icon__letter {
  font-size: 0.95rem;
}
</style>
