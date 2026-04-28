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
  gap: 0.75rem;
  flex: 1 1 180px;
  padding: 0.75rem 0.9rem;
  border-radius: 16px;
  background: #F4F4F6;
  text-decoration: none;
  color: inherit;
  transition: background-color 0.15s ease;
}

.wizard-step:hover {
  background: #EBF1FB;
}

.wizard-step--active {
  background: #EBF1FB;
  outline: 2px solid #1259C3;
  outline-offset: -2px;
}

.wizard-step--done {
  background: #F4F4F6;
}

.wizard-step__index {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: #6E6E73;
  background: #E5E5EA;
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: #fff;
  background: #1259C3;
}

.wizard-step--done .wizard-step__index {
  color: #1259C3;
  background: #D0E4FF;
}

.wizard-step__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1C1C1E;
}

.wizard-step__meta {
  font-size: 0.7rem;
  color: #6E6E73;
}
</style>
