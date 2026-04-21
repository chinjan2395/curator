<template>
  <svg v-if="type === 'youtube'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.7 3.6 12 3.6 12 3.6s-7.7 0-9.4.5A3 3 0 0 0 .5 6.2 31.8 31.8 0 0 0 0 12a31.8 31.8 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.7.5 9.4.5 9.4.5s7.7 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.8 31.8 0 0 0 24 12a31.8 31.8 0 0 0-.5-5.8ZM9.6 15.6V8.4L16 12l-6.4 3.6Z" />
  </svg>
  <svg v-else-if="type === 'twitter'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L.8 2h6.5l4.4 5.9L18.9 2Z" />
  </svg>
  <svg v-else-if="type === 'facebook'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7v-3.5h3.1V9.4c0-3.1 1.9-4.8 4.7-4.8 1.3 0 2.6.2 2.6.2v3h-1.5c-1.5 0-2 .9-2 1.9v2.3h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12Z" />
  </svg>
  <svg v-else-if="type === 'instagram'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm8.2 2H8a4 4 0 0 0-4 4v8a4 4 0 0 0 4 4h8a4 4 0 0 0 4-4V8a4 4 0 0 0-4-4Zm-4 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5Zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5Zm5-3a1.1 1.1 0 1 1-1.1 1.1A1.1 1.1 0 0 1 17 6.5Z" />
  </svg>
  <svg v-else-if="type === 'tiktok'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M14.8 2c.4 2.1 1.6 3.8 3.7 4.6.9.3 1.8.5 2.7.4v3.1a9.6 9.6 0 0 1-3.7-.8V15a7 7 0 1 1-7-7c.4 0 .8 0 1.2.1v3.2a3.9 3.9 0 1 0 2.9 3.7V2h2.2Z" />
  </svg>
  <svg v-else-if="type === 'rss'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M4 17a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm-4-9v4a11 11 0 0 1 11 11h4C15 14.2 8.8 8 0 8Zm0-8v4c14.4 0 20 5.6 20 20h4C24 7.7 16.3 0 0 0Z" />
  </svg>
  <svg v-else viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm7.7 9h-3.2a15.5 15.5 0 0 0-1-5 8 8 0 0 1 4.2 5ZM12 4.2c.8 1 1.8 3.1 2.3 6H9.7c.5-2.9 1.5-5 2.3-6ZM4.3 13h3.2a15.5 15.5 0 0 0 1 5 8 8 0 0 1-4.2-5Zm3.2-2H4.3a8 8 0 0 1 4.2-5 15.5 15.5 0 0 0-1 5Zm4.5 8.8c-.8-1-1.8-3.1-2.3-6h4.6c-.5 2.9-1.5 5-2.3 6Zm2.8-1.8a15.5 15.5 0 0 0 1-5h3.2a8 8 0 0 1-4.2 5Z" />
  </svg>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    default: 'other',
  },
});
</script>
