<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  breadcrumb: { type: Array, default: () => [] },
  badge: { type: String, default: '' },
})
</script>

<template>
  <div class="space-y-3 mb-5">
    <nav v-if="breadcrumb.length" class="page-breadcrumb flex items-center gap-1 text-sm text-slate-600">
      <span v-for="(item, i) in breadcrumb" :key="i">
        <span>{{ item }}</span>
        <span v-if="i < breadcrumb.length - 1" class="mx-1 text-slate-300">/</span>
      </span>
    </nav>

    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <div class="flex items-center gap-2">
          <svg v-if="icon" class="w-5 h-5 text-indigo-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path v-if="icon === 'dashboard'" d="M3 3.75A.75.75 0 0 1 3.75 3h12.5a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1-.75-.75V3.75Zm3 2.5a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0v-7.5Zm4.75 2a.75.75 0 0 0-1.5 0v5.5a.75.75 0 0 0 1.5 0v-5.5Zm4.75-1.5a.75.75 0 0 0-1.5 0v7a.75.75 0 0 0 1.5 0v-7Z" />
            <path v-else-if="icon === 'lock'" d="M10 2.5a4.5 4.5 0 0 0-4.5 4.5v1H5A2 2 0 0 0 3 10v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2h-.5V7A4.5 4.5 0 0 0 10 2.5Zm3 5.5V7a3 3 0 1 0-6 0v1h6Z" />
            <path v-else-if="icon === 'users'" d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM14.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.615 16.428a1.224 1.224 0 0 1-.569-1.175 6.002 6.002 0 0 1 11.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 0 1 7 18a9.953 9.953 0 0 1-5.385-1.572ZM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 0 0-1.588-3.755 4.502 4.502 0 0 1 5.874 2.636.818.818 0 0 1-.36.98A7.465 7.465 0 0 1 14.5 16Z" />
            <path v-else-if="icon === 'folder'" d="M1 6a2 2 0 0 1 2-2h2a1 1 0 0 0 1-.414l.706-1.414A2 2 0 0 1 9.414 2h5.586a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V6Z" />
            <path v-else-if="icon === 'pencil'" d="M5.433 13.917l1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.643-1.342.765l-3.155 1.262a.5.5 0 0 1-.65-.65Z" />
            <path v-else-if="icon === 'cog'" d="M10.5 1.5H9.7a1.5 1.5 0 0 0-1.174.565l-.458.458a.5.5 0 0 1-.707 0l-.458-.458A1.5 1.5 0 0 0 5.3 1.5H4.5A1.5 1.5 0 0 0 3 3v1.3a1.5 1.5 0 0 0 .565 1.174l.458.458a.5.5 0 0 1 0 .707l-.458.458A1.5 1.5 0 0 0 3 8.7V9.5a1.5 1.5 0 0 0 1.5 1.5h.8a1.5 1.5 0 0 0 1.174-.565l.458-.458a.5.5 0 0 1 .707 0l.458.458c.3.3.728.565 1.174.565h.8a1.5 1.5 0 0 0 1.5-1.5v-.8a1.5 1.5 0 0 0-.565-1.174l-.458-.458a.5.5 0 0 1 0-.707l.458-.458A1.5 1.5 0 0 0 15 5.3V4.5a1.5 1.5 0 0 0-1.5-1.5Zm0 6a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
            <path v-else d="M2.5 3A1.5 1.5 0 0 0 1 4.5v11A1.5 1.5 0 0 0 2.5 17h15a1.5 1.5 0 0 0 1.5-1.5v-11A1.5 1.5 0 0 0 17.5 3h-15Z" />
          </svg>
          <h1 class="text-lg font-bold text-slate-900">{{ title }}</h1>
          <span v-if="badge" class="inline-flex items-center gap-1.5 rounded-full border border-white/75 bg-white/80 px-2.5 py-1 text-2xs text-slate-600 ml-2">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-cyan-500" />
            {{ badge }}
          </span>
        </div>
        <p v-if="subtitle" class="text-sm text-slate-600 mt-1">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
