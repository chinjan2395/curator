<template>
  <div class="wizard-page">
    <nav class="page-breadcrumb">
      <slot name="breadcrumb" />
    </nav>
    <div class="wizard-page__header">
      <div>
        <h1 class="page-title">{{ title }}</h1>
        <p class="page-kicker">{{ description }}</p>
      </div>
    </div>
    <WorkspaceWizardStepper :current="current" :workspaceId="workspaceId" class="mt-1" />
    <div class="wizard-page__actions">
      <slot name="actions" />
    </div>
    <div class="wizard-page__body">
      <slot />
    </div>
    <div class="wizard-page__footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import WorkspaceWizardStepper from './WorkspaceWizardStepper.vue';

defineProps({
  current: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  workspaceId: String,
});
</script>

<style scoped>
.wizard-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.wizard-page__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.wizard-page__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.wizard-page__body {
  margin-top: 1rem;
}

.wizard-page__footer {
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(226, 232, 240, 0.8);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.wizard-page__footer > :deep(*) {
  white-space: nowrap;
}
</style>
