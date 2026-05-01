import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import ForgotPassword from '../views/ForgotPassword.vue';
import ResetPassword from '../views/ResetPassword.vue';
import SocialCallback from '../views/SocialCallback.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import Dashboard from '../views/Dashboard.vue';
import WorkspacesList from '../views/WorkspacesList.vue';
import WorkspaceForm from '../views/WorkspaceForm.vue';
import FeedsList from '../views/FeedsList.vue';
import FeedForm from '../views/FeedForm.vue';
import Curate from '../views/Curate.vue';
import WorkspaceCurate from '../views/WorkspaceCurate.vue';
import Credentials from '../views/Credentials.vue';
import OAuthApps from '../views/OAuthApps.vue';
import Publish from '../views/Publish.vue';
import UsersList from '../views/admin/UsersList.vue';
import UserDetail from '../views/admin/UserDetail.vue';
import { useAuthStore } from '../stores/auth';

const routes = [
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/forgot-password', component: ForgotPassword },
  { path: '/reset-password', component: ResetPassword },
  { path: '/auth/social/callback', component: SocialCallback },
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
      { path: 'oauth-apps', name: 'oauth-apps', component: OAuthApps },
      { path: 'publish', name: 'publish', component: Publish },
      { path: 'admin/users', name: 'admin-users', component: UsersList, meta: { requiresAdmin: true } },
      { path: 'admin/users/:id', name: 'admin-user', component: UserDetail, meta: { requiresAdmin: true } },
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
  } else if (to.meta.requiresAdmin && auth.user?.role !== 'admin') {
    next('/');
  } else {
    next();
  }
});

export default router;
