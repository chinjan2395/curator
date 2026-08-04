<script setup>
import { computed, ref, watch } from 'vue'
import { AppButton, AppInput, AppSkeleton, AppText } from '../ui/index.js'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: '' },
  items: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Search by name or ID…' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'No accounts found' },
  noMatchText: { type: String, default: 'No matches for your search' },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const query = ref('')
const brokenAvatars = ref(new Set())

watch(
  () => props.items,
  () => {
    brokenAvatars.value = new Set()
  },
)

const filteredItems = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.items
  return props.items.filter((item) => {
    const hay = String(item.searchText || `${item.label || ''} ${item.secondary || ''} ${item.value || ''}`).toLowerCase()
    return hay.includes(q)
  })
})

const selectedLabel = computed(() => {
  const match = props.items.find((item) => String(item.value) === String(props.modelValue ?? ''))
  return match?.label || ''
})

function select(item) {
  if (props.disabled) return
  emit('update:modelValue', String(item.value))
}

function isSelected(item) {
  return String(item.value) === String(props.modelValue ?? '')
}

function initials(item) {
  const source = String(item.label || item.secondary || item.value || '?').trim()
  const parts = source.replace(/^@/, '').split(/[\s._-]+/).filter(Boolean)
  if (parts.length >= 2) {
    return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase()
  }
  return source.slice(0, 2).toUpperCase() || '?'
}

function showAvatar(item) {
  const url = String(item.avatarUrl || '').trim()
  return url !== '' && !brokenAvatars.value.has(String(item.value))
}

function onAvatarError(item) {
  const next = new Set(brokenAvatars.value)
  next.add(String(item.value))
  brokenAvatars.value = next
}
</script>

<template>
  <div class="account-picker" :class="{ 'account-picker--disabled': disabled }">
    <input
      v-if="required"
      type="text"
      class="account-picker__required"
      tabindex="-1"
      aria-hidden="true"
      :value="modelValue || ''"
      required
      :disabled="disabled"
      @focus="$event.target.blur()"
    />

    <AppInput
      v-model="query"
      type="search"
      :placeholder="placeholder"
      :disabled="disabled || loading"
      autocomplete="off"
      input-class="account-picker__search"
    />

    <div v-if="loading" class="account-picker__loading">
      <AppSkeleton variant="line" :lines="3" />
    </div>

    <div
      v-else
      class="account-picker__list"
      role="listbox"
      :aria-label="selectedLabel ? `Selected: ${selectedLabel}` : 'Select an account'"
    >
      <div v-if="!items.length" class="account-picker__empty">
        <AppText size="sm" muted>{{ emptyText }}</AppText>
      </div>
      <div v-else-if="!filteredItems.length" class="account-picker__empty">
        <AppText size="sm" muted>{{ noMatchText }}</AppText>
      </div>
      <AppButton
        v-for="item in filteredItems"
        :key="String(item.value)"
        type="button"
        variant="ghost"
        class="account-picker__row"
        :class="{ 'account-picker__row--selected': isSelected(item) }"
        :disabled="disabled"
        role="option"
        :aria-selected="isSelected(item)"
        @click="select(item)"
      >
        <span class="account-picker__avatar" aria-hidden="true">
          <img
            v-if="showAvatar(item)"
            :src="item.avatarUrl"
            alt=""
            class="account-picker__avatar-img"
            loading="lazy"
            referrerpolicy="no-referrer"
            @error="onAvatarError(item)"
          >
          <span v-else class="account-picker__avatar-fallback">{{ initials(item) }}</span>
        </span>
        <span class="account-picker__meta">
          <span class="account-picker__label">{{ item.label }}</span>
          <span v-if="item.secondary" class="account-picker__secondary">{{ item.secondary }}</span>
        </span>
        <span v-if="isSelected(item)" class="account-picker__check" aria-hidden="true">✓</span>
      </AppButton>
    </div>
  </div>
</template>

<style scoped>
.account-picker {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  min-width: 0;
  width: 100%;
}

.account-picker--disabled {
  opacity: 0.72;
}

.account-picker__required {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.account-picker__list {
  max-height: 17.5rem;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 0.85rem;
  background: #fff;
  padding: 0.35rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.account-picker__loading {
  border: 1px solid #e2e8f0;
  border-radius: 0.85rem;
  background: #fff;
  padding: 0.85rem;
}

.account-picker__empty {
  padding: 1rem 0.75rem;
  text-align: center;
}

.account-picker__row {
  display: flex !important;
  align-items: center;
  gap: 0.7rem;
  width: 100%;
  justify-content: flex-start !important;
  text-align: left;
  white-space: normal !important;
  border-radius: 0.7rem !important;
  padding: 0.55rem 0.65rem !important;
  min-height: 2.85rem;
  color: #0f172a;
}

.account-picker__row:hover:not(:disabled) {
  background: #f8fafc !important;
}

.account-picker__row--selected {
  background: #eff6ff !important;
  box-shadow: inset 0 0 0 1px #bfdbfe;
}

.account-picker__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  flex-shrink: 0;
  overflow: hidden;
  background: #e2e8f0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.account-picker__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.account-picker__avatar-fallback {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #475569;
}

.account-picker__meta {
  min-width: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 0.08rem;
}

.account-picker__label {
  font-size: 0.84rem;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.account-picker__secondary {
  font-size: 0.72rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.account-picker__check {
  flex-shrink: 0;
  color: #2563eb;
  font-size: 0.85rem;
  font-weight: 700;
}
</style>
