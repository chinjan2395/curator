<template>
  <div class="space-y-6">
    <AppPageHeader
      title="Developer Tools"
      subtitle="Run whitelisted Artisan commands on the live server. Admin-only."
      icon="terminal"
    />

    <div
      class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
      <AppIcon name="warning" class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-500" />
      <span>
        These commands run directly on the production server. Use with care — particularly
        <strong>migrate</strong> which applies pending database migrations.
      </span>
    </div>

    <AppLoader v-if="loadingCommands" />
    <AppAlert v-else-if="loadError" variant="danger">{{ loadError }}</AppAlert>

    <div v-else class="grid gap-4 sm:grid-cols-2">
      <AppCard
        v-for="cmd in commands"
        :key="cmd.id"
        class="p-4 flex flex-col gap-3"
      >
        <!-- Command header -->
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="text-sm font-semibold text-slate-800">{{ cmd.id }}</div>
            <code class="text-xs text-slate-400">{{ cmd.command }}</code>
          </div>
          <span
            v-if="results[cmd.id]"
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
            :class="results[cmd.id].exit_code === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
          >
            <AppIcon
              :name="results[cmd.id].exit_code === 0 ? 'check' : 'close'"
              class="w-3 h-3"
            />
            {{ results[cmd.id].exit_code === 0 ? 'Success' : 'Failed' }}
          </span>
        </div>

        <!-- Output block (shown after run) -->
        <pre
          v-if="results[cmd.id]"
          class="text-xs bg-slate-900 text-emerald-400 rounded-md p-3 overflow-auto max-h-40 whitespace-pre-wrap"
        >{{ results[cmd.id].output }}</pre>

        <!-- Run button -->
        <button
          type="button"
          class="mt-auto self-start inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          :disabled="running[cmd.id]"
          @click="runCommand(cmd.id)"
        >
          <span
            v-if="running[cmd.id]"
            class="inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"
          />
          <AppIcon v-else name="sync" class="w-3.5 h-3.5" />
          {{ running[cmd.id] ? 'Running…' : 'Run' }}
        </button>
      </AppCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { AppAlert, AppCard, AppLoader } from '../../components/ui';
import { AppPageHeader } from '../../components/layout';
import { AppIcon } from '../../components/ui';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const commands = ref([]);
const loadingCommands = ref(true);
const loadError = ref(null);

const running = ref({});
const results = ref({});

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/dev-tools/commands', { skipErrorToast: true });
    commands.value = data.data || data;
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Failed to load commands.';
  } finally {
    loadingCommands.value = false;
  }
});

async function runCommand(id) {
  running.value = { ...running.value, [id]: true };
  results.value = { ...results.value, [id]: null };

  try {
    const { data } = await axios.post('/api/admin/dev-tools/run', { command: id });
    const result = data.data || data;
    results.value = { ...results.value, [id]: result };

    if (result.exit_code === 0) {
      toast.success(`"${id}" completed successfully.`);
    } else {
      toast.warning(`"${id}" finished with exit code ${result.exit_code}.`);
    }
  } catch (e) {
    const message = e.response?.data?.message || 'Command failed.';
    results.value = { ...results.value, [id]: { exit_code: 1, output: message } };
    toast.error(message);
  } finally {
    running.value = { ...running.value, [id]: false };
  }
}
</script>
