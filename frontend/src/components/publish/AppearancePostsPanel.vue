<script setup>
import { AppFormField, AppSelect } from '../ui';
import AppearanceGroup from './AppearanceGroup.vue';
import AppearanceToggleRow from './AppearanceToggleRow.vue';
import { hasGroup } from '../../constants/embedCapabilities';

/**
 * "Posts" tab — what each post card shows, plus source-row and showcase
 * alignment. See `AppearanceLayoutPanel` for the `settings` binding contract.
 */
const settings = defineModel('settings', { type: Object, required: true });

const props = defineProps({
  /** Visibility map from `resolveCapabilities()` — see `constants/embedCapabilities`. */
  caps: { type: Object, required: true },
});

const group = (name) => hasGroup(props.caps, 'posts', name);
</script>

<template>
  <div>
    <AppearanceGroup v-if="group('shows')" tab="posts" group="shows" title="What each post shows">
      <div class="space-y-0.5">
        <AppearanceToggleRow
          v-if="caps['post.show_titles']"
          v-model="settings.post.show_titles"
          title="Title"
        />
        <AppearanceToggleRow
          v-if="caps['post.show_platform_icon']"
          v-model="settings.post.show_platform_icon"
          title="Platform icon"
        />
        <AppearanceToggleRow
          v-if="caps['post.show_feed_name']"
          v-model="settings.post.show_feed_name"
          title="Feed / account name"
        />
        <AppearanceToggleRow
          v-if="caps['post.show_share_icons']"
          v-model="settings.post.show_share_icons"
          title="Share icons"
        />
        <AppearanceToggleRow
          v-if="caps['post.show_likes']"
          v-model="settings.post.show_likes"
          title="Likes"
        />
        <AppearanceToggleRow
          v-if="caps['post.show_comments']"
          v-model="settings.post.show_comments"
          title="Comments"
        />
        <AppearanceToggleRow
          v-if="caps['post.autoplay_videos']"
          v-model="settings.post.autoplay_videos"
          title="Autoplay videos"
        />
      </div>
    </AppearanceGroup>

    <AppearanceGroup v-if="group('source_row')" tab="posts" group="source_row" title="Source row">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <AppFormField id="publish-source-layout" label="Layout">
          <AppSelect
            id="publish-source-layout"
            v-model="settings.post.source_row_layout"
            :show-placeholder="false"
          >
            <option value="stacked">Stacked</option>
            <option value="inline">Inline (compact)</option>
          </AppSelect>
        </AppFormField>
        <AppFormField id="publish-source-alignment" label="Alignment">
          <AppSelect
            id="publish-source-alignment"
            v-model="settings.post.source_row_alignment"
            :show-placeholder="false"
          >
            <option value="center">Center</option>
            <option value="start">Start (left)</option>
          </AppSelect>
        </AppFormField>
      </div>
    </AppearanceGroup>

    <AppearanceGroup
      v-if="group('showcase')"
      tab="posts"
      group="showcase"
      title="Showcase layout"
      badge="Showcase only"
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <AppFormField id="publish-showcase-alignment" label="Content alignment">
          <AppSelect
            id="publish-showcase-alignment"
            v-model="settings.post.showcase_content_alignment"
            :show-placeholder="false"
          >
            <option value="start">Start (left)</option>
            <option value="center">Center</option>
          </AppSelect>
        </AppFormField>
        <AppFormField id="publish-showcase-share" label="Share icon">
          <AppSelect
            id="publish-showcase-share"
            v-model="settings.post.showcase_share_icon"
            :show-placeholder="false"
          >
            <option value="upload_share">Upload / share</option>
            <option value="arrow">Arrow only</option>
            <option value="none">Hidden</option>
          </AppSelect>
        </AppFormField>
      </div>
    </AppearanceGroup>
  </div>
</template>
