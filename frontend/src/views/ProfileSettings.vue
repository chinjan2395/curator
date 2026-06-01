<template>
  <div class="space-y-4 max-w-2xl">
    <AppPageHeader title="Profile & account" subtitle="Manage your profile and GDPR data rights." icon="user" />
    <AppCard class="p-4 space-y-3">
      <AppFormField label="Name"><AppInput v-model="form.name" /></AppFormField>
      <AppFormField label="Avatar URL"><AppInput v-model="form.avatar_url" /></AppFormField>
      <AppFormField label="Industry"><AppInput v-model="form.industry" /></AppFormField>
      <AppFormField label="Target audience"><AppInput v-model="form.target_audience" type="textarea" /></AppFormField>
      <AppFormField label="Brand voice"><AppInput v-model="form.brand_voice" type="textarea" /></AppFormField>
      <AppButton variant="primary" @click="saveProfile">Save profile</AppButton>
    </AppCard>
    <AppCard class="p-4 space-y-3">
      <AppTitle size="sm">Data export</AppTitle>
      <AppButton variant="secondary" @click="exportData">Download my data</AppButton>
    </AppCard>
    <AppCard class="p-4 space-y-3 border-rose-200">
      <AppTitle size="sm">Delete account</AppTitle>
      <AppFormField label="Password"><AppInput v-model="deleteForm.password" type="password" /></AppFormField>
      <label class="flex items-center gap-2 text-sm"><input v-model="deleteForm.confirm" type="checkbox" /> I understand this is permanent</label>
      <AppButton variant="danger" @click="deleteAccount">Delete my account</AppButton>
    </AppCard>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { AppButton, AppCard, AppFormField, AppInput, AppTitle } from '../components/ui';
import { AppPageHeader } from '../components/layout';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const form = reactive({
  name: auth.user?.name || '',
  avatar_url: auth.user?.avatar_url || '',
  industry: auth.user?.industry || '',
  target_audience: auth.user?.target_audience || '',
  brand_voice: auth.user?.brand_voice || '',
});
const deleteForm = reactive({ password: '', confirm: false });

async function saveProfile() {
  await axios.put('/api/auth/profile', form);
  await auth.fetchUser();
  toast.success('Profile saved');
}

async function exportData() {
  const { data } = await axios.get('/api/auth/export');
  const blob = new Blob([JSON.stringify(data.data || data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'account-export.json';
  a.click();
  URL.revokeObjectURL(url);
}

async function deleteAccount() {
  await axios.delete('/api/auth/account', { data: deleteForm });
  await auth.logout();
  router.push('/login');
}
</script>
