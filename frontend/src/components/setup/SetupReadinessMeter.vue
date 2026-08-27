<template>
  <div
    v-if="!setup.ready && setup.loaded"
    class="mb-2"
  >
    <router-link
      v-if="collapsed"
      to="/credentials"
      class="flex items-center justify-center rounded-lg px-2 py-2 text-blue-200/75 hover:bg-white/10 hover:text-white"
      :title="`Setup — ${setup.satisfiedCount} of ${setup.totalCount} done`"
    >
      <AppIcon
        name="rocket"
        class="w-5 h-5"
      />
    </router-link>

    <div
      v-else
      class="rounded-lg bg-white/5 px-3 py-2.5"
    >
      <div class="flex items-baseline justify-between gap-2">
        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-200/50">
          Setup
        </span>
        <span class="text-[10px] font-semibold tabular-nums text-blue-200/50">
          {{ setup.satisfiedCount }} of {{ setup.totalCount }} done
        </span>
      </div>

      <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/10">
        <div :class="['h-full rounded-full bg-blue-300', fillClass]" />
      </div>

      <p class="mt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-200/40">
        Still to do
      </p>

      <ul class="mt-1 space-y-0.5">
        <li
          v-for="item in outstanding"
          :key="item.key"
        >
          <router-link
            :to="item.route"
            class="flex items-center gap-2 rounded-md px-1.5 py-1 text-xs text-blue-200/75 hover:bg-white/10 hover:text-white"
          >
            <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', dotClass(item)]" />
            <span class="truncate">{{ item.label }}</span>
          </router-link>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { AppIcon } from '../ui';
import { useSetupStore } from '../../stores/setup';

defineProps({
  collapsed: {
    type: Boolean,
    default: false,
  },
});

const setup = useSetupStore();

// Listed literally so Tailwind's static scanner emits them — a runtime
// `w-${n}/12` would never make it into the stylesheet.
const FILL_CLASSES = [
  'w-0', 'w-1/12', 'w-2/12', 'w-3/12', 'w-4/12', 'w-5/12', 'w-6/12',
  'w-7/12', 'w-8/12', 'w-9/12', 'w-10/12', 'w-11/12', 'w-full',
];

const fillClass = computed(() => {
  if (!setup.totalCount) return FILL_CLASSES[0];
  const twelfths = Math.round((setup.satisfiedCount / setup.totalCount) * 12);
  return FILL_CLASSES[Math.min(twelfths, 12)];
});

const outstanding = computed(() => setup.outstanding);

function dotClass(item) {
  if (item.tier === 'block') return 'bg-red-400';
  if (item.tier === 'lock') return 'bg-amber-300';
  return 'bg-blue-300/50';
}
</script>
