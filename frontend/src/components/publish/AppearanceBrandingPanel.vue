<script setup>
import { computed } from 'vue';
import { AppFormField, AppInput, AppSelect } from '../ui';
import AppearanceGroup from './AppearanceGroup.vue';
import AppearanceToggleRow from './AppearanceToggleRow.vue';

/**
 * "Branding" tab — post badges, feed icons and the showcase footer avatar.
 *
 * The three slots differ only in their option lists, so they're driven from one
 * config instead of the three near-identical blocks the old template carried.
 * See `AppearanceLayoutPanel` for the `settings` binding contract.
 */
const BRANDING_SLOTS = [
  {
    key: 'media_badge',
    title: 'Media badge',
    description: 'Badge on the thumbnail image',
    placeholder: 'https://example.com/badge.png',
    imageOptions: [
      { value: 'platform', label: 'Platform icon' },
      { value: 'custom', label: 'Custom URL' },
      { value: 'none', label: 'Hidden' },
    ],
    positionOptions: [
      { value: 'center', label: 'Center' },
      { value: 'top_left', label: 'Top left' },
      { value: 'top_right', label: 'Top right' },
      { value: 'bottom_left', label: 'Bottom left' },
      { value: 'bottom_right', label: 'Bottom right' },
    ],
  },
  {
    key: 'source_icon',
    title: 'Feed icon',
    description: 'Icon beside the feed name',
    placeholder: 'https://example.com/icon.png',
    imageOptions: [
      { value: 'platform', label: 'Platform icon' },
      { value: 'custom', label: 'Custom URL' },
      { value: 'none', label: 'Hidden' },
    ],
    positionOptions: [
      { value: 'before_name', label: 'Before name' },
      { value: 'after_name', label: 'After name' },
    ],
  },
  {
    key: 'account_avatar',
    title: 'Footer avatar',
    description: 'Avatar in the carousel footer',
    placeholder: 'https://example.com/avatar.jpg',
    imageOptions: [
      { value: 'connected', label: 'Account photo' },
      { value: 'initial', label: 'Feed name letter' },
      { value: 'custom', label: 'Custom URL' },
      { value: 'none', label: 'Hidden' },
    ],
    positionOptions: [
      { value: 'footer_start', label: 'Left side' },
      { value: 'footer_end', label: 'Right side' },
    ],
  },
];

/**
 * The appearance draft is owned by `Publish.vue` and saved as one payload, so
 * it is bound as a model rather than a read-only prop: panels write nested
 * fields in place and the page decides when to PUT.
 */
const settings = defineModel('settings', { type: Object, required: true });

const props = defineProps({
  /** Visibility map from `resolveCapabilities()` — see `constants/embedCapabilities`. */
  caps: { type: Object, required: true },
});

const visibleSlots = computed(() => BRANDING_SLOTS.filter((slot) => props.caps[`branding.${slot.key}`]));
</script>

<template>
  <AppearanceGroup
    tab="branding"
    group="slots"
    title="Branding"
    hint="Custom images need an HTTPS URL — square PNG or JPG."
  >
    <div class="space-y-2.5">
      <div
        v-for="slot in visibleSlots"
        :key="slot.key"
        class="rounded-xl border border-slate-200 bg-slate-50 p-3.5"
      >
        <AppearanceToggleRow
          v-model="settings.branding[slot.key].show"
          :title="slot.title"
          :description="slot.description"
        />

        <div v-if="settings.branding[slot.key].show" class="mt-3 pl-7 space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <AppFormField :id="`publish-branding-${slot.key}-image`" label="Image">
              <AppSelect
                :id="`publish-branding-${slot.key}-image`"
                v-model="settings.branding[slot.key].image_source"
                :options="slot.imageOptions"
                :show-placeholder="false"
              />
            </AppFormField>
            <AppFormField :id="`publish-branding-${slot.key}-position`" label="Position">
              <AppSelect
                :id="`publish-branding-${slot.key}-position`"
                v-model="settings.branding[slot.key].position"
                :options="slot.positionOptions"
                :show-placeholder="false"
              />
            </AppFormField>
          </div>

          <AppInput
            v-if="settings.branding[slot.key].image_source === 'custom'"
            v-model="settings.branding[slot.key].custom_url"
            type="url"
            :placeholder="slot.placeholder"
            :aria-label="`${slot.title} image URL`"
            input-class="font-mono !text-xs"
          />
        </div>
      </div>
    </div>
  </AppearanceGroup>
</template>
