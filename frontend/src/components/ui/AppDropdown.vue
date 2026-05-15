<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
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
const trigger = ref(null)
const dropdown = ref(null)
const position = ref({ top: 0, left: 0 })
const align = computed(() => props.align)
const dropdownOffset = 6

function close() {
  open.value = false
}

function updatePosition() {
  if (!open.value || !trigger.value || !dropdown.value) return

  const triggerRect = trigger.value.getBoundingClientRect()
  const dropdownRect = dropdown.value.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  let left = align.value === 'right'
    ? triggerRect.right - dropdownRect.width
    : triggerRect.left
  let top = triggerRect.bottom + dropdownOffset

  left = Math.max(8, Math.min(left, viewportWidth - dropdownRect.width - 8))

  if (top + dropdownRect.height > viewportHeight - 8) {
    const aboveTop = triggerRect.top - dropdownRect.height - dropdownOffset
    top = aboveTop >= 8 ? aboveTop : Math.max(8, viewportHeight - dropdownRect.height - 8)
  }

  position.value = {
    top,
    left,
  }
}

function handleOutsideClick(e) {
  if (container.value && !container.value.contains(e.target) && !dropdown.value?.contains(e.target)) {
    close()
  }
}

watch(open, async (isOpen) => {
  if (!isOpen) return
  await nextTick()
  updatePosition()
})

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  window.addEventListener('resize', updatePosition)
  window.addEventListener('scroll', updatePosition, true)
})

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', updatePosition, true)
})
</script>

<template>
  <div ref="container" class="relative inline-block">
    <div ref="trigger" @click.stop="open = !open">
      <slot name="trigger" :open="open" />
    </div>

    <Teleport to="body">
      <Transition name="dropdown">
        <div
          v-if="open"
          ref="dropdown"
          class="fixed z-[1000] min-w-[10rem] bg-white border border-slate-100 rounded-xl shadow-lg py-1.5"
          :style="{ top: `${position.top}px`, left: `${position.left}px` }"
        >
          <slot :close="close">
            <button
              v-for="item in items"
              :key="item.label"
              class="mx-0.5 w-[calc(100%-4px)] rounded-md px-3.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
              @click="$emit('select', item); close()"
            >
              {{ item.label }}
            </button>
          </slot>
        </div>
      </Transition>
    </Teleport>
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
