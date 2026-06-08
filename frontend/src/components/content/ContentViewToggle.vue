<template>
  <div class="content-view-toggle">
    <button
      type="button"
      class="content-view-toggle__btn"
      :class="modelValue === 'grid' ? 'content-view-toggle__btn--active' : ''"
      @click="$emit('update:modelValue', 'grid')"
    >
      Grid
    </button>
    <button
      type="button"
      class="content-view-toggle__btn"
      :class="modelValue === 'list' ? 'content-view-toggle__btn--active' : ''"
      @click="$emit('update:modelValue', 'list')"
    >
      List
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: String, default: 'grid' },
});

defineEmits(['update:modelValue']);
</script>

<style scoped>
.content-view-toggle {
  display: inline-flex;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  overflow: hidden;
  background: #fff;
}

.content-view-toggle__btn {
  padding: 0.35rem 0.65rem;
  font-size: 0.78rem;
  font-weight: 650;
  color: #64748b;
  background: transparent;
  border: 0;
  cursor: pointer;
}

.content-view-toggle__btn:hover {
  background: rgba(248, 250, 252, 0.9);
  color: #334155;
}

.content-view-toggle__btn--active {
  background: rgba(239, 246, 255, 0.95);
  color: #1d4ed8;
}
</style>
