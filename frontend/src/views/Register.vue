<template>
  <AuthLayout title="Create account" :is-login="false">
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
            <span class="text-2xs text-slate-400">or sign up with email</span>
            <div class="flex-1 border-t border-slate-200" />
          </div>
        </template>
      </template>

      <form @submit.prevent="register" class="space-y-4">
        <AppFormField label="Name" id="name" :required="true">
          <AppInput
            id="name"
            v-model="name"
            type="text"
            placeholder="Your name"
          />
        </AppFormField>

        <AppFormField label="Email" id="email" :required="true">
          <AppInput
            id="email"
            v-model="email"
            type="email"
            placeholder="you@example.com"
          />
        </AppFormField>

        <AppFormField label="Password" id="password" :required="true" hint="Minimum 8 characters.">
          <AppInput
            id="password"
            v-model="password"
            type="password"
            placeholder="••••••••"
          />
        </AppFormField>

        <AppAlert v-if="auth.error" variant="danger">{{ auth.error }}</AppAlert>

        <AppButton type="submit" variant="primary" :full="true" class="mt-2">
          Create account
        </AppButton>

        <p class="text-2xs text-slate-500 text-center pt-2 border-t border-slate-100">
          Use a work email so your team can recognize the account.
        </p>
      </form>
    </AppStack>
  </AuthLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AuthLayout from '../layouts/AuthLayout.vue'
import { fetchEnabledSocialLoginProviders, socialLoginButtonGridClass } from '../data/socialLoginProviders'
import { AppAlert, AppButton, AppFormField, AppInput, AppLoader } from '../components/ui/index.js'
import { AppStack } from '../components/layout/index.js'

const name = ref('')
const email = ref('')
const password = ref('')
const auth = useAuthStore()
const router = useRouter()

const socialProviders = ref([])
const socialLoginNotice = ref('')
const socialLoginLoading = ref(true)

const socialLoginGridClass = computed(() => socialLoginButtonGridClass(socialProviders.value.length))

onMounted(async () => {
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

function register() {
  auth.register(name.value, email.value, password.value)
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
