<template>
  <AuthLayout title="Set new password" :is-login="true">
    <div v-if="success" class="space-y-4">
      <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-2xs text-emerald-700">
        {{ success }}
      </div>
      <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
        <router-link to="/login" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
          Sign in with your new password
        </router-link>
      </p>
    </div>
    <form v-else @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="label-pro">Email</label>
        <input
          v-model="email"
          type="email"
          class="input-pro"
          placeholder="you@example.com"
          required
          autocomplete="email"
        />
      </div>
      <div>
        <label class="label-pro">New password</label>
        <input
          v-model="password"
          type="password"
          class="input-pro"
          placeholder="••••••••"
          required
          autocomplete="new-password"
          minlength="8"
        />
      </div>
      <div>
        <label class="label-pro">Confirm password</label>
        <input
          v-model="passwordConfirmation"
          type="password"
          class="input-pro"
          placeholder="••••••••"
          required
          autocomplete="new-password"
          minlength="8"
        />
      </div>
      <div v-if="error" class="text-2xs text-red-600">{{ error }}</div>
      <button type="submit" class="btn-primary mt-2" :disabled="loading">
        {{ loading ? 'Resetting…' : 'Reset password' }}
      </button>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import AuthLayout from '../layouts/AuthLayout.vue';

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
    const res = await axios.post('/api/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    success.value = res.data.message;
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
