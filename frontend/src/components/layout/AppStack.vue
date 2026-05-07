<script setup>
defineProps({
  direction: {
    type: String,
    default: 'vertical',
    validator: (v) => ['vertical', 'horizontal'].includes(v),
  },
  spacing: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v),
  },
  align: { type: String, default: '' },
  justify: { type: String, default: '' },
})

const spacingMap = { xs: '1', sm: '2', md: '4', lg: '6', xl: '8' }
</script>

<template>
  <div
    :class="[
      direction === 'horizontal' ? 'flex flex-row' : 'flex flex-col',
      `gap-${spacingMap[spacing]}`,
      align ? `items-${align}` : '',
      justify ? `justify-${justify}` : '',
    ]"
  >
    <slot />
  </div>
</template>
