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
              <AiProviderModelPicker kind="text" v-model="generatePickerValue" />
              <AppButton variant="primary" :disabled="generating || savingCampaign || aiUnavailable" @click="generate">
                <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
                {{ generating ? (generationProgress || 'Generating…') : 'Generate content' }}
              </AppButton>
            </div>
          </template>
        </AppPageHeader>

        <CapabilityBanner context="ai" />

        <AppAlert v-if="loadError" variant="danger">{{ loadError }}</AppAlert>

        <div class="cd-top-row">
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

          <!-- Campaign overview: compact strip, top-right (hidden for now, not removed) -->
          <div v-if="false" class="cd-overview-strip">
            <div class="cd-overview-strip__id">
              <span class="cd-campaign-name">{{ campaign.name }}</span>
              <div class="cd-platform-pills cd-platform-pills--compact">
                <SocialPlatformLabel
                  v-for="p in splitList(campaignForm.platformsText)"
                  :key="p"
                  :type="p"
                  variant="pill"
                  size="sm"
                />
              </div>
            </div>
            <div class="cd-overview-strip__stats">
              <div class="cd-stat-chip">
                <span class="cd-stat-chip__value cd-stat-chip__value--blue">{{ packages.length }}</span>
                <span class="cd-stat-chip__label">Drafts</span>
              </div>
              <div class="cd-stat-chip">
                <span class="cd-stat-chip__value cd-stat-chip__value--emerald">{{ approvedCount }}</span>
                <span class="cd-stat-chip__label">Approved</span>
              </div>
              <div class="cd-stat-chip">
                <span class="cd-stat-chip__value cd-stat-chip__value--red">{{ draftIssueCount }}</span>
                <span class="cd-stat-chip__label">Issues</span>
              </div>
              <div class="cd-stat-chip">
                <span class="cd-stat-chip__value cd-stat-chip__value--violet">{{ readinessPercent }}%</span>
                <span class="cd-stat-chip__label">Ready</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Brief -->
        <div v-show="activeTab === 'brief'" class="space-y-4">
          <AppCard padding="none" class="campaign-brief-card">
            <div class="cf-config-layout p-4">
              <aside class="cf-overview-panel">
                <div class="cf-hero">
                  <div class="cf-hero-icon">
                    <AppIcon name="megaphone" class="w-4 h-4" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="cf-section-kicker">
                      Preview
                    </p>
                    <p class="text-sm-pro font-semibold text-slate-800 truncate">
                      <span v-if="campaignForm.name.trim()">{{ campaignForm.name }}</span>
                      <span
                        v-else
                        class="italic text-slate-400"
                      >Untitled campaign</span>
                    </p>
                    <p class="text-2xs text-slate-500 mt-1">
                      {{ campaignForm.tone.trim() ? campaignForm.tone : 'No tone set' }} &middot;
                      {{ splitList(campaignForm.platformsText).length }} platform{{ splitList(campaignForm.platformsText).length === 1 ? '' : 's' }}
                    </p>
                  </div>
                </div>

                <div class="cf-overview-grid">
                  <div class="cf-overview-stat">
                    <span class="cf-overview-stat__label">Status</span>
                    <div class="cf-status-pill" :class="autoPilotEnabled ? 'cf-status-pill--on' : ''">
                      <span class="cf-status-pill__dot" />
                      {{ autoPilotEnabled ? 'Auto-pilot on' : 'Auto-pilot off' }}
                    </div>
                  </div>
                  <div class="cf-overview-stat">
                    <span class="cf-overview-stat__label">Tone</span>
                    <span
                      v-if="campaignForm.tone.trim()"
                      class="cf-overview-stat__value"
                    >{{ campaignForm.tone }}</span>
                    <span
                      v-else
                      class="cf-overview-stat__value cf-overview-stat__value--muted"
                    >Not set</span>
                  </div>
                  <div class="cf-overview-stat cf-overview-stat--wide">
                    <span class="cf-overview-stat__label">Platforms</span>
                    <div
                      v-if="splitList(campaignForm.platformsText).length"
                      class="flex flex-wrap gap-1.5"
                    >
                      <SocialPlatformLabel
                        v-for="p in splitList(campaignForm.platformsText)"
                        :key="p"
                        :type="p"
                        variant="pill"
                        size="sm"
                      />
                    </div>
                    <span
                      v-else
                      class="text-2xs text-slate-400"
                    >No platforms yet</span>
                  </div>
                </div>
              </aside>

              <form
                class="space-y-4"
                @submit.prevent="saveCampaign"
              >
                <!-- Basics -->
                <div class="cf-form-panel">
                  <div class="cf-form-panel__header">
                    <div
                      class="cf-panel-icon"
                      style="background:#eff6ff;color:#3b82f6"
                    >
                      <AppIcon name="edit" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="cf-section-kicker">
                        Step 1
                      </p>
                      <p class="text-sm-pro font-semibold text-slate-800">
                        Basics
                      </p>
                      <p class="text-2xs text-slate-500 mt-1">
                        Name your campaign and choose the platforms it will publish to.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="cf-field-card">
                      <AppFormField label="Campaign name" required>
                        <AppInput v-model="campaignForm.name" type="text" placeholder="Summer product launch" />
                      </AppFormField>
                    </div>
                    <div class="cf-field-card">
                      <AppFormField label="Tone">
                        <AppInput v-model="campaignForm.tone" type="text" placeholder="Professional" />
                      </AppFormField>
                    </div>
                  </div>

                  <div class="cf-field-card">
                    <AppFormField label="Platforms" hint="Comma-separated: instagram, linkedin, tiktok">
                      <AppInput v-model="campaignForm.platformsText" type="text" placeholder="instagram, twitter, facebook" />
                    </AppFormField>
                  </div>

                  <PlatformPublishGuide
                    v-if="splitList(campaignForm.platformsText).length"
                    :platforms="splitList(campaignForm.platformsText)"
                    variant="compact"
                    title="Native publish by platform"
                    subtitle="Aa = text · IMG = image · VID = video · CAR = carousel · LINK = article"
                  />
                </div>

                <!-- Brand & template -->
                <div class="cf-form-panel">
                  <div class="cf-form-panel__header">
                    <div
                      class="cf-panel-icon"
                      style="background:#fdf4ff;color:#9333ea"
                    >
                      <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="cf-section-kicker">
                        Step 2
                      </p>
                      <p class="text-sm-pro font-semibold text-slate-800">
                        Brand &amp; template
                      </p>
                      <p class="text-2xs text-slate-500 mt-1">
                        Optionally link a brand kit so AI uses your colors and identity, and a template to seed the caption structure.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="cf-field-card">
                      <AppFormField label="Brand kit" hint="Colors, fonts, and logo passed to AI">
                        <AppSelect v-model="campaignForm.brand_kit_id" :show-placeholder="false" select-class="w-full">
                          <option value="">No brand kit</option>
                          <option v-for="kit in brandKits" :key="kit.id" :value="String(kit.id)">
                            {{ kit.name }}{{ kit.is_default ? ' (default)' : '' }}
                          </option>
                        </AppSelect>
                      </AppFormField>
                    </div>
                    <div class="cf-field-card">
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
                </div>

                <!-- Message -->
                <div class="cf-form-panel">
                  <div class="cf-form-panel__header">
                    <div
                      class="cf-panel-icon"
                      style="background:#fffbeb;color:#d97706"
                    >
                      <AppIcon name="send" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="cf-section-kicker">
                        Step 3
                      </p>
                      <p class="text-sm-pro font-semibold text-slate-800">
                        Message
                      </p>
                      <p class="text-2xs text-slate-500 mt-1">
                        Give the AI the product context it needs to draft on-brand copy.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3">
                    <div class="cf-field-card">
                      <AppFormField label="Product / service">
                        <AppInput
                          v-model="campaignForm.product_info"
                          type="textarea"
                          :rows="4"
                          placeholder="What are you promoting?"
                        />
                      </AppFormField>
                    </div>
                    <div class="cf-field-card">
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
                </div>

                <!-- Audience & Goals -->
                <div class="cf-form-panel">
                  <div class="cf-form-panel__header">
                    <div
                      class="cf-panel-icon"
                      style="background:#f0fdf4;color:#16a34a"
                    >
                      <AppIcon name="users" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="cf-section-kicker">
                        Step 4
                      </p>
                      <p class="text-sm-pro font-semibold text-slate-800">
                        Audience &amp; goals
                      </p>
                      <p class="text-2xs text-slate-500 mt-1">
                        Help the AI tailor messaging with audience and goal details.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="cf-field-card">
                      <AppFormField label="Target audience" hint="One per line or comma-separated">
                        <AppInput v-model="campaignForm.targetAudienceText" type="textarea" :rows="4" />
                      </AppFormField>
                    </div>
                    <div class="cf-field-card">
                      <AppFormField label="Goals" hint="One per line or comma-separated">
                        <AppInput v-model="campaignForm.goalsText" type="textarea" :rows="4" />
                      </AppFormField>
                    </div>
                  </div>
                </div>

                <!-- Auto-pilot -->
                <div class="cf-form-panel">
                  <div class="cf-form-panel__header">
                    <div
                      class="cf-panel-icon"
                      style="background:#eef2ff;color:#4f46e5"
                    >
                      <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="cf-section-kicker">
                        Step 5
                      </p>
                      <p class="text-sm-pro font-semibold text-slate-800">
                        Auto-pilot
                      </p>
                      <p class="text-2xs text-slate-500 mt-1">
                        When enabled, Curator picks the highest-scored approved draft per platform and schedules it for the next weekday at 9:00 AM.
                      </p>
                    </div>
                  </div>

                  <div class="cf-field-card">
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
                </div>

                <div v-if="campaignError" class="text-2xs text-red-600">{{ campaignError }}</div>
              </form>
            </div>
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
            <AppButton variant="primary" :disabled="generating || aiUnavailable" @click="generate">
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

            <div class="cd-master">
              <!-- LEFT: draft rail -->
              <div class="cd-rail">
                <div class="cd-rail-toolbar">
                  <div class="cd-rail-search">
                    <AppIcon name="search" class="cd-rail-search__icon" />
                    <AppInput
                      v-model="draftSearch"
                      placeholder="Search drafts…"
                      wrapper-class="flex-1"
                      input-class="!pl-7 !py-1.5 !text-xs"
                    />
                  </div>
                  <AppDropdown>
                    <template #trigger>
                      <AppButton size="sm" variant="ghost" title="Filter by status">
                        <AppIcon name="filter" class="w-3.5 h-3.5" />
                      </AppButton>
                    </template>
                    <template #default="{ close }">
                      <button
                        v-for="opt in DRAFT_STATUS_FILTERS"
                        :key="opt.id"
                        class="cd-dropdown-item"
                        :class="statusFilter === opt.id ? 'cd-dropdown-item--active' : ''"
                        @click="statusFilter = opt.id; close()"
                      >
                        {{ opt.label }}
                      </button>
                    </template>
                  </AppDropdown>
                </div>

                <div class="cd-rail-list">
                  <AppEmptyState
                    v-if="!paginatedPackages.length"
                    title="No matching drafts"
                    description="Try a different search term or filter."
                    icon="feeds"
                  />
                  <div
                    v-for="pkg in paginatedPackages"
                    :key="pkg.id"
                    role="button"
                    tabindex="0"
                    class="cd-rail-item"
                    :class="[
                      activeDraftId === pkg.id ? 'cd-rail-item--active' : '',
                      draftHasPublishIssue(pkg) ? 'cd-rail-item--issue' : '',
                      draftIsPublishReady(pkg) ? 'cd-rail-item--ready' : '',
                    ]"
                    @click="selectDraft(pkg.id)"
                    @keydown.enter="selectDraft(pkg.id)"
                    @keydown.space.prevent="selectDraft(pkg.id)"
                  >
                    <div class="cd-rail-item__meta">
                      <SocialPlatformLabel :type="pkg.platform" size="sm" />
                      <span class="campaign-draft-version">v{{ pkg.version }}</span>
                      <AppBadge :variant="draftStatusVariant(pkg.status)">{{ formatStatusLabel(pkg.status) }}</AppBadge>
                      <AppDropdown class="cd-rail-item__menu" align="right">
                        <template #trigger>
                          <AppButton size="sm" variant="ghost" title="Draft actions">
                            <AppIcon name="more" class="w-3.5 h-3.5" />
                          </AppButton>
                        </template>
                        <template #default="{ close }">
                          <button
                            class="cd-dropdown-item"
                            @click="selectDraft(pkg.id); close()"
                          >
                            <AppIcon name="eye" class="w-3.5 h-3.5" />
                            Open
                          </button>
                          <button
                            class="cd-dropdown-item"
                            @click="duplicateDraft(pkg); close()"
                          >
                            <AppIcon name="layers" class="w-3.5 h-3.5" />
                            Duplicate
                          </button>
                          <button
                            v-if="pkg.status !== 'approved'"
                            class="cd-dropdown-item"
                            @click="quickStatusChange(pkg, 'approved'); close()"
                          >
                            <AppIcon name="check" class="w-3.5 h-3.5" />
                            Approve
                          </button>
                          <button
                            v-if="pkg.status !== 'rejected'"
                            class="cd-dropdown-item"
                            @click="quickStatusChange(pkg, 'rejected'); close()"
                          >
                            <AppIcon name="close" class="w-3.5 h-3.5" />
                            Reject
                          </button>
                          <button
                            v-if="pkg.status !== 'draft'"
                            class="cd-dropdown-item"
                            @click="quickStatusChange(pkg, 'draft'); close()"
                          >
                            <AppIcon name="edit" class="w-3.5 h-3.5" />
                            Move back to draft
                          </button>
                          <button
                            class="cd-dropdown-item cd-dropdown-item--danger"
                            @click="deleteDraft(pkg); close()"
                          >
                            <AppIcon name="delete" class="w-3.5 h-3.5" />
                            Delete
                          </button>
                        </template>
                      </AppDropdown>
                    </div>
                    <div v-if="draftHasPublishIssue(pkg) || draftIsPublishReady(pkg) || pkg.ai_score != null" class="cd-rail-item__meta">
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
                    </div>
                    <p class="campaign-draft-preview">{{ pkg.caption }}</p>
                    <p v-if="draftIssueSummary(pkg)" class="campaign-draft-issue-line">
                      <AppIcon name="alert" class="w-3 h-3 inline-block flex-shrink-0" />
                      {{ draftIssueSummary(pkg) }}
                    </p>
                    <p v-if="pkg.hashtags?.length" class="campaign-draft-hashtags">{{ pkg.hashtags.join(' ') }}</p>
                    <div class="cd-rail-item__footer">
                      <span class="cd-rail-item__time">
                        <AppIcon name="clock" class="w-3 h-3" />
                        Updated {{ formatRelativeTime(pkg.updated_at) }}
                      </span>
                    </div>
                  </div>
                </div>

                <div v-if="draftsTotalPages > 1" class="cd-pagination">
                  <button
                    type="button"
                    class="cd-page-btn"
                    :disabled="draftsPage === 1"
                    @click="draftsPage--"
                  >
                    <AppIcon name="chevron-left" class="w-3.5 h-3.5" />
                  </button>
                  <span class="cd-page-info">Page {{ draftsPage }} of {{ draftsTotalPages }}</span>
                  <button
                    type="button"
                    class="cd-page-btn"
                    :disabled="draftsPage === draftsTotalPages"
                    @click="draftsPage++"
                  >
                    <AppIcon name="chevron-right" class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <!-- RIGHT: draft detail -->
              <AppEmptyState
                v-if="!activeDraft"
                class="cd-detail cd-detail--empty"
                title="Select a draft"
                description="Choose a draft from the list on the left to review, edit, and publish it."
                icon="feeds"
              />

              <div v-else class="cd-detail">
                <div class="cd-detail-header">
                  <div class="cd-detail-header__meta">
                    <SocialPlatformLabel :type="activeDraft.platform" size="md" />
                    <span class="campaign-draft-version">v{{ activeDraft.version }}</span>
                    <AppBadge :variant="draftStatusVariant(activeDraft.status)">{{ formatStatusLabel(activeDraft.status) }}</AppBadge>
                    <AppBadge
                      v-if="draftHasPublishIssue(activeDraft)"
                      variant="danger"
                      :title="draftPublishIssues(activeDraft).join(' ')"
                    >
                      <AppIcon name="alert" class="w-2.5 h-2.5 mr-0.5" />
                      Publish issue
                    </AppBadge>
                    <AppBadge v-else-if="draftIsPublishReady(activeDraft)" variant="success">Ready to publish</AppBadge>
                    <AppBadge
                      v-if="activeDraft.ai_score != null"
                      :variant="aiScoreBadgeVariant(activeDraft.ai_score)"
                      :title="'AI quality score'"
                    >
                      {{ formatAiScore(activeDraft.ai_score) }}
                    </AppBadge>
                    <AppBadge v-if="activeDraft.is_winner" variant="success">
                      <AppIcon name="star" class="w-2.5 h-2.5 mr-0.5" />Winner
                    </AppBadge>
                    <AppBadge v-else-if="activeDraft.variant_group_id" variant="info">A/B</AppBadge>
                  </div>
                  <div class="cd-detail-header__actions">
                    <AppSelect
                      :model-value="activeDraft.status"
                      select-class="!py-1.5 !text-xs min-w-[7.5rem]"
                      :show-placeholder="false"
                      @update:model-value="(value) => onPackageStatusChange(activeDraft, value)"
                    >
                      <option value="draft">Draft</option>
                      <option value="in_review">In review</option>
                      <option value="approved">Approved</option>
                      <option value="rejected">Rejected</option>
                    </AppSelect>
                  </div>
                </div>

                <div class="cd-detail-tabs-row">
                  <AppTabs v-model="detailTab" :tabs="detailTabs" aria-label="Draft sections" class="cd-detail-tabs-row__tabs" />
                  <div class="cd-detail-tabs-row__actions">
                    <AppButton size="sm" variant="primary" @click="openRefine(activeDraft)">
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
                          class="cd-dropdown-item"
                          @click="openPreview(activeDraft); close()"
                        >
                          <AppIcon name="image" class="w-3.5 h-3.5" />
                          Preview all platforms
                        </button>
                        <button
                          v-if="activeDraft.status === 'approved' && showScheduleAction"
                          class="cd-dropdown-item"
                          :disabled="draftHasPublishIssue(activeDraft)"
                          :title="draftHasPublishIssue(activeDraft) ? draftIssueSummary(activeDraft) : 'Schedule native publish'"
                          @click="schedulePackage(activeDraft); close()"
                        >
                          <AppIcon name="send" class="w-3.5 h-3.5" />
                          Schedule
                        </button>
                        <button
                          v-if="activeDraft.variant_group_id"
                          class="cd-dropdown-item"
                          @click="openVariantsModal(activeDraft); close()"
                        >
                          <AppIcon name="layers" class="w-3.5 h-3.5" />
                          View variants
                        </button>
                        <button
                          v-else
                          class="cd-dropdown-item"
                          @click="generateVariantsForPackage(activeDraft); close()"
                        >
                          <AppIcon name="sparkles" class="w-3.5 h-3.5" />
                          A/B test
                        </button>
                      </template>
                    </AppDropdown>
                  </div>
                </div>

                <div class="cd-detail-body">
                  <div class="cd-detail-main">
                    <!-- Review -->
                    <template v-if="detailTab === 'review'">
                      <ScheduleValidationPanel
                        v-if="isNativePublishPlatform(activeDraft.platform)"
                        :platform="activeDraft.platform"
                        :content-package="activeDraft"
                      />
                      <PlatformPublishGuide
                        v-else
                        :platforms="[activeDraft.platform]"
                        variant="cards"
                        title="Publish requirements"
                        subtitle="This platform uses embed/sync rather than native scheduling."
                      />

                      <div class="cd-preview-section">
                        <p class="cd-detail-subheading">Post preview</p>
                        <div v-if="previewSupported" class="cd-preview-stage">
                          <PostPreviewCard
                            :platform="previewPlatform"
                            :caption="activeDraft.caption"
                            :hashtags="activeDraft.hashtags || []"
                            :media-url="activeDraft.media_urls?.[0] || null"
                          />
                        </div>
                        <p v-else class="text-2xs text-slate-500">
                          A visual preview isn't available for {{ platformLabel(activeDraft.platform) }} yet.
                        </p>
                      </div>
                    </template>

                    <!-- Content -->
                    <template v-else>
                      <p class="text-sm text-slate-800 whitespace-pre-wrap leading-6">{{ activeDraft.caption }}</p>
                      <p v-if="activeDraft.hashtags?.length" class="campaign-draft-hashtags mt-2">{{ activeDraft.hashtags.join(' ') }}</p>

                      <div v-if="contentBlocks.length" class="flex flex-wrap items-end gap-2 mt-3">
                        <AppFormField label="Insert block" class="flex-1 min-w-[10rem]">
                          <AppSelect
                            :model-value="blockPickValue(activeDraft.id)"
                            :show-placeholder="true"
                            placeholder="Choose block…"
                            select-class="!text-sm"
                            @update:model-value="(v) => setBlockPick(activeDraft.id, v)"
                          >
                            <option v-for="b in contentBlocks" :key="b.id" :value="String(b.id)">
                              {{ b.name }} ({{ b.type }})
                            </option>
                          </AppSelect>
                        </AppFormField>
                        <AppButton
                          size="sm"
                          variant="secondary"
                          :disabled="!blockPickValue(activeDraft.id)"
                          @click="appendBlock(activeDraft)"
                        >
                          Append
                        </AppButton>
                      </div>
                    </template>
                  </div>

                  <div class="cd-detail-side">
                    <!-- Media -->
                    <div class="cd-side-card">
                      <div class="cd-side-card__head">
                        <p class="cd-side-card__title">Media</p>
                      </div>
                      <div v-if="(activeDraft.media_urls || []).length" class="flex flex-wrap gap-2">
                        <div v-for="(url, i) in activeDraft.media_urls" :key="i" class="campaign-media-thumb">
                          <a :href="url" target="_blank" rel="noopener" :title="url">
                            <img :src="url" :alt="`Media ${i + 1}`" class="h-16 w-16 rounded-lg border border-slate-200 object-cover" />
                          </a>
                          <AppButton
                            variant="ghost"
                            size="sm"
                            class="campaign-media-thumb__remove !p-0"
                            title="Remove media"
                            @click="removeMedia(activeDraft, url)"
                          >
                            <AppIcon name="close" class="h-3 w-3" />
                          </AppButton>
                        </div>
                      </div>
                      <div v-else class="cd-media-empty">
                        <AppIcon name="image" class="w-5 h-5" />
                        <p>No media attached. Add at least one image or video to publish on {{ platformLabel(activeDraft.platform) }}.</p>
                      </div>
                    </div>

                    <!-- Asset -->
                    <div class="cd-side-card">
                      <p class="cd-side-card__title mb-2">Asset</p>
                      <div class="campaign-media-toggle mb-2">
                        <button
                          type="button"
                          class="campaign-media-toggle__btn"
                          :class="mediaModeFor(activeDraft.id) === 'library' ? 'campaign-media-toggle__btn--active' : ''"
                          @click="setMediaMode(activeDraft.id, 'library')"
                        >
                          Library
                        </button>
                        <button
                          type="button"
                          class="campaign-media-toggle__btn"
                          :class="mediaModeFor(activeDraft.id) === 'url' ? 'campaign-media-toggle__btn--active' : ''"
                          @click="setMediaMode(activeDraft.id, 'url')"
                        >
                          URL
                        </button>
                      </div>

                      <div v-if="mediaModeFor(activeDraft.id) === 'library'" class="space-y-2">
                        <AppFormField label="Choose asset">
                          <AppSelect
                            :model-value="assetPickValue(activeDraft.id)"
                            select-class="!py-2 !text-sm"
                            :show-placeholder="true"
                            placeholder="Choose asset"
                            @update:model-value="(value) => setAssetPick(activeDraft.id, value)"
                          >
                            <option
                              v-for="a in assetsForPlatform(activeDraft.platform)"
                              :key="a.id"
                              :value="String(a.id)"
                            >
                              {{ a.file_name }}{{ formatAssetTags(a) }}
                            </option>
                          </AppSelect>
                        </AppFormField>
                        <div class="flex items-center gap-2">
                          <img
                            v-if="pickedAsset(activeDraft)?.url && pickedAsset(activeDraft)?.type === 'image'"
                            :src="pickedAsset(activeDraft).url"
                            :alt="pickedAsset(activeDraft).file_name"
                            class="h-10 w-10 rounded-lg border border-slate-200 object-cover"
                          />
                          <AppButton
                            size="sm"
                            variant="secondary"
                            :disabled="!assetPickValue(activeDraft.id)"
                            @click="attachAsset(activeDraft)"
                          >
                            Attach
                          </AppButton>
                        </div>
                        <p
                          v-if="!assetsForPlatform(activeDraft.platform).length"
                          class="text-2xs text-amber-700"
                        >
                          <template v-if="showContentLibraryLink">
                            No matching assets for this platform.
                            <router-link to="/content-library" class="underline font-medium">Upload in Content library</router-link>
                            or use the URL tab.
                          </template>
                          <template v-else>
                            No matching assets for this platform. Use the URL tab instead.
                          </template>
                        </p>
                      </div>

                      <div v-else class="space-y-2">
                        <AppFormField label="Public URL">
                          <AppInput
                            :model-value="manualUrlValue(activeDraft.id)"
                            placeholder="https://…"
                            input-class="!text-sm"
                            @update:model-value="(value) => setManualUrl(activeDraft.id, value)"
                          />
                        </AppFormField>
                        <AppButton size="sm" :disabled="!manualUrlValue(activeDraft.id).trim()" @click="addManualUrl(activeDraft)">Add</AppButton>
                      </div>
                    </div>

                    <!-- AI image -->
                    <div class="cd-side-card">
                      <div class="cd-side-card__head">
                        <p class="cd-side-card__title">AI image</p>
                        <AppBadge variant="info">New</AppBadge>
                        <router-link
                          v-if="isMenuEnabled('ai-settings')"
                          to="/settings/ai"
                          title="Manage AI settings"
                          class="ml-auto text-slate-400 hover:text-slate-600"
                        >
                          <AppIcon name="settings" class="w-3.5 h-3.5" />
                        </router-link>
                      </div>
                      <p class="text-2xs text-slate-500 mb-2">
                        Generate from a description, optionally using a reference image.
                      </p>
                      <AppButton
                        size="sm"
                        variant="secondary"
                        :loading="generatingImageId === activeDraft.id"
                        :disabled="generatingImageId === activeDraft.id || (activeDraft.media_urls || []).length >= 4"
                        @click="openImageModal(activeDraft)"
                      >
                        <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1" />
                        Generate image
                      </AppButton>
                    </div>
                  </div>
                </div>

                <div class="cd-detail-footer">
                  <span class="cd-detail-footer__time">
                    <AppIcon name="clock" class="w-3.5 h-3.5" />
                    Last updated: {{ formatRelativeTime(activeDraft.updated_at) }}
                  </span>
                  <div class="cd-detail-footer__actions">
                    <AppButton variant="secondary" @click="onPackageStatusChange(activeDraft, 'draft')">Save as draft</AppButton>
                    <AppButton variant="secondary" @click="schedulePackage(activeDraft)">
                      <AppIcon name="calendar" class="w-3.5 h-3.5 mr-1" />
                      Schedule
                    </AppButton>
                    <AppButton variant="primary" @click="schedulePackage(activeDraft)">
                      <AppIcon name="send" class="w-3.5 h-3.5 mr-1" />
                      Publish now
                    </AppButton>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
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

        <AppFormField label="AI service &amp; model" hint="Leave unset to use your saved default.">
          <AiProviderModelPicker kind="text" v-model="refinePickerValue" />
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
        <AppButton :disabled="refining || !instruction.trim() || aiUnavailable" @click="refine">
          <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
          {{ refining ? 'Refining…' : 'Refine with AI' }}
        </AppButton>
      </template>
    </AppModal>

    <GenerateImageModal
      :open="imageModalOpen"
      :package-id="imageModalPackage?.id"
      :assets="assets"
      :submitting="submittingImage"
      @close="imageModalOpen = false"
      @generate="generateImage"
    />

    <PostPreviewModal
      :open="previewModalOpen"
      :content-package="previewPackage"
      @close="previewModalOpen = false"
    />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useCampaignsStore } from '../stores/campaigns';
