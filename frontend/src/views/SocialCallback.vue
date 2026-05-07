<template>
  <div class="min-h-screen flex items-center justify-center">
    <div v-if="error" class="text-center space-y-3">
      <p class="text-sm text-red-600">{{ error }}</p>
      <router-link to="/login" class="text-sm text-slate-600 underline">Back to sign in</router-link>
    </div>
    <div v-else class="text-sm text-slate-500">Signing you in…</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const error = ref(null);

onMounted(async () => {
  const token = route.query.token;
  if (!token) {
    error.value = 'Authentication failed. Please try again.';
    return;
  }
  auth.token = token;
  localStorage.setItem('token', token);
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  await auth.fetchUser();
  if (auth.user) {
    router.push('/');
  } else {
    error.value = 'Could not load your account. Please try signing in again.';
  }
});
</script>
