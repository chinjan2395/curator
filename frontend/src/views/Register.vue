<template>
  <AuthLayout title="Create account" :is-login="false">
    <form @submit.prevent="register" class="space-y-4">
      <div>
        <label class="label-pro">Name</label>
        <input
          v-model="name"
          type="text"
          class="input-pro"
          placeholder="Your name"
          required
          autocomplete="name"
        />
      </div>
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
        <label class="label-pro">Password</label>
        <input
          v-model="password"
          type="password"
          class="input-pro"
          placeholder="••••••••"
          required
          autocomplete="new-password"
        />
      </div>
      <div v-if="auth.error" class="text-2xs text-rose-600 bg-rose-50 rounded-btn px-3 py-2">
        {{ auth.error }}
      </div>
      <button type="submit" class="btn-primary mt-2">
        Create account
      </button>
      <p class="text-2xs text-one-sub text-center pt-2 border-t border-one-divider">
        Use a work email so your team can recognize the account.
      </p>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AuthLayout from '../layouts/AuthLayout.vue';

const name = ref('');
const email = ref('');
const password = ref('');
const auth = useAuthStore();
const router = useRouter();

function register() {
  auth.register(name.value, email.value, password.value);
}

watch(() => auth.token, (token) => {
  if (token) router.push('/');
});
</script>
