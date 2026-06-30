<template>
  <div>
    <!-- Loading / Error states (full width, outside grid) -->
    <AppLoader v-if="loading" label="Loading campaign…" />

    <template v-else-if="loadError">
      <AppPageHeader title="Campaign" subtitle="Could not load this campaign." icon="megaphone" />
      <AppAlert variant="danger">{{ loadError }}</AppAlert>
      <AppButton @click="$router.push('/campaigns')">Back to campaigns</AppButton>
    </template>

    <!-- Main two-column grid -->
    <div v-else-if="campaign" class="cd-layout">

      <!-- LEFT: main content -->
      <div class="cd-main">
        <AppPageHeader :title="campaign.name" :subtitle="pageSubtitle" icon="megaphone">
          <template #actions>
            <div class="flex flex-wrap gap-2">
              <AppButton
                v-if="activeTab === 'brief'"
                variant="secondary"
                :disabled="!canSaveCampaign"
                @click="saveCampaign"
              >
                {{ savingCampaign ? 'Saving…' : 'Save brief' }}
              </AppButton>
              <AppButton variant="primary" :disabled="generating || savingCampaign" @click="generate">
                <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
                {{ generating ? (generationProgress || 'Generating…') : 'Generate content' }}
              </AppButton>
            </div>
          </template>
        </AppPageHeader>

        <CapabilityBanner context="ai" />

        <AppAlert v-if="loadError" variant="danger">{{ loadError }}</AppAlert>

        <nav class="campaign-mode-tabs" aria-label="Campaign sections">
          <button
            type="button"
            class="campaign-mode-tab"
            :class="activeTab === 'brief' ? 'campaign-mode-tab--active' : ''"
            @click="activeTab = 'brief'"
          >
            <AppIcon name="edit" class="w-3.5 h-3.5" />
            Brief
          </button>
          <button
            type="button"
            class="campaign-mode-tab"
            :class="activeTab === 'drafts' ? 'campaign-mode-tab--active' : ''"
            @click="activeTab = 'drafts'"
          >
            <AppIcon name="feeds" class="w-3.5 h-3.5" />
            Drafts
            <span v-if="packages.length" class="campaign-mode-tab__badge">{{ packages.length }}</span>
          </button>
        </nav>

        <!-- Brief -->
        <div v-show="activeTab === 'brief'" class="space-y-4">
          <AppCard padding="none" class="campaign-brief-card p-4">
            <form class="space-y-3" @submit.prevent="saveCampaign">
              <div class="campaign-form-panel">
                <div class="cf-panel-header">
                  <div class="cf-panel-icon" style="background:#eff6ff;color:#3b82f6">
                    <AppIcon name="edit" class="w-3.5 h-3.5" />
                  </div>
                  <p class="text-sm font-semibold text-slate-800">Basics</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
                  <AppFormField label="Campaign name" required>
                    <AppInput v-model="campaignForm.name" type="text" placeholder="Summer product launch" />
                  </AppFormField>
                  <AppFormField label="Tone">
                    <AppInput v-model="campaignForm.tone" type="text" placeholder="Professional" />
                  </AppFormField>
                </div>
                <AppFormField label="Platforms" hint="Comma-separated: instagram, linkedin, tiktok" class="mt-3">
                  <AppInput v-model="campaignForm.platformsText" type="text" placeholder="instagram, twitter, facebook" />
                </AppFormField>
                <PlatformPublishGuide
                  v-if="splitList(campaignForm.platformsText).length"
                  class="mt-3"
                  :platforms="splitList(campaignForm.platformsText)"
                  variant="compact"
                  title="Native publish by platform"
                  subtitle="Aa = text · IMG = image · VID = video · CAR = carousel · LINK = article"
                />
              </div>

              <div class="campaign-form-panel">
                <div class="cf-panel-header">
                  <div class="cf-panel-icon" style="background:#fdf4ff;color:#9333ea">
                    <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                  </div>
                  <p class="text-sm font-semibold text-slate-800">Brand &amp; template</p>
                </div>
                <p class="text-2xs text-slate-500 mt-2 mb-3">
                  Optionally link a brand kit so AI uses your colors and identity, and a template to seed the caption structure.
                </p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <AppFormField label="Brand kit" hint="Colors, fonts, and logo passed to AI">
                    <AppSelect v-model="campaignForm.brand_kit_id" :show-placeholder="false" select-class="w-full">
                      <option value="">No brand kit</option>
                      <option v-for="kit in brandKits" :key="kit.id" :value="String(kit.id)">
                        {{ kit.name }}{{ kit.is_default ? ' (default)' : '' }}
                      </option>
                    </AppSelect>
                  </AppFormField>
                  <AppFormField label="Caption template" hint="Seeds the caption structure for generation">
                    <AppSelect v-model="campaignForm.template_id" :show-placeholder="false" select-class="w-full">
                      <option value="">No template</option>
                      <option v-for="tpl in contentTemplates" :key="tpl.id" :value="String(tpl.id)">
                        {{ tpl.name }}{{ tpl.platform ? ` · ${tpl.platform}` : '' }}
                      </option>
                    </AppSelect>
                  </AppFormField>
                </div>
              </div>

              <div class="campaign-form-panel">
                <div class="cf-panel-header">
                  <div class="cf-panel-icon" style="background:#fffbeb;color:#d97706">
                    <AppIcon name="send" class="w-3.5 h-3.5" />
                  </div>
                  <p class="text-sm font-semibold text-slate-800">Message</p>
                </div>
                <div class="grid grid-cols-1 gap-3 mt-3">
                  <AppFormField label="Product / service">
                    <AppInput
                      v-model="campaignForm.product_info"
                      type="textarea"
                      :rows="4"
                      placeholder="What are you promoting?"
                    />
                  </AppFormField>
                  <AppFormField label="Description (optional)">
                    <AppInput
                      v-model="campaignForm.description"
                      type="textarea"
                      :rows="3"
                      placeholder="Launch notes or extra context"
                    />
                  </AppFormField>
                </div>
              </div>

              <div class="campaign-form-panel">
                <div class="cf-panel-header">
                  <div class="cf-panel-icon" style="background:#f0fdf4;color:#16a34a">
                    <AppIcon name="users" class="w-3.5 h-3.5" />
                  </div>
                  <p class="text-sm font-semibold text-slate-800">Audience &amp; goals</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
                  <AppFormField label="Target audience" hint="One per line or comma-separated">
                    <AppInput v-model="campaignForm.targetAudienceText" type="textarea" :rows="4" />
                  </AppFormField>
                  <AppFormField label="Goals" hint="One per line or comma-separated">
                    <AppInput v-model="campaignForm.goalsText" type="textarea" :rows="4" />
                  </AppFormField>
                </div>
              </div>

              <div class="campaign-form-panel">
                <div class="cf-panel-header">
                  <div class="cf-panel-icon" style="background:#eef2ff;color:#4f46e5">
                    <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                  </div>
                  <p class="text-sm font-semibold text-slate-800">Auto-pilot</p>
                </div>
                <p class="text-2xs text-slate-500 mt-2 mb-3">
                  When enabled, Curator picks the highest-scored approved draft per platform and schedules it for the next weekday at 9:00 AM.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                  <div class="campaign-media-toggle">
                    <button
                      type="button"
                      class="campaign-media-toggle__btn"
                      :class="!autoPilotEnabled ? 'campaign-media-toggle__btn--active' : ''"
                      :disabled="autoPilotLoading"
                      @click="setAutoPilot(false)"
                    >
                      Off
                    </button>
                    <button
                      type="button"
                      class="campaign-media-toggle__btn"
                      :class="autoPilotEnabled ? 'campaign-media-toggle__btn--active' : ''"
                      :disabled="autoPilotLoading"
                      @click="setAutoPilot(true)"
                    >
                      On
                    </button>
                  </div>
                  <AppButton
                    v-if="autoPilotEnabled"
                    type="button"
                    variant="secondary"
                    size="sm"
                    :loading="autoPilotRunning"
                    :disabled="autoPilotLoading || autoPilotRunning"
                    @click="runAutoPilot"
                  >
                    Run now
                  </AppButton>
                </div>
              </div>

              <div v-if="campaignError" class="text-2xs text-red-600">{{ campaignError }}</div>
            </form>
          </AppCard>
        </div>

        <!-- Drafts -->
        <div v-show="activeTab === 'drafts'" class="space-y-3">

          <AppEmptyState
            v-if="!packages.length"
            title="No drafts yet"
            description="Generate platform captions from your campaign brief."
            icon="megaphone"
          >
            <AppButton variant="primary" :disabled="generating" @click="generate">
              <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
              Generate content
            </AppButton>
          </AppEmptyState>

          <template v-else>

            <div v-if="platformAcceptsEntries.length" class="cd-accepts">
              <p class="cd-accepts__title">
                What each draft platform accepts
                <span class="cd-accepts__hint">Aa = text · IMG = image · VID = video · CAR = carousel · LINK = article</span>
              </p>
              <div class="cd-accepts-grid">
                <div v-for="{ platform, spec } in platformAcceptsEntries" :key="platform" class="cd-accepts-card">
                  <SocialPlatformLabel :type="platform" variant="pill" size="sm" :show-label="true" />
                  <div class="cd-accepts-types">
                    <span
                      v-for="type in spec.content_types"
                      :key="type.id"
                      class="cd-accepts-chip"
                      :class="type.supported ? 'cd-accepts-chip--yes' : 'cd-accepts-chip--no'"
                      :title="type.label"
                    >
                      {{ typeShortLabel(type) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="cd-draft-tabs">
              <button
                v-for="opt in draftPlatformOptions"
                :key="opt.id"
                type="button"
                class="cd-draft-tab"
                :class="platformFilter === opt.id ? 'cd-draft-tab--active' : ''"
                @click="platformFilter = opt.id"
              >
                <SocialPlatformLabel
                  v-if="opt.id !== 'all'"
                  :type="opt.id"
                  :show-label="true"
                  size="sm"
                />
                <span v-else>All Drafts</span>
                <span class="cd-draft-tab__count">{{ opt.count }}</span>
              </button>
            </div>

            <div class="space-y-2">
              <AppCard
                v-for="pkg in filteredPackages"
                :key="pkg.id"
                padding="none"
                class="campaign-draft-card"
                :class="[
                  expandedPackageId === pkg.id ? 'campaign-draft-card--expanded' : '',
                  draftHasPublishIssue(pkg) ? 'campaign-draft-card--issue' : '',
                  draftIsPublishReady(pkg) ? 'campaign-draft-card--ready' : '',
                ]"
              >
                <div class="campaign-draft-row">
                  <!-- Main clickable area -->
                  <button type="button" class="campaign-draft-row__main" @click="toggleExpand(pkg.id)">
                    <div class="campaign-draft-row__meta">
                      <SocialPlatformLabel :type="pkg.platform" size="md" />
                      <span class="campaign-draft-version">v{{ pkg.version }}</span>
                      <AppBadge :variant="draftStatusVariant(pkg.status)">{{ formatStatusLabel(pkg.status) }}</AppBadge>
                      <AppBadge
                        v-if="draftHasPublishIssue(pkg)"
                        variant="danger"
                        :title="draftPublishIssues(pkg).join(' ')"
                      >
                        <AppIcon name="alert" class="w-2.5 h-2.5 mr-0.5" />
                        Publish issue
                      </AppBadge>
                      <AppBadge v-else-if="draftIsPublishReady(pkg)" variant="success">
                        Ready to publish
                      </AppBadge>
                      <AppBadge
                        v-if="pkg.ai_score != null"
                        :variant="aiScoreBadgeVariant(pkg.ai_score)"
                        :title="'AI quality score'"
                      >
                        {{ formatAiScore(pkg.ai_score) }}
                      </AppBadge>
                      <AppBadge v-if="pkg.is_winner" variant="success">
                        <AppIcon name="star" class="w-2.5 h-2.5 mr-0.5" />Winner
                      </AppBadge>
                      <AppBadge v-else-if="pkg.variant_group_id" variant="info">A/B</AppBadge>
                    </div>
                    <p class="campaign-draft-preview">{{ pkg.caption }}</p>
                    <p v-if="draftIssueSummary(pkg)" class="campaign-draft-issue-line">
                      <AppIcon name="alert" class="w-3 h-3 inline-block flex-shrink-0" />
                      {{ draftIssueSummary(pkg) }}
                    </p>
                    <p v-if="pkg.hashtags?.length" class="campaign-draft-hashtags">{{ pkg.hashtags.join(' ') }}</p>
                  </button>

                  <!-- Right side actions -->
                  <div class="campaign-draft-row__side" @click.stop>
                    <AppSelect
                      :model-value="pkg.status"
                      select-class="!py-1.5 !text-xs min-w-[7.5rem]"
                      :show-placeholder="false"
                      @update:model-value="(value) => onPackageStatusChange(pkg, value)"
                    >
                      <option value="draft">Draft</option>
                      <option value="in_review">In review</option>
                      <option value="approved">Approved</option>
                      <option value="rejected">Rejected</option>
                    </AppSelect>
                    <div class="campaign-draft-actions">
                      <AppButton size="sm" variant="ghost" :title="expandedPackageId === pkg.id ? 'Collapse' : 'Expand'" @click="toggleExpand(pkg.id)">
                        <AppIcon name="eye" class="w-3.5 h-3.5" />
                      </AppButton>
                      <AppButton size="sm" variant="secondary" @click="openRefine(pkg)">
                        <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1" />
                        Refine
                      </AppButton>
                      <AppDropdown>
                        <template #trigger>
                          <AppButton size="sm" variant="ghost">
                            <AppIcon name="more" class="w-3.5 h-3.5" />
                          </AppButton>
                        </template>
                        <template #default="{ close }">
                          <button
                            v-if="pkg.status === 'approved'"
                            class="cd-dropdown-item"
                            :disabled="draftHasPublishIssue(pkg)"
                            :title="draftHasPublishIssue(pkg) ? draftIssueSummary(pkg) : 'Schedule native publish'"
                            @click="schedulePackage(pkg); close()"
                          >
                            <AppIcon name="send" class="w-3.5 h-3.5" />
                            Schedule
                          </button>
                          <button
                            v-if="pkg.variant_group_id"
                            class="cd-dropdown-item"
                            @click="openVariantsModal(pkg); close()"
                          >
                            <AppIcon name="layers" class="w-3.5 h-3.5" />
                            View variants
                          </button>
                          <button
                            v-else
                            class="cd-dropdown-item"
                            @click="generateVariantsForPackage(pkg); close()"
                          >
                            <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                            A/B test
                          </button>
                        </template>
                      </AppDropdown>
                    </div>
                  </div>
                </div>

                <div v-if="expandedPackageId === pkg.id" class="campaign-draft-expanded">
                  <ScheduleValidationPanel
                    v-if="isNativePublishPlatform(pkg.platform)"
                    :platform="pkg.platform"
                    :content-package="pkg"
                  />
                  <PlatformPublishGuide
                    v-else
                    :platforms="[pkg.platform]"
                    variant="cards"
                    title="Publish requirements"
                    subtitle="This platform uses embed/sync rather than native scheduling."
                  />
                  <p class="text-sm text-slate-800 whitespace-pre-wrap leading-6 mt-3">{{ pkg.caption }}</p>

                  <!-- Insert content block -->
                  <div v-if="contentBlocks.length" class="flex flex-wrap items-end gap-2 mt-2">
                    <AppFormField label="Insert block" class="flex-1 min-w-[10rem]">
                      <AppSelect
                        :model-value="blockPickValue(pkg.id)"
                        :show-placeholder="true"
                        placeholder="Choose block…"
                        select-class="!text-sm"
                        @update:model-value="(v) => setBlockPick(pkg.id, v)"
                      >
                        <option v-for="b in contentBlocks" :key="b.id" :value="String(b.id)">
                          {{ b.name }} ({{ b.type }})
                        </option>
                      </AppSelect>
                    </AppFormField>
                    <AppButton
                      size="sm"
                      variant="secondary"
                      :disabled="!blockPickValue(pkg.id)"
                      @click="appendBlock(pkg)"
                    >
                      Append
                    </AppButton>
                  </div>

                  <div v-if="(pkg.media_urls || []).length" class="campaign-media-list">
                    <span class="text-xs font-medium text-slate-700">Media ({{ pkg.media_urls.length }}/4)</span>
                    <div v-for="(url, i) in pkg.media_urls" :key="i" class="truncate font-mono text-2xs text-slate-600">{{ url }}</div>
                  </div>

                  <div class="campaign-media-block">
                    <div class="campaign-media-toggle">
                      <button
                        type="button"
                        class="campaign-media-toggle__btn"
                        :class="mediaModeFor(pkg.id) === 'library' ? 'campaign-media-toggle__btn--active' : ''"
                        @click="setMediaMode(pkg.id, 'library')"
                      >
                        Library
                      </button>
                      <button
                        type="button"
                        class="campaign-media-toggle__btn"
                        :class="mediaModeFor(pkg.id) === 'url' ? 'campaign-media-toggle__btn--active' : ''"
                        @click="setMediaMode(pkg.id, 'url')"
                      >
                        URL
                      </button>
                    </div>

                    <div v-if="mediaModeFor(pkg.id) === 'library'" class="flex flex-wrap gap-2 items-end">
                      <AppFormField label="Asset" class="flex-1 min-w-[12rem]">
                        <AppSelect
                          :model-value="assetPickValue(pkg.id)"
                          select-class="!py-2 !text-sm"
                          :show-placeholder="true"
                          placeholder="Choose asset"
                          @update:model-value="(value) => setAssetPick(pkg.id, value)"
                        >
                          <option
                            v-for="a in assetsForPlatform(pkg.platform)"
                            :key="a.id"
                            :value="String(a.id)"
                          >
                            {{ a.file_name }}{{ formatAssetTags(a) }}
                          </option>
                        </AppSelect>
                      </AppFormField>
                      <AppButton
                        size="sm"
                        variant="secondary"
                        :disabled="!assetPickValue(pkg.id)"
                        @click="attachAsset(pkg)"
                      >
                        Attach
                      </AppButton>
                      <p
                        v-if="!assetsForPlatform(pkg.platform).length"
                        class="w-full text-2xs text-amber-700"
                      >
                        No matching assets for this platform.
                        <router-link to="/content-library" class="underline font-medium">Upload in Content library</router-link>
                        or use the URL tab.
                      </p>
                    </div>

                    <div v-else class="flex flex-wrap gap-2 items-end">
                      <AppFormField label="Public URL" class="flex-1 min-w-[12rem]">
                        <AppInput
                          :model-value="manualUrlValue(pkg.id)"
                          placeholder="https://…"
                          input-class="!text-sm"
                          @update:model-value="(value) => setManualUrl(pkg.id, value)"
                        />
                      </AppFormField>
                      <AppButton size="sm" :disabled="!manualUrlValue(pkg.id).trim()" @click="addManualUrl(pkg)">Add</AppButton>
                    </div>

                    <div class="mt-3 space-y-2 border-t border-slate-200/80 pt-3">
                      <p class="text-xs font-medium text-slate-700">AI image</p>
                      <p class="text-2xs text-slate-500">
                        Generate a branded image from the caption and campaign brief. Saved to your library and attached here.
                      </p>
                      <AppFormField label="Extra direction (optional)" class="w-full">
                        <AppInput
                          :model-value="imageInstructionValue(pkg.id)"
                          placeholder="e.g. minimal flat lay, soft natural light"
                          input-class="!text-sm"
                          @update:model-value="(value) => setImageInstruction(pkg.id, value)"
                        />
                      </AppFormField>
                      <AppButton
                        size="sm"
                        variant="secondary"
                        :loading="generatingImageId === pkg.id"
                        :disabled="generatingImageId === pkg.id || (pkg.media_urls || []).length >= 4"
                        @click="generateImage(pkg)"
                      >
                        Generate image
                      </AppButton>
                    </div>
                  </div>
                </div>
              </AppCard>
            </div>
          </template>
        </div>
      </div>

      <!-- RIGHT: sidebar -->
      <aside class="cd-sidebar">
        <!-- Campaign overview -->
        <div class="cd-sidebar-section">
          <p class="cd-sidebar-heading">Campaign overview</p>
          <p class="cd-campaign-name">{{ campaign.name }}</p>
          <div class="cd-platform-pills">
            <SocialPlatformLabel
              v-for="p in splitList(campaignForm.platformsText)"
              :key="p"
              :type="p"
              variant="pill"
              size="sm"
            />
          </div>
          <div class="cd-stats-grid">
            <div class="cd-stat-tile">
              <span class="cd-stat-tile__value cd-stat-tile__value--blue">{{ packages.length }}</span>
              <span class="cd-stat-tile__label">Drafts</span>
            </div>
            <div class="cd-stat-tile">
              <span class="cd-stat-tile__value cd-stat-tile__value--emerald">{{ approvedCount }}</span>
              <span class="cd-stat-tile__label">Approved</span>
            </div>
            <div class="cd-stat-tile">
              <span class="cd-stat-tile__value cd-stat-tile__value--red">{{ draftIssueCount }}</span>
              <span class="cd-stat-tile__label">Issues</span>
            </div>
            <div class="cd-stat-tile">
              <span class="cd-stat-tile__value cd-stat-tile__value--violet">{{ readinessPercent }}%</span>
              <span class="cd-stat-tile__label">Avg. readiness</span>
            </div>
          </div>
        </div>

        <!-- Platform filter (only in drafts tab with packages) -->
        <div v-if="activeTab === 'drafts' && packages.length" class="cd-sidebar-section">
          <p class="cd-sidebar-heading">Platform filter</p>
          <div class="cd-filter-list">
            <button
              v-for="opt in draftPlatformOptions"
              :key="opt.id"
              type="button"
              class="cd-filter-item"
              :class="platformFilter === opt.id ? 'cd-filter-item--active' : ''"
              @click="platformFilter = opt.id"
            >
              <span class="cd-filter-all-icon" v-if="opt.id === 'all'">
                <AppIcon name="feeds" class="w-3.5 h-3.5" />
              </span>
              <SocialPlatformLabel
                v-else
                :type="opt.id"
                :show-label="true"
                size="sm"
              />
              <span v-if="opt.id === 'all'" style="font-size:0.8125rem;font-weight:500;">All platforms</span>
            </button>
          </div>
        </div>

        <!-- Status filter (only in drafts tab with packages) -->
        <div v-if="activeTab === 'drafts' && packages.length" class="cd-sidebar-section">
          <p class="cd-sidebar-heading">Status filter</p>
          <div class="cd-filter-list">
            <button
              type="button"
              class="cd-filter-item"
              :class="statusFilter === 'all' ? 'cd-filter-item--active' : ''"
              @click="statusFilter = 'all'"
            >
              <span class="cd-filter-all-icon"><AppIcon name="feeds" class="w-3.5 h-3.5" /></span>
              <span style="font-size:0.8125rem;font-weight:500;">All statuses</span>
            </button>
            <button
              type="button"
              class="cd-filter-item"
              :class="statusFilter === 'ready' ? 'cd-filter-item--active' : ''"
              @click="statusFilter = 'ready'"
            >
              <span class="cd-filter-dot cd-filter-dot--emerald" />
              <span style="font-size:0.8125rem;font-weight:500;">Ready to publish</span>
            </button>
            <button
              type="button"
              class="cd-filter-item"
              :class="statusFilter === 'approved' ? 'cd-filter-item--active' : ''"
              @click="statusFilter = 'approved'"
            >
              <span class="cd-filter-dot cd-filter-dot--emerald" />
              <span style="font-size:0.8125rem;font-weight:500;">Approved</span>
            </button>
            <button
              type="button"
              class="cd-filter-item"
              :class="statusFilter === 'draft' ? 'cd-filter-item--active' : ''"
              @click="statusFilter = 'draft'"
            >
              <span class="cd-filter-dot cd-filter-dot--slate" />
              <span style="font-size:0.8125rem;font-weight:500;">Draft</span>
            </button>
            <button
              type="button"
              class="cd-filter-item"
              :class="statusFilter === 'issue' ? 'cd-filter-item--active' : ''"
              @click="statusFilter = 'issue'"
            >
              <span class="cd-filter-dot cd-filter-dot--red" />
              <span style="font-size:0.8125rem;font-weight:500;">Needs attention</span>
            </button>
          </div>
        </div>
      </aside>
    </div>

    <!-- Modals (outside grid) -->
    <AppModal
      :open="abVariantsModalOpen"
      title="A/B Caption Variants"
      size="xl"
      @close="closeVariantsModal"
    >
      <template v-if="variantGroup.length">
        <p class="text-xs text-slate-500 mb-4">
          Pick the best caption. The winner will be approved and the others rejected.
        </p>
        <div class="space-y-3">
          <div
            v-for="variant in variantGroup"
            :key="variant.id"
            :class="[
              'ab-variant-card',
              variant.is_winner ? 'ab-variant-card--winner' : '',
              variant.status === 'rejected' ? 'ab-variant-card--rejected' : '',
            ]"
          >
            <div class="flex items-center gap-2 mb-2">
              <span class="ab-variant-label">{{ variantLabel(variant) }}</span>
              <AppBadge v-if="variant.is_winner" variant="success">
                <AppIcon name="star" class="w-2.5 h-2.5 mr-0.5" />Winner
              </AppBadge>
              <AppBadge v-else-if="variant.status === 'rejected'" variant="danger">Rejected</AppBadge>
              <AppBadge v-else :variant="draftStatusVariant(variant.status)">{{ formatStatusLabel(variant.status) }}</AppBadge>
            </div>
            <p class="text-sm text-slate-700 whitespace-pre-wrap leading-6 mb-3">{{ variant.caption }}</p>
            <AppButton
              v-if="!variant.is_winner"
              size="sm"
              :disabled="!!pickingWinnerId"
              @click="pickVariantWinner(variant)"
            >
              <AppIcon name="star" class="w-3.5 h-3.5 mr-1" />
              {{ pickingWinnerId === variant.id ? 'Picking…' : 'Pick as winner' }}
            </AppButton>
          </div>
        </div>
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeVariantsModal">Close</AppButton>
      </template>
    </AppModal>

    <AppModal
      :open="refineModalOpen"
      title="Refine caption"
      size="xl"
      @close="closeRefineModal"
    >
      <template v-if="selected">
        <SocialPlatformLabel :type="selected.platform" size="md" class="mb-3" />
        <AppFormField label="Refinement prompt" hint="e.g. Shorter, add CTA, more urgent">
          <AppInput v-model="instruction" type="textarea" :rows="4" placeholder="How should this caption change?" />
        </AppFormField>

        <div v-if="refinedCaption" class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-4 text-sm">
          <div class="campaign-compare-card">
            <div class="text-xs font-semibold text-slate-500 mb-1">Before</div>
            <p class="text-slate-700 whitespace-pre-wrap leading-6">{{ selected.caption }}</p>
          </div>
          <div class="campaign-compare-card campaign-compare-card--success">
            <div class="text-xs font-semibold text-emerald-700 mb-1">After</div>
            <p class="text-slate-700 whitespace-pre-wrap leading-6">{{ refinedCaption }}</p>
          </div>
        </div>

        <div v-if="versions.length" class="mt-4 space-y-2">
          <div class="flex items-center gap-1.5 mb-2">
            <AppIcon name="activity" class="w-3.5 h-3.5 text-slate-400" />
            <p class="text-xs font-semibold text-slate-500">Version history</p>
          </div>
          <div v-for="v in versions" :key="v.id" class="campaign-version-row">
            <div class="flex items-center gap-1.5">
              <AppIcon name="layers" class="w-3.5 h-3.5 text-slate-400" />
              <span class="font-medium text-slate-700">v{{ v.version }}</span>
            </div>
            <AppBadge :variant="draftStatusVariant(v.status)">{{ formatStatusLabel(v.status) }}</AppBadge>
          </div>
        </div>
      </template>

      <template #footer>
        <AppButton variant="secondary" @click="closeRefineModal">Close</AppButton>
        <AppButton :disabled="refining || !instruction.trim()" @click="refine">
          <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
          {{ refining ? 'Refining…' : 'Refine with AI' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useCampaignsStore } from '../stores/campaigns';
import { useRealtimeStore } from '../stores/realtime';
import { useToastStore } from '../stores/toast';
import {
  AppAlert,
  AppBadge,
  AppButton,
  AppCard,
  AppDropdown,
  AppEmptyState,
  AppFormField,
  AppIcon,
  AppInput,
  AppLoader,
  AppModal,
  AppSelect,
} from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import PlatformPublishGuide from '../components/PlatformPublishGuide.vue';
import ScheduleValidationPanel from '../components/ScheduleValidationPanel.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import { usePlatformPublishSpecs } from '../composables/usePlatformPublishSpecs';
import { CONTENT_TYPE_ICONS } from '../constants/platformPublishSpecs';
import {
  draftHasPublishIssue,
  draftIssueSummary,
  isNativePublishPlatform,
  validateDraftForNativePublish,
} from '../utils/scheduleContentValidation';

const route = useRoute();
const router = useRouter();
const store = useCampaignsStore();
const toast = useToastStore();
const realtime = useRealtimeStore();
let unsubscribeAi = null;

const { getSpecsForPlatforms } = usePlatformPublishSpecs();
const platformAcceptsEntries = computed(() => getSpecsForPlatforms(splitList(campaignForm.platformsText)));
function typeShortLabel(type) { return CONTENT_TYPE_ICONS[type.id] || type.label.slice(0, 3).toUpperCase(); }

const campaign = ref(null);
const loading = ref(true);
const loadError = ref(null);
const campaignError = ref('');
const generating = ref(false);
const generationProgress = ref('');
const savingCampaign = ref(false);
const autoPilotEnabled = ref(false);
const autoPilotLoading = ref(false);
const autoPilotRunning = ref(false);

const activeTab = ref('brief');
const expandedPackageId = ref(null);
const platformFilter = ref('all');
const statusFilter = ref('all');
const refineModalOpen = ref(false);

// A/B variants state
const abVariantsModalOpen = ref(false);
const variantGroup = ref([]);
const generatingImageId = ref(null);
const imageInstruction = reactive({});
const pickingWinnerId = ref(null);

const selected = ref(null);
const selectedPackageId = ref(null);
const instruction = ref('');
const refining = ref(false);
const refinedCaption = ref('');
const versions = ref([]);
const assets = ref([]);
const brandKits = ref([]);
const contentTemplates = ref([]);
const contentBlocks = ref([]);
const assetPick = reactive({});
const blockPick = reactive({});
const manualUrl = reactive({});
const mediaMode = reactive({});

const campaignForm = reactive({
  name: '',
  description: '',
  product_info: '',
  tone: '',
  targetAudienceText: '',
  goalsText: '',
  platformsText: '',
  brand_kit_id: '',
  template_id: '',
});

const packages = computed(() => campaign.value?.content_packages || []);
const approvedCount = computed(() => packages.value.filter((pkg) => pkg.status === 'approved').length);

const draftIssueCount = computed(() => packages.value.filter((pkg) => draftHasPublishIssue(pkg)).length);

const readinessPercent = computed(() => {
  if (!packages.value.length) return 0;
  return Math.round((packages.value.filter(draftIsPublishReady).length / packages.value.length) * 100);
});

function draftIsPublishReady(pkg) {
  const { publishReady, embedOnly } = validateDraftForNativePublish(pkg);
  return !embedOnly && publishReady === true;
}

function draftPublishIssues(pkg) {
  return validateDraftForNativePublish(pkg).issues;
}


const pageSubtitle = computed(() => (
  activeTab.value === 'brief'
    ? 'Set up the campaign brief for AI generation.'
    : 'Review and publish platform drafts.'
));

const draftPlatformOptions = computed(() => {
  const platforms = [...new Set(packages.value.map((p) => p.platform))];
  return [
    { id: 'all', count: packages.value.length },
    ...platforms.map((p) => ({
      id: p,
      count: packages.value.filter((x) => x.platform === p).length,
    })),
  ];
});

const filteredPackages = computed(() => {
  let list = platformFilter.value === 'all'
    ? packages.value
    : packages.value.filter((p) => p.platform === platformFilter.value);

  if (statusFilter.value === 'ready') list = list.filter((p) => draftIsPublishReady(p));
  else if (statusFilter.value === 'approved') list = list.filter((p) => p.status === 'approved');
  else if (statusFilter.value === 'draft') list = list.filter((p) => p.status === 'draft');
  else if (statusFilter.value === 'issue') list = list.filter((p) => draftHasPublishIssue(p));

  return [...list].sort((a, b) => {
    const scoreA = a.ai_score ?? -1;
    const scoreB = b.ai_score ?? -1;
    if (scoreB !== scoreA) return scoreB - scoreA;
    return (b.id ?? 0) - (a.id ?? 0);
  });
});

const campaignPayload = computed(() => ({
  name: campaignForm.name.trim(),
  description: nullableString(campaignForm.description),
  product_info: nullableString(campaignForm.product_info),
  tone: nullableString(campaignForm.tone),
  target_audience: normalizedListValue(campaignForm.targetAudienceText),
  goals: normalizedListValue(campaignForm.goalsText),
  platforms: normalizedListValue(campaignForm.platformsText),
  brand_kit_id: campaignForm.brand_kit_id ? Number(campaignForm.brand_kit_id) : null,
  template_id: campaignForm.template_id ? Number(campaignForm.template_id) : null,
}));

const normalizedCampaignSnapshot = computed(() => {
  if (!campaign.value) return null;
  return JSON.stringify({
    name: campaign.value.name || '',
    description: campaign.value.description || null,
    product_info: campaign.value.product_info || null,
    tone: campaign.value.tone || null,
    target_audience: normalizeExistingList(campaign.value.target_audience),
    goals: normalizeExistingList(campaign.value.goals),
    platforms: normalizeExistingList(campaign.value.platforms),
    brand_kit_id: campaign.value.brand_kit_id || null,
    template_id: campaign.value.template_id || null,
  });
});

const normalizedFormSnapshot = computed(() => JSON.stringify(campaignPayload.value));
const canSaveCampaign = computed(
  () => Boolean(campaignForm.name.trim())
    && !savingCampaign.value
    && normalizedFormSnapshot.value !== normalizedCampaignSnapshot.value,
);

onMounted(async () => {
  await Promise.all([load(), loadAssets(), loadBrandKits(), loadContentTemplates(), loadContentBlocks()]);
  unsubscribeAi = realtime.on('aiGeneration', handleAiGeneration);
});

onUnmounted(() => {
  if (unsubscribeAi) unsubscribeAi();
});

function handleAiGeneration(event) {
  const campaignId = Number(route.params.id);

  if (event.job_type === 'campaign_generate' && Number(event.resource_id) === campaignId) {
    if (event.status === 'started') {
      generating.value = true;
      generationProgress.value = event.message || '';
      return;
    }
    if (event.status === 'progress') {
      generating.value = true;
      const { platform, step, total } = event.data || {};
      generationProgress.value = platform
        ? `Generating ${platform} (${step}/${total})…`
        : (event.message || 'Generating…');
      return;
    }
    generating.value = false;
    generationProgress.value = '';
    if (event.status === 'completed') {
      toast.success(event.message || 'Content generated');
      load({ showLoader: false }).then(() => {
        activeTab.value = 'drafts';
        platformFilter.value = 'all';
        if (packages.value.length === 1) {
          expandedPackageId.value = packages.value[0].id;
        }
      });
    } else if (event.status === 'failed') {
      toast.error(event.message || 'Content generation failed');
    }
    return;
  }

  if (event.job_type === 'refine' && selected.value && Number(event.resource_id) === Number(selected.value.id)) {
    if (event.status === 'started') {
      refining.value = true;
      return;
    }
    refining.value = false;
    if (event.status === 'completed') {
      refinedCaption.value = event.data?.package?.caption || '';
      toast.success(event.message || 'Caption refined');
      load({ showLoader: false, preserveSelectedId: selected.value.id }).then(() => {
        expandedPackageId.value = selected.value.id;
      });
    } else if (event.status === 'failed') {
      toast.error(event.message || 'Refine failed');
    }
    return;
  }

  if (event.job_type === 'variants') {
    const pkgId = Number(event.resource_id);
    if (event.status === 'started') {
      // job queued — nothing to show yet
      return;
    }
    if (event.status === 'completed') {
      variantGroup.value = event.data?.variants || [];
      abVariantsModalOpen.value = true;
      toast.success(event.message || 'A/B variants generated');
      load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
    } else if (event.status === 'failed') {
      toast.error(event.message || 'Failed to generate variants');
      // modal was never opened, nothing to close
    }
    return;
  }

  if (event.job_type === 'image' && Number(event.resource_id) === Number(generatingImageId.value)) {
    if (event.status === 'started') return;
    generatingImageId.value = null;
    if (event.status === 'completed') {
      toast.success(event.message || 'Image generated');
      Promise.all([
        load({ showLoader: false, preserveSelectedId: event.resource_id }),
        loadAssets(),
      ]).then(() => {
        expandedPackageId.value = event.resource_id;
      });
    } else if (event.status === 'failed') {
      toast.error(event.message || 'Image generation failed');
    }
  }
}

watch(
  () => packages.value.length,
  (len, prev) => {
    if (len > 0 && (prev === 0 || prev === undefined)) {
      activeTab.value = 'drafts';
    }
  },
);

function applyDefaultTab() {
  activeTab.value = packages.value.length > 0 ? 'drafts' : 'brief';
}

async function load(options = {}) {
  const { showLoader = true, preserveSelectedId = selectedPackageId.value } = options;
  if (showLoader) loading.value = true;
  loadError.value = null;
  try {
    campaign.value = await store.fetchOne(route.params.id);
    hydrateCampaignForm(campaign.value);
    if (showLoader) applyDefaultTab();
    restoreSelectedPackage(preserveSelectedId);
  } catch (e) {
    loadError.value = store.error || e.response?.data?.message || 'Failed to load campaign';
  } finally {
    if (showLoader) loading.value = false;
  }
}

function extractAssetList(responseData) {
  const payload = responseData?.data ?? responseData;
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

async function loadAssets() {
  try {
    const { data } = await axios.get('/api/content/assets', {
      params: { per_page: 100 },
      skipErrorToast: true,
    });
    assets.value = extractAssetList(data);
  } catch {
    assets.value = [];
  }
}

async function loadBrandKits() {
  try {
    const { data } = await axios.get('/api/content/brand-kits', { skipErrorToast: true });
    brandKits.value = data.data || data || [];
  } catch {
    brandKits.value = [];
  }
}

async function loadContentTemplates() {
  try {
    const { data } = await axios.get('/api/content/templates', { skipErrorToast: true });
    contentTemplates.value = data.data || data || [];
  } catch {
    contentTemplates.value = [];
  }
}

async function loadContentBlocks() {
  try {
    const { data } = await axios.get('/api/content/blocks', { skipErrorToast: true });
    contentBlocks.value = data.data || data || [];
  } catch {
    contentBlocks.value = [];
  }
}

function blockPickValue(packageId) {
  return blockPick[packageKey(packageId)] ?? '';
}

function setBlockPick(packageId, value) {
  blockPick[packageKey(packageId)] = value;
}

async function appendBlock(pkg) {
  const blockId = blockPickValue(pkg.id);
  if (!blockId) return;
  const block = contentBlocks.value.find((b) => String(b.id) === blockId);
  if (!block?.body) return;
  const newCaption = [pkg.caption, block.body].filter(Boolean).join('\n\n');
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/caption`, { caption: newCaption });
    toast.success(`Block "${block.name}" appended`);
    blockPick[packageKey(pkg.id)] = '';
    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
  } catch {
    // interceptor handles error toast
  }
}

function hydrateCampaignForm(value) {
  campaignForm.name = value?.name || '';
  campaignForm.description = value?.description || '';
  campaignForm.product_info = value?.product_info || '';
  campaignForm.tone = value?.tone || '';
  campaignForm.targetAudienceText = joinList(value?.target_audience);
  campaignForm.goalsText = joinList(value?.goals);
  campaignForm.platformsText = joinList(value?.platforms, ', ');
  campaignForm.brand_kit_id = value?.brand_kit_id ? String(value.brand_kit_id) : '';
  campaignForm.template_id = value?.template_id ? String(value.template_id) : '';
  autoPilotEnabled.value = Boolean(value?.auto_pilot_enabled);
}

async function setAutoPilot(enabled) {
  if (!campaign.value || autoPilotLoading.value || autoPilotEnabled.value === enabled) return;

  autoPilotLoading.value = true;
  try {
    const action = enabled ? 'enable' : 'disable';
    const { data } = await axios.post(`/api/campaigns/${campaign.value.id}/auto-pilot/${action}`);
    const updated = data.data || data;
    autoPilotEnabled.value = Boolean(updated.auto_pilot_enabled);
    campaign.value = { ...campaign.value, ...updated };
    toast.success(enabled ? 'Auto-pilot enabled' : 'Auto-pilot disabled');
  } catch {
    // interceptor handles error toast
  } finally {
    autoPilotLoading.value = false;
  }
}

async function runAutoPilot() {
  if (!campaign.value || autoPilotRunning.value) return;

  autoPilotRunning.value = true;
  try {
    const { data } = await axios.post(`/api/campaigns/${campaign.value.id}/auto-pilot/run`);
    const result = data.data || data;
    const scheduledCount = Array.isArray(result?.scheduled) ? result.scheduled.length : 0;
    const skippedCount = Array.isArray(result?.skipped) ? result.skipped.length : 0;

    if (scheduledCount > 0) {
      toast.success(`Scheduled ${scheduledCount} post${scheduledCount === 1 ? '' : 's'}${skippedCount > 0 ? ` (${skippedCount} skipped)` : ''}`);
    } else {
      toast.info(skippedCount > 0 ? `No posts scheduled (${skippedCount} platform${skippedCount === 1 ? '' : 's'} skipped)` : 'No approved drafts to schedule');
    }

    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
  } catch {
    // interceptor handles error toast
  } finally {
    autoPilotRunning.value = false;
  }
}

function joinList(value, separator = '\n') {
  return Array.isArray(value) ? value.join(separator) : '';
}

function splitList(value) {
  return String(value || '')
    .split(/\r?\n|,/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function nullableString(value) {
  const trimmed = String(value || '').trim();
  return trimmed ? trimmed : null;
}

function normalizedListValue(value) {
  const list = splitList(value);
  return list.length ? list : null;
}

function normalizeExistingList(value) {
  return Array.isArray(value) && value.length ? value : null;
}

function restoreSelectedPackage(packageId) {
  if (!packageId) {
    selected.value = null;
    return;
  }
  const nextPackage = packages.value.find((pkg) => pkg.id === packageId);
  if (!nextPackage) {
    selected.value = null;
    selectedPackageId.value = null;
    instruction.value = '';
    refinedCaption.value = '';
    versions.value = [];
    return;
  }
  selected.value = nextPackage;
}

function packageKey(packageId) {
  return String(packageId);
}

function assetPickValue(packageId) {
  return assetPick[packageKey(packageId)] ?? '';
}

function setAssetPick(packageId, value) {
  assetPick[packageKey(packageId)] = value;
}

function manualUrlValue(packageId) {
  return manualUrl[packageKey(packageId)] ?? '';
}

function setManualUrl(packageId, value) {
  manualUrl[packageKey(packageId)] = value;
}

function ensurePackageMediaState(packageId) {
  const key = packageKey(packageId);
  if (!(key in assetPick)) assetPick[key] = '';
  if (!(key in manualUrl)) manualUrl[key] = '';
  if (!(key in mediaMode)) mediaMode[key] = 'library';
  if (!(key in imageInstruction)) imageInstruction[key] = '';
}

function imageInstructionValue(packageId) {
  return imageInstruction[packageKey(packageId)] ?? '';
}

function setImageInstruction(packageId, value) {
  imageInstruction[packageKey(packageId)] = value;
}

async function generateImage(pkg) {
  ensurePackageMediaState(pkg.id);
  generatingImageId.value = pkg.id;
  try {
    const instruction = imageInstructionValue(pkg.id).trim();
    await axios.post(`/api/content-packages/${pkg.id}/generate-image`, {
      instruction: instruction || undefined,
    });
    toast.info('Image generation started…');
    setImageInstruction(pkg.id, '');
  } catch {
    generatingImageId.value = null;
  }
}

function toggleExpand(packageId) {
  if (expandedPackageId.value === packageId) {
    expandedPackageId.value = null;
    return;
  }
  expandedPackageId.value = packageId;
  ensurePackageMediaState(packageId);
}

function onPackageStatusChange(pkg, status) {
  pkg.status = status;
  updateStatus(pkg);
}

function mediaModeFor(packageId) {
  return mediaMode[packageKey(packageId)] || 'library';
}

function setMediaMode(packageId, mode) {
  mediaMode[packageKey(packageId)] = mode;
}

function openRefine(pkg) {
  selectedPackageId.value = pkg.id;
  selected.value = pkg;
  instruction.value = '';
  refinedCaption.value = '';
  loadVersions(pkg);
  refineModalOpen.value = true;
}

function closeRefineModal() {
  refineModalOpen.value = false;
}

function formatAssetTags(asset) {
  const tags = asset.ai_tags || [];
  if (!tags.length) return '';
  return ` · ${tags.slice(0, 3).join(', ')}`;
}

function formatStatusLabel(status) {
  const labels = {
    draft: 'Draft',
    in_review: 'In review',
    approved: 'Approved',
    rejected: 'Rejected',
    generated: 'Generated',
  };
  return labels[status] || 'Draft';
}

function draftStatusVariant(status) {
  if (status === 'approved') return 'success';
  if (status === 'rejected') return 'danger';
  if (status === 'in_review') return 'info';
  if (status === 'generated') return 'purple';
  return 'default';
}

function formatAiScore(score) {
  return `${Math.round(Number(score) * 100)}%`;
}

function aiScoreBadgeVariant(score) {
  const value = Number(score);
  if (value >= 0.8) return 'success';
  if (value >= 0.6) return 'info';
  return 'default';
}

function assetsForPlatform(platform) {
  const list = assets.value;
  if (!list.length) return [];

  const needsVideo = platform === 'tiktok';
  const needsImage = ['instagram', 'twitter', 'threads', 'facebook', 'linkedin'].includes(platform);
  const filtered = list.filter((asset) => {
    if (needsVideo) return asset.type === 'video';
    if (needsImage) return asset.type === 'image' || !asset.type;
    return true;
  });

  return filtered.length ? filtered : list;
}

async function saveCampaign() {
  if (!campaign.value || !canSaveCampaign.value) return;
  savingCampaign.value = true;
  campaignError.value = '';
  try {
    await axios.patch(`/api/campaigns/${campaign.value.id}`, campaignPayload.value);
    toast.success('Campaign updated');
    await load({ showLoader: false });
  } catch (e) {
    campaignError.value = e.response?.data?.message || 'Failed to update campaign';
  } finally {
    savingCampaign.value = false;
  }
}

async function generate() {
  generating.value = true;
  try {
    await store.generate(route.params.id);
  } catch {
    generating.value = false;
  }
}

async function loadVersions(pkg) {
  try {
    const { data } = await axios.get(`/api/content-packages/${pkg.id}/versions`);
    versions.value = data.data || data || [];
  } catch {
    versions.value = [];
  }
}

async function refine() {
  if (!selected.value || !instruction.value.trim()) return;
  refining.value = true;
  try {
    await axios.post(`/api/content-packages/${selected.value.id}/refine`, {
      instruction: instruction.value.trim(),
    });
  } catch {
    refining.value = false;
  }
}

async function updateStatus(pkg) {
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/status`, { status: pkg.status });
    toast.success('Status updated');
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

async function attachAsset(pkg) {
  const assetId = assetPickValue(pkg.id);
  if (!assetId) return;
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/media`, {
      asset_ids: [Number(assetId)],
    });
    toast.success('Asset attached');
    setAssetPick(pkg.id, '');
    await load({ showLoader: false, preserveSelectedId: pkg.id });
    expandedPackageId.value = pkg.id;
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

async function addManualUrl(pkg) {
  const url = manualUrlValue(pkg.id).trim();
  if (!url) return;
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/media`, {
      media_urls: [url],
    });
    toast.success('Media URL added');
    setManualUrl(pkg.id, '');
    await load({ showLoader: false, preserveSelectedId: pkg.id });
    expandedPackageId.value = pkg.id;
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

function schedulePackage(pkg) {
  if (draftHasPublishIssue(pkg)) {
    toast.error(draftIssueSummary(pkg) || 'Fix publish requirements before scheduling.');
    return;
  }
  router.push({ path: '/calendar', query: { content_package_id: pkg.id } });
}

// ── A/B Variants ──────────────────────────────────────────────────────────────

function variantLabel(pkg) {
  if (pkg.variant_index === 0) return 'Original';
  return `Variant ${'ABC'[pkg.variant_index - 1] ?? pkg.variant_index}`;
}

function closeVariantsModal() {
  abVariantsModalOpen.value = false;
}

async function openVariantsModal(pkg) {
  abVariantsModalOpen.value = true;
  variantGroup.value = [];
  try {
    const { data } = await axios.get(`/api/content-packages/${pkg.id}/variants`);
    variantGroup.value = data.data || data || [];
  } catch {
    toast.error('Could not load variants');
    abVariantsModalOpen.value = false;
  }
}

async function generateVariantsForPackage(pkg) {
  variantGroup.value = [];
  try {
    await axios.post(`/api/content-packages/${pkg.id}/variants`);
    toast.info('Generating variants — you\'ll be notified when ready.');
  } catch {
    // nothing to close — modal was never opened
  }
}

async function pickVariantWinner(pkg) {
  pickingWinnerId.value = pkg.id;
  try {
    await axios.post(`/api/content-packages/${pkg.id}/winner`);
    toast.success(`${variantLabel(pkg)} picked as winner`);
    // Refresh variant group in modal
    const { data } = await axios.get(`/api/content-packages/${pkg.id}/variants`);
    variantGroup.value = data.data || data || [];
    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
  } catch {
    toast.error('Failed to pick winner');
  } finally {
    pickingWinnerId.value = null;
  }
}
</script>

<style scoped>
/* ── Two-column layout ──────────────────────────────── */
.cd-layout {
  display: grid;
  grid-template-columns: 1fr 15rem;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 900px) {
  .cd-layout { grid-template-columns: 1fr; }
  .cd-sidebar { order: -1; }
}

.cd-main { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }

.cd-sidebar { display: flex; flex-direction: column; gap: 1rem; }

.cd-sidebar-section {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 1rem;
}

.cd-sidebar-heading {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
}

.cd-stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.cd-stat-tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 0.625rem 0.75rem;
  border-radius: 0.625rem;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
}

.cd-stat-tile__value { font-size: 1.375rem; font-weight: 700; line-height: 1; margin-bottom: 0.2rem; }
.cd-stat-tile__value--blue   { color: #3b82f6; }
.cd-stat-tile__value--emerald { color: #10b981; }
.cd-stat-tile__value--red    { color: #ef4444; }
.cd-stat-tile__value--violet { color: #8b5cf6; }

.cd-stat-tile__label { font-size: 0.7rem; font-weight: 500; color: #94a3b8; }

.cd-campaign-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 0.5rem;
}

.cd-platform-pills { display: flex; flex-wrap: wrap; gap: 0.375rem; margin-bottom: 0.75rem; }

.cd-quick-actions { display: flex; flex-direction: column; gap: 0.5rem; }

.cd-autopilot-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.5rem;
  border-top: 1px solid #f1f5f9;
  margin-top: 0.25rem;
}

/* ── Existing styles (preserved) ───────────────────── */
.campaign-mode-tabs {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.25rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #f8fafc;
}

.campaign-mode-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  border-radius: 0.55rem;
  font-size: 0.82rem;
  font-weight: 500;
  color: #64748b;
  transition: background 0.15s ease, color 0.15s ease;
}

.campaign-mode-tab:hover {
  color: #334155;
  background: rgba(255, 255, 255, 0.7);
}

.campaign-mode-tab--active {
  background: #fff;
  color: #1e3a8a;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.campaign-mode-tab__badge {
  font-size: 0.68rem;
  line-height: 1;
  padding: 0.15rem 0.4rem;
  border-radius: 999px;
  background: rgba(30, 58, 138, 0.1);
  color: #1d4ed8;
}

.campaign-brief-card {
  border: 1px solid #e6ebf2;
  background: #fff;
}

.campaign-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 0.85rem;
  background: rgba(248, 250, 252, 0.5);
}

.cf-panel-header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.cf-panel-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.45rem;
  flex-shrink: 0;
}


.campaign-platform-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.7rem;
  border: 1px solid #e6ebf2;
  border-radius: 999px;
  background: #fff;
  transition: all 0.15s ease;
}

