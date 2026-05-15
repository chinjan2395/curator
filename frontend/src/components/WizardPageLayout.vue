<template>
  <div class="wizard-page">
    <div
      class="wizard-page__sticky-header"
      :class="{ 'wizard-page__sticky-header--compact': !noSticky && isHeaderCompact, 'wizard-page__sticky-header--static': noSticky }"
    >
      <div class="mb-5 wizard-page__title-block">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <h1 class="text-xl font-bold text-slate-900 tracking-tight wizard-page__title">{{ title }}</h1>
            <p class="text-sm text-slate-500 mt-1 wizard-page__description">{{ description }}</p>
          </div>
          <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
            <slot name="actions" />
          </div>
        </div>
      </div>
      <WorkspaceWizardStepper :current="current" :workspaceId="workspaceId" />
    </div>
    <div class="wizard-page__body">
      <slot />
    </div>
    <div v-if="$slots.footer" class="wizard-page__footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { inject, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import WorkspaceWizardStepper from './WorkspaceWizardStepper.vue';

const props = defineProps({
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
  noSticky: {
    type: Boolean,
    default: false,
  },
  breadcrumb: {
    type: Array,
    default: () => [],
  },
});

const isHeaderCompact = ref(false);

const setHeaderBreadcrumbs = inject('setHeaderBreadcrumbs', null);
watchEffect(() => {
  if (setHeaderBreadcrumbs) {
    const crumbs = props.breadcrumb.length ? props.breadcrumb : [props.title];
    setHeaderBreadcrumbs(crumbs);
  }
});

function handleWindowScroll() {
  if (typeof window === 'undefined') return;
  isHeaderCompact.value = window.scrollY > 16;
}

onMounted(() => {
  handleWindowScroll();
  window.addEventListener('scroll', handleWindowScroll, { passive: true });
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleWindowScroll);
});
</script>

<style scoped>
.wizard-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.wizard-page__sticky-header.wizard-page__sticky-header--static {
  position: relative;
  top: auto;
  backdrop-filter: none;
  box-shadow: none;
  border-radius: 0.95rem;
  padding: 0.65rem;
  border: 1px solid rgba(226, 232, 240, 0.85);
}

.wizard-page__sticky-header {
  position: sticky;
  top: 0.35rem;
  z-index: 20;
  padding: 0.65rem;
  border-radius: 0.95rem;
  backdrop-filter: saturate(130%) blur(8px);
  background: rgba(255, 255, 255, 0.86);
  border: 1px solid rgba(226, 232, 240, 0.85);
  box-shadow: 0 8px 24px -20px rgba(15, 23, 42, 0.35);
  transition:
    padding 0.2s ease,
    background-color 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.wizard-page__title-block {
  transition: margin-bottom 0.2s ease;
}

.wizard-page__title,
.wizard-page__description {
  transition: font-size 0.2s ease, margin 0.2s ease;
}

.wizard-page__sticky-header--compact {
  padding: 0.38rem 0.62rem;
  box-shadow: 0 10px 30px -22px rgba(15, 23, 42, 0.45);
  border-color: rgba(203, 213, 225, 0.95);
}

.wizard-page__sticky-header--compact .wizard-page__title-block {
  margin-bottom: 0.2rem;
}

.wizard-page__sticky-header--compact .wizard-page__title {
  font-size: 1rem;
}

.wizard-page__sticky-header--compact .wizard-page__description {
  font-size: 0.75rem;
  margin-top: 0.08rem;
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
