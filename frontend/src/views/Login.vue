<template>
  <AuthLayout title="Sign in" :is-login="true">
    <div class="space-y-4">
      <!-- Social login (providers come from server .env; hidden until loaded) -->
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
            <span class="text-2xs text-slate-400">or continue with email</span>
            <div class="flex-1 border-t border-slate-200" />
          </div>
        </template>
      </template>

      <form @submit.prevent="login" class="space-y-4">
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
            autocomplete="current-password"
          />
        </div>
        <div v-if="displayError" class="text-2xs text-red-600">
          {{ displayError }}
        </div>
        <button type="submit" class="btn-primary mt-2">
          Sign in
        </button>
        <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
          <router-link to="/forgot-password" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
            Forgot password?
          </router-link>
        </p>
      </form>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import AuthLayout from '../layouts/AuthLayout.vue';
import { fetchEnabledSocialLoginProviders, socialLoginButtonGridClass } from '../data/socialLoginProviders';

const email = ref('');
const password = ref('');
const urlError = ref('');
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const displayError = computed(() => auth.error || urlError.value);

const socialProviders = ref([]);
const socialLoginNotice = ref('');
const socialLoginLoading = ref(true);

const socialLoginGridClass = computed(() => socialLoginButtonGridClass(socialProviders.value.length));

onMounted(async () => {
  if (route.query.error) {
    const msgs = {
      social_auth_failed: 'Social sign-in failed. Please try again.',
      email_required: 'Your social account has no public email. Try a different method.',
      facebook_not_configured: 'Facebook login is not configured on the server (missing App ID).',
      facebook_invalid_app_id:
        'Facebook App ID in server configuration is invalid. Use the numeric App ID from Meta → App settings → Basic, not the App Secret.',
      google_not_configured: 'Google login is not configured on the server.',
      github_not_configured: 'GitHub login is not configured on the server.',
      twitter_not_configured: 'Twitter login is not configured.',
      unsupported_provider: 'That sign-in method is not supported.',
      token_exchange_failed: 'Authentication failed. Please try again.',
      user_fetch_failed: 'Could not fetch your account info. Please try again.',
      invalid_state: 'Invalid OAuth state. Please try again.',
      account_deactivated: 'Your account has been deactivated. Please contact support.',
    };
    urlError.value = msgs[route.query.error] || 'Sign-in failed. Please try again.';
  }

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

function login() {
  urlError.value = '';
  auth.login(email.value, password.value);
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
