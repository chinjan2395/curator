<template>
  <WizardPageLayout
    current="publish"
    title="Publish"
    description="Publish approved posts, customize how the embed looks, and copy the embed snippet."
    :workspaceId="workspaceId"
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <span>{{ workspaceName }}</span>
    </template>

    <template #actions>
      <AppButton
        size="sm"
        class="!w-auto !px-3 !py-1.5"
        :disabled="publish.publishing"
        @click="publishNow"
        title="Publish and finish"
      >
        {{ publish.publishing ? '⏳' : '✓' }}
      </AppButton>
    </template>

    <!-- Publish success banner -->
    <div v-if="publishedCount !== null" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-emerald-300 bg-emerald-50 text-sm-pro text-emerald-800 mb-2">
      <span>✓ <strong>{{ publishedCount }} post{{ publishedCount !== 1 ? 's' : '' }}</strong> published and live in your embed.</span>
      <div class="flex items-center gap-2">
        <AppButton variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="showEmbedPreview = true">Test embed</AppButton>
        <AppButton variant="secondary" size="sm" class="border-emerald-300 text-emerald-700 hover:bg-emerald-100" @click="openCode">Get code</AppButton>
        <AppButton variant="ghost" size="sm" class="text-emerald-500 hover:text-emerald-700 text-lg leading-none" @click="publishedCount = null" title="Dismiss">✕</AppButton>
      </div>
    </div>

    <div v-if="publish.loading && !publish.stats" class="surface-card-soft flex items-center gap-2 text-sm-pro text-slate-500 px-4 py-3">
      <span class="inline-block w-4 h-4 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
      Loading…
    </div>

    <div v-else-if="!workspaceId" class="surface-card p-6 text-sm-pro text-slate-600">
      <div class="flex items-center gap-2 mb-3">
        <AppSelect v-model="workspaceId" select-class="!py-1.5 !px-2.5 !text-sm-pro flex-1" :show-placeholder="false">
          <option value="">Select workspace</option>
          <option v-for="w in workspaces.list" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
        </AppSelect>
      </div>
      Pick a workspace to manage publishing.
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-[minmax(0,550px)_minmax(0,1fr)] gap-6 items-start">
        <div class="space-y-4">
          <div v-if="appearance" class="surface-card p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-200">
              <div>
                <h2 class="text-base font-semibold text-slate-900">Feed appearance</h2>
                <p class="text-xs text-slate-500 mt-1">Customize how your posts look in the embed</p>
              </div>
              <AppButton
                size="sm"
                :disabled="publish.savingSettings"
                @click="saveAppearance"
              >
                {{ publish.savingSettings ? 'Saving…' : 'Save' }}
              </AppButton>
            </div>

            <!-- Feed Style Section -->
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-semibold text-slate-900 mb-2">Feed Layout</label>
                <AppSelect v-model="appearance.feed_style" select-class="w-full" :show-placeholder="false">
                  <option v-for="opt in feedStyleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </AppSelect>
                <p class="text-xs text-slate-500 mt-2">
                  Changes appear in the preview immediately. Save to update your embed on external sites.
                </p>
              </div>
            </div>

            <!-- Feed Options Section -->
            <div class="space-y-4 pt-4 border-t border-slate-200">
              <h3 class="text-sm font-semibold text-slate-900">Feed Options</h3>
              <div class="space-y-3">
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.feed.lazy_load" />
                  <div>
                    <div class="font-medium text-slate-900">Lazy load</div>
                    <div class="text-xs text-slate-500">Auto-fetch posts as visitors scroll</div>
                  </div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.feed.show_load_more" />
                  <div>
                    <div class="font-medium text-slate-900">Load more button</div>
                    <div class="text-xs text-slate-500">Let visitors click to load additional posts</div>
                  </div>
                </label>
              </div>

              <div class="grid grid-cols-2 gap-3 pt-2">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Posts per page</label>
                  <AppInput v-model.number="appearance.feed.posts_per_page" type="number" min="1" max="100" input-class="w-full py-2" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Min width (px)</label>
                  <AppInput v-model.number="appearance.feed.post_min_width" type="number" min="120" max="600" input-class="w-full py-2" />
                </div>
              </div>
            </div>

            <!-- Post Display Section -->
            <div class="space-y-4 pt-4 border-t border-slate-200">
              <h3 class="text-sm font-semibold text-slate-900">Post Display</h3>
              <div class="space-y-3">
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_titles" />
                  <div class="font-medium text-slate-900">Show titles</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_platform_icon" />
                  <div class="font-medium text-slate-900">Show platform icon</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_feed_name" />
                  <div class="font-medium text-slate-900">Show feed / account name</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_share_icons" />
                  <div class="font-medium text-slate-900">Show share icons</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_likes" />
                  <div class="font-medium text-slate-900">Show likes</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.show_comments" />
                  <div class="font-medium text-slate-900">Show comments</div>
                </label>
                <label class="flex items-center gap-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50 p-2 rounded -mx-2">
                  <AppCheckbox v-model="appearance.post.autoplay_videos" />
                  <div class="font-medium text-slate-900">Autoplay videos</div>
                </label>
              </div>
            </div>

            <!-- Post Layout Section -->
            <div class="space-y-4 pt-4 border-t border-slate-200">
              <h3 class="text-sm font-semibold text-slate-900">Layout</h3>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Source layout</label>
                  <AppSelect v-model="appearance.post.source_row_layout" select-class="w-full py-2" :show-placeholder="false">
                    <option value="stacked">Stacked</option>
                    <option value="inline">Inline (compact)</option>
                  </AppSelect>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Alignment</label>
                  <AppSelect v-model="appearance.post.source_row_alignment" select-class="w-full py-2" :show-placeholder="false">
                    <option value="center">Center</option>
                    <option value="start">Start (left)</option>
                  </AppSelect>
                </div>
              </div>
              <div v-if="previewIsShowcase" class="grid grid-cols-2 gap-3 pt-3 border-t border-slate-200">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Showcase alignment</label>
                  <AppSelect v-model="appearance.post.showcase_content_alignment" select-class="w-full py-2" :show-placeholder="false">
                    <option value="start">Start (left)</option>
                    <option value="center">Center</option>
                  </AppSelect>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Share icon</label>
                  <AppSelect v-model="appearance.post.showcase_share_icon" select-class="w-full py-2" :show-placeholder="false">
                    <option value="upload_share">Upload / share</option>
                    <option value="arrow">Arrow only</option>
                    <option value="none">Hidden</option>
                  </AppSelect>
                </div>
              </div>
              <div v-if="previewIsShowcase && appearance.post.showcase_share_icon !== 'none'" class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Share icon color</label>
                  <AppSelect v-model="appearance.post.showcase_share_icon_color_mode" select-class="w-full py-2" :show-placeholder="false">
                    <option value="post_icon">Post icon color</option>
                    <option value="post_text">Post text color</option>
                    <option value="post_button">Post button color</option>
                    <option value="custom">Custom color</option>
                  </AppSelect>
                </div>
                <div v-if="appearance.post.showcase_share_icon_color_mode === 'custom'">
                  <label class="block text-xs font-semibold text-slate-700 mb-2">Custom share color</label>
                  <div class="flex items-center gap-2">
                    <AppInput
                      v-model="appearance.post.showcase_share_icon_color"
                      type="color"
                      input-class="h-10 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0"
                    />
                    <AppInput
                      v-model="appearance.post.showcase_share_icon_color"
                      type="text"
                      input-class="!py-2 !text-xs font-mono flex-1 min-w-0"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Colors Section -->
            <div class="space-y-4 pt-4 border-t border-slate-200">
              <h3 class="text-sm font-semibold text-slate-900">Colors</h3>
              <div class="space-y-3">
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <span class="text-xs font-semibold text-slate-700 w-20 shrink-0">Icon</span>
                  <AppInput v-model="appearance.colors.post_icon" type="color" input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0" />
                  <AppInput v-model="appearance.colors.post_icon" type="text" input-class="!py-1 !text-xs font-mono flex-1 min-w-0" />
                </div>
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <span class="text-xs font-semibold text-slate-700 w-20 shrink-0">Text</span>
                  <AppInput v-model="appearance.colors.post_text" type="color" input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0" />
                  <AppInput v-model="appearance.colors.post_text" type="text" input-class="!py-1 !text-xs font-mono flex-1 min-w-0" />
                </div>
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <span class="text-xs font-semibold text-slate-700 w-20 shrink-0">Date</span>
                  <AppInput v-model="appearance.colors.post_date" type="color" input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0" />
                  <AppInput v-model="appearance.colors.post_date" type="text" input-class="!py-1 !text-xs font-mono flex-1 min-w-0" />
                </div>
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <span class="text-xs font-semibold text-slate-700 w-20 shrink-0">Link</span>
                  <AppInput v-model="appearance.colors.post_link" type="color" input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0" />
                  <AppInput v-model="appearance.colors.post_link" type="text" input-class="!py-1 !text-xs font-mono flex-1 min-w-0" />
                </div>
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <span class="text-xs font-semibold text-slate-700 w-20 shrink-0">Button</span>
                  <AppInput v-model="appearance.colors.post_button" type="color" input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0" />
                  <AppInput v-model="appearance.colors.post_button" type="text" input-class="!py-1 !text-xs font-mono flex-1 min-w-0" />
                </div>
              </div>

              <!-- Optional Colors -->
              <div class="space-y-3 pt-3 border-t border-slate-200">
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <AppCheckbox v-model="appearance.colors.post_border.enabled" />
                  <span class="text-xs font-semibold text-slate-700 flex-1">Post border</span>
                  <AppInput
                    v-model="appearance.colors.post_border.color"
                    type="color"
                    input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0"
                    :disabled="!appearance.colors.post_border.enabled"
                  />
                </div>
                <div class="flex items-center gap-3 p-2 rounded hover:bg-slate-50">
                  <AppCheckbox v-model="appearance.colors.post_bg.enabled" />
                  <span class="text-xs font-semibold text-slate-700 flex-1">Post background</span>
                  <AppInput
                    v-model="appearance.colors.post_bg.color"
                    type="color"
                    input-class="h-8 w-12 rounded border border-slate-300 cursor-pointer bg-white p-0"
                    :disabled="!appearance.colors.post_bg.enabled"
                  />
                </div>
              </div>
            </div>

            <!-- Branding Section -->
            <div v-if="appearance?.branding" class="space-y-4 pt-4 border-t border-slate-200">
              <div>
                <h3 class="text-sm font-semibold text-slate-900">Showcase branding</h3>
                <p class="text-xs text-slate-500 mt-2">Customize icons and avatars on the Showcase carousel. Use HTTPS URLs for custom images (square PNG or JPG).</p>
              </div>

              <!-- Thumbnail Badge -->
              <div class="bg-slate-50 rounded-lg p-4 space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                  <AppCheckbox v-model="appearance.branding.media_badge.show" />
                  <div>
                    <div class="text-sm font-semibold text-slate-900">Media badge</div>
                    <div class="text-xs text-slate-500">Show badge on thumbnail images</div>
                  </div>
                </label>
                <div v-if="appearance.branding.media_badge.show" class="space-y-3 pl-7">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs font-semibold text-slate-700 mb-2">Image</label>
                      <AppSelect v-model="appearance.branding.media_badge.image_source" select-class="w-full py-2" :show-placeholder="false">
                        <option value="platform">Platform icon</option>
                        <option value="custom">Custom URL</option>
                        <option value="none">Hidden</option>
                      </AppSelect>
                    </div>
                    <div>
                      <label class="block text-xs font-semibold text-slate-700 mb-2">Position</label>
                      <AppSelect v-model="appearance.branding.media_badge.position" select-class="w-full py-2" :show-placeholder="false">
                        <option value="center">Center</option>
                        <option value="top_left">Top left</option>
                        <option value="top_right">Top right</option>
                        <option value="bottom_left">Bottom left</option>
                        <option value="bottom_right">Bottom right</option>
                      </AppSelect>
                    </div>
                  </div>
                  <AppInput
                    v-if="appearance.branding.media_badge.image_source === 'custom'"
                    v-model="appearance.branding.media_badge.custom_url"
                    type="url"
                    placeholder="https://example.com/badge.png"
                    input-class="w-full py-2 font-mono text-xs"
                  />
                </div>
              </div>

              <!-- Source Icon -->
              <div class="bg-slate-50 rounded-lg p-4 space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                  <AppCheckbox v-model="appearance.branding.source_icon.show" />
                  <div>
                    <div class="text-sm font-semibold text-slate-900">Feed icon</div>
                    <div class="text-xs text-slate-500">Show icon next to feed name</div>
                  </div>
                </label>
                <div v-if="appearance.branding.source_icon.show" class="space-y-3 pl-7">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs font-semibold text-slate-700 mb-2">Image</label>
                      <AppSelect v-model="appearance.branding.source_icon.image_source" select-class="w-full py-2" :show-placeholder="false">
                        <option value="platform">Platform icon</option>
                        <option value="custom">Custom URL</option>
                        <option value="none">Hidden</option>
                      </AppSelect>
                    </div>
                    <div>
                      <label class="block text-xs font-semibold text-slate-700 mb-2">Position</label>
                      <AppSelect v-model="appearance.branding.source_icon.position" select-class="w-full py-2" :show-placeholder="false">
                        <option value="before_name">Before name</option>
                        <option value="after_name">After name</option>
                      </AppSelect>
                    </div>
                  </div>
                  <AppInput
                    v-if="appearance.branding.source_icon.image_source === 'custom'"
                    v-model="appearance.branding.source_icon.custom_url"
                    type="url"
                    placeholder="https://example.com/icon.png"
                    input-class="w-full py-2 font-mono text-xs"
                  />
                </div>
              </div>

              <!-- Footer Avatar -->
              <div class="bg-slate-50 rounded-lg p-4 space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                  <AppCheckbox v-model="appearance.branding.account_avatar.show" />
                  <div>
                    <div class="text-sm font-semibold text-slate-900">Footer avatar</div>
                    <div class="text-xs text-slate-500">Show avatar in carousel footer</div>
                  </div>
                </label>
                <div v-if="appearance.branding.account_avatar.show" class="space-y-3 pl-7">
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Image source</label>
                        <AppSelect v-model="appearance.branding.account_avatar.image_source" select-class="w-full py-2" :show-placeholder="false">
                          <option value="connected">Account photo</option>
                          <option value="initial">Feed name letter</option>
                          <option value="custom">Custom URL</option>
                          <option value="none">Hidden</option>
                        </AppSelect>
                      </div>
                      <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-2">Position</label>
                        <AppSelect v-model="appearance.branding.account_avatar.position" select-class="w-full py-2" :show-placeholder="false">
                          <option value="footer_start">Left side</option>
                          <option value="footer_end">Right side</option>
                        </AppSelect>
                      </div>
                    </div>
                    <AppInput
                      v-if="appearance.branding.account_avatar.image_source === 'custom'"
                      v-model="appearance.branding.account_avatar.custom_url"
                      type="url"
                      placeholder="https://example.com/avatar.jpg"
                      input-class="w-full py-2 font-mono text-xs"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

        <div class="lg:sticky lg:top-5 self-start max-h-[calc(100vh-140px)] overflow-y-auto pr-1">
          <div class="surface-card p-4">
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="text-sm-pro text-slate-700">
                Publishing will make all <span class="font-medium">approved</span> posts live in the public feed.
              </div>
              <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="!w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="refresh"
                >
                  Refresh
                </AppButton>
                <AppButton
                  size="sm"
                  class="!w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  :disabled="publish.publishing"
                  @click="publishNow"
                >
                  {{ publish.publishing ? 'Publishing…' : 'Publish changes' }}
                </AppButton>
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="!w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="openCode"
                >
                  Get code
                </AppButton>
                <AppButton
                  variant="secondary"
                  size="sm"
                  class="!w-auto !py-1.5 !px-3 text-sm-pro whitespace-nowrap"
                  @click="showEmbedPreview = true"
                  :disabled="!embedPublicKey"
                  title="Test embed in iframe"
                >
                  Test embed
                </AppButton>
              </div>
            </div>
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="text-sm-pro font-medium text-slate-800">Preview (published)</div>
            <div class="text-2xs text-slate-500 mt-0.5">
              Uses the same layout CSS as your embed. Changes as you adjust options; save so live sites load the updated script.
            </div>
          </div>
          <a
            v-if="previewPostsJsonUrl"
            :href="previewPostsJsonUrl"
            target="_blank"
            rel="noreferrer"
            class="text-sm-pro text-slate-600 hover:text-slate-800 underline underline-offset-2 shrink-0"
          >
            Open JSON
          </a>
        </div>

        <Teleport to="head">
          <link
            v-if="embedCssHref"
            rel="stylesheet"
            :href="embedCssHref"
            :key="embedCssHref"
          />
        </Teleport>

        <div v-if="previewLoading" class="text-sm-pro text-slate-500">Loading preview…</div>
        <div v-else-if="!previewPosts.length" class="text-sm-pro text-slate-600 space-y-2">
          <p>No published posts in this preview yet.</p>
          <p class="text-2xs text-slate-500">
            Approving in <strong class="font-medium text-slate-600">Curate</strong> only marks posts as approved.
            Click <strong class="font-medium text-slate-600">Publish changes</strong> above so approved items get a
            <code class="text-slate-700">published_at</code> time—then they show here and in the public embed.
          </p>
        </div>
        <div
          v-else-if="appearance && previewUsesEmbedStylesheet"
          class="rounded-lg border border-slate-200/90 bg-slate-50/90 p-3 overflow-x-auto curator-embed-preview"
          :class="{ 'curator-embed-preview--showcase': previewIsShowcase }"
        >
          <div
            class="crt-wrap"
            :class="{ 'crt-wrap--showcase': previewIsShowcase }"
            :style="previewColorCssVars"
          >
            <template v-if="previewIsShowcase">
              <div class="crt-showcase-viewport">
                <AppButton
                  variant="ghost"
                  class="crt-showcase-nav crt-showcase-nav--prev"
                  aria-label="Previous posts"
                  @click="showcasePreviewScroll(-1)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.25"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M15 18 9 12l6-6" />
                  </svg>
                </AppButton>
                <div
                  ref="previewStripRef"
                  :class="['crt-inner', previewLayoutClass]"
                  :style="previewInnerStyle"
                >
                  <a
                    v-for="(row, idx) in showcasePreviewRows"
                    :key="row.p.id"
                    :href="row.p.video_url || '#'"
                    target="_blank"
                    rel="noreferrer"
                    class="crt-card crt-card--showcase crt-link"
                    :style="previewCardStyle(idx)"
                  >
                    <div v-if="row.p.thumbnail_url" class="crt-media">
                      <img :src="row.p.thumbnail_url" :alt="row.p.title || 'Post'" loading="lazy" />
                      <div
                        v-if="previewMediaBadgeVisible"
                        :class="['crt-media-overlay', `crt-media-overlay--${previewMediaOverlayPositionClass}`]"
                      >
                        <img
                          v-if="previewMediaBadgeUsesCustom"
                          :src="appearance.branding.media_badge.custom_url"
                          alt=""
                          class="crt-brand-img crt-brand-img--media"
                          loading="lazy"
                        />
                        <span
                          v-else
                          :class="[
                            'crt-showcase-provider-icon flex items-center justify-center',
                            previewSocialIconClass(row.p.provider),
                          ]"
                        >
                          <SocialIcon
                            :type="previewProviderIconType(row.p.provider)"
                            class="w-14 h-14 opacity-95 drop-shadow-md"
                          />
                        </span>
                      </div>
                    </div>
                    <div
                      class="crt-body crt-body--showcase"
                      :class="{ 'crt-showcase--align-center': previewShowcaseAlignCenter }"
                    >
                      <div class="crt-showcase-source">
                        <template v-if="previewSourceIconAfterName">
                          <span class="crt-showcase-feed-name">{{
                            previewPostAccountLabel(row.p) || row.p.provider || 'Social'
                          }}</span>
                          <span
                            v-if="previewSourceIconVisible"
                            :class="[
                              'crt-showcase-source-icon flex items-center justify-center',
                              previewSocialIconClass(row.p.provider),
                            ]"
                          >
                            <img
                              v-if="previewSourceIconUsesCustom"
                              :src="appearance.branding.source_icon.custom_url"
                              alt=""
                              class="crt-brand-img crt-brand-img--inline"
                              loading="lazy"
                            />
                            <SocialIcon
                              v-else
                              :type="previewProviderIconType(row.p.provider)"
                              class="w-4 h-4"
                            />
                          </span>
                        </template>
                        <template v-else>
                          <span
                            v-if="previewSourceIconVisible"
                            :class="[
                              'crt-showcase-source-icon flex items-center justify-center',
                              previewSocialIconClass(row.p.provider),
                            ]"
                          >
                            <img
                              v-if="previewSourceIconUsesCustom"
                              :src="appearance.branding.source_icon.custom_url"
                              alt=""
                              class="crt-brand-img crt-brand-img--inline"
                              loading="lazy"
                            />
                            <SocialIcon
                              v-else
                              :type="previewProviderIconType(row.p.provider)"
                              class="w-4 h-4"
                            />
                          </span>
                          <span class="crt-showcase-feed-name">{{
                            previewPostAccountLabel(row.p) || row.p.provider || 'Social'
                          }}</span>
                        </template>
                      </div>
                      <div v-if="appearance.post.show_titles !== false" class="crt-title crt-title--showcase">
                        {{ row.p.title || 'Untitled' }}
                      </div>
                      <div class="crt-text crt-text--showcase">
                        {{ clampPreview(row.plain, 260) }}
                      </div>
                      <div v-if="row.tags.length" class="crt-hashtags">
                        <span v-for="tag in row.tags.slice(0, 8)" :key="tag" class="crt-hashtag">{{
                          tag
                        }}</span>
                      </div>
                      <div class="crt-showcase-footer">
                        <template v-if="previewAccountFooterEnd">
                          <div class="crt-showcase-meta-stack">
                            <span class="crt-showcase-handle">{{ previewShowcaseHandle(row.p) }}</span>
                            <span class="crt-showcase-foot-date">{{
                              formatPreviewDateShowcase(row.p.posted_at)
                            }}</span>
                          </div>
                          <div
                            v-if="row.p.video_url && !previewShowcaseShareHidden"
                            class="crt-showcase-share"
                            @click.stop.prevent
                            @mousedown.stop.prevent
                          >
                            <button
                              type="button"
                              class="crt-showcase-share-btn"
                              aria-label="Share"
                              @click.stop.prevent="togglePreviewShareMenu(row.p.id)"
                            >
                              <svg
                                v-if="previewShowcaseShareIsArrow"
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                              >
                                <path d="M7 17 17 7M17 7H9M17 7v8" />
                              </svg>
                              <svg
                                v-else
                                width="18"
                                height="15"
                                viewBox="0 0 18 15"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                              >
                                <path
                                  fill-rule="evenodd"
                                  clip-rule="evenodd"
                                  d="M9.65875 0.821899V13.0736L17.2182 6.94777L9.65875 0.821899Z"
                                  fill="currentColor"
                                ></path>
                                <path
                                  fill-rule="evenodd"
                                  clip-rule="evenodd"
                                  d="M0.138031 13.1146C0.138031 8.46583 3.7997 4.60382 10.2833 4.60382V9.39066C10.2833 9.39066 6.07325 8.10554 1.03012 13.1146C0.76259 13.1439 0.138031 13.1146 0.138031 13.1146Z"
                                  fill="currentColor"
                                ></path>
                              </svg>
                            </button>
                            <div
                              v-if="previewShareMenuForPostId === row.p.id"
                              class="crt-showcase-share-tooltip"
                              role="menu"
                              aria-label="Choose platform"
                              @click.stop.prevent
                              @mousedown.stop.prevent
                            >
                              <button
                                type="button"
                                class="crt-showcase-share-platform"
                                title="Share on X"
                                aria-label="Share on X"
                                @click.stop.prevent="openPreviewShare('twitter', row.p.video_url)"
                              >
                                <SocialIcon type="twitter" class="w-4 h-4" />
                              </button>
                              <button
                                type="button"
                                class="crt-showcase-share-platform"
                                title="Share on Facebook"
                                aria-label="Share on Facebook"
                                @click.stop.prevent="openPreviewShare('facebook', row.p.video_url)"
                              >
                                <SocialIcon type="facebook" class="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                          <img
                            v-if="previewAccountVisible && previewAccountUsesCustom"
                            :src="appearance.branding.account_avatar.custom_url"
                            alt=""
                            class="crt-showcase-avatar crt-showcase-avatar--img"
                            loading="lazy"
                          />
                          <img
                            v-else-if="
                              previewAccountVisible &&
                              previewAccountUsesConnected &&
                              row.p.account_avatar_url
                            "
                            :src="row.p.account_avatar_url"
                            alt=""
                            class="crt-showcase-avatar crt-showcase-avatar--img"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                          />
                          <span
                            v-else-if="previewAccountVisible && previewShowcaseAvatarUsesLetter(row.p)"
                            class="crt-showcase-avatar"
                            >{{ previewShowcaseAvatarLetter(row.p) }}</span
                          >
                        </template>
                        <template v-else>
                          <img
                            v-if="previewAccountVisible && previewAccountUsesCustom"
                            :src="appearance.branding.account_avatar.custom_url"
                            alt=""
                            class="crt-showcase-avatar crt-showcase-avatar--img"
                            loading="lazy"
                          />
                          <img
                            v-else-if="
                              previewAccountVisible &&
                              previewAccountUsesConnected &&
                              row.p.account_avatar_url
                            "
                            :src="row.p.account_avatar_url"
                            alt=""
                            class="crt-showcase-avatar crt-showcase-avatar--img"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                          />
                          <span
                            v-else-if="previewAccountVisible && previewShowcaseAvatarUsesLetter(row.p)"
                            class="crt-showcase-avatar"
                            >{{ previewShowcaseAvatarLetter(row.p) }}</span
                          >
                          <div class="crt-showcase-meta-stack">
                            <span class="crt-showcase-handle">{{ previewShowcaseHandle(row.p) }}</span>
                            <span class="crt-showcase-foot-date">{{
                              formatPreviewDateShowcase(row.p.posted_at)
                            }}</span>
                          </div>
                          <div
                            v-if="row.p.video_url && !previewShowcaseShareHidden"
                            class="crt-showcase-share"
                            @click.stop.prevent
                            @mousedown.stop.prevent
                          >
                            <button
                              type="button"
                              class="crt-showcase-share-btn"
                              aria-label="Share"
                              @click.stop.prevent="togglePreviewShareMenu(row.p.id)"
                            >
                              <svg
                                v-if="previewShowcaseShareIsArrow"
                                xmlns="http://www.w3.org/2000/svg"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                              >
                                <path d="M7 17 17 7M17 7H9M17 7v8" />
                              </svg>
                              <svg
                                v-else
                                width="18"
                                height="15"
                                viewBox="0 0 18 15"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                              >
                                <path
                                  fill-rule="evenodd"
                                  clip-rule="evenodd"
                                  d="M9.65875 0.821899V13.0736L17.2182 6.94777L9.65875 0.821899Z"
                                  fill="currentColor"
                                ></path>
                                <path
                                  fill-rule="evenodd"
                                  clip-rule="evenodd"
                                  d="M0.138031 13.1146C0.138031 8.46583 3.7997 4.60382 10.2833 4.60382V9.39066C10.2833 9.39066 6.07325 8.10554 1.03012 13.1146C0.76259 13.1439 0.138031 13.1146 0.138031 13.1146Z"
                                  fill="currentColor"
                                ></path>
                              </svg>
                            </button>
                            <div
                              v-if="previewShareMenuForPostId === row.p.id"
                              class="crt-showcase-share-tooltip"
                              role="menu"
                              aria-label="Choose platform"
                              @click.stop.prevent
                              @mousedown.stop.prevent
                            >
                              <button
                                type="button"
                                class="crt-showcase-share-platform"
                                title="Share on X"
                                aria-label="Share on X"
                                @click.stop.prevent="openPreviewShare('twitter', row.p.video_url)"
                              >
                                <SocialIcon type="twitter" class="w-4 h-4" />
                              </button>
                              <button
                                type="button"
                                class="crt-showcase-share-platform"
                                title="Share on Facebook"
                                aria-label="Share on Facebook"
                                @click.stop.prevent="openPreviewShare('facebook', row.p.video_url)"
                              >
                                <SocialIcon type="facebook" class="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                  </a>
                </div>
                <AppButton
                  variant="ghost"
                  class="crt-showcase-nav crt-showcase-nav--next"
                  aria-label="Next posts"
                  @click="showcasePreviewScroll(1)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.25"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M9 18l6-6-6-6" />
                  </svg>
                </AppButton>
              </div>
            </template>
            <template v-else>
              <div :class="['crt-inner', previewLayoutClass]" :style="previewInnerStyle">
                <a
                  v-for="(p, idx) in previewPosts"
                  :key="p.id"
                  :href="p.video_url || '#'"
                  target="_blank"
                  rel="noreferrer"
                  class="crt-card crt-link"
                  :style="previewCardStyle(idx)"
                >
                  <div v-if="p.thumbnail_url" class="crt-media">
                    <img :src="p.thumbnail_url" :alt="p.title || 'Post'" loading="lazy" />
                  </div>
                  <div class="crt-body">
                    <div v-if="previewStandardSourceRowShowsForPost(p)" :class="previewStandardSourceRowClassList()">
                      <span
                        v-if="appearance.post.show_platform_icon !== false && p.provider"
                        :class="['crt-platform-badge crt-platform-badge--inline', previewSocialIconClass(p.provider)]"
                      >
                        <SocialIcon :type="previewProviderIconType(p.provider)" class="w-[22px] h-[22px]" />
                      </span>
                      <span
                        v-if="
                          appearance.post.show_feed_name !== false &&
                          String(previewPostAccountLabel(p) || p.provider || '').trim()
                        "
                        class="crt-source-label"
                      >
                        {{ String(previewPostAccountLabel(p) || p.provider || '').trim() }}
                      </span>
                    </div>
                    <div v-if="appearance.post.show_titles !== false" class="crt-title">
                      {{ p.title || 'Untitled' }}
                    </div>
                    <div class="crt-text">{{ clampPreview(p.content, 220) }}</div>
                    <div class="crt-meta">
                      <span class="crt-meta-date">{{ formatPreviewDate(p.posted_at) }}</span>
                      <span
                        v-if="appearance.post.show_likes || appearance.post.show_comments"
                        class="crt-meta-social"
                      >
                        <template v-if="appearance.post.show_likes">♥</template>
                        <template v-if="appearance.post.show_likes && appearance.post.show_comments">
                        </template>
                        <template v-if="appearance.post.show_comments">💬</template>
                      </span>
                    </div>
                    <div
                      v-if="appearance.post.show_share_icons && p.video_url"
                      class="crt-share"
                      @click.stop.prevent
                      @mousedown.stop.prevent
                    >
                      <button
                        type="button"
                        class="crt-share-link crt-share-link--trigger"
                        aria-label="Share"
                        title="Share"
                        @click.stop.prevent="togglePreviewShareMenu(p.id)"
                      >
                        <svg
                          width="18"
                          height="15"
                          viewBox="0 0 18 15"
                          fill="none"
                          xmlns="http://www.w3.org/2000/svg"
                        >
                          <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M9.65875 0.821899V13.0736L17.2182 6.94777L9.65875 0.821899Z"
                            fill="currentColor"
                          ></path>
                          <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M0.138031 13.1146C0.138031 8.46583 3.7997 4.60382 10.2833 4.60382V9.39066C10.2833 9.39066 6.07325 8.10554 1.03012 13.1146C0.76259 13.1439 0.138031 13.1146 0.138031 13.1146Z"
                            fill="currentColor"
                          ></path>
                        </svg>
                      </button>
                      <div
                        v-if="previewShareMenuForPostId === p.id"
                        class="crt-share-tooltip"
                        role="menu"
                        aria-label="Choose platform"
                        @click.stop.prevent
                        @mousedown.stop.prevent
                      >
                        <button
                          type="button"
                          class="crt-showcase-share-platform"
                          title="Share on X"
                          aria-label="Share on X"
                          @click.stop.prevent="openPreviewShare('twitter', p.video_url)"
                        >
                          <SocialIcon type="twitter" class="w-4 h-4" />
                        </button>
                        <button
                          type="button"
                          class="crt-showcase-share-platform"
                          title="Share on Facebook"
                          aria-label="Share on Facebook"
                          @click.stop.prevent="openPreviewShare('facebook', p.video_url)"
                        >
                          <SocialIcon type="facebook" class="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  </div>
                </a>
              </div>
            </template>
          </div>
        </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <a
              v-for="p in previewPosts"
              :key="p.id"
              :href="p.video_url || '#'"
              target="_blank"
              rel="noreferrer"
              class="block bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-card transition overflow-hidden"
            >
              <div v-if="p.thumbnail_url" class="aspect-video bg-slate-100 overflow-hidden">
                <img :src="p.thumbnail_url" class="w-full h-full object-cover" :alt="p.title || 'Post'" />
              </div>
              <div class="p-3">
                <div class="text-sm-pro font-medium text-slate-800 line-clamp-2">{{ p.title || 'Untitled' }}</div>
                <div class="text-2xs text-slate-500 mt-1 line-clamp-3">{{ p.content }}</div>
              </div>
            </a>
          </div>
        </div>
        </div>
    </div>

    <div v-if="showCode" class="fixed inset-0 bg-black/30 flex items-center justify-center p-4 z-50">
      <div class="w-full max-w-2xl surface-card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <div class="text-sm-pro font-medium text-slate-800">Embed code</div>
          <AppButton variant="secondary" size="sm" @click="showCode = false">Close</AppButton>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-2xs text-slate-500">
            Paste this into your website where you want the feed to appear.
          </div>
          <AppInput type="textarea" input-class="font-mono text-2xs !h-40" readonly :model-value="publish.code?.embed_html || ''" />
          <div class="flex items-center gap-2">
            <AppButton size="sm" @click="copyCode">Copy</AppButton>
            <div v-if="copied" class="text-2xs text-slate-500">Copied</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Embed iframe preview modal -->
    <div v-if="showEmbedPreview" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
      <div class="w-full max-w-3xl surface-card overflow-hidden flex flex-col h-[80vh]">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between shrink-0">
          <div class="text-sm-pro font-medium text-slate-800">Live embed preview</div>
          <AppButton variant="secondary" size="sm" @click="showEmbedPreview = false">Close</AppButton>
        </div>
        <iframe
          v-if="embedPublicKey"
          :srcdoc="embedIframeHtml"
          class="flex-1 w-full border-0"
          title="Embed preview"
          sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
        />
      </div>
    </div>

    <template #footer>
      <AppButton :to="`/workspaces/${workspaceId}/curate`" variant="secondary" size="sm" title="Go back">←</AppButton>
      <AppButton
        size="sm"
        :disabled="publish.publishing"
        @click="publishNow"
        title="Publish and finish"
      >
        {{ publish.publishing ? '⏳' : '✓' }}
      </AppButton>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useWorkspacesStore } from '../stores/workspaces';
