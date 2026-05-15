<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'danger', 'success', 'warning', 'pending', 'ghost'].includes(v),
  },
  // Semantic color modifier. Currently only meaningful for the `ghost` variant,
  // where it replaces the per-call !important overrides callers used to sprinkle
  // (`!text-rose-700 hover:!bg-rose-50/75` etc.). Ignored on filled variants.
  tone: {
    type: String,
    default: 'neutral',
    validator: (v) => ['neutral', 'warning', 'success', 'destructive'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  full: { type: Boolean, default: false },
  // When provided, the button renders as a <router-link>. Avoids the
  // <router-link><AppButton/></router-link> anti-pattern (invalid HTML and a
  // common source of layout drift inside flex rows).
  to: { type: [String, Object], default: null },
  href: { type: String, default: null },
  target: { type: String, default: null },
  rel: { type: String, default: null },
})

defineEmits(['click'])

const variantClasses = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  danger: 'btn-danger',
  success: 'btn-success',
  warning: 'btn-warning',
  pending: 'btn-pending',
  ghost: 'btn-ghost',
}

const sizeClasses = {
  sm: 'px-3 py-1.5 text-xs-pro',
  md: 'px-4 py-2 text-sm-pro',
  lg: 'px-5 py-2.5 text-base-pro',
}

const ghostToneClasses = {
  neutral: '',
  warning: '!text-amber-700 hover:!bg-amber-50/75 hover:!text-amber-800',
  success: '!text-emerald-700 hover:!bg-emerald-50/75 hover:!text-emerald-800',
  destructive: '!text-rose-700 hover:!bg-rose-50/75 hover:!text-rose-800',
}

const tag = computed(() => {
  if (props.to) return 'router-link'
  if (props.href) return 'a'
  return 'button'
})

const tagAttrs = computed(() => {
  if (props.to) return { to: props.to }
  if (props.href) return { href: props.href, target: props.target, rel: props.rel }
  return { type: props.type }
})

const isDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <component
    :is="tag"
    v-bind="tagAttrs"
    :disabled="tag === 'button' ? isDisabled : null"
    :aria-disabled="isDisabled || null"
    :tabindex="tag !== 'button' && isDisabled ? -1 : null"
    :class="[
      'inline-flex items-center justify-center gap-2 whitespace-nowrap',
      variantClasses[variant],
      sizeClasses[size],
      variant === 'ghost' ? ghostToneClasses[tone] : '',
      full ? 'w-full' : '',
      isDisabled ? 'opacity-60 cursor-not-allowed pointer-events-none' : '',
    ]"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="inline-flex items-center gap-2">
      <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
      <slot />
    </span>
    <slot v-else />
  </component>
</template>
