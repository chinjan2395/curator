<template>
  <div class="space-y-4">
    <AppPageHeader
      title="New campaign"
      subtitle="Define your briefing for AI content generation."
      icon="megaphone"
      :breadcrumb="['Campaigns', 'New campaign']"
    >
      <template #actions>
        <AppButton variant="secondary" to="/campaigns">Cancel</AppButton>
        <AppButton
          variant="primary"
          type="submit"
          form="campaign-form"
          :disabled="saving || !form.name.trim()"
        >
          <AppIcon name="sparkles" class="w-3.5 h-3.5 mr-1.5" />
          {{ saving ? 'Creating…' : 'Create campaign' }}
        </AppButton>
      </template>
    </AppPageHeader>

    <CapabilityBanner context="ai" />

    <AppCard padding="none" class="cf-brief-card">
      <form id="campaign-form" class="space-y-3 p-4" @submit.prevent="submit">

        <!-- Basics -->
        <div class="cf-panel">
          <div class="cf-panel-header">
            <div class="cf-panel-icon" style="background:#eff6ff;color:#3b82f6">
              <AppIcon name="edit" class="w-3.5 h-3.5" />
            </div>
            <p class="text-sm font-semibold text-slate-800">Basics</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
            <AppFormField label="Campaign name" required>
              <AppInput v-model="form.name" type="text" placeholder="Summer product launch" required />
            </AppFormField>
            <AppFormField label="Tone">
              <AppInput v-model="form.tone" type="text" placeholder="Professional, playful, urgent…" />
            </AppFormField>
          </div>
          <AppFormField label="Platforms" hint="Comma-separated: instagram, linkedin, tiktok" class="mt-3">
            <AppInput v-model="platformsText" type="text" placeholder="instagram, twitter, facebook" />
          </AppFormField>
          <div v-if="parsedPlatforms.length" class="flex flex-wrap gap-1.5 mt-2">
            <SocialPlatformLabel
              v-for="p in parsedPlatforms"
              :key="p"
              :type="p"
              variant="pill"
              size="sm"
            />
          </div>
        </div>

        <!-- Message -->
        <div class="cf-panel">
          <div class="cf-panel-header">
            <div class="cf-panel-icon" style="background:#fdf4ff;color:#9333ea">
              <AppIcon name="send" class="w-3.5 h-3.5" />
            </div>
            <p class="text-sm font-semibold text-slate-800">Message</p>
          </div>
          <div class="grid grid-cols-1 gap-3 mt-3">
            <AppFormField label="Product / service">
              <AppInput
                v-model="form.product_info"
                type="textarea"
                :rows="4"
                placeholder="What are you promoting? Describe the product or service."
              />
            </AppFormField>
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

        <!-- Audience & Goals -->
        <div class="cf-panel">
          <div class="cf-panel-header">
            <div class="cf-panel-icon" style="background:#f0fdf4;color:#16a34a">
              <AppIcon name="users" class="w-3.5 h-3.5" />
            </div>
            <p class="text-sm font-semibold text-slate-800">Audience &amp; goals</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
            <AppFormField label="Target audience" hint="One per line or comma-separated">
              <AppInput v-model="targetAudienceText" type="textarea" :rows="4" placeholder="e.g. Small business owners, marketers…" />
            </AppFormField>
            <AppFormField label="Goals" hint="One per line or comma-separated">
              <AppInput v-model="goalsText" type="textarea" :rows="4" placeholder="e.g. Drive sign-ups, increase brand awareness…" />
            </AppFormField>
          </div>
        </div>

      </form>
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

.cf-panel {
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
</style>