import { useAiSettingsStore } from '../stores/aiSettings';
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
  AppTabs,
} from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import PlatformPublishGuide from '../components/PlatformPublishGuide.vue';
import ScheduleValidationPanel from '../components/ScheduleValidationPanel.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';
import GenerateImageModal from '../components/content/GenerateImageModal.vue';
import { useSetupStore } from '../stores/setup';
import PostPreviewModal from '../components/content/PostPreviewModal.vue';
import PostPreviewCard from '../components/content/PostPreviewCard.vue';
import AiProviderModelPicker from '../components/content/AiProviderModelPicker.vue';
import { usePlatformPublishSpecs } from '../composables/usePlatformPublishSpecs';
import { useNavigationVisibility } from '../composables/useNavigationVisibility';
import { useRealtimeWithFallback } from '../composables/useRealtimeWithFallback';
import { CONTENT_TYPE_ICONS } from '../constants/platformPublishSpecs';
import { normalizePlatformType, getPlatformLabel } from '../constants/socialPlatforms';
import {
  draftHasPublishIssue,
  draftIssueSummary,
  isNativePublishPlatform,
  validateDraftForNativePublish,
} from '../utils/scheduleContentValidation';

const GENERATION_TIMEOUT_MS = 3 * 60 * 1000;
// Image jobs can outlast a campaign generate: FLUX polls its provider for the result.
const IMAGE_GENERATION_TIMEOUT_MS = 5 * 60 * 1000;

