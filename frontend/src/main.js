import { createApp } from 'vue';
import './style.css';
import App from './App.vue';
import router from './router/index.js';
import { createPinia } from 'pinia';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(router);
const pinia = createPinia();
app.use(pinia);

const auth = useAuthStore(pinia);
auth.init();
auth.fetchUser();

app.mount('#app');