import { usePublishStore } from '../stores/publish';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import { AppButton, AppCheckbox, AppInput, AppSelect } from '../components/ui';
import { fetchPreviewPosts } from '../composables/usePublishApi';

defineOptions({ name: 'PublishView' });

const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const workspaces = useWorkspacesStore();
const publish = usePublishStore();

const workspaceId = ref('');

/** Mirrors backend PublishSettings branding defaults for nested form + preview */
const BRANDING_DEFAULTS = {
  media_badge: {
    show: true,
    image_source: 'platform',
    custom_url: '',
    position: 'center',
  },
  source_icon: {
    show: true,
    image_source: 'platform',
    custom_url: '',
    position: 'before_name',
  },
  account_avatar: {
    show: true,
    image_source: 'connected',
    custom_url: '',
    position: 'footer_start',
  },
};

const POST_DEFAULTS = {
  show_titles: true,
  show_share_icons: false,
  show_comments: false,
  show_likes: false,
  autoplay_videos: false,
  show_platform_icon: true,
  show_feed_name: true,
  source_row_layout: 'stacked',
  source_row_alignment: 'center',
  showcase_content_alignment: 'start',
  showcase_share_icon: 'upload_share',
  showcase_share_icon_color_mode: 'post_icon',
  showcase_share_icon_color: '#e2e8f0',
};

