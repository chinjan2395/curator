<template>
    <div v-if="platform" class="svp" :class="panelClass">
    <div class="svp__header">
      <SocialPlatformLabel :type="platform" variant="pill" size="sm" :show-label="true" />
      <span v-if="contentPackage" class="svp__status" :class="validation.valid ? 'svp__status--ok' : 'svp__status--bad'">
        {{ validation.valid ? 'Ready to schedule' : 'Not ready to schedule' }}
      </span>
      <span v-else class="svp__status svp__status--info">Requirements</span>
    </div>

    <p v-if="hint && !contentPackage" class="svp__hint">{{ hint }}</p>
    <p v-if="platform && !contentPackage" class="svp__select-note">Select a content package below to check if it meets these rules.</p>

    <ul v-if="checks.length" class="svp__checks">
      <li
        v-for="item in checks"
        :key="item.id"
        class="svp__check"
        :class="item.passed ? 'svp__check--pass' : 'svp__check--fail'"
      >
        <span class="svp__check-icon" aria-hidden="true">{{ item.passed ? '✓' : '✕' }}</span>
        <div class="svp__check-body">
          <span class="svp__check-label">{{ item.label }}</span>
          <span v-if="!item.passed && item.message" class="svp__check-msg">{{ item.message }}</span>
        </div>
      </li>
    </ul>

    <p v-if="contentPackage && validation.valid" class="svp__ok-note">
      This package meets {{ platformLabel }} requirements for native publish.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SocialPlatformLabel from './SocialPlatformLabel.vue';
import { getPlatformDisplayLabel } from '../constants/platformPublishSpecs';
import {
  platformScheduleHint,
  validateScheduleContent,
} from '../utils/scheduleContentValidation';

const props = defineProps({
  platform: {
    type: String,
    default: null,
  },
  contentPackage: {
    type: Object,
    default: null,
  },
});

const hint = computed(() => (props.platform ? platformScheduleHint(props.platform) : null));
const platformLabel = computed(() => getPlatformDisplayLabel(props.platform));

const validation = computed(() => {
  if (!props.platform || !props.contentPackage) {
    return { valid: false, checks: [] };
  }
  return validateScheduleContent(props.contentPackage, props.platform);
});

const panelClass = computed(() => {
  if (!props.contentPackage) return 'svp--info';
  return validation.value.valid ? 'svp--valid' : 'svp--invalid';
});

const checks = computed(() => validation.value.checks);
</script>

<style scoped>
.svp {
  border-radius: 0.75rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
}

.svp--valid {
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.svp--invalid {
  border-color: #fecaca;
  background: #fff5f5;
}

.svp--info {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.svp__status--info {
  color: #1d4ed8;
}

.svp__select-note {
  font-size: 0.72rem;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.svp__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.svp__status {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.svp__status--ok {
  color: #047857;
}

.svp__status--bad {
  color: #b91c1c;
}

.svp__hint {
  font-size: 0.78rem;
  color: #475569;
  line-height: 1.45;
  margin-bottom: 0.5rem;
}

.svp__checks {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.4rem;
}

.svp__check {
  display: flex;
  gap: 0.5rem;
  font-size: 0.75rem;
  line-height: 1.4;
}

.svp__check-icon {
  flex-shrink: 0;
  width: 1rem;
  font-weight: 700;
  text-align: center;
}

.svp__check--pass .svp__check-icon {
  color: #059669;
}

.svp__check--fail .svp__check-icon {
  color: #dc2626;
}

.svp__check-label {
  font-weight: 600;
  color: #334155;
}

.svp__check--fail .svp__check-label {
  color: #991b1b;
}

.svp__check-msg {
  display: block;
  color: #b91c1c;
  font-weight: 400;
  margin-top: 0.1rem;
}

.svp__ok-note {
  margin-top: 0.5rem;
  font-size: 0.72rem;
  color: #047857;
  font-weight: 500;
}
</style>
