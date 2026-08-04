<template>
  <div
    v-if="visible"
    class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl border border-amber-300 bg-amber-50 text-sm-pro text-amber-800"
  >
    <AppIcon
      name="alert"
      class="w-4 h-4 text-amber-600 shrink-0"
    />
    <p class="flex-1 min-w-[14rem]">
      <span v-if="driftCount !== null">{{ driftCount }} setting{{ driftCount === 1 ? '' : 's' }} customized</span>
      <span v-else>Settings customized</span>
      since applying <strong>{{ kitName }}</strong>.
    </p>
    <div class="flex flex-wrap items-center gap-2">
      <AppButton
        size="sm"
        variant="secondary"
        :disabled="busy"
        @click="saveAsNew"
      >
        Save as new brand kit
      </AppButton>
      <AppButton
        size="sm"
        variant="secondary"
        :disabled="busy"
        @click="revert"
      >
        Revert to brand kit
      </AppButton>
      <AppButton
        size="sm"
        variant="ghost"
        :disabled="busy"
        @click="keepBoth"
      >
        Keep both
      </AppButton>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useBrandKitsStore } from '../../stores/brandKits';
import { usePublishStore } from '../../stores/publish';
import { AppButton, AppIcon } from '../ui';

const props = defineProps({
  workspaceId: { type: [String, Number], required: true },
});

const emit = defineEmits(['reverted']);

const brandKits = useBrandKitsStore();
const publish = usePublishStore();

const dismissed = ref(false);
const busy = ref(false);
const driftCount = ref(null);

const kitName = computed(() => publish.stats?.brand_kit_name || 'brand kit');

const visible = computed(
  () => !dismissed.value && !!publish.stats?.brand_kit_id && publish.stats?.brand_kit_synced === false,
);

function countDiffLeaves(mapped, current) {
  if (mapped === null || typeof mapped !== 'object') {
    return JSON.stringify(mapped) === JSON.stringify(current) ? 0 : 1;
  }
  if (Array.isArray(mapped)) {
    return JSON.stringify(mapped) === JSON.stringify(current) ? 0 : 1;
  }
  let count = 0;
  for (const key of Object.keys(mapped)) {
    count += countDiffLeaves(mapped[key], current?.[key]);
  }
  return count;
}

async function refreshDriftCount() {
  driftCount.value = null;
  const kitId = publish.stats?.brand_kit_id;
  if (!kitId || !publish.publishSettings) return;
  try {
    const { resolved } = await brandKits.getResolved(kitId);
    const mapped = {
      feed_style: resolved.feed_style,
      feed: resolved.feed,
      post: resolved.post,
      colors: resolved.feed_colors,
      widget: resolved.widget,
      branding: resolved.branding,
    };
    driftCount.value = countDiffLeaves(mapped, publish.publishSettings);
  } catch {
    driftCount.value = null;
  }
}

watch(
  () => `${publish.stats?.brand_kit_id || ''}:${publish.stats?.brand_kit_synced}`,
  () => {
    dismissed.value = false;
    if (publish.stats?.brand_kit_id && publish.stats?.brand_kit_synced === false) {
      refreshDriftCount();
    }
  },
  { immediate: true },
);

async function saveAsNew() {
  const kitId = publish.stats?.brand_kit_id;
  if (!kitId) return;
  busy.value = true;
  try {
    await brandKits.duplicateKit(kitId);
    dismissed.value = true;
  } catch {
    // toast handled in store
  } finally {
    busy.value = false;
  }
}

async function revert() {
  const kitId = publish.stats?.brand_kit_id;
  if (!kitId) return;
  busy.value = true;
  try {
    await publish.applyBrandKit(props.workspaceId, kitId);
    emit('reverted');
  } catch {
    // toast handled in store
  } finally {
    busy.value = false;
  }
}

function keepBoth() {
  dismissed.value = true;
}
</script>