function mergePublishAppearance(raw) {
  const clone = JSON.parse(JSON.stringify(raw));
  clone.post = { ...POST_DEFAULTS, ...(clone.post || {}) };
  const b = clone.branding || {};
  clone.branding = {
    media_badge: { ...BRANDING_DEFAULTS.media_badge, ...(b.media_badge || {}) },
    source_icon: { ...BRANDING_DEFAULTS.source_icon, ...(b.source_icon || {}) },
    account_avatar: { ...BRANDING_DEFAULTS.account_avatar, ...(b.account_avatar || {}) },
  };
  return clone;
}

const showCode = ref(false);
const copied = ref(false);
const publishedCount = ref(null);
const showEmbedPreview = ref(false);

const previewLoading = ref(false);
const previewPosts = ref([]);
const previewStripRef = ref(null);
const previewShareMenuForPostId = ref(null);
const embedPreviewVersion = ref(0);

/** Local copy of publish_settings for the appearance form */
const appearance = ref(null);

const feedStyleOptions = [
  { value: 'waterfall', label: 'Waterfall' },
  { value: 'grid', label: 'Grid' },
  { value: 'grid_carousel', label: 'Grid carousel' },
  { value: 'carousel', label: 'Carousel' },
  {
    value: 'showcase_carousel',
    label: 'Showcase carousel',
  },
  { value: 'mosaic', label: 'Mosaic' },
  { value: 'tetris', label: 'Tetris' },
  { value: 'select', label: 'Select' },
  { value: 'cover_flow', label: 'Cover flow' },
  { value: 'list', label: 'List' },
  { value: 'stagger', label: 'Stagger' },
  { value: 'layers', label: 'Layers' },
];