const route = useRoute();
const router = useRouter();
const store = useCampaignsStore();
const aiSettings = useAiSettingsStore();
const toast = useToastStore();
const { confirm } = inject('confirm');
const { isMenuEnabled } = useNavigationVisibility();
const showScheduleAction = computed(() => isMenuEnabled('schedule'));
const showContentLibraryLink = computed(() => isMenuEnabled('content-library'));
let generationTimeoutId = null;
let generationPollId = null;
let packagesBeforeGenerate = 0;

const { getSpecsForPlatforms } = usePlatformPublishSpecs();
const platformAcceptsEntries = computed(() => getSpecsForPlatforms(splitList(campaignForm.platformsText)));
function typeShortLabel(type) { return CONTENT_TYPE_ICONS[type.id] || type.label.slice(0, 3).toUpperCase(); }

const platformLabel = getPlatformLabel;

const DRAFT_STATUS_FILTERS = [
  { id: 'all', label: 'All statuses' },
  { id: 'ready', label: 'Ready to publish' },
  { id: 'approved', label: 'Approved' },
  { id: 'draft', label: 'Draft' },
  { id: 'issue', label: 'Needs attention' },
];

const campaign = ref(null);
const loading = ref(true);
const loadError = ref(null);
const campaignError = ref('');
const setup = useSetupStore();
// Never let a generate request fire into a 500 from an unconfigured provider.
// CapabilityBanner (context="ai", above) carries the reason and the fix link.
const aiUnavailable = computed(() => {
  const requirement = setup.requirement('ai_provider');
  return Boolean(requirement) && requirement.state !== 'satisfied';
});

