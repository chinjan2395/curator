<template>
  <AppDropdown align="left">
    <template #trigger>
      <AppButton
        type="button"
        variant="secondary"
        size="sm"
        class="!w-auto !gap-2"
      >
        <AppIcon
          name="sparkles"
          class="w-4 h-4 text-blue-500 shrink-0"
        />
        <span
          v-if="appliedKitName"
          class="whitespace-nowrap"
        >Brand Kit: <strong class="font-semibold">{{ appliedKitName }}</strong></span>
        <span
          v-else
          class="whitespace-nowrap"
        >Apply a brand kit</span>
        <AppBadge
          v-if="syncBadge"
          :variant="syncBadge.variant"
        >
          {{ syncBadge.label }}
        </AppBadge>
        <AppIcon
          name="chevron-down"
          class="w-3.5 h-3.5 text-slate-400"
        />
      </AppButton>
    </template>

    <template #default="{ close }">
      <div class="w-72 p-1 max-h-96 overflow-y-auto">
        <p class="px-3 py-1.5 text-2xs font-semibold uppercase tracking-wide text-slate-400">
          Apply a brand kit
        </p>
        <p
          v-if="!brandKits.kits.length"
          class="px-3 py-1.5 text-xs-pro text-slate-400"
        >
          No brand kits yet.
        </p>
        <!-- Grouped by Master so it is obvious which kits inherit from which;
             a flat list gave no clue that children exist at all. -->
        <template
          v-for="family in brandKits.groupedByMaster"
          :key="family.master.id"
        >
          <AppButton
            type="button"
            variant="ghost"
            size="sm"
            class="!w-full !justify-start !text-left"
            :disabled="applying"
            @click="apply(family.master, close)"
          >
            <span class="truncate font-semibold">{{ family.master.name }}</span>
            <AppBadge
              v-if="family.master.id === currentKitId"
              variant="success"
              class="ml-auto"
            >
              Current
            </AppBadge>
          </AppButton>
          <AppButton
            v-for="child in family.children"
            :key="child.id"
            type="button"
            variant="ghost"
            size="sm"
            class="!w-full !justify-start !text-left !pl-7"
            :disabled="applying"
            @click="apply(child, close)"
          >
            <span class="truncate text-slate-600">{{ child.name }}</span>
            <AppBadge
              v-if="child.id === currentKitId"
              variant="success"
              class="ml-auto"
            >
              Current
            </AppBadge>
          </AppButton>
        </template>
        <div class="my-1 border-t border-slate-100" />
        <AppButton
          type="button"
          variant="ghost"
          size="sm"
          class="!w-full !justify-start !text-left"
          @click="goManage(close)"
        >
          Manage brand kits →
        </AppButton>
      </div>
    </template>
  </AppDropdown>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useBrandKitsStore } from '../../stores/brandKits';
import { usePublishStore } from '../../stores/publish';
import { AppBadge, AppButton, AppDropdown, AppIcon } from '../ui';

const props = defineProps({
  workspaceId: { type: [String, Number], required: true },
});

const emit = defineEmits(['applied']);

const router = useRouter();
const brandKits = useBrandKitsStore();
const publish = usePublishStore();

const appliedKitName = computed(() => publish.stats?.brand_kit_name || '');
const currentKitId = computed(() => publish.stats?.brand_kit_id || null);
const applying = computed(() => publish.savingSettings);

const syncBadge = computed(() => {
  if (!currentKitId.value) return null;
  if (publish.stats?.brand_kit_synced === false) return { label: 'Customized', variant: 'warning' };
  return { label: 'Synced', variant: 'success' };
});

async function apply(kit, close) {
  try {
    await publish.applyBrandKit(props.workspaceId, kit.id);
    close();
    emit('applied');
  } catch {
    // toast handled in store
  }
}

function goManage(close) {
  close();
  router.push('/brand-kits');
}

onMounted(() => {
  if (!brandKits.kits.length) brandKits.loadKits();
});
</script>