const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

/** Workspace public key from embed code response or publish stats (either may load first). */
const embedPublicKey = computed(
  () => publish.code?.public_key || publish.stats?.public_key || '',
);

function toSameOriginPath(url) {
  if (!url || typeof window === 'undefined') return '';
  try {
    const u = new URL(url, window.location.origin);

    return u.pathname + u.search;
  } catch {
    return url.startsWith('/') ? url : `/${url}`;
  }
}

/** Relative `/api/embed/...` so the SPA origin + Vite proxy load CSS (APP_URL on another host breaks otherwise). */
const embedCssHref = computed(() => {
  const key = embedPublicKey.value;
  if (!key) return '';

  const fromCode = publish.code?.embed_css_url;
  if (fromCode) {
    const sep = fromCode.includes('?') ? '&' : '?';
    return `${toSameOriginPath(fromCode)}${sep}pv=${embedPreviewVersion.value}`;
  }

  const v = encodeURIComponent(String(publish.stats?.last_published_at || Date.now()));

  return `/api/embed/${encodeURIComponent(key)}.css?v=${v}&pv=${embedPreviewVersion.value}`;
});

const embedJsPath = computed(() => {
  const key = embedPublicKey.value;
  if (!key) return '';

  const fromCode = publish.code?.embed_js_url;
  if (fromCode) {
    const sep = fromCode.includes('?') ? '&' : '?';
    return `${toSameOriginPath(fromCode)}${sep}pv=${embedPreviewVersion.value}`;
  }

  const v = encodeURIComponent(String(publish.stats?.last_published_at || Date.now()));

  return `/api/embed/${encodeURIComponent(key)}.js?v=${v}&pv=${embedPreviewVersion.value}`;
});

