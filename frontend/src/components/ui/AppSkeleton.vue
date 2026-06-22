<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'line',
    validator: (value) => ['line', 'block', 'avatar', 'card'].includes(value),
  },
  lines: {
    type: Number,
    default: 1,
  },
});

const lineWidths = ['100%', '86%', '92%', '70%'];

const rootClass = computed(() => {
  switch (props.variant) {
    case 'avatar':
      return 'app-skeleton app-skeleton--avatar';
    case 'card':
      return 'app-skeleton app-skeleton--card';
    case 'block':
      return 'app-skeleton app-skeleton--block';
    default:
      return 'app-skeleton app-skeleton--line';
  }
});
</script>

<template>
  <div v-if="variant === 'line'" class="space-y-2" aria-hidden="true">
    <div
      v-for="n in Math.max(lines, 1)"
      :key="n"
      class="app-skeleton app-skeleton--line"
      :style="{ width: lineWidths[(n - 1) % lineWidths.length] }"
    />
  </div>
  <div v-else :class="rootClass" aria-hidden="true" />
</template>

<style scoped>
.app-skeleton {
  position: relative;
  overflow: hidden;
  background: linear-gradient(90deg, rgba(226, 232, 240, 0.78), rgba(241, 245, 249, 0.98), rgba(226, 232, 240, 0.78));
  background-size: 220% 100%;
  animation: app-skeleton-shimmer 1.3s ease-in-out infinite;
}

.app-skeleton--line {
  height: 0.8rem;
  border-radius: 9999px;
}

.app-skeleton--block {
  width: 100%;
  min-height: 6rem;
  border-radius: 1rem;
}

.app-skeleton--avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 9999px;
}

.app-skeleton--card {
  width: 100%;
  min-height: 12rem;
  border-radius: 1rem;
}

@keyframes app-skeleton-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
</style>