const generating = ref(false);
const generationProgress = ref('');
const savingCampaign = ref(false);
const autoPilotEnabled = ref(false);
const autoPilotLoading = ref(false);
const autoPilotRunning = ref(false);

const activeTab = ref('brief');
const activeDraftId = ref(null);
const platformFilter = ref('all');
const statusFilter = ref('all');
const draftSearch = ref('');
const detailTab = ref('review');
const detailTabs = [
  { key: 'review', label: 'Review' },
  { key: 'content', label: 'Content' },
];
const refineModalOpen = ref(false);
const generatePickerValue = ref({ provider: '', model: '' });
const refinePickerValue = ref({ provider: '', model: '' });

const draftsPage = ref(1);
const DRAFTS_PER_PAGE = 10;

// A/B variants state
const abVariantsModalOpen = ref(false);
const variantGroup = ref([]);
const generatingImageId = ref(null);
let imageTimeoutId = null;
let imagePollId = null;
const imageModalOpen = ref(false);
const imageModalPackage = ref(null);

const previewModalOpen = ref(false);
const previewPackage = ref(null);
const submittingImage = ref(false);
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

  const query = draftSearch.value.trim().toLowerCase();
  if (query) {
    list = list.filter((p) => {
      const caption = String(p.caption || '').toLowerCase();
      const hashtags = (p.hashtags || []).join(' ').toLowerCase();
      return caption.includes(query) || hashtags.includes(query);
    });
  }

  return [...list].sort((a, b) => {
    const scoreA = a.ai_score ?? -1;
    const scoreB = b.ai_score ?? -1;
    if (scoreB !== scoreA) return scoreB - scoreA;
    return (b.id ?? 0) - (a.id ?? 0);
  });
});

