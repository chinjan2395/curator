<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <AppCard class="p-6 max-w-md w-full text-center space-y-3">
      <p v-if="loading" class="text-sm text-slate-600">Verifying your email…</p>
      <p v-else-if="success" class="text-sm text-emerald-700">{{ message }}</p>
      <p v-else class="text-sm text-red-600">{{ message }}</p>
      <router-link to="/" class="text-sm text-blue-600 hover:underline">Go to dashboard</router-link>
    </AppCard>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { AppCard } from '../components/ui';

const route = useRoute();
const auth = useAuthStore();
const loading = ref(true);
const success = ref(false);
const message = ref('');

onMounted(async () => {
  try {
    await axios.post('/api/auth/email/verify', {
      id: route.query.id,
      hash: route.query.hash,
    });
    success.value = true;
    message.value = 'Email verified successfully.';
    await auth.fetchUser();
  } catch (err) {
    message.value = err.response?.data?.message || 'Verification failed.';
  } finally {
    loading.value = false;
  }
});
</script>
