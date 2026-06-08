<template>
  <AppCard padding="none" class="content-library-row">
    <div class="content-library-row__inner">
      <div v-if="$slots.leading" class="content-library-row__leading">
        <slot name="leading" />
      </div>
      <div class="content-library-row__content min-w-0">
        <slot />
      </div>
      <div v-if="$slots.actions" class="content-library-row__actions">
        <slot name="actions" />
      </div>
    </div>
  </AppCard>
</template>

<script setup>
import { AppCard } from '../ui';
</script>

<style scoped>
.content-library-row__inner {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.85rem 1rem;
}

.content-library-row__leading {
  flex-shrink: 0;
}

.content-library-row__content {
  flex: 1 1 auto;
}

.content-library-row__actions {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
</style>
