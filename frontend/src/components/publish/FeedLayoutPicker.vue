<script setup>
import { AppChoiceGrid } from '../ui';

/**
 * Visual picker for `publish_settings.feed_style`.
 *
 * The 12 layouts were previously a plain `<select>` — names like "Tetris",
 * "Layers", "Cover flow" and "Stagger" carry no meaning as text, so choosing
 * one meant picking blind and waiting for the preview to redraw. Each tile
 * draws a wireframe of the arrangement it produces, so the choice is made by
 * eye. Selection behaviour lives in `AppChoiceGrid`; this component supplies
 * only the wireframes. Block counts are cosmetic — the CSS positions them.
 */
const BLOCK_COUNT = {
  waterfall: 6,
  grid: 6,
  grid_carousel: 6,
  carousel: 3,
  showcase_carousel: 3,
  mosaic: 4,
  tetris: 5,
  select: 5,
  cover_flow: 5,
  list: 3,
  stagger: 4,
  layers: 3,
};

defineProps({
  modelValue: { type: String, default: 'grid' },
  options: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

function styleKey(value) {
  return String(value).replace(/-/g, '_');
}

function blocksFor(value) {
  return BLOCK_COUNT[styleKey(value)] ?? 6;
}
</script>

<template>
  <AppChoiceGrid
    :model-value="modelValue"
    :options="options"
    aria-label="Feed layout"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #option="{ option, selected }">
      <span
        :class="[
          'feed-layout__thumb',
          `feed-layout__thumb--${styleKey(option.value)}`,
          { 'feed-layout__thumb--active': selected },
        ]"
        aria-hidden="true"
      >
        <i v-for="n in blocksFor(option.value)" :key="n" />
      </span>
    </template>
  </AppChoiceGrid>
</template>

<style scoped>
.feed-layout__thumb {
  position: relative;
  display: grid;
  gap: 2px;
  height: 34px;
  padding: 3px;
  border-radius: 0.3rem;
  background: #f1f5f9;
  overflow: hidden;
}

.feed-layout__thumb i {
  display: block;
  background: #b6c2d4;
  border-radius: 1.5px;
}

.feed-layout__thumb--active {
  background: #dbe5fb;
}

.feed-layout__thumb--active i {
  background: #6f8ac9;
}

.feed-layout__thumb--waterfall {
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(5, 1fr);
}
.feed-layout__thumb--waterfall i:nth-child(1) { grid-column: 1; grid-row: 1 / 4; }
.feed-layout__thumb--waterfall i:nth-child(2) { grid-column: 2; grid-row: 1 / 3; }
.feed-layout__thumb--waterfall i:nth-child(3) { grid-column: 3; grid-row: 1 / 5; }
.feed-layout__thumb--waterfall i:nth-child(4) { grid-column: 1; grid-row: 4 / 6; }
.feed-layout__thumb--waterfall i:nth-child(5) { grid-column: 2; grid-row: 3 / 6; }
.feed-layout__thumb--waterfall i:nth-child(6) { grid-column: 3; grid-row: 5 / 6; }

.feed-layout__thumb--grid,
.feed-layout__thumb--grid_carousel {
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(2, 1fr);
}
.feed-layout__thumb--grid_carousel i:nth-child(3),
.feed-layout__thumb--grid_carousel i:nth-child(6) {
  opacity: 0.45;
}

.feed-layout__thumb--carousel {
  grid-template-columns: repeat(3, 1fr);
  align-items: center;
}
.feed-layout__thumb--carousel i { height: 22px; }
.feed-layout__thumb--carousel i:nth-child(3) { opacity: 0.45; }

.feed-layout__thumb--showcase_carousel {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: repeat(2, 1fr);
}
.feed-layout__thumb--showcase_carousel i:nth-child(1) { grid-row: 1 / 3; }
.feed-layout__thumb--showcase_carousel i:nth-child(3) { opacity: 0.45; }

.feed-layout__thumb--mosaic {
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(2, 1fr);
}
.feed-layout__thumb--mosaic i:nth-child(1) { grid-column: 1 / 3; grid-row: 1 / 3; }
.feed-layout__thumb--mosaic i:nth-child(4) { grid-column: 3 / 5; }

.feed-layout__thumb--tetris {
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(3, 1fr);
}
.feed-layout__thumb--tetris i:nth-child(1) { grid-column: 1 / 3; grid-row: 1 / 3; }
.feed-layout__thumb--tetris i:nth-child(2) { grid-column: 3 / 5; grid-row: 1 / 2; }
.feed-layout__thumb--tetris i:nth-child(3) { grid-column: 3 / 4; grid-row: 2 / 4; }
.feed-layout__thumb--tetris i:nth-child(4) { grid-column: 4 / 5; grid-row: 2 / 4; }
.feed-layout__thumb--tetris i:nth-child(5) { grid-column: 1 / 3; grid-row: 3 / 4; }

.feed-layout__thumb--select {
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: 2fr 1fr;
}
.feed-layout__thumb--select i:nth-child(1) { grid-column: 1 / 5; }

.feed-layout__thumb--cover_flow {
  grid-template-columns: repeat(5, 1fr);
  align-items: center;
}
.feed-layout__thumb--cover_flow i { height: 16px; }
.feed-layout__thumb--cover_flow i:nth-child(2),
.feed-layout__thumb--cover_flow i:nth-child(4) { height: 21px; opacity: 0.7; }
.feed-layout__thumb--cover_flow i:nth-child(3) { height: 26px; }
.feed-layout__thumb--cover_flow i:nth-child(1),
.feed-layout__thumb--cover_flow i:nth-child(5) { opacity: 0.4; }

.feed-layout__thumb--list {
  grid-template-rows: repeat(3, 1fr);
}

.feed-layout__thumb--stagger {
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(4, 1fr);
}
.feed-layout__thumb--stagger i:nth-child(1) { grid-column: 1; grid-row: 1 / 3; }
.feed-layout__thumb--stagger i:nth-child(2) { grid-column: 2; grid-row: 2 / 4; }
.feed-layout__thumb--stagger i:nth-child(3) { grid-column: 3; grid-row: 1 / 3; }
.feed-layout__thumb--stagger i:nth-child(4) { grid-column: 4; grid-row: 2 / 4; }

.feed-layout__thumb--layers {
  display: block;
}
.feed-layout__thumb--layers i { position: absolute; border-radius: 2px; }
.feed-layout__thumb--layers i:nth-child(1) { left: 14%; right: 14%; top: 4px; bottom: 12px; opacity: 0.35; }
.feed-layout__thumb--layers i:nth-child(2) { left: 9%; right: 9%; top: 8px; bottom: 7px; opacity: 0.6; }
.feed-layout__thumb--layers i:nth-child(3) { left: 4%; right: 4%; top: 12px; bottom: 3px; }
</style>
