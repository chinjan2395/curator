<script setup>
const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  perPage: { type: Number, default: 15 },
  totalItems: { type: Number, default: 0 },
})

const emit = defineEmits(['page-change'])

const hasPrev = computed(() => props.currentPage > 1)
const hasNext = computed(() => props.currentPage < props.totalPages)

import { computed } from 'vue'
</script>

<template>
  <div class="flex items-center justify-between text-sm text-slate-500 mt-4">
    <span class="text-xs">
      Page {{ currentPage }} of {{ totalPages }}
      <span v-if="totalItems"> &middot; {{ totalItems }} total</span>
    </span>
    <div class="flex items-center gap-1">
      <button
        :disabled="!hasPrev"
        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition"
        @click="emit('page-change', currentPage - 1)"
      >
        &larr; Prev
      </button>
      <button
        :disabled="!hasNext"
        class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed transition"
        @click="emit('page-change', currentPage + 1)"
      >
        Next &rarr;
      </button>
    </div>
  </div>
</template>
