<template>
  <div class="wizard-page">
    <div class="space-y-3 mb-5">
      <nav v-if="$slots.breadcrumb" class="page-breadcrumb flex items-center gap-1 text-sm text-slate-600">
        <slot name="breadcrumb" />
      </nav>
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <h1 class="text-lg font-bold text-slate-900">{{ title }}</h1>
          <p class="text-sm text-slate-600 mt-1">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
          <slot name="actions" />
        </div>
      </div>
    </div>
    <WorkspaceWizardStepper :current="current" :workspaceId="workspaceId" class="mt-1" />
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
