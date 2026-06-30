<script setup>
import { inject, watchEffect } from 'vue';
import AppIcon from '../ui/AppIcon.vue';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  breadcrumb: { type: Array, default: () => [] },
  badge: { type: String, default: '' },
})

const setHeaderBreadcrumbs = inject('setHeaderBreadcrumbs', null);

watchEffect(() => {
  if (setHeaderBreadcrumbs) {
    setHeaderBreadcrumbs(props.breadcrumb.length ? props.breadcrumb : [props.title]);
  }
});
</script>

<template>
  <div>
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <div class="flex items-center gap-2">
          <AppIcon v-if="icon" :name="icon" class="w-5 h-5 text-blue-500 flex-shrink-0" />
          <h1 class="text-lg font-bold text-slate-900">{{ title }}</h1>
          <span v-if="badge" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-2xs text-slate-600 ml-2">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-blue-500" />
            {{ badge }}
          </span>
        </div>
        <p v-if="subtitle" class="text-sm text-slate-500 mt-1">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
