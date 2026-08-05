<script setup>
import { AppFormField, AppInput, AppSelect } from '../ui';
import AppearanceGroup from './AppearanceGroup.vue';
import AppearanceToggleRow from './AppearanceToggleRow.vue';
import FeedLayoutPicker from './FeedLayoutPicker.vue';
import { hasGroup } from '../../constants/embedCapabilities';

/**
 * "Layout" tab — feed style, loading behaviour and thumbnail sizing.
 *
 * The appearance draft is owned by `Publish.vue` and saved as one payload, so
 * it is bound as a model rather than a read-only prop: panels write nested
 * fields in place and the page decides when to PUT.
 */
const settings = defineModel('settings', { type: Object, required: true });

const props = defineProps({
  feedStyleOptions: { type: Array, default: () => [] },
  /** Visibility map from `resolveCapabilities()` — see `constants/embedCapabilities`. */
  caps: { type: Object, required: true },
});

const group = (name) => hasGroup(props.caps, 'layout', name);
</script>

<template>
  <div>
    <AppearanceGroup
      tab="layout"
      group="style"
      title="Feed layout"
      :note="`${feedStyleOptions.length} styles`"
      hint="The preview updates immediately. Save so live sites load the updated script."
    >
      <FeedLayoutPicker v-model="settings.feed_style" :options="feedStyleOptions" />
    </AppearanceGroup>

    <AppearanceGroup v-if="group('loading')" tab="layout" group="loading" title="Loading">
      <div class="space-y-0.5">
        <AppearanceToggleRow
          v-model="settings.feed.lazy_load"
          title="Lazy load"
          description="Auto-fetch posts as visitors scroll"
        />
        <AppearanceToggleRow
          v-model="settings.feed.show_load_more"
          title="Load more button"
          description="Let visitors click to load additional posts"
        />
      </div>

      <div class="grid gap-3 mt-3" :class="caps['feed.post_min_width'] ? 'grid-cols-2' : 'grid-cols-1'">
        <AppFormField id="publish-posts-per-page" label="Posts per page">
          <AppInput
            id="publish-posts-per-page"
            v-model.number="settings.feed.posts_per_page"
            type="number"
            min="1"
            max="100"
          />
        </AppFormField>
        <AppFormField
          v-if="caps['feed.post_min_width']"
          id="publish-post-min-width"
          label="Min post width (px)"
        >
          <AppInput
            id="publish-post-min-width"
            v-model.number="settings.feed.post_min_width"
            type="number"
            min="120"
            max="600"
          />
        </AppFormField>
      </div>
    </AppearanceGroup>

    <AppearanceGroup
      v-if="group('thumbnails')"
      tab="layout"
      group="thumbnails"
      title="Thumbnails"
      hint="Controls the width and height of post thumbnails in the embedded feed."
    >
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <AppFormField v-if="caps['feed.media_size_mode']" id="publish-media-size-mode" label="Sizing">
          <AppSelect
            id="publish-media-size-mode"
            v-model="settings.feed.media_size_mode"
            :show-placeholder="false"
          >
            <option value="auto">Automatic (original size)</option>
            <option value="aspect">Fixed aspect ratio</option>
            <option value="fixed">Fixed height</option>
          </AppSelect>
        </AppFormField>

        <AppFormField v-if="caps['feed.media_aspect_ratio']" id="publish-media-aspect" label="Aspect ratio">
          <AppSelect
            id="publish-media-aspect"
            v-model="settings.feed.media_aspect_ratio"
            :show-placeholder="false"
          >
            <option value="1:1">Square (1:1)</option>
            <option value="4:3">Classic (4:3)</option>
            <option value="16:9">Landscape (16:9)</option>
            <option value="3:4">Portrait (3:4)</option>
            <option value="9:16">Story (9:16)</option>
          </AppSelect>
        </AppFormField>

        <AppFormField v-if="caps['feed.media_height']" id="publish-media-height" label="Height (px)">
          <AppInput
            id="publish-media-height"
            v-model.number="settings.feed.media_height"
            type="number"
            min="80"
            max="800"
          />
        </AppFormField>

        <AppFormField v-if="caps['feed.media_fit']" id="publish-media-fit" label="Image fit">
          <AppSelect id="publish-media-fit" v-model="settings.feed.media_fit" :show-placeholder="false">
            <option value="cover">Fill (crop)</option>
            <option value="contain">Fit (no crop)</option>
          </AppSelect>
        </AppFormField>

        <AppFormField
          v-if="caps['feed.showcase_card_width']"
          id="publish-showcase-card-width"
          label="Card width (px)"
        >
          <AppInput
            id="publish-showcase-card-width"
            v-model.number="settings.feed.showcase_card_width"
            type="number"
            min="180"
            max="640"
          />
        </AppFormField>
      </div>
    </AppearanceGroup>
  </div>
</template>
