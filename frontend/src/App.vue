<template>
  <div class="min-h-screen text-slate-700">
    <router-view />
    <ToastContainer />
  </div>
</template>

<script setup>
import ToastContainer from './components/ToastContainer.vue';
</script>
