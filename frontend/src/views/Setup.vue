<template>
  <div
    class="min-h-screen flex items-center justify-center p-6"
    style="background: linear-gradient(160deg, #1e3a8a 0%, #172554 100%)"
  >
    <AppCard class="w-full max-w-2xl p-6 space-y-5">
      <div class="space-y-1">
        <AppTitle size="lg">
          {{ heading }}
        </AppTitle>
        <AppText
          size="sm"
          muted
        >
          {{ subheading }}
        </AppText>
      </div>

      <AppLoader
        v-if="!setup.loaded"
        label="Checking setup…"
      />

      <AppAlert
        v-else-if="setup.error"
        variant="warning"
      >
        {{ setup.error }} You can keep going and check again in a moment.
      </AppAlert>

      <ul
        v-else
        class="divide-y divide-slate-100"
      >
        <li
          v-for="step in steps"
          :key="step.key"
          class="flex items-center gap-3 py-3"
        >
          <span
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
            :class="markClass(step)"
          >
            <AppIcon
              :name="markIcon(step)"
              class="w-4 h-4"
            />
          </span>

          <span class="flex-1 min-w-0">
            <AppText
              size="sm"
              class="font-medium text-slate-800"
            >{{ step.label }}</AppText>
            <AppText
              size="xs"
              muted
            >{{ step.detail }}</AppText>
          </span>

          <AppButton
            v-if="showAction(step)"
            :to="step.route"
            :variant="step.tier === 'block' ? 'primary' : 'secondary'"
            size="sm"
          >
            {{ actionLabel(step) }}
          </AppButton>
        </li>
      </ul>

      <AppAlert
        v-if="waitingOnAdmin"
        variant="info"
      >
        Only an admin can register a platform app. Let them know you're waiting — we'll
        open Curator for you as soon as it's done.
      </AppAlert>

      <div class="flex flex-wrap items-center gap-2 pt-1">
        <AppButton
          variant="primary"
          :disabled="isBlocked"
          @click="continueToApp"
        >
          Continue to Curator
        </AppButton>

        <AppButton
          variant="secondary"
          :loading="setup.loading"
          @click="checkAgain"
        >
          Check again
        </AppButton>

        <AppButton
          v-if="waitingOnAdmin"
          variant="ghost"
          :loading="setup.notifying"
          @click="notifyAdmin"
        >
          Notify admin
        </AppButton>

        <span class="flex-1" />

        <AppButton
          variant="ghost"
          size="sm"
          @click="signOut"
        >
          Sign out
        </AppButton>
      </div>
    </AppCard>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSetupStore } from '../stores/setup';
import { useAuthStore } from '../stores/auth';
import { AppAlert, AppButton, AppCard, AppIcon, AppLoader, AppText, AppTitle } from '../components/ui';

defineOptions({ name: 'SetupGate' });

const router = useRouter();
const setup = useSetupStore();
const auth = useAuthStore();

// The gate only argues about what stops work. Quality nudges (brand kit, synced
// posts) belong to the readiness meter, not to a screen the user is held on.
const GATE_TIERS = ['block', 'lock'];

const steps = computed(() => Object.values(setup.requirements)
  .filter((requirement) => GATE_TIERS.includes(requirement.tier)));

const isBlocked = computed(() => setup.blocking.length > 0);

const waitingOnAdmin = computed(() => setup.blocking.some((requirement) => !requirement.fixable_by_me));

const heading = computed(() => {
  if (!isBlocked.value) return 'Curator is ready';
  return waitingOnAdmin.value ? 'Curator is still being set up' : 'Finish setup to start using Curator';
});

const subheading = computed(() => {
  if (!isBlocked.value) return 'Everything that stops work is done. You can head in.';
  if (waitingOnAdmin.value) return 'Your admin is registering a social platform app. Nothing works until that is saved.';
  return 'Curator posts to social platforms on your behalf, so it needs an app registration before any account can connect.';
});

function markIcon(step) {
  if (step.state === 'satisfied') return 'check';
  return step.tier === 'block' ? 'alert' : 'lock';
}

function markClass(step) {
  if (step.state === 'satisfied') return 'bg-emerald-50 text-emerald-600';
  if (step.tier === 'block') return 'bg-red-50 text-red-600';
  return 'bg-amber-50 text-amber-600';
}

function showAction(step) {
  return step.state !== 'satisfied' && step.fixable_by_me;
}

function actionLabel(step) {
  return step.state === 'partial' ? 'Reconnect' : 'Set up';
}

async function checkAgain() {
  await setup.refresh();
}

async function notifyAdmin() {
  await setup.notifyAdmin().catch(() => {});
}

async function continueToApp() {
  await setup.refresh();
  if (!setup.blocking.length) {
    router.push('/');
  }
}

async function signOut() {
  await auth.logout();
  router.push('/login');
}

onMounted(() => setup.ensureLoaded());
</script>
