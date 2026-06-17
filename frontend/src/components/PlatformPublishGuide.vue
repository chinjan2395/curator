<template>
  <div class="ppg" :class="`ppg--${variant}`">
    <div v-if="title" class="ppg__title-row">
      <p class="ppg__title">{{ title }}</p>
      <p v-if="subtitle" class="ppg__subtitle">{{ subtitle }}</p>
    </div>

    <AppLoader v-if="loading && !entries.length" label="Loading platform specs…" />

    <p v-else-if="!entries.length" class="ppg__empty">
      {{ emptyMessage }}
    </p>

    <template v-else>
      <!-- Compact: one row per platform -->
      <div v-if="variant === 'compact'" class="ppg-compact-list">
        <div v-for="{ platform, spec } in entries" :key="platform" class="ppg-compact-row">
          <SocialPlatformLabel :type="platform" variant="pill" size="sm" :show-label="true" />
          <div class="ppg-compact-types">
            <span
              v-for="type in spec.content_types"
              :key="type.id"
              class="ppg-type-chip"
              :class="type.supported ? 'ppg-type-chip--yes' : 'ppg-type-chip--no'"
              :title="typeChipTitle(type)"
            >
              {{ typeShortLabel(type) }}
            </span>
          </div>
          <span v-if="!spec.native_publish" class="ppg-embed-only">Embed / sync only</span>
        </div>
      </div>

      <!-- Cards: full detail per platform -->
      <div v-else class="ppg-card-list">
        <article
          v-for="{ platform, spec } in entries"
          :key="platform"
          class="ppg-card"
          :class="spec.native_publish ? 'ppg-card--native' : 'ppg-card--embed'"
        >
          <header class="ppg-card__header">
            <SocialPlatformLabel :type="platform" variant="pill" size="sm" :show-label="true" />
            <AppBadge :variant="spec.native_publish ? 'success' : 'secondary'">
              {{ spec.native_publish ? 'Native publish' : 'Embed / sync' }}
            </AppBadge>
          </header>

          <p class="ppg-card__summary">{{ spec.summary }}</p>

          <div class="ppg-card__section">
            <p class="ppg-card__section-label">Content types</p>
            <ul class="ppg-type-list">
              <li
                v-for="type in spec.content_types"
                :key="type.id"
                class="ppg-type-row"
                :class="type.supported ? 'ppg-type-row--yes' : 'ppg-type-row--no'"
              >
                <span class="ppg-type-row__status" :aria-label="type.supported ? 'Supported' : 'Not supported'">
                  {{ type.supported ? '✓' : '—' }}
                </span>
                <div class="ppg-type-row__body">
                  <span class="ppg-type-row__label">{{ type.label }}</span>
                  <span v-if="type.limits" class="ppg-type-row__limits">{{ type.limits }}</span>
                  <span v-if="type.note" class="ppg-type-row__note">{{ type.note }}</span>
                </div>
              </li>
            </ul>
          </div>

          <div v-if="spec.requirements?.length" class="ppg-card__section">
            <p class="ppg-card__section-label">Requirements</p>
            <ul class="ppg-bullet-list">
              <li v-for="(item, i) in spec.requirements" :key="i">{{ item }}</li>
            </ul>
          </div>

          <div v-if="spec.media_rules?.length" class="ppg-card__section">
            <p class="ppg-card__section-label">Media rules</p>
            <ul class="ppg-bullet-list">
              <li v-for="(item, i) in spec.media_rules" :key="i">{{ item }}</li>
            </ul>
          </div>

          <a
            v-if="spec.docs_url"
            :href="spec.docs_url"
            target="_blank"
            rel="noopener noreferrer"
            class="ppg-docs-link"
          >
            {{ spec.docs_label || 'Official API docs' }}
            <span aria-hidden="true">↗</span>
          </a>
        </article>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { AppBadge, AppLoader } from './ui';
