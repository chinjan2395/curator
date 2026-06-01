<template>
  <div class="min-h-screen text-slate-700">
    <router-view />
    <ToastContainer />
    <ConfirmModal />
  </div>
</template>

<script setup>
import { provide } from 'vue';
import ToastContainer from './components/ToastContainer.vue';
import ConfirmModal from './components/ConfirmModal.vue';
import { useConfirm } from './composables/useConfirm';

const confirmApi = useConfirm();
provide('confirm', confirmApi);
</script>