const previewUsesEmbedStylesheet = computed(() => !!embedCssHref.value);

const previewPostsJsonUrl = computed(() => {
  const key = embedPublicKey.value;
  if (!key) return '';
  if (publish.code?.public_posts_url) return publish.code.public_posts_url;
  return `/api/public/feeds/${encodeURIComponent(key)}/posts`;
});

const embedIframeHtml = computed(() => {
  const key = embedPublicKey.value;
  const origin = typeof window !== 'undefined' ? window.location.origin : '';
  const markup =
    publish.code?.embed_html ||
    (key ? `<div data-curator-feed="${key}"></div>` : '');

  if (!key || !markup) {
    return '<p style="font-family:sans-serif;padding:1rem;color:#64748b">No embed code yet — publish once so this workspace has a public key, then try again.</p>';
  }

  const cssPath = embedCssHref.value;
  const jsPath = embedJsPath.value;
  const css = cssPath ? `<link rel="stylesheet" href="${origin}${cssPath}">` : '';
  const js = jsPath ? `<script src="${origin}${jsPath}"></scr` + 'ipt>' : '';

  return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${css}</head><body style="margin:0;padding:16px;background:#f8fafc">${markup}${js}</body></html>`;
});

const previewLayoutClass = computed(() => {
  const st = String(appearance.value?.feed_style || 'grid').replace(/-/g, '_');
  return `crt-layout--${st}`;
});

const previewIsShowcase = computed(
  () => String(appearance.value?.feed_style || '').replace(/-/g, '_') === 'showcase_carousel',
);

const showcasePreviewRows = computed(() =>
  previewPosts.value.map((p) => {
    const { plain, tags } = splitPreviewHashtags(p.content || '');
    return { p, plain, tags };
  }),
);

const previewMediaBadgeVisible = computed(() => {
  const mb = appearance.value?.branding?.media_badge;
  if (!mb) return true;
  return mb.show !== false && mb.image_source !== 'none';
});

const previewMediaBadgeUsesCustom = computed(() => {
  const mb = appearance.value?.branding?.media_badge;
  return mb?.image_source === 'custom' && !!String(mb?.custom_url || '').trim();
});

const previewMediaOverlayPositionClass = computed(() =>
  String(appearance.value?.branding?.media_badge?.position || 'center').replace(/_/g, '-'),
);

const previewSourceIconVisible = computed(() => {
  const si = appearance.value?.branding?.source_icon;
  if (!si) return true;
  return si.show !== false && si.image_source !== 'none';
});

const previewSourceIconUsesCustom = computed(() => {
  const si = appearance.value?.branding?.source_icon;
  return si?.image_source === 'custom' && !!String(si?.custom_url || '').trim();
});

const previewSourceIconAfterName = computed(
  () => appearance.value?.branding?.source_icon?.position === 'after_name',
);

const previewAccountVisible = computed(() => {
  const a = appearance.value?.branding?.account_avatar;
  if (!a) return true;
  return a.show !== false && a.image_source !== 'none';
});

const previewAccountUsesCustom = computed(() => {
  const a = appearance.value?.branding?.account_avatar;
  return a?.image_source === 'custom' && !!String(a?.custom_url || '').trim();
});

const previewAccountUsesConnected = computed(
  () => appearance.value?.branding?.account_avatar?.image_source === 'connected',
);

const previewShowcaseAlignCenter = computed(
  () =>
    String(appearance.value?.post?.showcase_content_alignment || 'start').replace(/-/g, '_') ===
    'center',
);

const previewShowcaseShareHidden = computed(
  () =>
    String(appearance.value?.post?.showcase_share_icon || 'upload_share').replace(/-/g, '_') ===
    'none',
);

const previewShowcaseShareIsArrow = computed(
  () =>
    String(appearance.value?.post?.showcase_share_icon || 'upload_share').replace(/-/g, '_') ===
    'arrow',
);

const previewAccountFooterEnd = computed(
  () => appearance.value?.branding?.account_avatar?.position === 'footer_end',
);

const previewColorCssVars = computed(() => {
  const a = appearance.value;
  if (!a?.colors) return {};
  const c = a.colors;
  const feed = a.feed || {};
  const minW = Math.max(120, Math.min(Number(feed.post_min_width) || 260, 600));
  const b = c.post_border || {};
  const g = c.post_bg || {};
  const post = a.post || {};
  const shareColorMode = String(post.showcase_share_icon_color_mode || 'post_icon');
  let shareColor = c.post_icon;
  if (shareColorMode === 'post_text') shareColor = c.post_text;
  else if (shareColorMode === 'post_button') shareColor = c.post_button;
  else if (shareColorMode === 'custom') shareColor = post.showcase_share_icon_color || c.post_icon;
  return {
    '--crt-icon': c.post_icon,
    '--crt-text': c.post_text,
    '--crt-date': c.post_date,
    '--crt-link': c.post_link,
    '--crt-btn': c.post_button,
    '--crt-showcase-share-color': shareColor || c.post_icon || '#e2e8f0',
    '--crt-post-min': `${minW}px`,
    '--crt-border': b.enabled !== false ? b.color || '#e2e8f0' : 'transparent',
    '--crt-card-bg': g.enabled !== false ? g.color || '#ffffff' : 'transparent',
  };
});

const previewInnerStyle = computed(() => {
  if (!appearance.value) return {};
  const st = String(appearance.value.feed_style || 'grid').replace(/-/g, '_');
  if (st === 'layers') {
    const n = previewPosts.value.length;
    return {
      position: 'relative',
      minHeight: `${Math.min(520, 140 + Math.max(0, n - 1) * 26)}px`,
    };
  }
  return {};
});

function previewCardStyle(idx) {
  if (!appearance.value) return {};
  const st = String(appearance.value.feed_style || 'grid').replace(/-/g, '_');
  if (st === 'stagger') return { animationDelay: `${idx * 0.07}s` };
  if (st === 'layers') {
    const baseW = 300;
    return {
      position: 'absolute',
      width: `${baseW}px`,
      left: '50%',
      marginLeft: `${-baseW / 2 + idx * 14}px`,
      top: `${24 + idx * 20}px`,
      zIndex: 10 + idx,
    };
  }
  return {};
}

function clampPreview(text, n) {
  const s = String(text || '');
  return s.length > n ? `${s.slice(0, n - 1)}…` : s;
}

function splitPreviewHashtags(raw) {
  const tags = [];
  const plain = String(raw || '').replace(/#([a-zA-Z0-9_]+)/g, (_, w) => {
    tags.push(`#${w}`);
    return '';
  });
  return { plain: plain.replace(/\s+/g, ' ').trim(), tags };
}

