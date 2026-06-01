import { createRouter, createWebHistory } from 'vue-router';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import ForgotPassword from '../views/ForgotPassword.vue';
import ResetPassword from '../views/ResetPassword.vue';
import SocialCallback from '../views/SocialCallback.vue';
import Onboarding from '../views/Onboarding.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import Dashboard from '../views/Dashboard.vue';
import WorkspacesList from '../views/WorkspacesList.vue';
import WorkspaceForm from '../views/WorkspaceForm.vue';
import FeedsList from '../views/FeedsList.vue';
import FeedForm from '../views/FeedForm.vue';
import Curate from '../views/Curate.vue';
import Credentials from '../views/Credentials.vue';
import OAuthApps from '../views/OAuthApps.vue';
import Publish from '../views/Publish.vue';
import ProfileSettings from '../views/ProfileSettings.vue';
import CampaignsList from '../views/CampaignsList.vue';
import CampaignForm from '../views/CampaignForm.vue';
import CampaignDetail from '../views/CampaignDetail.vue';
import ContentLibrary from '../views/ContentLibrary.vue';
import Calendar from '../views/Calendar.vue';
import PublisherQueue from '../views/PublisherQueue.vue';
import Analytics from '../views/Analytics.vue';
import CuratorFeed from '../views/CuratorFeed.vue';
import Inbox from '../views/Inbox.vue';
import NotificationsCenter from '../views/NotificationsCenter.vue';
import NotificationPreferences from '../views/NotificationPreferences.vue';
import VerifyEmail from '../views/VerifyEmail.vue';
import AnalyticsPlatform from '../views/AnalyticsPlatform.vue';
import AdminSystem from '../views/admin/AdminSystem.vue';
import AdminTrends from '../views/admin/AdminTrends.vue';
import AdminModeration from '../views/admin/AdminModeration.vue';
import UsersList from '../views/admin/UsersList.vue';
import UserDetail from '../views/admin/UserDetail.vue';
import SyncOperations from '../views/admin/SyncOperations.vue';
import ActivityLogs from '../views/admin/ActivityLogs.vue';
import { useAuthStore } from '../stores/auth';

const routes = [
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/forgot-password', component: ForgotPassword },
  { path: '/reset-password', component: ResetPassword },
  { path: '/auth/social/callback', component: SocialCallback },
  { path: '/verify-email', component: VerifyEmail },
  { path: '/onboarding', component: Onboarding, meta: { requiresAuth: true } },
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
      { path: 'integrations', redirect: '/credentials' },
      { path: 'oauth-apps', name: 'oauth-apps', component: OAuthApps },
      { path: 'publish', name: 'publish', component: Publish },
      { path: 'curator', name: 'curator', component: CuratorFeed },
      { path: 'curator/embed-builder', name: 'embed-builder', component: Publish },
      { path: 'settings/profile', name: 'profile-settings', component: ProfileSettings },
      { path: 'campaigns', name: 'campaigns', component: CampaignsList },
      { path: 'campaigns/new', name: 'campaign-new', component: CampaignForm },
      { path: 'campaigns/:id', name: 'campaign-detail', component: CampaignDetail },
      { path: 'content', name: 'content-library', component: ContentLibrary },
      { path: 'calendar', name: 'calendar', component: Calendar },
      { path: 'publisher', name: 'publisher', component: PublisherQueue },
      { path: 'analytics', name: 'analytics', component: Analytics },
      { path: 'analytics/platforms/:platform', name: 'analytics-platform', component: AnalyticsPlatform },
      { path: 'inbox', name: 'inbox', component: Inbox },
      { path: 'notifications', name: 'notifications', component: NotificationsCenter },
      { path: 'notifications/preferences', name: 'notification-preferences', component: NotificationPreferences },
      { path: 'admin/users', name: 'admin-users', component: UsersList, meta: { requiresAdmin: true } },
      { path: 'admin/users/:id', name: 'admin-user', component: UserDetail, meta: { requiresAdmin: true } },
      { path: 'admin/sync-ops', name: 'admin-sync-ops', component: SyncOperations, meta: { requiresAdmin: true } },
      { path: 'admin/activity', name: 'admin-activity', component: ActivityLogs, meta: { requiresAdmin: true } },
      { path: 'admin/system', name: 'admin-system', component: AdminSystem, meta: { requiresAdmin: true } },
      { path: 'admin/trends', name: 'admin-trends', component: AdminTrends, meta: { requiresAdmin: true } },
      { path: 'admin/moderation', name: 'admin-moderation', component: AdminModeration, meta: { requiresAdmin: true } },
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
  } else if (to.meta.requiresAdmin && !auth.user?.role?.match(/admin|superadmin/)) {
    next('/');
  } else if (auth.token && auth.user && !auth.user.is_onboarded && to.path !== '/onboarding' && to.meta.requiresAuth) {
    next('/onboarding');
  } else {
    next();
  }
});

export default router;
