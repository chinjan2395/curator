import { createApp } from 'vue';
import axios from 'axios';
import './style.css';
import App from './App.vue';
import router from './router/index.js';
import { createPinia } from 'pinia';
import { useAuthStore } from './stores/auth';
import { apiBrowserBaseUrl } from './config/api.js';

if (apiBrowserBaseUrl) {
  axios.defaults.baseURL = apiBrowserBaseUrl;
}

const app = createApp(App);
app.use(router);
const pinia = createPinia();
app.use(pinia);

const auth = useAuthStore(pinia);
auth.init();
auth.fetchUser();

app.mount('#app');
