<template>
  <AuthLayout title="Create account" :is-login="false">
    <div class="space-y-4">
      <!-- Social signup (same env-backed list as login) -->
      <div v-if="socialLoginLoading" class="flex justify-center py-5">
        <span class="inline-block w-5 h-5 border-2 border-slate-200 border-t-slate-600 rounded-full animate-spin" aria-hidden="true" />
      </div>
      <template v-else>
        <p
          v-if="socialLoginNotice"
          class="text-2xs text-amber-800 bg-amber-50 border border-amber-200/80 rounded-lg px-3 py-2"
        >
          {{ socialLoginNotice }}
        </p>

        <template v-if="socialProviders.length">
          <div :class="socialLoginGridClass">
            <a
              v-for="p in socialProviders"
              :key="p.id"
              :href="`/api/auth/social/${p.id}`"
              class="social-btn"
            >
              <span class="social-icon" v-html="p.icon" />
              <span>{{ p.label }}</span>
            </a>
          </div>

          <div class="flex items-center gap-2">
            <div class="flex-1 border-t border-slate-200" />
            <span class="text-2xs text-slate-400">or sign up with email</span>
            <div class="flex-1 border-t border-slate-200" />
          </div>
        </template>
      </template>

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
            minlength="8"
          />
        </div>
        <div v-if="auth.error" class="text-2xs text-red-600">
          {{ auth.error }}
        </div>
        <button type="submit" class="btn-primary mt-2">
          Create account
        </button>
        <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
          Use a work email so your team can recognize the account.
        </p>
      </form>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AuthLayout from '../layouts/AuthLayout.vue';
import { fetchEnabledSocialLoginProviders, socialLoginButtonGridClass } from '../data/socialLoginProviders';

const name = ref('');
const email = ref('');
const password = ref('');
const auth = useAuthStore();
const router = useRouter();

const socialProviders = ref([]);
const socialLoginNotice = ref('');
const socialLoginLoading = ref(true);

const socialLoginGridClass = computed(() => socialLoginButtonGridClass(socialProviders.value.length));

onMounted(async () => {
  try {
    const { providers, notice } = await fetchEnabledSocialLoginProviders();
    socialProviders.value = providers;
    socialLoginNotice.value = notice;
  } catch {
    socialProviders.value = [];
    socialLoginNotice.value = '';
  } finally {
    socialLoginLoading.value = false;
  }
});

function register() {
  auth.register(name.value, email.value, password.value);
}

watch(() => auth.token, (token) => {
  if (token) router.push('/');
});
</script>

<style scoped>
.social-btn {
  @apply flex w-full min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-2xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-300;
}
.social-icon {
  @apply flex-shrink-0 flex items-center;
}
</style>
