<script setup>
import { computed } from 'vue';
import { AppButton, AppIcon } from '../ui';

/**
 * Sticky unsaved-changes bar for the appearance form.
 *
 * Save used to live in the settings card header, which scrolled out of view as
 * soon as the user reached the colour or widget controls — and nothing marked
 * the page as dirty, so it was easy to leave edits unsaved and wonder why the
 * live embed hadn't changed. This appears only when there is something to save.
 */
const props = defineProps({
  count: { type: Number, default: 0 },
  saving: { type: Boolean, default: false },
});

defineEmits(['save', 'discard']);

const label = computed(
  () => `${props.count} unsaved appearance change${props.count === 1 ? '' : 's'}`,
);
</script>

<template>
  <div v-if="count > 0" class="publish-save-bar">
    <AppIcon name="warning" class="w-4 h-4 flex-shrink-0 text-amber-600" />
    <p class="flex-1 text-2xs text-amber-900">
      <strong class="font-semibold tabular-nums">{{ label }}</strong>
      — live sites still show the saved style.
    </p>
    <AppButton variant="ghost" size="sm" :disabled="saving" @click="$emit('discard')">
      Discard
    </AppButton>
    <AppButton size="sm" :disabled="saving" :loading="saving" @click="$emit('save')">
      {{ saving ? 'Saving…' : 'Save appearance' }}
    </AppButton>
  </div>
</template>

<style scoped>
.publish-save-bar {
  position: sticky;
  bottom: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.875rem;
  border: 1px solid #d0a368;
  border-radius: 0.75rem;
  background: #fdf6ec;
  box-shadow: 0 -2px 14px rgba(15, 23, 42, 0.06);
}
</style>