.campaign-platform-chip:hover {
  border-color: rgba(30, 58, 138, 0.25);
  color: #334155;
}

.campaign-platform-chip--active {
  border-color: rgba(30, 58, 138, 0.35);
  background: rgba(239, 246, 255, 0.95);
  color: #1d4ed8;
}

.campaign-draft-card {
  border: 1px solid #e6ebf2;
  background: #fff;
  overflow: hidden;
  border-left: 3px solid transparent;
}

.campaign-draft-card--issue {
  border-left-color: #ef4444;
  background: linear-gradient(90deg, rgba(254, 242, 242, 0.45) 0%, #fff 12%);
}

.campaign-draft-card--ready {
  border-left-color: #10b981;
}

.campaign-draft-issue-stat {
  color: #dc2626;
  font-weight: 600;
}

.campaign-draft-issue-line {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  font-size: 0.72rem;
  line-height: 1.4;
  color: #b91c1c;
  font-weight: 500;
}

.campaign-draft-card--expanded {
  border-color: rgba(30, 58, 138, 0.22);
  box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.06);
}

.campaign-draft-row {
  display: grid;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}

@media (min-width: 768px) {
  .campaign-draft-row {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }
}

.campaign-draft-row__main {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
  text-align: left;
  width: 100%;
  cursor: pointer;
}

