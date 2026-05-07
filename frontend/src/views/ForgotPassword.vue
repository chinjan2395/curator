<template>
  <AuthLayout title="Reset password" :is-login="true">
    <div v-if="success" class="space-y-4">
      <AppAlert variant="success">{{ success }}</AppAlert>
      <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
        <router-link to="/login" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
          Back to sign in
        </router-link>
      </p>
    </div>
    <form v-else @submit.prevent="submit" class="space-y-4">
      <p class="text-2xs text-slate-500 -mt-2">
        Enter your email and we'll send you a link to reset your password.
      </p>
      <AppFormField label="Email">
        <AppInput
          v-model="email"
          type="email"
          placeholder="you@example.com"
        />
      </AppFormField>
      <AppAlert v-if="error" variant="danger">{{ error }}</AppAlert>
      <AppButton type="submit" class="mt-2" :loading="loading">
        {{ loading ? 'Sending…' : 'Send reset link' }}
      </AppButton>
      <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
        <router-link to="/login" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
          Back to sign in
        </router-link>
      </p>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import { AppAlert, AppButton, AppFormField, AppInput } from '../components/ui';
import { requestPasswordResetLink } from '../composables/usePasswordReset';

const email = ref('');
const loading = ref(false);
const success = ref('');
const error = ref('');

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    const data = await requestPasswordResetLink(email.value);
    success.value = data.message;
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
