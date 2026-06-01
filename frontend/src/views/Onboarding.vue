<template>
  <div class="min-h-screen flex items-center justify-center p-6" style="background: linear-gradient(160deg, #1e3a8a 0%, #172554 100%)">
    <AppCard class="w-full max-w-lg p-6 space-y-4">
      <AppTitle size="lg">Welcome to Curator</AppTitle>
      <AppText tone="secondary">Complete your profile to personalize AI content and recommendations.</AppText>
      <AppFormField label="Industry">
        <AppInput v-model="form.industry" placeholder="e.g. SaaS, E-commerce" />
      </AppFormField>
      <AppFormField label="Target audience">
        <AppInput v-model="form.target_audience" type="textarea" placeholder="Who are you trying to reach?" />
      </AppFormField>
      <AppFormField label="Brand voice">
        <AppInput v-model="form.brand_voice" type="textarea" placeholder="Professional, casual, witty..." />
      </AppFormField>
      <AppButton variant="primary" :disabled="saving" @click="save">Continue to dashboard</AppButton>
    </AppCard>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { AppButton, AppCard, AppFormField, AppInput, AppText, AppTitle } from '../components/ui';

const router = useRouter();
const auth = useAuthStore();
const saving = ref(false);
const form = reactive({
  industry: auth.user?.industry || '',
  target_audience: auth.user?.target_audience || '',
  brand_voice: auth.user?.brand_voice || '',
});

async function save() {
  saving.value = true;
  try {
    await axios.put('/api/auth/profile', { ...form, is_onboarded: true });
    await auth.fetchUser();
    router.push('/');
  } finally {
    saving.value = false;
  }
}
</script>