.campaign-draft-row__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.campaign-draft-version {
  font-size: 0.7rem;
  font-weight: 600;
  color: #94a3b8;
}

.campaign-draft-preview {
  font-size: 0.8125rem;
  line-height: 1.5;
  color: #475569;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.campaign-draft-hashtags {
  font-size: 0.72rem;
  color: #6366f1;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.campaign-draft-row__side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
  flex-shrink: 0;
}

.campaign-draft-actions {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.cd-dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #334155;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
  border-radius: 0.375rem;
  transition: background 0.1s;
}

.cd-dropdown-item:hover { background: #f8fafc; }
.cd-dropdown-item:disabled { opacity: 0.45; cursor: not-allowed; }

.campaign-draft-expanded {
  display: grid;
  gap: 0.75rem;
  padding: 0 0.85rem 0.85rem;
  border-top: 1px solid #eef2f6;
  background: rgba(248, 250, 252, 0.45);
}

.campaign-media-list {
  display: grid;
  gap: 0.35rem;
  padding: 0.65rem 0.75rem;
  border: 1px dashed #cbd5e1;
  border-radius: 0.75rem;
  background: #fff;
}

.campaign-media-block {
  display: grid;
  gap: 0.65rem;
}

.campaign-media-toggle {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.2rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.55rem;
  background: #f8fafc;
  width: fit-content;
}

.campaign-media-toggle__btn {
  padding: 0.3rem 0.65rem;
  border-radius: 0.4rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
}

.campaign-media-toggle__btn--active {
  background: #fff;
  color: #1d4ed8;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}

.campaign-compare-card {
  padding: 0.75rem 0.85rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.875rem;
  background: #f8fafc;
}

.campaign-compare-card--success {
  border-color: rgba(16, 185, 129, 0.22);
  background: rgba(236, 253, 245, 0.85);
}

.campaign-version-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0.7rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #f8fafc;
}

/* ── A/B Variant cards ─────────────────────────────── */
.ab-variant-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  padding: 1rem;
  background: #fff;
  transition: border-color 0.15s ease;
}

