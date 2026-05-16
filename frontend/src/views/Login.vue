<template>
  <AuthLayout title="Sign in" :is-login="true">
    <AppStack direction="vertical" spacing="md">
      <AppLoader v-if="socialLoginLoading" size="sm" />

      <template v-else>
        <AppAlert v-if="socialLoginNotice" variant="warning">{{ socialLoginNotice }}</AppAlert>

        <template v-if="socialProviders.length">
          <div :class="socialLoginGridClass">
            <AppButton
              v-for="p in socialProviders"
              :key="p.id"
              :href="`/api/auth/social/${p.id}`"
              variant="secondary"
              class="social-btn"
            >
              <span class="social-icon" v-html="p.icon" />
              <span>{{ p.label }}</span>
            </AppButton>
          </div>

          <div class="flex items-center gap-2">
            <div class="flex-1 border-t border-slate-200" />
            <span class="text-2xs text-slate-400">or continue with email</span>
            <div class="flex-1 border-t border-slate-200" />
          </div>
        </template>
      </template>

      <form @submit.prevent="login" class="space-y-4">
        <AppFormField label="Email" id="email" :required="true">
          <AppInput
            id="email"
            v-model="email"
            type="email"
            placeholder="you@example.com"
            :error="''"
          />
        </AppFormField>

        <AppFormField label="Password" id="password" :required="true">
          <AppInput
            id="password"
            v-model="password"
            type="password"
            placeholder="••••••••"
          />
        </AppFormField>

        <AppAlert v-if="displayError" variant="danger">{{ displayError }}</AppAlert>

        <AppButton type="submit" variant="primary" :full="true" class="mt-2">
          Sign in
        </AppButton>

        <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
          <router-link to="/forgot-password" class="text-slate-600 hover:text-slate-800 underline underline-offset-2">
            Forgot password?
          </router-link>
        </p>
      </form>
    </AppStack>
  </AuthLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthLayout from '../layouts/AuthLayout.vue'
import { fetchEnabledSocialLoginProviders, socialLoginButtonGridClass } from '../data/socialLoginProviders'
import { AppAlert, AppButton, AppFormField, AppInput, AppLoader } from '../components/ui/index.js'
import { AppStack } from '../components/layout/index.js'

const email = ref('')
const password = ref('')
const urlError = ref('')
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const displayError = computed(() => auth.error || urlError.value)

const socialProviders = ref([])
const socialLoginNotice = ref('')
const socialLoginLoading = ref(true)

const socialLoginGridClass = computed(() => socialLoginButtonGridClass(socialProviders.value.length))

onMounted(async () => {
  if (route.query.error) {
    const msgs = {
      social_auth_failed: 'Social sign-in failed. Please try again.',
      email_required: 'Your social account has no public email. Try a different method.',
      facebook_not_configured: 'Facebook login is not configured on the server (missing App ID).',
      facebook_invalid_app_id: 'Facebook App ID in server configuration is invalid.',
      google_not_configured: 'Google login is not configured on the server.',
      github_not_configured: 'GitHub login is not configured on the server.',
      twitter_not_configured: 'Twitter login is not configured.',
      unsupported_provider: 'That sign-in method is not supported.',
      token_exchange_failed: 'Authentication failed. Please try again.',
      user_fetch_failed: 'Could not fetch your account info. Please try again.',
      invalid_state: 'Invalid OAuth state. Please try again.',
      account_deactivated: 'Your account has been deactivated. Please contact support.',
    }
    urlError.value = msgs[route.query.error] || 'Sign-in failed. Please try again.'
  }

  try {
    const { providers, notice } = await fetchEnabledSocialLoginProviders()
    socialProviders.value = providers
    socialLoginNotice.value = notice
  } catch {
    socialProviders.value = []
    socialLoginNotice.value = ''
  } finally {
    socialLoginLoading.value = false
  }
})

function login() {
  urlError.value = ''
  auth.login(email.value, password.value)
}

watch(() => auth.token, (token) => {
  if (token) router.push('/')
})
</script>

<style scoped>
.social-btn {
  @apply flex w-full min-w-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-2xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-300;
}
.social-icon {
  @apply flex-shrink-0 flex items-center;
}
</style>
