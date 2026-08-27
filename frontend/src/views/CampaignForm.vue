<template>
  <div class="space-y-4">
    <AppPageHeader
      title="New campaign"
      subtitle="Define your briefing for AI content generation."
      icon="megaphone"
      :breadcrumb="['Campaigns', 'New campaign']"
    >
      <template #actions>
        <AppButton
          variant="secondary"
          to="/campaigns"
        >
          Cancel
        </AppButton>
        <AppButton
          variant="primary"
          type="submit"
          form="campaign-form"
          :disabled="saving || !form.name.trim()"
        >
          <AppIcon
            name="sparkles"
            class="w-3.5 h-3.5 mr-1.5"
          />
          {{ saving ? 'Creating…' : 'Create campaign' }}
        </AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="ai" />

    <AppCard
      padding="none"
      class="cf-brief-card"
    >
      <div class="cf-config-layout p-4">
        <aside class="cf-overview-panel">
          <div class="cf-hero">
            <div class="cf-hero-icon">
              <AppIcon
                name="megaphone"
                class="w-4 h-4"
              />
            </div>
            <div class="min-w-0 flex-1">
              <p class="cf-section-kicker">
                Preview
              </p>
              <p class="text-sm-pro font-semibold text-slate-800 truncate">
                <span v-if="form.name.trim()">{{ form.name }}</span>
                <span
                  v-else
                  class="italic text-slate-400"
                >Untitled campaign</span>
              </p>
              <p class="text-2xs text-slate-500 mt-1">
                {{ form.tone.trim() ? form.tone : 'No tone set' }} &middot;
                {{ parsedPlatforms.length }} platform{{ parsedPlatforms.length === 1 ? '' : 's' }}
              </p>
            </div>
          </div>

          <div class="cf-overview-grid">
            <div class="cf-overview-stat">
              <span class="cf-overview-stat__label">Status</span>
              <div class="cf-status-pill">
                <span class="cf-status-pill__dot" />
                Draft
              </div>
            </div>
            <div class="cf-overview-stat">
              <span class="cf-overview-stat__label">Tone</span>
              <span
                v-if="form.tone.trim()"
                class="cf-overview-stat__value"
              >{{ form.tone }}</span>
              <span
                v-else
                class="cf-overview-stat__value cf-overview-stat__value--muted"
              >Not set</span>
            </div>
            <div class="cf-overview-stat cf-overview-stat--wide">
              <span class="cf-overview-stat__label">Platforms</span>
              <div
                v-if="parsedPlatforms.length"
                class="flex flex-wrap gap-1.5"
              >
                <SocialPlatformLabel
                  v-for="p in parsedPlatforms"
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
          id="campaign-form"
          class="space-y-4"
          @submit.prevent="submit"
        >
          <!-- Basics -->
          <div class="cf-form-panel">
            <div class="cf-form-panel__header">
              <div
                class="cf-panel-icon"
                style="background:#eff6ff;color:#3b82f6"
              >
                <AppIcon
                  name="edit"
                  class="w-3.5 h-3.5"
                />
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
                <AppFormField
                  label="Campaign name"
                  required
                >
                  <AppInput
                    v-model="form.name"
                    type="text"
                    placeholder="Summer product launch"
                    required
                  />
                </AppFormField>
              </div>
              <div class="cf-field-card">
                <AppFormField label="Tone">
                  <AppInput
                    v-model="form.tone"
                    type="text"
                    placeholder="Professional, playful, urgent…"
                  />
                </AppFormField>
              </div>
            </div>

            <div class="cf-field-card">
              <AppFormField
                label="Platforms"
                hint="Comma-separated: instagram, linkedin, tiktok"
              >
                <AppInput
                  v-model="platformsText"
                  type="text"
                  placeholder="instagram, twitter, facebook"
                />
              </AppFormField>
            </div>

            <PlatformPublishGuide
              v-if="parsedPlatforms.length"
              :platforms="parsedPlatforms"
              variant="compact"
              title="What you can publish"
              subtitle="Based on each platform's official API and Curator's native publisher."
            />
          </div>

          <!-- Message -->
          <div class="cf-form-panel">
            <div class="cf-form-panel__header">
              <div
                class="cf-panel-icon"
                style="background:#fdf4ff;color:#9333ea"
              >
                <AppIcon
                  name="send"
                  class="w-3.5 h-3.5"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="cf-section-kicker">
                  Step 2
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
                    v-model="form.product_info"
                    type="textarea"
                    :rows="4"
                    placeholder="What are you promoting? Describe the product or service."
                  />
                </AppFormField>
              </div>
              <div class="cf-field-card">
                <AppFormField label="Description (optional)">
                  <AppInput
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    placeholder="Launch notes, context, or extra details for the AI."
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
                <AppIcon
                  name="users"
                  class="w-3.5 h-3.5"
                />
              </div>
              <div class="min-w-0 flex-1">
                <p class="cf-section-kicker">
                  Step 3
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
                <AppFormField
                  label="Target audience"
                  hint="One per line or comma-separated"
                >
                  <AppInput
                    v-model="targetAudienceText"
                    type="textarea"
                    :rows="4"
                    placeholder="e.g. Small business owners, marketers…"
                  />
                </AppFormField>
              </div>
              <div class="cf-field-card">
                <AppFormField
                  label="Goals"
                  hint="One per line or comma-separated"
                >
                  <AppInput
                    v-model="goalsText"
                    type="textarea"
                    :rows="4"
                    placeholder="e.g. Drive sign-ups, increase brand awareness…"
                  />
                </AppFormField>
              </div>
            </div>
          </div>
        </form>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useCampaignsStore } from '../stores/campaigns';
import { AppButton, AppCard, AppFormField, AppIcon, AppInput } from '../components/ui';
import { AppPageHeader } from '../components/layout';
import CapabilityBanner from '../components/CapabilityBanner.vue';
import PlatformPublishGuide from '../components/PlatformPublishGuide.vue';
import SocialPlatformLabel from '../components/SocialPlatformLabel.vue';

const router = useRouter();
const store = useCampaignsStore();
const platformsText = ref('instagram,twitter,facebook');
const targetAudienceText = ref('');
const goalsText = ref('');
const saving = ref(false);
const form = reactive({
  name: '',
  product_info: '',
  tone: 'professional',
  description: '',
});

const parsedPlatforms = computed(() =>
  splitList(platformsText.value).filter(Boolean),
);

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

async function submit() {
  if (!form.name.trim()) return;

  saving.value = true;
  try {
    const campaign = await store.create({
      name: form.name.trim(),
      description: nullableString(form.description),
      product_info: nullableString(form.product_info),
      tone: nullableString(form.tone),
      target_audience: normalizedListValue(targetAudienceText.value),
      goals: normalizedListValue(goalsText.value),
      platforms: normalizedListValue(platformsText.value),
    });
    router.push(`/campaigns/${campaign.id}`);
  } catch {
    // toast handled by store / interceptor
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.cf-brief-card {
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
</style>
