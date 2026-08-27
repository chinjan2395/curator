<template>
  <AppCard class="p-8">
    <AppEmptyState
      icon="lock"
      :title="title"
      :description="requirement.detail"
    >
      <div class="flex flex-wrap items-center justify-center gap-2">
        <AppButton
          v-if="requirement.fixable_by_me"
          :to="requirement.route"
          variant="primary"
          size="sm"
        >
          {{ actionLabel }}
        </AppButton>

        <AppText
          v-else
          size="xs"
          muted
        >
          Only an admin can do this. Ask them to open {{ requirement.route }}.
        </AppText>
      </div>
    </AppEmptyState>
  </AppCard>
</template>

<script setup>
import { computed } from 'vue';
import { AppButton, AppCard, AppEmptyState, AppText } from '../ui';

const props = defineProps({
  requirement: {
    type: Object,
    required: true,
  },
});

// The lock always names which prerequisite is missing — never a bare
// "you don't have access".
const title = computed(() => {
  if (props.requirement.state === 'partial') {
    return `${props.requirement.label} — needs reconnecting`;
  }
  return props.requirement.label;
});

const actionLabel = computed(() => (props.requirement.state === 'partial' ? 'Reconnect' : 'Set it up'));
</script>
