<script setup>
import { computed } from 'vue';
import SocialIcon from './SocialIcon.vue';
import { getPlatformLabel, getPlatformMeta, normalizePlatformType } from '../constants/socialPlatforms';

const props = defineProps({
  type: {
    type: String,
    default: 'other',
  },
  /** sm | md */
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v),
  },
  /** inline (default) | badge (colored icon tile) | pill (Feeds list style) */
  variant: {
    type: String,
    default: 'inline',
    validator: (v) => ['inline', 'badge', 'pill'].includes(v),
  },
  showLabel: {
    type: Boolean,
    default: true,
  },
  /** Appended after label, e.g. " (2)" */
  suffix: {
    type: String,
    default: '',
  },
  labelClass: {
    type: String,
    default: '',
  },
});

const iconType = computed(() => normalizePlatformType(props.type));
const label = computed(() => getPlatformLabel(props.type));
const meta = computed(() => getPlatformMeta(props.type));

const iconWrapStyle = computed(() => ({
  background: meta.value.softBg,
  color: meta.value.color,
}));

const iconClass = computed(() => (props.size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'));
</script>

<template>
  <span
    v-if="variant === 'pill'"
    class="type-pill"
    :class="`type-pill--${iconType}`"
  >
    <span class="type-pill__icon" :style="iconWrapStyle">
      <SocialIcon :type="iconType" :class="iconClass" />
    </span>
    <span v-if="showLabel" class="type-pill__label" :class="labelClass">{{ label }}{{ suffix }}</span>
  </span>

  <span
    v-else
    class="social-platform-label"
    :class="[
      size === 'sm' ? 'social-platform-label--sm' : 'social-platform-label--md',
      variant === 'badge' ? 'social-platform-label--badge' : '',
    ]"
  >
    <span
      class="social-platform-label__icon"
      :class="variant === 'badge' ? 'social-platform-label__icon--badge' : ''"
      :style="variant === 'badge' ? iconWrapStyle : undefined"
      aria-hidden="true"
    >
      <SocialIcon :type="iconType" :class="iconClass" />
    </span>
    <span v-if="showLabel" class="social-platform-label__text" :class="labelClass">
      {{ label }}<span v-if="suffix" class="social-platform-label__suffix">{{ suffix }}</span>
    </span>
    <slot />
  </span>
</template>

<style scoped>
.social-platform-label {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  min-width: 0;
}

.social-platform-label--sm {
  gap: 0.35rem;
}

.social-platform-label__icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: currentColor;
}

.social-platform-label__icon--badge {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 999px;
}

.social-platform-label__text {
  font-weight: 600;
  color: #0f172a;
  line-height: 1.2;
}

.social-platform-label--sm .social-platform-label__text {
  font-size: 0.82rem;
}

.social-platform-label--md .social-platform-label__text {
  font-size: 0.875rem;
}

.social-platform-label__suffix {
  font-weight: 500;
  color: #64748b;
}

/* Pill variant (matches FeedsList type-pill) */
.type-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.2rem 0.55rem 0.2rem 0.25rem;
  border-radius: 999px;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  font-size: 0.75rem;
  font-weight: 500;
  color: #334155;
  white-space: nowrap;
}

.type-pill__icon {
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.type-pill__label {
  line-height: 1.2;
}

.type-pill--youtube .type-pill__icon { background: rgba(254, 226, 226, 0.96); color: rgb(220 38 38); }
.type-pill--facebook .type-pill__icon { background: rgba(219, 234, 254, 0.96); color: rgb(37 99 235); }
.type-pill--instagram .type-pill__icon { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-pill--tiktok .type-pill__icon,
.type-pill--threads .type-pill__icon,
.type-pill--twitter .type-pill__icon { background: rgba(241, 245, 249, 0.96); color: rgb(15 23 42); }
.type-pill--linkedin .type-pill__icon { background: rgba(219, 234, 254, 0.96); color: rgb(10 102 194); }
.type-pill--rss .type-pill__icon { background: rgba(255, 237, 213, 0.96); color: rgb(234 88 12); }
.type-pill--google .type-pill__icon { background: rgba(219, 234, 254, 0.96); color: rgb(37 99 235); }
</style>