const draftsTotal = computed(() => filteredPackages.value.length);
const draftsTotalPages = computed(() => Math.max(1, Math.ceil(draftsTotal.value / DRAFTS_PER_PAGE)));
const paginatedPackages = computed(() => {
  const start = (draftsPage.value - 1) * DRAFTS_PER_PAGE;
  return filteredPackages.value.slice(start, start + DRAFTS_PER_PAGE);
});

const activeDraft = computed(() => packages.value.find((p) => p.id === activeDraftId.value) || null);

const PREVIEWABLE_PLATFORMS = ['facebook', 'instagram', 'twitter'];
const previewPlatform = computed(() => normalizePlatformType(activeDraft.value?.platform));
const previewSupported = computed(() => PREVIEWABLE_PLATFORMS.includes(previewPlatform.value));

// Keep the detail panel pointed at a visible draft: land on the first result
// when nothing is selected yet, and follow the list when filters/search drop
// the currently active draft out of view.
watch(paginatedPackages, (list) => {
  if (list.some((p) => p.id === activeDraftId.value)) return;
  activeDraftId.value = list[0]?.id ?? null;
}, { immediate: true });

watch(activeDraftId, () => { detailTab.value = 'review'; });

function formatRelativeTime(iso) {
  if (!iso) return '';
  try {
    const diffMs = Date.now() - new Date(iso).getTime();
    const mins = Math.max(0, Math.floor(diffMs / 60000));
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
  } catch {
    return '';
  }
}

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
  aiSettings.load().catch(() => {});
  await Promise.all([load(), loadAssets(), loadBrandKits(), loadContentTemplates(), loadContentBlocks()]);
});

