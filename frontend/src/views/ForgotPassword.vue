<template>
  <AuthLayout title="Reset password" :is-login="true">
    <div v-if="success" class="space-y-4">
      <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-2xs text-emerald-700">
        {{ success }}
      </div>
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
      <div v-if="error" class="text-2xs text-red-600">{{ error }}</div>
      <button type="submit" class="btn-primary mt-2" :disabled="loading">
        {{ loading ? 'Sending…' : 'Send reset link' }}
      </button>
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
import axios from 'axios';
import AuthLayout from '../layouts/AuthLayout.vue';

const email = ref('');
const loading = ref(false);
const success = ref('');
const error = ref('');

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    const res = await axios.post('/api/forgot-password', { email: email.value });
    success.value = res.data.message;
  } catch (err) {
    error.value = err.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>
