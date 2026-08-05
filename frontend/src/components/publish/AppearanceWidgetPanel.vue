<script setup>
import { AppCheckbox, AppFormField, AppInput, AppSelect } from '../ui';
import AppearanceGroup from './AppearanceGroup.vue';
import AppearanceToggleRow from './AppearanceToggleRow.vue';
import { hasGroup } from '../../constants/embedCapabilities';

/**
 * "Widget" tab — embed chrome and the feed-level content filters.
 * See `AppearanceLayoutPanel` for the `settings` binding contract.
 *
 * The appearance draft is owned by `Publish.vue` and saved as one payload, so
 * it is bound as a model rather than a read-only prop: panels write nested
 * fields in place and the page decides when to PUT.
 */
const settings = defineModel('settings', { type: Object, required: true });

const props = defineProps({
  /** Visibility map from `resolveCapabilities()` — see `constants/embedCapabilities`. */
  caps: { type: Object, required: true },
  platformFilterOptions: { type: Array, default: () => [] },
  contentTypeFilterOptions: { type: Array, default: () => [] },
});

const group = (name) => hasGroup(props.caps, 'widget', name);
</script>

<template>
  <div>
    <AppearanceGroup
      v-if="group('style')"
      tab="widget"
      group="style"
      title="Widget style"
      hint="WordPress: paste the snippet into a Custom HTML block. Squarespace: use a Code block."
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <AppFormField v-if="caps['widget.theme']" id="publish-widget-theme" label="Theme">
          <AppSelect id="publish-widget-theme" v-model="settings.widget.theme" :show-placeholder="false">
            <option value="light">Light</option>
            <option value="dark">Dark</option>
            <option value="auto">Auto (follow visitor's device)</option>
          </AppSelect>
        </AppFormField>

        <AppFormField v-if="caps['widget.columns']" id="publish-widget-columns" label="Columns">
          <AppSelect
            id="publish-widget-columns"
            v-model.number="settings.widget.columns"
            :show-placeholder="false"
          >
            <option :value="2">2</option>
            <option :value="3">3</option>
            <option :value="4">4</option>
            <option :value="5">5</option>
          </AppSelect>
        </AppFormField>

        <AppFormField v-if="caps['widget.animation']" id="publish-widget-animation" label="Animation">
          <AppSelect
            id="publish-widget-animation"
            v-model="settings.widget.animation"
            :show-placeholder="false"
          >
            <option value="none">None</option>
            <option value="fade">Fade</option>
            <option value="slide">Slide</option>
          </AppSelect>
        </AppFormField>

        <AppFormField v-if="caps['widget.gap']" id="publish-widget-gap" label="Gap (px)">
          <AppInput
            id="publish-widget-gap"
            v-model.number="settings.widget.gap"
            type="number"
            min="0"
            max="48"
          />
        </AppFormField>

        <AppFormField v-if="caps['widget.border_radius']" id="publish-widget-radius" label="Border radius (px)">
          <AppInput
            id="publish-widget-radius"
            v-model.number="settings.widget.border_radius"
            type="number"
            min="0"
            max="32"
          />
        </AppFormField>

        <AppFormField
          v-if="caps['widget.font_family']"
          id="publish-widget-font"
          label="Font family"
          class="sm:col-span-2"
        >
          <AppInput id="publish-widget-font" v-model="settings.widget.font_family" />
        </AppFormField>

        <AppFormField
          v-if="caps['widget.click_action']"
          id="publish-widget-click"
          label="Click action"
          class="sm:col-span-2"
        >
          <AppSelect
            id="publish-widget-click"
            v-model="settings.widget.click_action"
            :show-placeholder="false"
          >
            <option value="new_tab">Open in new tab</option>
            <option value="modal">Open in modal</option>
            <option value="none">No action (disabled)</option>
          </AppSelect>
        </AppFormField>
      </div>

      <div v-if="caps['widget.auto_refresh']" class="mt-1">
        <AppearanceToggleRow
          v-model="settings.widget.auto_refresh"
          title="Auto-refresh"
          description="Re-fetch the feed every 5 minutes"
        />
      </div>
    </AppearanceGroup>

    <AppearanceGroup
      v-if="group('filters')"
      tab="widget"
      group="filters"
      title="Content filters"
      note="limits which posts the widget loads"
    >
      <p v-if="caps['widget.platform_filters']" class="text-xs font-semibold text-slate-600 mb-2">
        Platforms
      </p>
      <div v-if="caps['widget.platform_filters']" class="flex flex-wrap gap-2">
        <AppCheckbox
          v-for="platform in platformFilterOptions"
          :key="platform"
          :model-value="settings.widget.platform_filters.includes(platform)"
          class="publish-filter-chip"
          @update:model-value="
            $event
              ? settings.widget.platform_filters.push(platform)
              : settings.widget.platform_filters.splice(
                  settings.widget.platform_filters.indexOf(platform),
                  1,
                )
          "
        >
          <span class="text-xs capitalize">{{ platform }}</span>
        </AppCheckbox>
      </div>

      <p v-if="caps['widget.content_type_filters']" class="text-xs font-semibold text-slate-600 mt-4 mb-2">
        Content types
      </p>
      <div v-if="caps['widget.content_type_filters']" class="flex flex-wrap gap-2">
        <AppCheckbox
          v-for="type in contentTypeFilterOptions"
          :key="type"
          :model-value="settings.widget.content_type_filters.includes(type)"
          class="publish-filter-chip"
          @update:model-value="
            $event
              ? settings.widget.content_type_filters.push(type)
              : settings.widget.content_type_filters.splice(
                  settings.widget.content_type_filters.indexOf(type),
                  1,
                )
          "
        >
          <span class="text-xs capitalize">{{ type }}</span>
        </AppCheckbox>
      </div>
    </AppearanceGroup>
  </div>
</template>

<style scoped>
.publish-filter-chip {
  padding: 0.3rem 0.6rem;
  border: 1px solid #e6ebf2;
  border-radius: 9999px;
  background: #fff;
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}

.publish-filter-chip:hover {
  border-color: #b8c4d6;
  background: #f8fafc;
}
</style>