onUnmounted(() => {
  clearGeneratingState();
  clearImageGenerationFallback();
});

function clearGenerationTimeout() {
  if (generationTimeoutId) {
    window.clearTimeout(generationTimeoutId);
    generationTimeoutId = null;
  }
}

function clearGenerationPoll() {
  if (generationPollId) {
    window.clearInterval(generationPollId);
    generationPollId = null;
  }
}

function clearGeneratingState() {
  clearGenerationTimeout();
  clearGenerationPoll();
  generating.value = false;
  generationProgress.value = '';
}

function clearImageGenerationFallback() {
  if (imageTimeoutId) {
    window.clearTimeout(imageTimeoutId);
    imageTimeoutId = null;
  }
  if (imagePollId) {
    window.clearInterval(imagePollId);
    imagePollId = null;
  }
}

/**
 * Reverb is not always available in production, and FLUX in particular can run
 * for a while, so poll for the attached image and give up loudly rather than
 * leaving the button spinning forever.
 */
function armImageGenerationFallback(packageId, mediaCountBefore) {
  clearImageGenerationFallback();

  imagePollId = window.setInterval(async () => {
    if (document.hidden || !generatingImageId.value) return;
    await load({ showLoader: false, preserveSelectedId: packageId });
    const current = packages.value.find((p) => Number(p.id) === Number(packageId));
    if (current && (current.media_urls || []).length > mediaCountBefore) {
      clearImageGenerationFallback();
      generatingImageId.value = null;
      loadAssets();
      toast.success('Image generated');
      activeDraftId.value = packageId;
    }
  }, 15_000);

  imageTimeoutId = window.setTimeout(() => {
    if (!generatingImageId.value) return;
    clearImageGenerationFallback();
    generatingImageId.value = null;
    toast.info('Still generating in the background — refresh shortly to see the image.');
  }, IMAGE_GENERATION_TIMEOUT_MS);
}

function armGenerationTimeout() {
  clearGenerationTimeout();
  generationTimeoutId = window.setTimeout(() => {
    if (!generating.value) return;
    clearGeneratingState();
    toast.info('Still processing in the background — refresh shortly or check Notifications for the result.');
  }, GENERATION_TIMEOUT_MS);
}

function startGenerationPoll() {
  clearGenerationPoll();
  // Always poll while a generate is in-flight so success is visible even when
  // WebSocket/Reverb is unavailable in production.
  generationPollId = window.setInterval(() => {
    if (document.hidden || !generating.value) return;
    pollGenerationProgress();
  }, 15_000);
}

async function pollGenerationProgress() {
  if (!generating.value) return;
  await load({ showLoader: false });
  if (packages.value.length > packagesBeforeGenerate) {
    clearGeneratingState();
    activeTab.value = 'drafts';
    platformFilter.value = 'all';
    toast.success('Content generated');
    if (packages.value.length === 1) {
      activeDraftId.value = packages.value[0].id;
    }
  }
}

useRealtimeWithFallback({
  event: 'aiGeneration',
  onEvent: handleAiGeneration,
  poll: () => pollGenerationProgress(),
});

