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
  gap: 0.625rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1 1 160px;
  padding: 0.75rem 1rem;
  border-radius: 0.875rem;
  border: 1px solid #e6ebf2;
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: all 0.15s ease;
  box-shadow: 0 1px 2px rgba(15,23,42,0.04);
}

.wizard-step:hover {
  border-color: rgba(30, 58, 138, 0.25);
  background: #f8fafc;
}

.wizard-step--active {
  border-color: rgba(30, 58, 138, 0.35);
  background: rgba(239, 246, 255, 0.8);
}

.wizard-step--done {
  border-color: rgba(30, 58, 138, 0.18);
  background: #fff;
}

.wizard-step__index {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: #fff;
  background: #1e3a8a;
  border-color: #1e3a8a;
}

.wizard-step--done .wizard-step__index {
  color: #1e3a8a;
  background: #dbeafe;
  border-color: #bfdbfe;
}

.wizard-step__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1e293b;
}

.wizard-step--active .wizard-step__label {
  color: #1e3a8a;
}

.wizard-step__meta {
  font-size: 0.6875rem;
  color: #94a3b8;
  margin-top: 0.1rem;
}
</style>
