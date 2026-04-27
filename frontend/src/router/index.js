import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import Dashboard from '../views/Dashboard.vue';
import WorkspacesList from '../views/WorkspacesList.vue';
import WorkspaceForm from '../views/WorkspaceForm.vue';
import FeedsList from '../views/FeedsList.vue';
import FeedForm from '../views/FeedForm.vue';
import Curate from '../views/Curate.vue';
import Credentials from '../views/Credentials.vue';
import Publish from '../views/Publish.vue';
import { useAuthStore } from '../stores/auth';
import { useWorkspacesStore } from '../stores/workspaces';

const routes = [
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  {
    path: '/',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: Dashboard },
      { path: 'workspaces/:workspaceId/curate', name: 'curate', component: Curate },
      { path: 'workspaces/:workspaceId/feeds/:feedId/curate', name: 'feed-curate', component: Curate },
      { path: 'workspaces', name: 'workspaces', component: WorkspacesList },
      { path: 'workspaces/new', name: 'workspace-new', component: WorkspaceForm },
      { path: 'workspaces/:id/edit', name: 'workspace-edit', component: WorkspaceForm },
      { path: 'workspaces/:workspaceId/feeds', name: 'feeds', component: FeedsList },
      { path: 'workspaces/:workspaceId/feeds/new', name: 'feed-new', component: FeedForm },
      { path: 'workspaces/:workspaceId/feeds/:feedId/edit', name: 'feed-edit', component: FeedForm },
      { path: 'workspaces/:workspaceId/publish', name: 'workspace-publish', component: Publish },
      { path: 'credentials', name: 'credentials', component: Credentials },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore();
  if (auth.token && !auth.user) {
    await auth.fetchUser();
  }
  if (to.meta.requiresAuth && !auth.token) {
    next('/login');
    return;
  }
  if (to.meta.requiresAuth && auth.token) {
    await useWorkspacesStore().fetchAll();
  }
  next();
});

export default router;