function previewShareUrl(provider, url) {
  const target = encodeURIComponent(String(url || ''));
  if (provider === 'facebook') {
    return `https://www.facebook.com/sharer/sharer.php?u=${target}`;
  }
  return `https://twitter.com/intent/tweet?url=${target}`;
}

function openPreviewShare(provider, url) {
  const href = previewShareUrl(provider, url);
  if (typeof window !== 'undefined') {
    window.open(href, '_blank', 'noopener,noreferrer');
  }
  closePreviewShareMenu();
}

function togglePreviewShareMenu(postId) {
  previewShareMenuForPostId.value = previewShareMenuForPostId.value === postId ? null : postId;
}

function closePreviewShareMenu() {
  previewShareMenuForPostId.value = null;
}

function handlePreviewShareOutsideClick() {
  closePreviewShareMenu();
}

function previewPostAccountLabel(p) {
  const a = String(p?.account_label || '').trim();
  if (a) return a;
  return String(p?.feed_name || '').trim();
}

function previewShowcaseHandle(p) {
  let raw = previewPostAccountLabel(p);
  if (!raw) raw = String(p?.provider || 'social').trim();
  if (!raw) return '@social';
  if (raw.startsWith('@')) return raw;
  return `@${raw}`;
}

function previewShowcaseAvatarLetter(p) {
  const raw = previewPostAccountLabel(p);
  const n = raw.replace(/^@+/, '').trim() || String(p?.provider || '?').trim();
  return n ? n.charAt(0).toUpperCase() : '?';
}

