<script setup>
import { computed } from 'vue';
import { AppFormField, AppInput, AppSelect } from '../ui';
import AppearanceColorRow from './AppearanceColorRow.vue';
import AppearanceGroup from './AppearanceGroup.vue';
import AppearanceToggleRow from './AppearanceToggleRow.vue';
import { hasGroup } from '../../constants/embedCapabilities';

/**
 * "Colors" tab — post text/icon colours plus the optional border and
 * background surfaces. See `AppearanceLayoutPanel` for the `settings` contract.
 */
const COLOR_FIELDS = [
  { key: 'post_icon', label: 'Icon' },
  { key: 'post_text', label: 'Text' },
  { key: 'post_date', label: 'Date' },
  { key: 'post_link', label: 'Link' },
  { key: 'post_button', label: 'Button' },
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

const group = (name) => hasGroup(props.caps, 'colors', name);

const visibleColorFields = computed(() => COLOR_FIELDS.filter((f) => props.caps[`colors.${f.key}`]));
</script>

<template>
  <div>
    <AppearanceGroup
      v-if="group('post_colors')"
      tab="colors"
      group="post_colors"
      title="Post colours"
      note="applies inside the embed"
    >
      <div class="space-y-1">
        <AppearanceColorRow
          v-for="field in visibleColorFields"
          :key="field.key"
          v-model="settings.colors[field.key]"
          :label="field.label"
        />
      </div>
    </AppearanceGroup>

    <AppearanceGroup v-if="group('surface')" tab="colors" group="surface" title="Post surface">
      <div v-if="caps['colors.post_border']" class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
        <AppearanceToggleRow
          v-model="settings.colors.post_border.enabled"
          title="Post border"
          description="Outline around each card"
        />
        <div v-if="settings.colors.post_border.enabled" class="mt-2 pl-7 flex items-center gap-3">
          <label class="text-xs font-semibold text-slate-600" for="publish-border-width">
            Thickness
          </label>
          <AppInput
            id="publish-border-width"
            v-model.number="settings.colors.post_border.width"
            type="number"
            min="0"
            max="8"
            wrapper-class="!w-auto"
            input-class="!h-8 !w-16 !py-1 !text-xs"
          />
          <span class="text-xs text-slate-500">px</span>
          <AppInput
            v-model="settings.colors.post_border.color"
            type="color"
            aria-label="Border colour"
            wrapper-class="!w-auto ml-auto shrink-0"
            input-class="!h-8 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
          />
        </div>
      </div>

      <div v-if="caps['colors.post_bg']" class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 mt-2.5">
        <AppearanceToggleRow
          v-model="settings.colors.post_bg.enabled"
          title="Post background"
          description="Off means transparent — the host page shows through"
        />
        <div v-if="settings.colors.post_bg.enabled" class="mt-2 pl-7">
          <AppInput
            v-model="settings.colors.post_bg.color"
            type="color"
            aria-label="Background colour"
            wrapper-class="!w-auto shrink-0"
            input-class="!h-8 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
          />
        </div>
      </div>

      <div
        v-if="caps['colors.showcase_shell_bg']"
        class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 mt-2.5"
      >
        <AppearanceToggleRow
          v-model="settings.colors.showcase_shell_bg.enabled"
          title="Carousel shell colour"
          description="Off means the backdrop follows the widget theme"
        />
        <div v-if="settings.colors.showcase_shell_bg.enabled" class="mt-2 pl-7">
          <AppInput
            v-model="settings.colors.showcase_shell_bg.color"
            type="color"
            aria-label="Carousel shell colour"
            wrapper-class="!w-auto shrink-0"
            input-class="!h-8 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
          />
        </div>
      </div>
    </AppearanceGroup>

    <AppearanceGroup
      v-if="group('header_footer')"
      tab="colors"
      group="header_footer"
      title="Header &amp; footer"
      note="source row and card footer"
      hint="The footer date follows the Post “Date” colour above."
    >
      <div class="space-y-1">
        <AppearanceColorRow
          v-if="caps['colors.header_text']"
          v-model="settings.colors.header_text"
          label="Header text"
        />
        <AppearanceColorRow
          v-if="caps['colors.footer_text']"
          v-model="settings.colors.footer_text"
          label="Footer text"
        />
      </div>

      <div v-if="caps['post.platform_icon_color_mode']" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <AppFormField id="publish-header-icon-mode" label="Header icon colour">
          <AppSelect
            id="publish-header-icon-mode"
            v-model="settings.post.platform_icon_color_mode"
            :show-placeholder="false"
          >
            <option value="brand">Native platform colours</option>
            <option value="custom">Custom colour</option>
          </AppSelect>
        </AppFormField>

        <AppFormField
          v-if="caps['post.platform_icon_color']"
          id="publish-header-icon-color"
          label="Custom header icon colour"
        >
          <div class="flex items-center gap-2">
            <AppInput
              v-model="settings.post.platform_icon_color"
              type="color"
              aria-label="Custom header icon colour picker"
              wrapper-class="!w-auto shrink-0"
              input-class="!h-10 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
            />
            <AppInput
              id="publish-header-icon-color"
              v-model="settings.post.platform_icon_color"
              type="text"
              input-class="!py-2 !text-xs font-mono"
            />
          </div>
        </AppFormField>
      </div>

      <div
        v-if="caps['post.showcase_share_icon_color_mode']"
        class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3"
      >
        <AppFormField id="publish-footer-icon-mode" label="Footer icon colour">
          <AppSelect
            id="publish-footer-icon-mode"
            v-model="settings.post.showcase_share_icon_color_mode"
            :show-placeholder="false"
          >
            <option value="post_icon">Post icon colour</option>
            <option value="post_text">Post text colour</option>
            <option value="post_button">Post button colour</option>
            <option value="custom">Custom colour</option>
          </AppSelect>
        </AppFormField>

        <AppFormField
          v-if="caps['post.showcase_share_icon_color']"
          id="publish-footer-icon-color"
          label="Custom footer icon colour"
        >
          <div class="flex items-center gap-2">
            <AppInput
              v-model="settings.post.showcase_share_icon_color"
              type="color"
              aria-label="Custom footer icon colour picker"
              wrapper-class="!w-auto shrink-0"
              input-class="!h-10 !w-12 !p-0 rounded border border-slate-300 cursor-pointer bg-white"
            />
            <AppInput
              id="publish-footer-icon-color"
              v-model="settings.post.showcase_share_icon_color"
              type="text"
              input-class="!py-2 !text-xs font-mono"
            />
          </div>
        </AppFormField>
      </div>
    </AppearanceGroup>
  </div>
</template>
