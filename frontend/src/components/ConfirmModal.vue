<template>
  <div v-if="pending" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" @click.self="cancel">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5">
      <h3 class="text-base font-semibold text-slate-900">{{ pending.title }}</h3>
      <p v-if="pending.message" class="text-sm text-slate-600 mt-2">{{ pending.message }}</p>
      <div class="flex justify-end gap-2 mt-5">
        <AppButton variant="secondary" size="sm" @click="cancel">{{ pending.cancelLabel }}</AppButton>
        <AppButton :variant="pending.variant === 'danger' ? 'danger' : 'primary'" size="sm" @click="accept">
          {{ pending.confirmLabel }}
        </AppButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue';
import { AppButton } from './ui';

const { pending, accept, cancel } = inject('confirm');
</script>
