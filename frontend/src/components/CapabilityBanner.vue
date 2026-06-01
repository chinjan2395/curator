<template>
  <AppAlert v-if="visible" :variant="variant" class="mb-4">
    <p class="text-sm">{{ message }}</p>
    <router-link
      v-if="linkTo"
      :to="linkTo"
      class="inline-block mt-2 text-sm font-medium underline"
    >
      {{ linkLabel }}
    </router-link>
  </AppAlert>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { AppAlert } from './ui';
import { useCapabilities } from '../composables/useCapabilities';

const props = defineProps({
  context: {
    type: String,
    required: true,
    validator: (v) => ['ai', 'publish'].includes(v),
  },
});

const { capabilities, fetchCapabilities } = useCapabilities();

onMounted(() => fetchCapabilities());

const visible = computed(() => {
  const caps = capabilities.value;
  if (!caps) return false;
  if (props.context === 'ai') {
    return caps.ai?.driver === 'stub';
  }
  if (props.context === 'publish') {
    const native = caps.publish?.native || {};
    return Object.values(native).some((p) => !p.enabled);
  }
  return false;
});

const variant = computed(() => (props.context === 'ai' ? 'warning' : 'info'));

const message = computed(() => {
  const caps = capabilities.value;
  if (!caps) return '';
  if (props.context === 'ai') {
    return 'AI content is using stub mode (placeholder text). Set AI_DRIVER=groq or ollama and the matching API keys in the backend .env for real generation.';
  }
  const native = caps.publish?.native || {};
  const disabled = Object.entries(native)
    .filter(([, v]) => !v.enabled)
    .map(([k, v]) => `${k}: ${v.reason}`)
    .join('; ');
  return `Native publish is available for X, Facebook, and Instagram. Other platforms: ${disabled}. Reconnect integrations after scope changes.`;
});

const linkTo = computed(() => (props.context === 'ai' ? null : '/credentials'));
const linkLabel = computed(() => 'Open integrations');
</script>