function previewShowcaseAvatarUsesLetter(p) {
  const a = appearance.value?.branding?.account_avatar;
  if (!a || a.show === false || a.image_source === 'none') return false;
  if (a.image_source === 'custom' && String(a.custom_url || '').trim()) return false;
  if (a.image_source === 'connected' && String(p?.account_avatar_url || '').trim()) return false;
  return true;
}

function previewSocialIconClass(provider) {
  const p = String(provider || '').toLowerCase();
  const map = {
    youtube: 'text-[#FF0000]',
    facebook: 'text-[#1877F2]',
    instagram: 'text-[#E4405F]',
    tiktok: 'text-[#000000]',
    twitter: 'text-[#000000]',
    threads: 'text-[#101419]',
    rss: 'text-[#ea580c]',
  };
  return map[p] || 'text-slate-600';
}

function previewProviderIconType(provider) {
  const p = String(provider || '').toLowerCase();
  if (['youtube', 'facebook', 'instagram', 'tiktok', 'twitter', 'threads', 'rss'].includes(p)) {
    return p;
  }
  return 'other';
}

function previewStandardSourceRowShowsForPost(p) {
  const po = appearance.value?.post;
  if (!po) return true;
  const showIcon = po.show_platform_icon !== false;
  const showName = po.show_feed_name !== false;
  const label = String(previewPostAccountLabel(p) || p.provider || '').trim();
  const prov = String(p.provider || '').trim();
  if (!showIcon && !showName) return false;
  if (!showIcon && (!showName || !label)) return false;
  if (!showName && (!showIcon || !prov)) return false;
  return true;
}

function previewStandardSourceRowClassList() {
  const po = appearance.value?.post || {};
  let layout = String(po.source_row_layout || 'stacked').replace(/-/g, '_');
  if (layout !== 'inline') layout = 'stacked';
  let align = String(po.source_row_alignment || 'center').replace(/-/g, '_');
  if (align !== 'start') align = 'center';
  return [`crt-source-row`, `crt-source-row--${layout}`, `crt-source-row--align-${align}`];
}

function formatPreviewDateShowcase(v) {
  try {
    return new Date(v)
      .toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
      .toUpperCase();
  } catch {
    return '';
  }
}

function showcasePreviewScroll(dir) {
  const el = previewStripRef.value;
  if (!el) return;
  const step = Math.max(260, Math.floor(el.clientWidth * 0.82));
  el.scrollBy({ left: dir * step, behavior: 'smooth' });
}

function formatPreviewDate(v) {
  try {
    return new Date(v).toLocaleDateString();
  } catch {
    return '';
  }
}

watch(
  () => publish.publishSettings,
  (s) => {
    appearance.value = s ? mergePublishAppearance(s) : null;
  },
  { immediate: true },
);

onMounted(async () => {
  document.addEventListener('click', handlePreviewShareOutsideClick);
  await workspaces.fetchAll();
  if (route.params.workspaceId) {
    workspaceId.value = String(route.params.workspaceId);
  } else if (!workspaceId.value && workspaces.list.length) {
    workspaceId.value = String(workspaces.list[0].id);
  }
  if (workspaceId.value) {
    await publish.fetchStats(workspaceId.value);
    await publish.fetchCode(workspaceId.value);
    await loadPreview();
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handlePreviewShareOutsideClick);
});

