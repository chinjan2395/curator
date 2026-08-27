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
import { useNavigationVisibility } from '../composables/useNavigationVisibility';
import { useSetupStore } from '../stores/setup';

const props = defineProps({
  context: {
    type: String,
    required: true,
    validator: (v) => ['ai', 'publish'].includes(v),
  },
});

const { capabilities, fetchCapabilities } = useCapabilities();
const { isMenuEnabled } = useNavigationVisibility();
const setup = useSetupStore();

onMounted(() => {
  // The AI banner reads the setup contract (which honours BYOK keys); only the
  // publish banner still needs the capabilities payload.
  if (props.context === 'ai') {
    setup.ensureLoaded();
    return;
  }
  fetchCapabilities();
});

const aiProvider = computed(() => setup.requirement('ai_provider'));

const visible = computed(() => {
  if (props.context === 'ai') {
    return Boolean(aiProvider.value) && aiProvider.value.state !== 'satisfied';
  }
  const caps = capabilities.value;
  if (!caps) return false;
  if (props.context === 'publish') {
    const native = caps.publish?.native || {};
    return Object.values(native).some((p) => !p.enabled);
  }
  return false;
});

const variant = computed(() => (props.context === 'ai' ? 'warning' : 'info'));

const message = computed(() => {
  if (props.context === 'ai') {
    return aiProvider.value?.detail
      || 'No AI provider has a usable key, so captions and campaigns cannot generate.';
  }
  const caps = capabilities.value;
  if (!caps) return '';
  const native = caps.publish?.native || {};
  const disabled = Object.entries(native)
    .filter(([, v]) => !v.enabled)
    .map(([k, v]) => `${k}: ${v.reason}`)
    .join('; ');
  return `Native publish is available for X, Facebook, Instagram, TikTok, Threads, and LinkedIn. Other platforms: ${disabled}. Reconnect integrations after scope changes.`;
});

const linkTo = computed(() => {
  // Point at the page that actually fixes it. BYOK keys live in AI Settings —
  // telling users to edit the backend .env has not been true since BYOK shipped.
  if (props.context === 'ai') {
    return isMenuEnabled('ai-settings') ? '/settings/ai' : null;
  }
  if (!isMenuEnabled('integrations')) return null;
  return '/credentials';
});
const linkLabel = computed(() => (props.context === 'ai' ? 'Open AI Settings' : 'Open integrations'));
</script>
