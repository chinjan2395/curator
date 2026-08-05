<script setup>
import { AppBadge, AppButton, AppText, AppTitle } from '../ui';
import { useAppearanceOverrides } from '../../composables/useAppearanceOverrides';

/**
 * One labelled group inside an appearance tab panel. Keeps heading weight,
 * divider and hint placement identical across all five panels.
 *
 * When a brand kit editor hosts the panels it also renders the group's
 * overridden fields as removable chips, so inheritance stays visible and
 * per-field resettable without forking the panels.
 */
const props = defineProps({
  title: { type: String, required: true },
  note: { type: String, default: '' },
  badge: { type: String, default: '' },
  hint: { type: String, default: '' },
  /** Capability-matrix coordinates; only needed when overrides are in play. */
  tab: { type: String, default: '' },
  group: { type: String, default: '' },
});

const { active, overrides, reset } = useAppearanceOverrides(props.tab, props.group);
</script>

<template>
  <section class="publish-group">
    <header class="flex flex-wrap items-center gap-2 mb-3">
      <AppTitle as="h3" size="sm">{{ title }}</AppTitle>
      <AppBadge v-if="badge" variant="info">{{ badge }}</AppBadge>
      <AppText v-if="note" size="xs" muted class="ml-auto">{{ note }}</AppText>
    </header>

    <div v-if="active" class="publish-group__overrides">
      <template v-if="overrides.length">
        <span class="publish-group__overrides-label">Overridden</span>
        <AppButton
          v-for="o in overrides"
          :key="o.path"
          variant="ghost"
          size="sm"
          class="publish-group__chip"
          :title="`Reset ${o.label} to the Master kit`"
          @click="reset(o.path)"
        >
          {{ o.label }}
          <span aria-hidden="true">×</span>
        </AppButton>
      </template>
      <span v-else class="publish-group__inherited">All inherited from Master</span>
    </div>

    <slot />

    <AppText v-if="hint" size="xs" muted class="mt-2">{{ hint }}</AppText>
  </section>
</template>

<style scoped>
.publish-group + .publish-group {
  margin-top: 1.375rem;
  padding-top: 1.375rem;
  border-top: 1px solid #f1f5f9;
}

.publish-group__overrides {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.75rem;
}

.publish-group__overrides-label {
  font-size: 0.68rem;
  font-weight: 650;
  color: #6d28d9;
}

.publish-group__inherited {
  font-size: 0.68rem;
  color: #94a3b8;
}

.publish-group__chip {
  gap: 0.3rem;
  padding: 0.15rem 0.5rem !important;
  border-radius: 9999px;
  border: 1px solid #ddd6fe;
  background: #f5f3ff;
  font-size: 0.68rem !important;
  color: #6d28d9 !important;
}

.publish-group__chip:hover {
  border-color: #c4b5fd;
  background: #ede9fe;
}
</style>
