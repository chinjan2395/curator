<script setup>
import { computed } from 'vue'

const props = defineProps({
  padding: {
    type: String,
    default: 'md',
    validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
  },
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'base', 'metric', 'panel'].includes(v),
  },
})

const paddingClasses = {
  none: '',
  sm: 'p-3',
  md: 'p-5',
  lg: 'p-7',
}

const cardClasses = computed(() => [
  'app-card',
  'surface-card',
  props.variant === 'metric' ? 'app-card--metric' : '',
  props.variant === 'panel' ? 'app-card--panel' : '',
  paddingClasses[props.padding],
])
</script>

<template>
  <article :class="cardClasses">
    <header v-if="$slots.header || $slots.icon || $slots.metric || $slots.trend || $slots.action" class="app-card-header">
      <div class="app-card-header-main">
        <div v-if="$slots.icon" class="app-card-icon">
          <slot name="icon" />
        </div>
        <div class="app-card-heading">
          <slot name="header" />
          <slot name="metric" />
        </div>
      </div>

      <div v-if="$slots.trend || $slots.action" class="app-card-header-side">
        <slot name="trend" />
        <slot name="action" />
      </div>
    </header>

    <section
      v-if="$slots.default || $slots.body"
      :class="['app-card-body', ($slots.header || $slots.icon || $slots.metric || $slots.trend || $slots.action) ? 'app-card-body--with-header' : '']"
    >
      <slot />
      <slot name="body" />
    </section>
  </article>
</template>
