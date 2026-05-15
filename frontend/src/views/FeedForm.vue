<template>
  <WizardPageLayout
    current="feed"
    :title="isEdit ? 'Edit feed' : 'Create a feed'"
    description="Connect the content source for this workspace."
    :workspaceId="route.params.workspaceId"
    :breadcrumb="['Workspaces', workspaceName || 'Workspace', 'Feeds', isEdit ? 'Edit Feed' : 'New Feed']"
    no-sticky
  >
    <template #breadcrumb>
      <router-link to="/workspaces">Workspaces</router-link>
      <span>/</span>
      <router-link :to="`/workspaces/${workspaceId}/feeds`">{{ workspaceName }}</router-link>
      <span>/</span>
      <span>Feeds</span>
    </template>

    <template #actions>
      <AppButton :to="`/workspaces/${workspaceId}/feeds`" variant="secondary" size="sm" title="Go back">Back</AppButton>
      <AppButton
        type="submit"
        form="feed-form"
        variant="primary"
        size="sm"
        :disabled="saving"
        title="Save and continue"
      >
        {{ saveButtonText }}
      </AppButton>
    </template>

    <AppCard class="feed-form-hero p-5 md:p-6">
      <div class="feed-hero-intro">
        <div>
          <p class="feed-section-kicker">Step 1</p>
          <h2 class="feed-hero-title">Choose a feed source</h2>
          <p class="feed-hero-copy">Pick the platform or feed type you want to connect.</p>
        </div>
        <div class="feed-hero-meta">
          <span class="feed-hero-badge">{{ availableSocialTypes.length }} ready</span>
          <span class="feed-hero-current">Selected: {{ selectedTypeMeta.label }}</span>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
        <AppButton
          v-for="t in availableSocialTypes"
          :key="t.type"
          variant="ghost"
          class="feed-type-card"
          :class="form.type === t.type ? 'feed-type-card--active' : ''"
          @click="form.type = t.type"
        >
          <div class="feed-type-icon-wrap" :style="{ background: t.softBg, color: t.color }">
            <SocialIcon :type="t.type" class="w-4 h-4" />
          </div>
          <div class="text-left min-w-0">
            <div class="text-xs-pro font-medium text-slate-700 truncate">{{ t.label }}</div>
            <div class="text-2xs text-slate-500 truncate">{{ t.tagline }}</div>
          </div>
        </AppButton>
      </div>
      <div class="mt-4 flex flex-wrap items-center gap-2.5">
        <div class="setup-step" :class="form.type ? 'setup-step--active' : ''">
          <span class="setup-step__dot">1</span>
          Select source
        </div>
        <div class="setup-step-divider" />
        <div class="setup-step" :class="form.social_credential_id ? 'setup-step--active' : ''">
          <span class="setup-step__dot">2</span>
          Connect account
        </div>
        <div class="setup-step-divider" />
        <div class="setup-step" :class="connectionStatus.kind === 'ok' ? 'setup-step--active' : ''">
          <span class="setup-step__dot">3</span>
          Verify connection
        </div>
      </div>
    </AppCard>
    <AppCard class="mt-4 p-4 md:p-5">
    <div class="feed-form-layout">
      <aside class="feed-overview-panel">
        <div class="feed-overview-hero">
          <div class="feed-overview-hero__icon" :style="{ background: selectedTypeMeta.softBg, color: selectedTypeMeta.color }">
            <SocialIcon :type="selectedTypeMeta.type" class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <p class="feed-section-kicker">Step 2</p>
            <h2 class="text-base font-semibold text-slate-900">{{ selectedTypeMeta.label }}</h2>
            <p class="text-2xs text-slate-500 mt-1">{{ selectedTypeMeta.tagline }}</p>
          </div>
        </div>

        <div class="feed-overview-grid">
          <div class="feed-overview-stat">
            <span class="feed-overview-stat__label">Mode</span>
            <span class="feed-overview-stat__value">{{ isEdit ? 'Editing existing feed' : 'New feed setup' }}</span>
          </div>
          <div class="feed-overview-stat">
            <span class="feed-overview-stat__label">Credentials</span>
            <span class="feed-overview-stat__value">{{ credentialStatusText }}</span>
          </div>
          <div class="feed-overview-stat feed-overview-stat--wide">
            <span class="feed-overview-stat__label">Verification</span>
            <div class="feed-status-pill" :class="connectionStatus.kind === 'ok' ? 'feed-status-pill--ok' : connectionStatus.kind === 'error' ? 'feed-status-pill--error' : ''">
              <span class="feed-status-pill__dot" />
              {{ verificationStatusText }}
            </div>
          </div>
        </div>

        <AppAlert v-if="requiresCredential && !selectedCredentialCount" variant="warning" title="Credential required">
          Connect {{ selectedTypeMeta.label }} in
          <router-link to="/credentials" class="font-medium underline underline-offset-2">Credentials</router-link>
          before completing this feed setup.
        </AppAlert>

        <AppAlert v-else-if="!requiresCredential" variant="info" title="Direct source setup">
          This source type uses a URL instead of an OAuth account connection.
        </AppAlert>

        <div class="feed-overview-note">
          <p class="text-xs-pro font-medium text-slate-700">What happens next</p>
          <p class="text-2xs text-slate-500 mt-1">Once this source is saved, the next step is feed curation where content selection and publishing options are configured.</p>
        </div>
      </aside>

      <form id="feed-form" @submit.prevent="submit" class="space-y-4">
        <div class="feed-section-panel">
          <div class="feed-section-panel__header">
            <div>
              <p class="feed-section-kicker">Feed details</p>
              <p class="text-sm-pro font-semibold text-slate-800">Basic information</p>
              <p class="text-2xs text-slate-500 mt-1">Set the internal feed name and confirm the source type.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="feed-field-card">
              <AppFormField label="Name" required hint="Used internally to identify this feed in the workspace.">
                <AppInput v-model="form.name" type="text" placeholder="Feed name" required />
              </AppFormField>
            </div>
            <div class="feed-field-card">
              <AppFormField label="Type" required :hint="selectedTypeMeta.tagline">
                <AppSelect v-model="form.type" required>
                  <option value="">Select type</option>
                  <option v-for="t in availableSocialTypes" :key="`type-${t.type}`" :value="t.type">{{ t.label }}</option>
                </AppSelect>
              </AppFormField>
            </div>
          </div>
        </div>

        <transition name="fade-slide" mode="out-in">
          <div :key="form.type" class="feed-section-panel">
            <div class="feed-section-panel__header">
              <div>
                <p class="feed-section-kicker">Source setup</p>
                <p class="text-sm-pro font-semibold text-slate-800">{{ selectedTypeMeta.label }} connection</p>
                <p class="text-2xs text-slate-500 mt-1">Choose the account, page, channel, or URL this feed should sync from.</p>
              </div>
            </div>

            <div v-if="form.type === 'youtube'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="YouTube credential" :hint="youtubeCredentials.length ? 'Choose the connected Google account used for this channel.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!youtubeCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in youtubeCredentials" :key="c.id" :value="String(c.id)">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!youtubeCredentials.length" class="feed-error-text">
                  No YouTube credentials found. Go to Credentials to connect YouTube first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="Channel" hint="Only channels managed by this Google account are listed from channels.list?mine=true.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedYoutubeChannelId"
                      :disabled="loadingYoutubeChannels || !form.social_credential_id"
                      :required="!!youtubeCredentials.length"
                    >
                      <option value="">
                        {{
                          !form.social_credential_id
                            ? 'Select credential first'
                            : loadingYoutubeChannels
                              ? 'Loading channels…'
                              : 'Select channel'
                        }}
                      </option>
                      <option v-for="ch in youtubeChannels" :key="ch.id" :value="ch.id">
                        {{ youtubeChannelLabel(ch) }}
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingYoutubeChannels || !form.social_credential_id"
                      @click="loadYoutubeChannels"
                    >
                      {{ loadingYoutubeChannels ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
              </div>

              <div v-if="form.youtube_channel_id" class="feed-field-card">
                <AppFormField label="Channel ID (internal)" hint="Embeds use the public handle or title, not this internal channel ID.">
                  <AppInput v-model="form.youtube_channel_id" type="text" readonly />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingConnection || !form.youtube_channel_id || !form.social_credential_id"
                  @click="testConnection"
                >
                  {{ testingConnection ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'facebook'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="Facebook credential" :hint="facebookCredentials.length ? 'Choose the connected Facebook account that can access this Page.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!facebookCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in facebookCredentials" :key="c.id" :value="c.id">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!facebookCredentials.length" class="feed-error-text">
                  No Facebook credentials found. Go to Credentials to connect Facebook first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="Facebook Page" hint="We’ll list Pages from /me/accounts. Reconnect Facebook if the correct Page does not appear.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedFacebookPageId"
                      :disabled="loadingFacebookPages || !form.social_credential_id"
                      required
                    >
                      <option value="">
                        {{ !form.social_credential_id ? 'Select credential first' : (loadingFacebookPages ? 'Loading pages…' : 'Select page') }}
                      </option>
                      <option v-for="p in facebookPages" :key="p.id" :value="p.id">
                        {{ p.name || p.id }} ({{ p.id }})
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingFacebookPages || !form.social_credential_id"
                      @click="loadFacebookPages"
                    >
                      {{ loadingFacebookPages ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
              </div>

              <div v-if="form.facebook_page_id" class="feed-field-card">
                <AppFormField label="Selected Page ID">
                  <AppInput v-model="form.facebook_page_id" type="text" readonly />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingFacebook || !form.facebook_page_id || !form.social_credential_id"
                  @click="testFacebookConnection"
                >
                  {{ testingFacebook ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'instagram'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="Instagram credential" :hint="instagramCredentials.length ? 'Choose the connected Meta account that owns the linked Page and Instagram profile.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!instagramCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in instagramCredentials" :key="c.id" :value="c.id">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!instagramCredentials.length" class="feed-error-text">
                  No Instagram credentials found. Go to Credentials to connect Instagram first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="Linked Page & Instagram account" hint="Only Pages with an attached Instagram Professional account are shown.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedInstagramCombo"
                      :disabled="loadingInstagramAccounts || !form.social_credential_id"
                      required
                    >
                      <option value="">
                        {{
                          !form.social_credential_id
                            ? 'Select credential first'
                            : loadingInstagramAccounts
                              ? 'Loading accounts…'
                              : 'Select account'
                        }}
                      </option>
                      <option v-for="a in instagramAccounts" :key="instagramAccountValue(a)" :value="instagramAccountValue(a)">
                        @{{ a.instagram_username || '…' }} · {{ a.facebook_page_name || 'Page' }} (IG {{ a.instagram_business_account_id }})
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingInstagramAccounts || !form.social_credential_id"
                      @click="loadInstagramAccounts"
                    >
                      {{ loadingInstagramAccounts ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
                <p
                  v-if="form.social_credential_id && !loadingInstagramAccounts && !instagramAccounts.length"
                  class="feed-error-text"
                >
                  No linked Instagram accounts found. Link Instagram to a Facebook Page in Meta, then reconnect and retry.
                </p>
              </div>

              <div v-if="form.facebook_page_id && form.instagram_business_account_id" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="feed-field-card">
                  <AppFormField label="Backing Page ID">
                    <AppInput v-model="form.facebook_page_id" type="text" readonly />
                  </AppFormField>
                </div>
                <div class="feed-field-card">
                  <AppFormField label="Instagram Business account ID">
                    <AppInput v-model="form.instagram_business_account_id" type="text" readonly />
                  </AppFormField>
                </div>
                <div class="feed-field-card md:col-span-2">
                  <AppFormField label="Instagram handle (embed label)" hint="Prefilled from the selected account and used on published widgets instead of the feed title.">
                    <AppInput
                      v-model="form.instagram_username"
                      type="text"
                      placeholder="username without @"
                      autocomplete="off"
                    />
                  </AppFormField>
                </div>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="
                    testingInstagram || !form.facebook_page_id || !form.instagram_business_account_id || !form.social_credential_id
                  "
                  @click="testInstagramConnection"
                >
                  {{ testingInstagram ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'twitter'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="X / Twitter credential" :hint="twitterCredentials.length ? 'Choose the connected X account for this feed.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!twitterCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in twitterCredentials" :key="c.id" :value="c.id">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!twitterCredentials.length" class="feed-error-text">
                  No Twitter / X credentials found. Go to Credentials to connect first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="X account" hint="Only the account returned from users/me for this credential will be synced.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedTwitterUserId"
                      :disabled="loadingTwitterAccount || !form.social_credential_id"
                      :required="!!twitterCredentials.length"
                    >
                      <option value="">
                        {{
                          !form.social_credential_id
                            ? 'Select credential first'
                            : loadingTwitterAccount
                              ? 'Loading account…'
                              : 'Select account'
                        }}
                      </option>
                      <option v-for="a in twitterAccounts" :key="a.id" :value="a.id">
                        {{ twitterAccountLabel(a) }}
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingTwitterAccount || !form.social_credential_id"
                      @click="loadTwitterAccount"
                    >
                      {{ loadingTwitterAccount ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
              </div>

              <div v-if="selectedTwitterUserId" class="feed-field-card">
                <AppFormField label="User ID">
                  <AppInput v-model="selectedTwitterUserId" type="text" readonly />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingTwitter || !form.social_credential_id || !selectedTwitterUserId"
                  @click="testTwitterConnection"
                >
                  {{ testingTwitter ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'tiktok'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="TikTok credential" :hint="tiktokCredentials.length ? 'Choose the connected TikTok account for this feed.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!tiktokCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in tiktokCredentials" :key="c.id" :value="c.id">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!tiktokCredentials.length" class="feed-error-text">
                  No TikTok credentials found. Go to Credentials to connect first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="TikTok account" hint="We load account info for this credential and sync videos from that connected account only.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedTikTokOpenId"
                      :disabled="loadingTikTokAccount || !form.social_credential_id"
                      :required="!!tiktokCredentials.length"
                    >
                      <option value="">
                        {{
                          !form.social_credential_id
                            ? 'Select credential first'
                            : loadingTikTokAccount
                              ? 'Loading account…'
                              : 'Select account'
                        }}
                      </option>
                      <option v-for="a in tiktokAccounts" :key="a.open_id" :value="a.open_id">
                        {{ tikTokAccountLabel(a) }}
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingTikTokAccount || !form.social_credential_id"
                      @click="loadTikTokAccount"
                    >
                      {{ loadingTikTokAccount ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
                <p
                  v-if="form.social_credential_id && !loadingTikTokAccount && !tiktokAccounts.length"
                  class="feed-error-text"
                >
                  Could not load a TikTok account for this credential. Reconnect TikTok and retry.
                </p>
              </div>

              <div v-if="selectedTikTokOpenId" class="feed-field-card">
                <AppFormField label="Open ID">
                  <AppInput v-model="selectedTikTokOpenId" type="text" readonly />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingTikTok || !form.social_credential_id || !selectedTikTokOpenId"
                  @click="testTikTokConnection"
                >
                  {{ testingTikTok ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'threads'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="Threads credential" :hint="threadsCredentials.length ? 'Choose the connected Threads account for this feed.' : ''">
                  <AppSelect
                    v-model="form.social_credential_id"
                    :required="!!threadsCredentials.length"
                  >
                    <option value="">Select credential</option>
                    <option v-for="c in threadsCredentials" :key="c.id" :value="c.id">
                      {{ c.account_label || c.account_id || c.provider }}{{ c.expires_at ? ` · ${formatDate(c.expires_at)}` : '' }}
                    </option>
                  </AppSelect>
                </AppFormField>
                <p v-if="!threadsCredentials.length" class="feed-error-text">
                  No Threads credentials found. Go to Credentials to connect first.
                </p>
              </div>

              <div class="feed-field-card">
                <AppFormField label="Threads account" hint="We read /me for this credential and sync posts from that connected account only.">
                  <div class="feed-inline-select">
                    <AppSelect
                      v-model="selectedThreadsUserId"
                      :disabled="loadingThreadsAccount || !form.social_credential_id"
                      :required="!!threadsCredentials.length"
                    >
                      <option value="">
                        {{
                          !form.social_credential_id
                            ? 'Select credential first'
                            : loadingThreadsAccount
                              ? 'Loading account…'
                              : 'Select account'
                        }}
                      </option>
                      <option v-for="a in threadsAccounts" :key="a.id" :value="a.id">
                        {{ threadsAccountLabel(a) }}
                      </option>
                    </AppSelect>
                    <AppButton
                      variant="secondary"
                      size="sm"
                      :disabled="loadingThreadsAccount || !form.social_credential_id"
                      @click="loadThreadsAccount"
                    >
                      {{ loadingThreadsAccount ? 'Loading…' : 'Refresh' }}
                    </AppButton>
                  </div>
                </AppFormField>
                <p
                  v-if="form.social_credential_id && !loadingThreadsAccount && !threadsAccounts.length"
                  class="feed-error-text"
                >
                  Could not load a Threads account for this credential. Reconnect Threads and retry.
                </p>
              </div>

              <div v-if="selectedThreadsUserId" class="feed-field-card">
                <AppFormField label="Threads user ID">
                  <AppInput v-model="selectedThreadsUserId" type="text" readonly />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingThreads || !form.social_credential_id || !selectedThreadsUserId"
                  @click="testThreadsConnection"
                >
                  {{ testingThreads ? 'Testing…' : 'Test connection' }}
                </AppButton>
              </div>
            </div>

            <div v-else-if="form.type === 'rss'" class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="RSS or Atom feed URL" required hint="Supports RSS 2.0 and Atom. Use a stable public URL that the server can fetch during sync.">
                  <AppInput
                    v-model="form.source_url"
                    type="url"
                    placeholder="https://example.com/feed.xml"
                    required
                  />
                </AppFormField>
              </div>

              <div class="feed-section-actions">
                <AppButton
                  variant="secondary"
                  size="sm"
                  :disabled="testingRss || !form.source_url?.trim()"
                  @click="testRss"
                >
                  {{ testingRss ? 'Testing…' : 'Test feed' }}
                </AppButton>
              </div>

              <div v-if="rssTestSummary" class="feed-summary-card">
                <div v-if="rssTestSummary.feed_title">
                  <span class="font-medium text-slate-600">Feed:</span>
                  {{ rssTestSummary.feed_title }}
                </div>
                <div v-if="rssTestSummary.item_count != null">
                  <span class="font-medium text-slate-600">Entries found:</span>
                  {{ rssTestSummary.item_count }}
                </div>
                <div v-if="rssTestSummary.sample_title">
                  <span class="font-medium text-slate-600">Latest title:</span>
                  {{ rssTestSummary.sample_title }}
                </div>
              </div>
            </div>

            <div v-else class="feed-provider-stack">
              <div class="feed-field-card">
                <AppFormField label="Source URL" hint="Optional reference URL for this feed type.">
                  <AppInput v-model="form.source_url" type="url" placeholder="https://…" />
                </AppFormField>
              </div>
            </div>
          </div>
        </transition>

        <AppCard class="mt-4 p-4 md:p-5 border border-slate-200/90">
          <p class="feed-section-kicker mb-2">Sync and embedded feed</p>
          <AppCheckbox
            id="feed-auto-publish"
            :model-value="form.auto_publish_new_posts"
            :disabled="syncToggleSaving"
            label="Auto upload new post to feed"
            @update:model-value="onAutoPublishToggle"
          />
          <p v-if="isEdit && syncToggleSaving" class="mt-1.5 text-2xs text-slate-500">Saving preference…</p>
          <p class="mt-2 text-2xs text-slate-500 leading-relaxed">
            When this is on, new posts synced from this source are approved and shown on your embedded feed automatically (you already curate what appears on the platform).
            When off, pick posts manually on the Curate page and publish when ready.
          </p>
        </AppCard>

        <AppAlert
          v-if="connectionStatus.message"
          :variant="connectionStatus.kind === 'ok' ? 'success' : 'danger'"
          :title="connectionStatus.kind === 'ok' ? 'Connection verified' : 'Connection issue'"
        >
          {{ connectionStatus.message }}
        </AppAlert>

        <AppAlert v-if="feeds.lastActionError" variant="danger" title="Could not save feed">
          {{ feeds.lastActionError }}
        </AppAlert>
      </form>
    </div>
    </AppCard>

    <template #footer>
      <AppButton :to="`/workspaces/${workspaceId}/feeds`" variant="secondary">Back</AppButton>
      <AppButton
        type="submit"
        form="feed-form"
        variant="primary"
        :disabled="
          saving ||
          (form.type === 'twitter' && (!form.social_credential_id || !selectedTwitterUserId)) ||
          (form.type === 'tiktok' && (!form.social_credential_id || !selectedTikTokOpenId)) ||
          (form.type === 'threads' && (!form.social_credential_id || !selectedThreadsUserId)) ||
          (form.type === 'instagram' &&
            (!form.social_credential_id || !form.facebook_page_id || !form.instagram_business_account_id)) ||
          (form.type === 'rss' && !String(form.source_url || '').trim())
        "
      >
        {{ saveButtonText }}
      </AppButton>
    </template>
  </WizardPageLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useFeedsStore } from '../stores/feeds';
import { useWorkspacesStore } from '../stores/workspaces';
import { useCredentialsStore } from '../stores/credentials';
import { useToastStore } from '../stores/toast';
import SocialIcon from '../components/SocialIcon.vue';
import { AppAlert, AppButton, AppCard, AppCheckbox, AppFormField, AppInput, AppSelect } from '../components/ui/index.js';
import WizardPageLayout from '../components/WizardPageLayout.vue';
import {
  fetchFacebookPages,
  fetchInstagramAccounts,
  fetchThreadsAccount,
  fetchTikTokAccount,
  fetchTwitterAccount,
  fetchYoutubeChannels,
  testFacebook,
  testInstagram,
  testRssFeed,
  testThreads,
  testTikTok,
  testTwitter,
  testYoutube,
} from '../composables/useFeedSourcesApi';

const route = useRoute();
const router = useRouter();
const feeds = useFeedsStore();
const workspaces = useWorkspacesStore();
const credentials = useCredentialsStore();
const toast = useToastStore();

const workspaceId = computed(() => route.params.workspaceId);
const feedId = computed(() => route.params.feedId);
const isEdit = computed(() => !!feedId.value);
const workspaceName = computed(() => {
  const w = workspaces.list.find((x) => x.id === Number(workspaceId.value));
  return w ? w.name : '…';
});

const form = reactive({
  name: '',
  type: '',
  source_url: '',
  youtube_channel_id: '',
  facebook_page_id: '',
  instagram_business_account_id: '',
  instagram_username: '',
  social_credential_id: '',
  auto_publish_new_posts: false,
});
const saving = ref(false);
const testingConnection = ref(false);
const testingFacebook = ref(false);
const testingInstagram = ref(false);
const testingTwitter = ref(false);
const testingTikTok = ref(false);
const testingThreads = ref(false);
const testingRss = ref(false);
/** PATCH sync-settings in flight (edit mode; avoids relying on blocked PUT feed). */
const syncToggleSaving = ref(false);
const loadingFacebookPages = ref(false);
const facebookPages = ref([]);
const selectedFacebookPageId = ref('');
const loadingInstagramAccounts = ref(false);
const instagramAccounts = ref([]);
const selectedInstagramCombo = ref('');
/** When true, Instagram credential watch only loads accounts; does not clear selection (edit hydrate). */
const skipNextInstagramCredReset = ref(false);
const loadingYoutubeChannels = ref(false);
const youtubeChannels = ref([]);
const selectedYoutubeChannelId = ref('');
/** When true, YouTube credential watch only loads channels; does not clear selection (edit hydrate). */
const skipNextYoutubeCredReset = ref(false);
const loadingTwitterAccount = ref(false);
const twitterAccounts = ref([]);
const selectedTwitterUserId = ref('');
/** When true, Twitter credential watch only loads account; does not clear selection (edit hydrate). */
const skipNextTwitterCredReset = ref(false);
const loadingTikTokAccount = ref(false);
const tiktokAccounts = ref([]);
const selectedTikTokOpenId = ref('');
/** When true, TikTok credential watch only loads account; does not clear selection (edit hydrate). */
const skipNextTikTokCredReset = ref(false);
const loadingThreadsAccount = ref(false);
const threadsAccounts = ref([]);
const selectedThreadsUserId = ref('');
/** When true, Threads credential watch only loads account; does not clear selection (edit hydrate). */
const skipNextThreadsCredReset = ref(false);
/** Last successful RSS test payload for inline summary. */
const rssTestSummary = ref(null);
const connectionStatus = reactive({
  kind: '',
  message: '',
});

const youtubeCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'youtube'),
);

const youtubeCredentialIds = computed(() =>
  new Set(youtubeCredentials.value.map((c) => String(c.id))),
);

const facebookCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'facebook'),
);

const instagramCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'instagram'),
);

const twitterCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'twitter'),
);
const tiktokCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'tiktok'),
);
const threadsCredentials = computed(() =>
  credentials.list.filter((c) => c.provider === 'threads'),
);
const credentialCounts = computed(() => ({
  youtube: youtubeCredentials.value.length,
  facebook: facebookCredentials.value.length,
  instagram: instagramCredentials.value.length,
  twitter: twitterCredentials.value.length,
  tiktok: tiktokCredentials.value.length,
  threads: threadsCredentials.value.length,
}));

const socialTypes = [
  { type: 'instagram', label: 'Instagram', tagline: 'Stories, reels, and posts', color: '#e1306c', softBg: 'rgba(225,48,108,0.13)' },
  { type: 'twitter', label: 'X / Twitter', tagline: 'Real-time conversations', color: '#111827', softBg: 'rgba(15,23,42,0.12)' },
  { type: 'youtube', label: 'YouTube', tagline: 'Channels and videos', color: '#ff0000', softBg: 'rgba(255,0,0,0.12)' },
  { type: 'rss', label: 'RSS / Atom', tagline: 'Blogs and publications', color: '#f97316', softBg: 'rgba(249,115,22,0.15)' },
  { type: 'tiktok', label: 'TikTok', tagline: 'Short-form video stream', color: '#111827', softBg: 'rgba(244,63,94,0.12)' },
  { type: 'threads', label: 'Threads', tagline: 'Text-first conversations', color: '#111827', softBg: 'rgba(15,23,42,0.12)' },
  { type: 'facebook', label: 'Facebook', tagline: 'Pages and updates', color: '#1877f2', softBg: 'rgba(24,119,242,0.12)' },
  { type: 'other', label: 'Other', tagline: 'Custom source setup', color: '#475569', softBg: 'rgba(71,85,105,0.12)' },
];
const accountOptionalTypes = new Set(['rss', 'other']);
const availableSocialTypes = computed(() => socialTypes.filter((item) => (
  accountOptionalTypes.has(item.type)
  || (credentialCounts.value[item.type] || 0) > 0
  || item.type === form.type
)));

const selectedTypeMeta = computed(() =>
  socialTypes.find((item) => item.type === form.type) || {
    type: 'other',
    label: 'Select a source',
    tagline: 'Choose a source type to configure credentials and sync settings.',
    color: '#475569',
    softBg: 'rgba(71,85,105,0.12)',
  },
);
const requiresCredential = computed(() => !accountOptionalTypes.has(form.type));
const selectedCredentialCount = computed(() => credentialCounts.value[form.type] || 0);
const credentialStatusText = computed(() => {
  if (!form.type) return 'Select a source type';
  if (!requiresCredential.value) return 'No OAuth credential required';
  if (!selectedCredentialCount.value) return 'No connected credentials';
  return `${selectedCredentialCount.value} credential${selectedCredentialCount.value === 1 ? '' : 's'} ready`;
});
const verificationStatusText = computed(() => {
  if (connectionStatus.kind === 'ok') return 'Verified';
  if (connectionStatus.kind === 'error') return 'Needs attention';
  return 'Not tested yet';
});
const saveButtonText = computed(() => {
  if (saving.value) return 'Saving…';
  return isEdit.value ? 'Save and continue' : 'Create and continue';
});

function youtubeChannelLabel(ch) {
  const title = ch.title || 'Channel';
  const cu = String(ch.custom_url || '').trim();
  if (cu) {
    const h = cu.startsWith('@') ? cu : `@${cu}`;
    return `${title} (${h})`;
  }
  return `${title} (${ch.id})`;
}

/** Public handle/title for embeds — matches backend youtube_display_label. */
function youtubePublicLabelForSubmit() {
  if (form.type !== 'youtube' || !form.youtube_channel_id) return null;
  const ch = youtubeChannels.value.find((x) => String(x.id) === String(form.youtube_channel_id));
  if (!ch) return null;
  const cu = String(ch.custom_url || '').trim();
  if (cu) {
    return cu.startsWith('@') ? cu : `@${cu}`;
  }
  const t = String(ch.title || '').trim();
  return t || null;
}

function twitterAccountLabel(a) {
  const u = (a.username && String(a.username)) || 'account';
  const n = a.name && String(a.name).trim() ? ` — ${a.name}` : '';
  return `@${u}${n} (${a.id})`;
}

function threadsAccountLabel(a) {
  const u = (a.username && String(a.username).trim()) || 'threads_user';
  const n = a.name && String(a.name).trim() ? ` — ${a.name}` : '';
  return `@${u}${n} (${a.id})`;
}

function tikTokAccountLabel(a) {
  const u = (a.username && String(a.username).trim()) || 'tiktok_user';
  const n = a.display_name && String(a.display_name).trim() ? ` — ${a.display_name}` : '';
  return `@${u}${n} (${a.open_id})`;
}

function instagramAccountValue(a) {
  return `${a.facebook_page_id}|${a.instagram_business_account_id}`;
}

async function loadInstagramAccounts() {
  if (form.type !== 'instagram' || !form.social_credential_id) return;
  loadingInstagramAccounts.value = true;
  try {
    const data = await fetchInstagramAccounts(workspaceId.value, Number(form.social_credential_id));
    instagramAccounts.value = data.accounts || [];
    if (form.facebook_page_id && form.instagram_business_account_id && !selectedInstagramCombo.value) {
      selectedInstagramCombo.value = instagramAccountValue({
        facebook_page_id: String(form.facebook_page_id),
        instagram_business_account_id: String(form.instagram_business_account_id),
      });
    }
    if (
      !selectedInstagramCombo.value &&
      instagramAccounts.value.length === 1
    ) {
      selectedInstagramCombo.value = instagramAccountValue(instagramAccounts.value[0]);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load Instagram accounts';
    toast.error(msg);
    instagramAccounts.value = [];
  } finally {
    loadingInstagramAccounts.value = false;
  }
}

async function loadThreadsAccount() {
  if (form.type !== 'threads' || !form.social_credential_id) return;
  loadingThreadsAccount.value = true;
  try {
    const data = await fetchThreadsAccount(workspaceId.value, Number(form.social_credential_id));
    threadsAccounts.value = data.accounts || [];
    if (!selectedThreadsUserId.value && threadsAccounts.value.length === 1) {
      selectedThreadsUserId.value = String(threadsAccounts.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load Threads account';
    toast.error(msg);
    threadsAccounts.value = [];
    selectedThreadsUserId.value = '';
  } finally {
    loadingThreadsAccount.value = false;
  }
}

async function loadTikTokAccount() {
  if (form.type !== 'tiktok' || !form.social_credential_id) return;
  loadingTikTokAccount.value = true;
  try {
    const data = await fetchTikTokAccount(workspaceId.value, Number(form.social_credential_id));
    tiktokAccounts.value = data.accounts || [];
    if (!selectedTikTokOpenId.value && tiktokAccounts.value.length === 1) {
      selectedTikTokOpenId.value = String(tiktokAccounts.value[0].open_id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load TikTok account';
    toast.error(msg);
    tiktokAccounts.value = [];
    selectedTikTokOpenId.value = '';
  } finally {
    loadingTikTokAccount.value = false;
  }
}

async function loadTwitterAccount() {
  if (form.type !== 'twitter' || !form.social_credential_id) return;
  loadingTwitterAccount.value = true;
  try {
    const data = await fetchTwitterAccount(workspaceId.value, Number(form.social_credential_id));
    twitterAccounts.value = data.accounts || [];
    if (!selectedTwitterUserId.value && twitterAccounts.value.length === 1) {
      selectedTwitterUserId.value = String(twitterAccounts.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load X account';
    toast.error(msg);
    twitterAccounts.value = [];
    selectedTwitterUserId.value = '';
  } finally {
    loadingTwitterAccount.value = false;
  }
}

async function loadYoutubeChannels() {
  if (form.type !== 'youtube' || !form.social_credential_id) return;
  if (!youtubeCredentialIds.value.has(String(form.social_credential_id))) return;
  loadingYoutubeChannels.value = true;
  try {
    const data = await fetchYoutubeChannels(workspaceId.value, Number(form.social_credential_id));
    youtubeChannels.value = data.channels || [];
    if (form.youtube_channel_id && !selectedYoutubeChannelId.value) {
      selectedYoutubeChannelId.value = String(form.youtube_channel_id);
    }
    if (!selectedYoutubeChannelId.value && youtubeChannels.value.length === 1) {
      selectedYoutubeChannelId.value = String(youtubeChannels.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load YouTube channels';
    toast.error(msg);
    youtubeChannels.value = [];
  } finally {
    loadingYoutubeChannels.value = false;
  }
}

async function loadFacebookPages() {
  if (form.type !== 'facebook' || !form.social_credential_id) return;
  loadingFacebookPages.value = true;
  try {
    const data = await fetchFacebookPages(workspaceId.value, Number(form.social_credential_id));
    facebookPages.value = data.pages || [];
    // If editing an existing feed, auto-select its saved page.
    if (form.facebook_page_id && !selectedFacebookPageId.value) {
      selectedFacebookPageId.value = String(form.facebook_page_id);
    }
    // If only one page is available, preselect it.
    if (!selectedFacebookPageId.value && facebookPages.value.length === 1) {
      selectedFacebookPageId.value = String(facebookPages.value[0].id);
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to load Facebook Pages';
    toast.error(msg);
    facebookPages.value = [];
  } finally {
    loadingFacebookPages.value = false;
  }
}

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'facebook') return;
    selectedFacebookPageId.value = '';
    form.facebook_page_id = '';
    facebookPages.value = [];
    if (cred) await loadFacebookPages();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'instagram') return;
    if (skipNextInstagramCredReset.value) {
      if (cred) await loadInstagramAccounts();
      return;
    }
    selectedInstagramCombo.value = '';
    form.facebook_page_id = '';
    form.instagram_business_account_id = '';
    form.instagram_username = '';
    instagramAccounts.value = [];
    if (cred) await loadInstagramAccounts();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'threads') return;
    if (skipNextThreadsCredReset.value) {
      if (cred) await loadThreadsAccount();
      return;
    }
    selectedThreadsUserId.value = '';
    threadsAccounts.value = [];
    if (cred) await loadThreadsAccount();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'tiktok') return;
    if (skipNextTikTokCredReset.value) {
      if (cred) await loadTikTokAccount();
      return;
    }
    selectedTikTokOpenId.value = '';
    tiktokAccounts.value = [];
    if (cred) await loadTikTokAccount();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'youtube') return;
    if (cred && !youtubeCredentialIds.value.has(String(cred))) {
      form.social_credential_id = '';
      return;
    }
    if (skipNextYoutubeCredReset.value) {
      if (cred) await loadYoutubeChannels();
      return;
    }
    selectedYoutubeChannelId.value = '';
    form.youtube_channel_id = '';
    youtubeChannels.value = [];
    if (cred) await loadYoutubeChannels();
  },
);

watch(
  () => [form.type, form.social_credential_id],
  async ([type, cred]) => {
    if (type !== 'twitter') return;
    if (skipNextTwitterCredReset.value) {
      if (cred) await loadTwitterAccount();
      return;
    }
    selectedTwitterUserId.value = '';
    twitterAccounts.value = [];
    if (cred) await loadTwitterAccount();
  },
);

watch(
  () => selectedFacebookPageId.value,
  (v) => {
    if (form.type !== 'facebook') return;
    form.facebook_page_id = v ? String(v) : '';
  },
);

watch(
  () => selectedInstagramCombo.value,
  (v) => {
    if (form.type !== 'instagram') return;
    if (!v || typeof v !== 'string') {
      form.facebook_page_id = '';
      form.instagram_business_account_id = '';
      form.instagram_username = '';
      return;
    }
    const i = v.indexOf('|');
    if (i <= 0) {
      form.facebook_page_id = '';
      form.instagram_business_account_id = '';
      form.instagram_username = '';
      return;
    }
    form.facebook_page_id = String(v.slice(0, i));
    form.instagram_business_account_id = String(v.slice(i + 1));
    const match = instagramAccounts.value.find((a) => instagramAccountValue(a) === v);
    const uname = match?.instagram_username != null ? String(match.instagram_username).trim() : '';
    form.instagram_username = uname.replace(/^@/, '');
  },
);

watch(
  () => selectedYoutubeChannelId.value,
  (v) => {
    if (form.type !== 'youtube') return;
    form.youtube_channel_id = v ? String(v) : '';
  },
);

function formatDate(v) {
  try {
    return new Date(v).toLocaleDateString();
  } catch {
    return String(v);
  }
}

onMounted(async () => {
  if (!workspaces.list.length) await workspaces.fetchAll();
  if (!credentials.list.length) await credentials.fetchAll();
  if (!isEdit.value) {
    const firstAvailableType = availableSocialTypes.value[0]?.type || '';
    if (!form.type || !availableSocialTypes.value.some((item) => item.type === form.type)) {
      form.type = firstAvailableType;
    }
  }
  if (isEdit.value && workspaceId.value) {
    await feeds.fetchAll(workspaceId.value);
    const f = feeds.list.find((x) => x.id === Number(feedId.value));
    if (f) {
      if (f.type === 'youtube' && f.social_credential_id) {
        skipNextYoutubeCredReset.value = true;
      }
      if (f.type === 'twitter' && f.social_credential_id) {
        skipNextTwitterCredReset.value = true;
      }
      if (f.type === 'tiktok' && f.social_credential_id) {
        skipNextTikTokCredReset.value = true;
      }
      if (f.type === 'threads' && f.social_credential_id) {
        skipNextThreadsCredReset.value = true;
      }
      if (f.type === 'instagram' && f.social_credential_id) {
        skipNextInstagramCredReset.value = true;
      }
      form.name = f.name;
      form.type = f.type;
      form.source_url = f.source_url || '';
      form.youtube_channel_id = f.youtube_channel_id || '';
      form.facebook_page_id = f.facebook_page_id || '';
      form.instagram_business_account_id = f.instagram_business_account_id || '';
      form.instagram_username = String(f.source_account_label || '')
        .trim()
        .replace(/^@/, '');
      form.social_credential_id = f.social_credential_id ? String(f.social_credential_id) : '';
      if (f.type === 'youtube' && form.social_credential_id && !youtubeCredentialIds.value.has(form.social_credential_id)) {
        form.social_credential_id = '';
      }
      form.auto_publish_new_posts = !!f.auto_publish_new_posts;
      if (f.type === 'facebook' && f.social_credential_id) {
        selectedFacebookPageId.value = String(f.facebook_page_id || '');
        await loadFacebookPages();
      }
      if (f.type === 'instagram' && f.social_credential_id) {
        await loadInstagramAccounts();
        if (form.facebook_page_id && form.instagram_business_account_id) {
          selectedInstagramCombo.value = instagramAccountValue({
            facebook_page_id: String(form.facebook_page_id),
            instagram_business_account_id: String(form.instagram_business_account_id),
          });
        }
        await nextTick();
        skipNextInstagramCredReset.value = false;
      }
      if (f.type === 'youtube' && f.social_credential_id) {
        await nextTick();
        skipNextYoutubeCredReset.value = false;
      }
      if (f.type === 'twitter' && f.social_credential_id) {
        await nextTick();
        skipNextTwitterCredReset.value = false;
      }
      if (f.type === 'tiktok' && f.social_credential_id) {
        await nextTick();
        skipNextTikTokCredReset.value = false;
      }
      if (f.type === 'threads' && f.social_credential_id) {
        await loadThreadsAccount();
        if (f.threads_user_id || f.account_id) {
          selectedThreadsUserId.value = String(f.threads_user_id || f.account_id || '');
        }
        await nextTick();
        skipNextThreadsCredReset.value = false;
      }
    }
  }
});

async function onAutoPublishToggle(nextVal) {
  const enabled = !!nextVal;
  if (!isEdit.value) {
    form.auto_publish_new_posts = enabled;
    return;
  }

  const prev = form.auto_publish_new_posts;
  form.auto_publish_new_posts = enabled;
  syncToggleSaving.value = true;
  try {
    await feeds.patchSyncSettings(workspaceId.value, Number(feedId.value), {
      auto_publish_new_posts: enabled,
    });
  } catch {
    form.auto_publish_new_posts = prev;
  } finally {
    syncToggleSaving.value = false;
  }
}

async function submit() {
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      type: form.type,
      source_url:
        form.type === 'youtube' ||
        form.type === 'facebook' ||
        form.type === 'instagram' ||
        form.type === 'twitter' ||
        form.type === 'tiktok' ||
        form.type === 'threads'
          ? ''
          : (form.source_url || ''),
      youtube_channel_id: form.type === 'youtube' ? form.youtube_channel_id : null,
      facebook_page_id:
        form.type === 'facebook' || form.type === 'instagram' ? form.facebook_page_id : null,
      instagram_business_account_id: form.type === 'instagram' ? form.instagram_business_account_id : null,
      instagram_username:
        form.type === 'instagram' && String(form.instagram_username || '').trim()
          ? String(form.instagram_username).trim().replace(/^@/, '')
          : null,
      youtube_display_label: form.type === 'youtube' ? youtubePublicLabelForSubmit() : null,
      social_credential_id:
        (form.type === 'youtube' ||
          form.type === 'facebook' ||
          form.type === 'instagram' ||
          form.type === 'twitter' ||
          form.type === 'tiktok' ||
          form.type === 'threads') &&
        form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    };
    if (!isEdit.value) {
      payload.auto_publish_new_posts = !!form.auto_publish_new_posts;
    }
    let savedFeedId = feedId.value;
    if (isEdit.value) {
      const updated = await feeds.update(workspaceId.value, Number(feedId.value), payload);
      savedFeedId = updated.id;
    } else {
      const created = await feeds.create(workspaceId.value, payload);
      savedFeedId = created.id;
    }
    router.push(`/workspaces/${workspaceId.value}/feeds/${savedFeedId}/curate`);
  } catch {
    // error in store
  } finally {
    saving.value = false;
  }
}

async function testConnection() {
  testingConnection.value = true;
  try {
    await testYoutube(workspaceId.value, {
      social_credential_id:
        form.type === 'youtube' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      youtube_channel_id: form.type === 'youtube' ? form.youtube_channel_id : null,
    });
    toast.success('YouTube connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'YouTube connection is healthy and ready for sync.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test YouTube connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingConnection.value = false;
  }
}

async function testFacebookConnection() {
  testingFacebook.value = true;
  try {
    await testFacebook(workspaceId.value, {
      social_credential_id:
        form.type === 'facebook' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      facebook_page_id: form.type === 'facebook' ? form.facebook_page_id : null,
    });
    toast.success('Facebook connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'Facebook page connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Facebook connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingFacebook.value = false;
  }
}

async function testInstagramConnection() {
  testingInstagram.value = true;
  try {
    await testInstagram(workspaceId.value, {
      social_credential_id:
        form.type === 'instagram' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
      facebook_page_id: form.type === 'instagram' ? form.facebook_page_id : null,
      instagram_business_account_id:
        form.type === 'instagram' ? form.instagram_business_account_id : null,
    });
    toast.success('Instagram connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'Instagram media endpoint verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Instagram connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingInstagram.value = false;
  }
}

async function testTwitterConnection() {
  testingTwitter.value = true;
  try {
    await testTwitter(workspaceId.value, {
      social_credential_id:
        form.type === 'twitter' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    });
    toast.success('X connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'X account connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test X connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingTwitter.value = false;
  }
}

async function testThreadsConnection() {
  testingThreads.value = true;
  try {
    await testThreads(workspaceId.value, {
      social_credential_id:
        form.type === 'threads' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    });
    toast.success('Threads connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'Threads connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test Threads connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingThreads.value = false;
  }
}

async function testTikTokConnection() {
  testingTikTok.value = true;
  try {
    await testTikTok(workspaceId.value, {
      social_credential_id:
        form.type === 'tiktok' && form.social_credential_id
          ? Number(form.social_credential_id)
          : null,
    });
    toast.success('TikTok connection successful.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'TikTok connection verified.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test TikTok connection';
    toast.error(msg);
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingTikTok.value = false;
  }
}

async function testRss() {
  testingRss.value = true;
  rssTestSummary.value = null;
  try {
    const data = await testRssFeed(workspaceId.value, form.source_url);
    rssTestSummary.value = {
      feed_title: data.feed_title || null,
      item_count: data.item_count ?? null,
      sample_title: data.sample_title || null,
    };
    toast.success(data.message || 'RSS feed looks good.');
    connectionStatus.kind = 'ok';
    connectionStatus.message = 'RSS feed URL resolved and parsed successfully.';
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to test RSS';
    toast.error(msg);
    rssTestSummary.value = null;
    connectionStatus.kind = 'error';
    connectionStatus.message = msg;
  } finally {
    testingRss.value = false;
  }
}

watch(
  () => form.type,
  (t) => {
    if (t !== 'rss') rssTestSummary.value = null;
    connectionStatus.kind = '';
    connectionStatus.message = '';
  },
);

watch(
  () => form.source_url,
  () => {
    if (form.type === 'rss') rssTestSummary.value = null;
    if (form.type === 'rss') {
      connectionStatus.kind = '';
      connectionStatus.message = '';
    }
  },
);
</script>

<style scoped>
.feed-form-shell {
  position: relative;
}

.feed-form-hero {
  background:
    radial-gradient(880px 260px at -8% -48%, rgba(30, 58, 138, 0.06), transparent 65%),
    radial-gradient(760px 240px at 110% -40%, rgba(30, 58, 138, 0.05), transparent 62%),
    linear-gradient(170deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
}

.feed-section-kicker {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}

.feed-hero-intro {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  margin-bottom: 0.9rem;
}

.feed-hero-title {
  font-size: 1.05rem;
  line-height: 1.2;
  font-weight: 650;
  color: #0f172a;
}

.feed-hero-copy {
  margin-top: 0.3rem;
  font-size: 0.76rem;
  color: #64748b;
}

.feed-hero-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.feed-hero-badge,
.feed-hero-current {
  display: inline-flex;
  align-items: center;
  min-height: 1.9rem;
  padding: 0.3rem 0.65rem;
  border-radius: 999px;
  border: 1px solid rgba(226, 232, 240, 0.92);
  background: rgba(255, 255, 255, 0.82);
  font-size: 0.72rem;
}

.feed-hero-badge {
  font-weight: 700;
  color: #0f172a;
}

.feed-hero-current {
  color: #64748b;
}

.feed-type-card {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.875rem;
  background: #fff;
  padding: 0.55rem 0.65rem;
  transition: all 0.18s ease;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.feed-type-card:hover {
  border-color: rgba(30, 58, 138, 0.2);
  background: rgba(239, 246, 255, 0.5);
}

.feed-type-card--active {
  border-color: rgba(30, 58, 138, 0.35);
  background: rgba(239, 246, 255, 0.9);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.feed-type-icon-wrap {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.feed-form-layout {
  display: grid;
  gap: 1rem;
}

.feed-overview-panel {
  display: grid;
  gap: 1rem;
}

.feed-overview-hero {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  padding: 1rem;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.82);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.feed-overview-hero__icon {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.feed-overview-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.feed-overview-stat {
  display: grid;
  gap: 0.45rem;
  padding: 0.85rem 0.95rem;
  border: 1px solid #e6ebf2;
  border-radius: 0.95rem;
  background: rgba(255, 255, 255, 0.82);
}

.feed-overview-stat--wide {
  grid-column: 1 / -1;
}

.feed-overview-stat__label {
  font-size: 0.68rem;
  line-height: 1;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #94a3b8;
}

.feed-overview-stat__value {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
}

.feed-overview-note {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.96);
  padding: 0.95rem;
}

.feed-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  width: fit-content;
  border: 1px solid #e6ebf2;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.68rem;
  padding: 0.28rem 0.55rem;
  border-radius: 999px;
}

.feed-status-pill__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: #94a3b8;
}

.feed-status-pill--ok {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(236, 253, 245, 0.92);
  color: rgb(6 95 70);
}

.feed-status-pill--ok .feed-status-pill__dot {
  background: rgb(5 150 105);
}

.feed-status-pill--error {
  border-color: rgba(244, 63, 94, 0.28);
  background: rgba(255, 241, 242, 0.94);
  color: rgb(190 24 93);
}

.feed-status-pill--error .feed-status-pill__dot {
  background: rgb(225 29 72);
}

.feed-section-panel {
  border: 1px solid #e6ebf2;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.9);
  padding: 1rem;
  display: grid;
  gap: 1rem;
}

.feed-section-panel__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.feed-provider-stack {
  display: grid;
  gap: 0.85rem;
}

.feed-field-card {
  border: 1px solid #e6ebf2;
  border-radius: 0.95rem;
  background: #fff;
  padding: 0.9rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.feed-inline-select {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
}

.feed-inline-select > :first-child {
  flex: 1 1 16rem;
  min-width: 0;
}

.feed-section-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
}

.feed-summary-card {
  border: 1px solid #e2e8f0;
  border-radius: 0.95rem;
  background: rgba(248, 250, 252, 0.82);
  padding: 0.9rem;
  font-size: 0.72rem;
  color: #334155;
  display: grid;
  gap: 0.35rem;
}

.feed-error-text {
  margin-top: 0.45rem;
  font-size: 0.72rem;
  color: #dc2626;
}

.setup-step {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid rgba(226, 232, 240, 0.9);
  background: rgba(255, 255, 255, 0.72);
  border-radius: 999px;
  padding: 0.28rem 0.62rem;
  color: rgb(100 116 139);
  font-size: 0.68rem;
}

.setup-step__dot {
  width: 1.05rem;
  height: 1.05rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(226, 232, 240, 0.88);
  color: rgb(71 85 105);
  font-size: 0.62rem;
  font-weight: 600;
}

.setup-step--active {
  border-color: rgba(30, 58, 138, 0.3);
  color: #1e3a8a;
  background: rgba(239, 246, 255, 0.9);
}

.setup-step--active .setup-step__dot {
  background: #1e3a8a;
  color: white;
}

.setup-step-divider {
  width: 1rem;
  height: 1px;
  background: rgba(203, 213, 225, 0.9);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.18s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

@media (min-width: 1024px) {
  .feed-form-layout {
    grid-template-columns: minmax(18rem, 22rem) minmax(0, 1fr);
    align-items: start;
  }

  .feed-overview-panel {
    position: sticky;
    top: 1rem;
  }
}
</style>
