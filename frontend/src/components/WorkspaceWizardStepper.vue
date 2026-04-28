<template>
  <div class="wizard-stepper" aria-label="Workspace setup progress">
    <router-link
      v-for="step in steps"
      :key="step.key"
      :to="getStepPath(step.key)"
      :class="['wizard-step', stepClass(step.key)]"
    >
      <span class="wizard-step__index">{{ step.index }}</span>
      <div>
        <div class="wizard-step__label">{{ step.label }}</div>
        <div class="wizard-step__meta">{{ step.meta }}</div>
      </div>
    </router-link>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();

const props = defineProps({
  current: {
    type: String,
    required: true,
    validator: (value) => ['feed', 'curate', 'publish'].includes(value),
  },
  workspaceId: String,
});

const steps = [
  { key: 'feed', index: 1, label: 'Feed', meta: 'Connect source content' },
  { key: 'curate', index: 2, label: 'Curate', meta: 'Approve and reject posts' },
  { key: 'publish', index: 3, label: 'Publish', meta: 'Embed and go live' },
];

function getStepPath(key) {
  const wsId = props.workspaceId || route.params.workspaceId;

  if (key === 'feed') return wsId ? `/workspaces/${wsId}/feeds` : '#';
  if (key === 'curate') return wsId ? `/workspaces/${wsId}/curate` : '#';
  if (key === 'publish') return wsId ? `/workspaces/${wsId}/publish` : '#';
  return '#';
}

function stepClass(key) {
  const currentIndex = steps.findIndex((step) => step.key === props.current);
  const stepIndex = steps.findIndex((step) => step.key === key);

  if (stepIndex === currentIndex) return 'wizard-step--active';
  if (stepIndex < currentIndex) return 'wizard-step--done';
  return '';
}
</script>

<style scoped>
.wizard-stepper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex: 1 1 180px;
  padding: 0.7rem 0.8rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  background: rgba(248, 250, 252, 0.82);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s ease;
}

.wizard-step:hover {
  border-color: rgba(99, 102, 241, 0.3);
  background: rgba(248, 250, 252, 0.95);
}

.wizard-step--active {
  border-color: rgba(99, 102, 241, 0.45);
  background: rgba(238, 242, 255, 0.72);
}

.wizard-step--done {
  border-color: rgba(191, 219, 254, 0.8);
}

.wizard-step__index {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgb(51 65 85);
  background: rgba(226, 232, 240, 0.95);
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: rgb(67 56 202);
  background: rgba(199, 210, 254, 0.95);
}

.wizard-step--done .wizard-step__index {
  color: rgb(30 64 175);
  background: rgba(219, 234, 254, 0.95);
}

.wizard-step__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(30 41 59);
}

.wizard-step__meta {
  font-size: 0.7rem;
  color: rgb(100 116 139);
}
</style>
