<template>
  <AuthLayout title="Set new password" :is-login="true">
    <div v-if="success" class="space-y-4">
      <AppAlert variant="success">{{ success }}</AppAlert>
      <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
        <router-link to="/login" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
          Sign in with your new password
        </router-link>
      </p>
    </div>
    <form v-else @submit.prevent="submit" class="space-y-4">
      <AppFormField label="Email">
        <AppInput
          v-model="email"
          type="email"
          placeholder="you@example.com"
        />
      </AppFormField>
      <AppFormField label="New password">
        <AppInput
          v-model="password"
          type="password"
          placeholder="••••••••"
        />
      </AppFormField>
      <AppFormField label="Confirm password">
        <AppInput
          v-model="passwordConfirmation"
          type="password"
          placeholder="••••••••"
        />
      </AppFormField>
      <AppAlert v-if="error" variant="danger">{{ error }}</AppAlert>
      <AppButton type="submit" class="mt-2" :loading="loading">
        {{ loading ? 'Resetting…' : 'Reset password' }}
      </AppButton>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AuthLayout from '../layouts/AuthLayout.vue';
import { AppAlert, AppButton, AppFormField, AppInput } from '../components/ui';
import { resetPassword } from '../composables/usePasswordReset';

const route = useRoute();
const token = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const success = ref('');
const error = ref('');

onMounted(() => {
  token.value = (route.query.token) || '';
  email.value = (route.query.email) || '';
});

async function submit() {
  if (password.value !== passwordConfirmation.value) {
    error.value = 'Passwords do not match.';
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    const data = await resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    success.value = data.message;
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
