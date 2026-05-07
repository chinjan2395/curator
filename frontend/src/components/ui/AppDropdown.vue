<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  align: {
    type: String,
    default: 'left',
    validator: (v) => ['left', 'right'].includes(v),
  },
})

defineEmits(['select'])

const open = ref(false)
const container = ref(null)

function handleOutsideClick(e) {
  if (container.value && !container.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))
</script>

<template>
  <div ref="container" class="relative inline-block">
    <div @click.stop="open = !open">
      <slot name="trigger" :open="open" />
    </div>

    <Transition name="dropdown">
      <div
        v-if="open"
        :class="[
          'absolute z-20 mt-1 min-w-[10rem] bg-white border border-slate-200 rounded-lg shadow-lg py-1',
          align === 'right' ? 'right-0' : 'left-0',
        ]"
      >
        <slot>
          <button
            v-for="item in items"
            :key="item.label"
            class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition"
            @click="$emit('select', item); open = false"
          >
            {{ item.label }}
          </button>
        </slot>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