import SocialPlatformLabel from './SocialPlatformLabel.vue';
import { usePlatformPublishSpecs } from '../composables/usePlatformPublishSpecs';
import { CONTENT_TYPE_ICONS } from '../constants/platformPublishSpecs';

const props = defineProps({
  platforms: {
    type: Array,
    default: () => [],
  },
  variant: {
    type: String,
    default: 'cards',
    validator: (v) => ['compact', 'cards'].includes(v),
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  emptyMessage: {
    type: String,
    default: 'Select a platform to see what content you can publish.',
  },
});

const { getSpecsForPlatforms, loading } = usePlatformPublishSpecs();

const entries = computed(() => getSpecsForPlatforms(props.platforms));

function typeShortLabel(type) {
  const icon = CONTENT_TYPE_ICONS[type.id] || type.label.slice(0, 3).toUpperCase();
  return icon;
}

function typeChipTitle(type) {
  const parts = [type.label, type.supported ? 'Supported' : 'Not supported'];
  if (type.limits) parts.push(type.limits);
  if (type.note) parts.push(type.note);
  return parts.join(' · ');
}
</script>

<style scoped>
.ppg__title-row {
  margin-bottom: 0.65rem;
}

.ppg__title {
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}

.ppg__subtitle {
  margin-top: 0.15rem;
  font-size: 0.72rem;
  color: #64748b;
  line-height: 1.4;
}

.ppg__empty {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}

/* Compact */
.ppg-compact-list {
  display: grid;
  gap: 0.45rem;
}

.ppg-compact-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.55rem;
  background: #f8fafc;
}

.ppg-compact-types {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.ppg-type-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  padding: 0.1rem 0.35rem;
  border-radius: 0.35rem;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.ppg-type-chip--yes {
  background: #d1fae5;
  color: #065f46;
}

.ppg-type-chip--no {
  background: #f1f5f9;
  color: #94a3b8;
  text-decoration: line-through;
}

.ppg-embed-only {
  margin-left: auto;
  font-size: 0.68rem;
  color: #64748b;
  font-weight: 500;
}

/* Cards */
.ppg-card-list {
  display: grid;
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .ppg-card-list {
    grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
  }
}

.ppg-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  padding: 0.75rem;
  background: #fff;
}

.ppg-card--native {
  border-left: 3px solid #10b981;
}

.ppg-card--embed {
  border-left: 3px solid #94a3b8;
}

.ppg-card__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.ppg-card__summary {
  font-size: 0.75rem;
  color: #475569;
  line-height: 1.45;
  margin-bottom: 0.65rem;
}

.ppg-card__section {
  margin-bottom: 0.55rem;
}

.ppg-card__section-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #94a3b8;
  margin-bottom: 0.3rem;
}

.ppg-type-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.35rem;
}

.ppg-type-row {
  display: flex;
  gap: 0.45rem;
  font-size: 0.72rem;
  line-height: 1.35;
}

.ppg-type-row__status {
  flex-shrink: 0;
  width: 1rem;
  font-weight: 700;
}

.ppg-type-row--yes .ppg-type-row__status {
  color: #059669;
}

.ppg-type-row--no .ppg-type-row__status {
  color: #cbd5e1;
}

.ppg-type-row__body {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.ppg-type-row__label {
  font-weight: 600;
  color: #334155;
}

.ppg-type-row--no .ppg-type-row__label {
  color: #94a3b8;
}

.ppg-type-row__limits {
  color: #64748b;
}

.ppg-type-row__note {
  color: #94a3b8;
}

.ppg-bullet-list {
  margin: 0;
  padding-left: 1rem;
  font-size: 0.72rem;
  color: #64748b;
  line-height: 1.45;
}

.ppg-docs-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.25rem;
  font-size: 0.72rem;
  font-weight: 600;
  color: #2563eb;
  text-decoration: none;
}

.ppg-docs-link:hover {
  text-decoration: underline;
}
</style>
