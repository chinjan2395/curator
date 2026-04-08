import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    error: null,
  }),
  actions: {
    init() {
      if (this.token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      }
    },
    async login(email, password) {
      try {
        const response = await axios.post('/api/login', { email, password });
        this.token = response.data.token;
        this.user = response.data.user;
        this.error = null;
        localStorage.setItem('token', this.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      } catch (err) {
        this.error = err.response?.data?.message || 'Login failed';
      }
    },
    async register(name, email, password) {
      try {
        const response = await axios.post('/api/register', { name, email, password });
        this.token = response.data.token;
        this.user = response.data.user;
        this.error = null;
        localStorage.setItem('token', this.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      } catch (err) {
        this.error = err.response?.data?.message || 'Registration failed';
      }
    },
    async logout() {
      try {
        if (this.token) {
          axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
          await axios.post('/api/logout');
        }
      } catch {
        // Ignore server-side logout errors; we still clear local state.
      }
      this.user = null;
      this.token = null;
      this.error = null;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
    },
    async fetchUser() {
      if (!this.token) return;
      try {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        const response = await axios.get('/api/user');
        this.user = response.data;
      } catch (err) {
        await this.logout();
      }
    },
  },
});