.ab-variant-card--winner {
  border-color: #86efac;
  background: #f0fdf4;
}

.ab-variant-card--rejected {
  opacity: 0.5;
}

.ab-variant-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #1e3a8a;
  background: rgba(239, 246, 255, 0.9);
  border: 1px solid rgba(30, 58, 138, 0.15);
  border-radius: 0.375rem;
  padding: 0.1rem 0.5rem;
}

/* ── Draft platform tabs ─────────────────────────────── */
.cd-draft-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  border-bottom: 1px solid #e6ebf2;
  margin-bottom: 0;
}

.cd-draft-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.875rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #64748b;
  border: none;
  border-bottom: 2px solid transparent;
  background: none;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  margin-bottom: -1px;
}

.cd-draft-tab:hover { color: #334155; }

.cd-draft-tab--active {
  color: #6366f1;
  border-bottom-color: #6366f1;
}

.cd-draft-tab__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: #f1f5f9;
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
}

.cd-draft-tab--active .cd-draft-tab__count {
  background: #ede9fe;
  color: #6366f1;
}

/* ── Sidebar filter list ─────────────────────────────── */
.cd-filter-list { display: flex; flex-direction: column; gap: 0.125rem; }

.cd-filter-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border-radius: 0.5rem;
  border: none;
  background: none;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s, color 0.12s;
}

.cd-filter-item:hover { background: #f8fafc; }

.cd-filter-item--active { background: #ede9fe; }
.cd-filter-item--active span { color: #6366f1; }

.cd-filter-all-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  color: #64748b;
  flex-shrink: 0;
}

.cd-filter-dot {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  flex-shrink: 0;
}

.cd-filter-dot--emerald { background: #10b981; }
.cd-filter-dot--slate   { background: #94a3b8; }
.cd-filter-dot--red     { background: #ef4444; }

/* ── Platform accepts ────────────────────────────────── */
.cd-accepts { display: flex; flex-direction: column; gap: 0.5rem; }

.cd-accepts__title {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
}

.cd-accepts__hint {
  font-size: 0.7rem;
  font-weight: 400;
  color: #94a3b8;
}

.cd-accepts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  gap: 0.625rem;
}

.cd-accepts-card {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  padding: 0.75rem 0.875rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
}

.cd-accepts-types {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.cd-accepts-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.cd-accepts-chip--yes { background: #d1fae5; color: #065f46; }
.cd-accepts-chip--no  { background: #f1f5f9; color: #94a3b8; text-decoration: line-through; }
</style>
