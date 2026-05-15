<script setup>
import AppLoader from './AppLoader.vue'

defineProps({
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
  loading: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'No records found.' },
  rowKey: { type: String, default: 'id' },
})
</script>

<template>
  <div class="table-shell">
    <div class="table-scroll">
      <table class="w-full text-sm">
        <thead class="table-head">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :class="['table-th text-left', col.class ?? '']"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length">
              <AppLoader label="Loading..." />
            </td>
          </tr>

          <tr v-else-if="!rows.length">
            <td :colspan="columns.length" class="text-center py-10 text-slate-400 text-xs">
              {{ emptyMessage }}
            </td>
          </tr>

          <tr
            v-for="row in rows"
            v-else
            :key="row[rowKey]"
            class="table-tr"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              :class="['table-td', col.cellClass ?? '']"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                {{ row[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