watch(workspaceId, async (id) => {
  publish.clear();
  previewPosts.value = [];
  if (id) {
    await publish.fetchStats(id);
    await publish.fetchCode(id);
    await loadPreview();
  }
  if (route.name === 'workspace-publish' && id) {
    router.replace(`/workspaces/${id}/publish`);
  }
});

async function saveAppearance() {
  if (!workspaceId.value || !appearance.value) return;
  await publish.savePublishSettings(workspaceId.value, appearance.value);
  await publish.fetchCode(workspaceId.value);
  embedPreviewVersion.value += 1;
}

async function refresh() {
  if (!workspaceId.value) return;
  await publish.fetchStats(workspaceId.value);
  await publish.fetchCode(workspaceId.value);
  await loadPreview();
}

async function publishNow() {
  if (!workspaceId.value) return;
  const result = await publish.publish(workspaceId.value);
  publishedCount.value = result?.published ?? 0;
  await publish.fetchCode(workspaceId.value);
  await loadPreview();
}

async function openCode() {
  if (!workspaceId.value) return;
  await publish.fetchCode(workspaceId.value);
  showCode.value = true;
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(publish.code?.embed_html || '');
    copied.value = true;
    setTimeout(() => (copied.value = false), 1200);
  } catch {
    toast.error('Copy failed');
  }
}

async function loadPreview() {
  const key = publish.code?.public_key || publish.stats?.public_key;
  if (!key) return;
  // Same-origin `/api/...` matches other stores (and Vite proxy) so preview works when APP_URL
  // differs from the SPA origin or CORS blocks absolute public_posts_url.
  const url = `/api/public/feeds/${encodeURIComponent(key)}/posts`;
  previewLoading.value = true;
  try {
    const data = await fetchPreviewPosts(url, 9);
    previewPosts.value = data.posts || [];
  } catch {
    previewPosts.value = [];
  } finally {
    previewLoading.value = false;
  }
}

</script>

<style scoped>
.curator-embed-preview--showcase {
  background: #0b0f19;
  border-color: rgba(148, 163, 184, 0.25);
}

.curator-embed-preview .crt-inner.crt-layout--showcase_carousel {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.curator-embed-preview .crt-inner.crt-layout--showcase_carousel::-webkit-scrollbar {
  width: 0;
  height: 0;
  display: none;
}
.curator-embed-preview .crt-showcase-nav {
  padding: 0;
  font-size: 0;
  line-height: 0;
}
.curator-embed-preview .crt-showcase-nav svg {
  display: block;
}
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-showcase-source {
  justify-content: center;
}
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-title.crt-title--showcase,
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-text.crt-text--showcase {
  text-align: center;
}
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-hashtags {
  justify-content: center;
}
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-showcase-footer {
  justify-content: center;
}
.curator-embed-preview .crt-body.crt-body--showcase.crt-showcase--align-center .crt-showcase-meta-stack {
  align-items: center;
  text-align: center;
}

.curator-embed-preview .crt-showcase-share {
  position: relative;
}

.curator-embed-preview .crt-showcase-share-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: var(--crt-showcase-share-color, var(--crt-icon, currentColor));
  cursor: pointer;
  padding: 0;
}

.curator-embed-preview .crt-showcase-share-tooltip {
  position: absolute;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 30;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 23, 42, 0.95);
  box-shadow: 0 10px 24px rgba(2, 6, 23, 0.35);
}

.curator-embed-preview .crt-showcase-share-platform {
  width: 28px;
  height: 28px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--crt-showcase-share-color, rgba(226, 232, 240, 0.95));
  background: rgba(30, 41, 59, 0.9);
  text-decoration: none;
  border: 0;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    color 0.15s ease;
}

.curator-embed-preview .crt-showcase-share-platform:hover {
  background: rgba(51, 65, 85, 0.95);
  color: #fff;
}

.curator-embed-preview .crt-showcase-share-platform :deep(svg) {
  width: 15px;
  height: 15px;
}

.curator-embed-preview .crt-share {
  position: relative;
}

.curator-embed-preview .crt-share-link {
  color: var(--crt-showcase-share-color, var(--crt-icon, currentColor));
}

.curator-embed-preview .crt-share-link--trigger {
  border: 1px solid var(--crt-border, #e2e8f0);
  background: rgba(255, 255, 255, 0.6);
  border-radius: 8px;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  cursor: pointer;
}

.curator-embed-preview .crt-share-tooltip {
  position: absolute;
  left: 34px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 30;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(15, 23, 42, 0.95);
  box-shadow: 0 10px 24px rgba(2, 6, 23, 0.35);
}

.publish-widget {
  position: relative;
  overflow: hidden;
  background: linear-gradient(150deg, #ffffff 0%, #f8fbff 100%);
  border-color: rgba(199, 210, 254, 0.7);
}
.publish-widget:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 30px -24px rgba(30, 41, 59, 0.88);
}
.publish-widget::before {
  content: '';
  position: absolute;
  width: 140px;
  height: 140px;
  right: -64px;
  top: -72px;
  border-radius: 9999px;
  background: radial-gradient(circle, rgba(129, 140, 248, 0.16), rgba(129, 140, 248, 0));
  pointer-events: none;
}
.publish-widget::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, rgba(99, 102, 241, 0.7), rgba(34, 211, 238, 0.45));
}
.metric-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  border: 1px solid rgba(165, 180, 252, 0.7);
  background: rgba(238, 242, 255, 0.9);
  color: rgb(79, 70, 229);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.publish-hero {
  background:
    radial-gradient(860px 240px at -8% -45%, rgba(56, 189, 248, 0.12), transparent 65%),
    radial-gradient(720px 220px at 110% -40%, rgba(99, 102, 241, 0.14), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
}

.type-dot {
  width: 1rem;
  height: 1rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.65rem;
  font-weight: 700;
  background: rgba(226, 232, 240, 0.9);
  color: rgb(51 65 85);
}

.type-dot :deep(svg) {
  width: 0.72rem;
  height: 0.72rem;
  display: block;
}

.type-dot--youtube { background: rgba(254, 226, 226, 0.95); color: rgb(220 38 38); }
.type-dot--facebook { background: rgba(219, 234, 254, 0.98); color: rgb(37 99 235); }
.type-dot--instagram { background: rgba(252, 231, 243, 0.96); color: rgb(190 24 93); }
.type-dot--tiktok { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--threads { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }
.type-dot--rss { background: rgba(255, 237, 213, 0.98); color: rgb(234 88 12); }
.type-dot--twitter { background: rgba(226, 232, 240, 0.98); color: rgb(15 23 42); }

@media (prefers-reduced-motion: reduce) {
  .publish-widget:hover {
    transform: none;
    box-shadow: none;
  }
}

.wizard-stepper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  flex: 1 1 180px;
  padding: 0.7rem 0.8rem;
  border-radius: 0.85rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  background: rgba(248, 250, 252, 0.82);
}

.wizard-step--active {
  border-color: rgba(99, 102, 241, 0.45);
  background: rgba(238, 242, 255, 0.72);
}

.wizard-step--done,
.wizard-step--ready {
  border-color: rgba(191, 219, 254, 0.8);
}

.wizard-step__index {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: rgb(51 65 85);
  background: rgba(226, 232, 240, 0.95);
  flex: 0 0 auto;
}

.wizard-step--active .wizard-step__index {
  color: rgb(67 56 202);
  background: rgba(199, 210, 254, 0.95);
}

.wizard-step__label {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgb(30 41 59);
}

.wizard-step__meta {
  font-size: 0.7rem;
  color: rgb(100 116 139);
}
</style>