function handleAiGeneration(event) {
  const campaignId = Number(route.params.id);

  if (event.job_type === 'campaign_generate' && Number(event.resource_id) === campaignId) {
    if (event.status === 'started') {
      generating.value = true;
      generationProgress.value = event.message || 'Generating…';
      armGenerationTimeout();
      startGenerationPoll();
      return;
    }
    if (event.status === 'progress') {
      generating.value = true;
      const { platform, step, total } = event.data || {};
      generationProgress.value = platform
        ? `Generating ${platform} (${step}/${total})…`
        : (event.message || 'Generating…');
      armGenerationTimeout();
      startGenerationPoll();
      return;
    }
    clearGeneratingState();
    if (event.status === 'completed') {
      toast.success(event.message || 'Content generated');
      load({ showLoader: false }).then(() => {
        activeTab.value = 'drafts';
        platformFilter.value = 'all';
        if (packages.value.length === 1) {
          activeDraftId.value = packages.value[0].id;
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
        activeDraftId.value = selected.value.id;
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
    clearImageGenerationFallback();
    generatingImageId.value = null;
    if (event.status === 'completed') {
      toast.success(event.message || 'Image generated');
      Promise.all([
        load({ showLoader: false, preserveSelectedId: event.resource_id }),
        loadAssets(),
      ]).then(() => {
        activeDraftId.value = event.resource_id;
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

watch([platformFilter, statusFilter], () => {
  draftsPage.value = 1;
});

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
}

function openImageModal(pkg) {
  imageModalPackage.value = pkg;
  imageModalOpen.value = true;
}

function openPreview(pkg) {
  previewPackage.value = pkg;
  previewModalOpen.value = true;
}

async function generateImage(options) {
  const pkg = imageModalPackage.value;
  if (!pkg) return;

  submittingImage.value = true;
  try {
    const mediaCountBefore = (pkg.media_urls || []).length;
    await store.generateImage(pkg.id, options);
    // The job is queued; the finished image arrives over the aiGeneration
    // channel, or via the poll fallback when Reverb is unavailable.
    generatingImageId.value = pkg.id;
    armImageGenerationFallback(pkg.id, mediaCountBefore);
    imageModalOpen.value = false;
    toast.info('Image generation started…');
  } catch {
    // The axios interceptor has already surfaced the error.
  } finally {
    submittingImage.value = false;
  }
}

function selectDraft(packageId) {
  activeDraftId.value = packageId;
  ensurePackageMediaState(packageId);
}

function onPackageStatusChange(pkg, status) {
  pkg.status = status;
  updateStatus(pkg);
}

async function quickStatusChange(pkg, status) {
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/status`, { status });
    toast.success('Status updated');
    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
  } catch {
    // The axios interceptor has already surfaced the error.
  }
}

async function duplicateDraft(pkg) {
  try {
    const { data } = await axios.post(`/api/content-packages/${pkg.id}/duplicate`);
    toast.success('Draft duplicated');
    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
    activeDraftId.value = data.data?.id ?? data.id ?? activeDraftId.value;
  } catch {
    // The axios interceptor has already surfaced the error.
  }
}

async function deleteDraft(pkg) {
  const ok = await confirm({
    title: 'Delete draft?',
    message: `Delete this ${platformLabel(pkg.platform)} draft (v${pkg.version})? This can't be undone.`,
    confirmLabel: 'Delete',
  });
  if (!ok) return;

  try {
    await axios.delete(`/api/content-packages/${pkg.id}`);
    toast.success('Draft deleted');
    if (activeDraftId.value === pkg.id) activeDraftId.value = null;
    await load({ showLoader: false, preserveSelectedId: selectedPackageId.value });
  } catch {
    // The axios interceptor has already surfaced the error.
  }
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
  refinePickerValue.value = { provider: '', model: '' };
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

function pickedAsset(pkg) {
  const assetId = assetPickValue(pkg.id);
  if (!assetId) return null;
  return assetsForPlatform(pkg.platform).find((a) => String(a.id) === String(assetId)) || null;
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
  packagesBeforeGenerate = packages.value.length;
  generating.value = true;
  generationProgress.value = 'Queued…';
  armGenerationTimeout();
  startGenerationPoll();
  try {
    await store.generate(route.params.id, {
      provider: generatePickerValue.value.provider || undefined,
      model: generatePickerValue.value.model || undefined,
    });
    toast.info('Generation runs in the background — this can take up to a minute. Drafts will appear here and you will get a notification when it is ready.');
  } catch {
    clearGeneratingState();
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
    await store.refine(selected.value.id, {
      instruction: instruction.value.trim(),
      provider: refinePickerValue.value.provider || undefined,
      model: refinePickerValue.value.model || undefined,
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
    activeDraftId.value = pkg.id;
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
    activeDraftId.value = pkg.id;
  } catch {
    await load({ showLoader: false, preserveSelectedId: pkg.id });
  }
}

async function removeMedia(pkg, url) {
  const remaining = (pkg.media_urls || []).filter((u) => u !== url);
  try {
    await axios.patch(`/api/content-packages/${pkg.id}/media`, {
      media_urls: remaining,
      replace: true,
    });
    toast.success('Media removed');
    await load({ showLoader: false, preserveSelectedId: pkg.id });
    activeDraftId.value = pkg.id;
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
    await store.generateVariants(pkg.id);
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
/* ── Page layout ─────────────────────────────────────── */
.cd-layout { display: flex; flex-direction: column; }

.cd-main { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }

/* ── Top row: mode tabs + compact campaign overview strip ── */
.cd-top-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}

@media (max-width: 900px) {
  .cd-top-row { flex-direction: column; }
}

.cd-overview-strip {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  flex-wrap: wrap;
  padding: 0.5rem 0.875rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #fff;
  margin-left: auto;
}

.cd-overview-strip__id { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }

.cd-overview-strip__stats { display: flex; align-items: center; gap: 0.625rem; }

.cd-stat-chip { display: flex; align-items: baseline; gap: 0.3rem; }

.cd-stat-chip__value { font-size: 0.875rem; font-weight: 700; line-height: 1; }
.cd-stat-chip__value--blue    { color: #3b82f6; }
.cd-stat-chip__value--emerald { color: #10b981; }
.cd-stat-chip__value--red     { color: #ef4444; }
.cd-stat-chip__value--violet  { color: #8b5cf6; }

.cd-stat-chip__label { font-size: 0.68rem; font-weight: 500; color: #94a3b8; }

.cd-campaign-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 12rem;
}

.cd-platform-pills { display: flex; flex-wrap: wrap; gap: 0.375rem; }
.cd-platform-pills--compact { gap: 0.3rem; }

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

.cf-section-kicker {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}

.cf-config-layout {
  display: grid;
  gap: 1rem;
}

.cf-overview-panel {
  display: grid;
  gap: 1rem;
  align-content: start;
}

.cf-hero {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 1rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.82);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.cf-hero-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.85rem;
  flex-shrink: 0;
  background: #eff6ff;
  color: #3b82f6;
}

.cf-overview-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.cf-overview-stat {
  display: grid;
  gap: 0.45rem;
  padding: 0.85rem 0.95rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.95rem;
  background: rgba(255, 255, 255, 0.82);
}

.cf-overview-stat--wide {
  grid-column: 1 / -1;
}

.cf-overview-stat__label {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}

.cf-overview-stat__value {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
}

.cf-overview-stat__value--muted {
  font-weight: 500;
  color: #94a3b8;
}

.cf-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.68rem;
  padding: 0.28rem 0.55rem;
  border-radius: 999px;
  width: fit-content;
}

.cf-status-pill__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: #94a3b8;
}

.cf-status-pill--on {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(236, 253, 245, 0.92);
  color: rgb(6 95 70);
}

.cf-status-pill--on .cf-status-pill__dot {
  background: rgb(5 150 105);
}

.cf-form-panel {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.88);
  padding: 1rem;
  display: grid;
  gap: 0.9rem;
}

.cf-form-panel__header {
  display: flex;
  align-items: flex-start;
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

.cf-field-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 0.85rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

@media (min-width: 1024px) {
  .cf-config-layout {
    grid-template-columns: minmax(18rem, 21rem) minmax(0, 1fr);
    align-items: start;
  }
  .cf-overview-panel {
    position: sticky;
    top: 1rem;
  }
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

.campaign-draft-issue-line {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  font-size: 0.72rem;
  line-height: 1.4;
  color: #b91c1c;
  font-weight: 500;
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
.cd-dropdown-item--active { color: #6366f1; background: #f5f3ff; }
.cd-dropdown-item--danger { color: #ef4444; }
.cd-dropdown-item--danger:hover { background: #fef2f2; }

/* ── Master-detail drafts layout ─────────────────────── */
.cd-master {
  display: grid;
  grid-template-columns: 22rem minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}

@media (max-width: 1100px) {
  .cd-master { grid-template-columns: 1fr; }
}

.cd-rail {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 0.75rem;
  max-height: 52rem;
}

.cd-rail-toolbar { display: flex; align-items: center; gap: 0.5rem; }

.cd-rail-search {
  position: relative;
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}

.cd-rail-search__icon {
  position: absolute;
  left: 0.6rem;
  width: 0.85rem;
  height: 0.85rem;
  color: #94a3b8;
  flex-shrink: 0;
  pointer-events: none;
  z-index: 1;
}

.cd-rail-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  overflow-y: auto;
}

.cd-rail-item {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  width: 100%;
  text-align: left;
  padding: 0.7rem 0.8rem;
  border: 1px solid #e6ebf2;
  border-left: 3px solid transparent;
  border-radius: 0.625rem;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.12s, background 0.12s, box-shadow 0.12s;
}

.cd-rail-item:hover { background: #f8fafc; }

.cd-rail-item--issue { border-left-color: #ef4444; }
.cd-rail-item--ready { border-left-color: #10b981; }

.cd-rail-item--active {
  border-color: rgba(99, 102, 241, 0.35);
  border-left-color: #6366f1;
  background: #f5f3ff;
  box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.15);
}

.cd-rail-item__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.cd-rail-item__menu {
  margin-left: auto;
}

.cd-rail-item__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.15rem;
}

.cd-rail-item__time {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.68rem;
  color: #94a3b8;
}

.cd-detail {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 1rem;
  min-width: 0;
}

.cd-detail--empty { padding: 3rem 1rem; }

.cd-detail-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.cd-detail-header__meta, .cd-detail-header__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.cd-detail-tabs-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.cd-detail-tabs-row__tabs { flex: 1 1 auto; min-width: 0; border-bottom: none !important; }

.cd-detail-tabs-row__actions { display: flex; align-items: center; gap: 0.4rem; flex-shrink: 0; padding-bottom: 0.4rem; }

.cd-detail-body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 16rem;
  gap: 1.25rem;
  align-items: start;
}

@media (max-width: 800px) {
  .cd-detail-body { grid-template-columns: 1fr; }
}

.cd-detail-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.cd-detail-side { display: flex; flex-direction: column; gap: 0.75rem; min-width: 0; }

.cd-detail-subheading {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.5rem;
}

.cd-detail-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding-top: 0.85rem;
  border-top: 1px solid #eef2f6;
}

.cd-detail-footer__time {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.75rem;
  color: #94a3b8;
}

.cd-detail-footer__actions { display: flex; align-items: center; gap: 0.5rem; }

.cd-preview-stage {
  display: flex;
  justify-content: center;
  padding: 1.25rem;
  background: #f1f5f9;
  border-radius: 1rem;
  border: 1px solid #e2e8f0;
}

.cd-side-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.75rem;
  background: #fbfcfe;
  padding: 0.75rem;
}

.cd-side-card__head {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}

.cd-side-card__title { font-size: 0.8125rem; font-weight: 600; color: #334155; }

.cd-media-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  text-align: center;
  padding: 1rem 0.5rem;
  color: #94a3b8;
}

.cd-media-empty p { font-size: 0.72rem; line-height: 1.4; }

.campaign-media-thumb {
  position: relative;
}

.campaign-media-thumb__remove {
  position: absolute !important;
  top: -0.4rem;
  right: -0.4rem;
  display: none !important;
  align-items: center;
  justify-content: center;
  width: 1.15rem !important;
  height: 1.15rem !important;
  min-height: 0 !important;
  border-radius: 9999px !important;
  background: rgba(15, 23, 42, 0.85) !important;
  color: #fff !important;
}

.campaign-media-thumb:hover .campaign-media-thumb__remove {
  display: flex !important;
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

/* ── Drafts pagination ────────────────────────────────── */
.cd-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 0.75rem 0;
}

.cd-page-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.5rem;
  background: #fff;
  color: #475569;
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s;
}

.cd-page-btn:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.cd-page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.cd-page-info {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #64748b;
}
</style>
