<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedSyncController;
use App\Http\Controllers\FeedSyncSettingsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SocialConnectController;
use App\Http\Controllers\SocialCredentialController;
use App\Http\Controllers\UserSyncSummaryController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\OAuthAppConfigController;
use App\Http\Controllers\FeedPublishController;
use App\Http\Controllers\PublicFeedController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EmbedAnalyticsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SyncController as AdminSyncController;
use App\Http\Controllers\Admin\SystemController as AdminSystemController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\TokenRefreshController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContentPackageController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\CuratorFeedController;
use App\Http\Controllers\BrandKitController;
use App\Http\Controllers\ContentTemplateController;
use App\Http\Controllers\ContentBlockController;
use App\Http\Controllers\Admin\TrendsController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\DevToolsController;
use App\Http\Controllers\CapabilitiesController;
use App\Http\Controllers\SetupStatusController;

// OAuth callbacks (public, no auth)
Route::get('social/callback/youtube', [SocialConnectController::class, 'callbackYouTube']);
Route::get('social/callback/google', [SocialConnectController::class, 'callbackGoogle']);
Route::get('social/callback/facebook', [SocialConnectController::class, 'callbackFacebook']);
Route::get('social/callback/twitter', [SocialConnectController::class, 'callbackTwitter']);
Route::get('social/callback/tiktok', [SocialConnectController::class, 'callbackTikTok']);
Route::get('social/callback/threads', [SocialConnectController::class, 'callbackThreads']);
Route::get('social/callback/linkedin', [SocialConnectController::class, 'callbackLinkedIn']);

// Public embed endpoints (no auth)
Route::get('public/feeds/{publicKey}/posts', [PublicFeedController::class, 'posts']);
Route::get('embed/{publicToken}/feed', [PublicFeedController::class, 'posts']);
Route::get('embed/{publicKey}.js', [EmbedController::class, 'js']);
Route::get('embed/{publicKey}.css', [EmbedController::class, 'css']);
Route::middleware('throttle:embed-analytics')->group(function () {
    Route::post('public/feeds/{publicKey}/posts/{post}/events', [EmbedAnalyticsController::class, 'store']);
});

// Signed asset preview (no Bearer token; used by <img src> via Vite /api proxy)
Route::get('content/assets/{asset}/file', [AssetController::class, 'file'])
    ->name('content.assets.file')
    ->middleware('signed');

// Sanctum authentication routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/user/sync-summary', [UserSyncSummaryController::class, 'show']);
    Route::post('/user/sync-summary/acknowledge', [UserSyncSummaryController::class, 'acknowledge']);
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::apiResource('workspaces.feeds', FeedController::class);
    Route::patch('workspaces/{workspace}/feeds/{feed}/sync-settings', [FeedSyncSettingsController::class, 'patch']);
    Route::post('workspaces/{workspace}/feeds/{feed}/sync', [FeedSyncController::class, 'sync']);
    Route::post('workspaces/{workspace}/feeds/test-youtube', [FeedSyncController::class, 'testYouTube']);
    Route::get('workspaces/{workspace}/feeds/youtube/channels', [FeedSyncController::class, 'youtubeChannels']);
    Route::get('workspaces/{workspace}/feeds/twitter/account', [FeedSyncController::class, 'twitterAccount']);
    Route::get('workspaces/{workspace}/feeds/tiktok/account', [FeedSyncController::class, 'tiktokAccount']);
    Route::get('workspaces/{workspace}/feeds/threads/account', [FeedSyncController::class, 'threadsAccount']);
    Route::get('workspaces/{workspace}/feeds/facebook/pages', [FeedSyncController::class, 'facebookPages']);
    Route::get('workspaces/{workspace}/feeds/instagram/accounts', [FeedSyncController::class, 'instagramAccounts']);
    Route::post('workspaces/{workspace}/feeds/test-facebook', [FeedSyncController::class, 'testFacebook']);
    Route::post('workspaces/{workspace}/feeds/test-instagram', [FeedSyncController::class, 'testInstagram']);
    Route::post('workspaces/{workspace}/feeds/test-twitter', [FeedSyncController::class, 'testTwitter']);
    Route::post('workspaces/{workspace}/feeds/test-tiktok', [FeedSyncController::class, 'testTikTok']);
    Route::post('workspaces/{workspace}/feeds/test-threads', [FeedSyncController::class, 'testThreads']);
    Route::post('workspaces/{workspace}/feeds/test-rss', [FeedSyncController::class, 'testRss']);
    Route::get('workspaces/{workspace}/publish/stats', [FeedPublishController::class, 'stats']);
    Route::put('workspaces/{workspace}/publish/settings', [FeedPublishController::class, 'updateSettings']);
    Route::post('workspaces/{workspace}/publish', [FeedPublishController::class, 'publish']);
    Route::get('workspaces/{workspace}/publish/code', [FeedPublishController::class, 'publishCode']);
    Route::put('workspaces/{workspace}/posts/bulk', [PostController::class, 'bulkUpdate']);
    Route::apiResource('workspaces.feeds.posts', PostController::class)->only(['index', 'update', 'destroy']);

    Route::apiResource('social-credentials', SocialCredentialController::class);
    Route::put('social-credentials/{socialCredential}/label', [SocialCredentialController::class, 'label']);
    Route::post('social-credentials/{socialCredential}/sync', [SocialCredentialController::class, 'sync']);
    Route::post('social/connect', [SocialConnectController::class, 'connect']);
    Route::post('social/disconnect', [SocialConnectController::class, 'disconnect']);

    Route::get('activity-logs', [ActivityLogController::class, 'index']);

    Route::get('oauth-app-configs', [OAuthAppConfigController::class, 'index']);
    Route::post('oauth-app-configs', [OAuthAppConfigController::class, 'upsert']);
    Route::post('oauth-app-configs/promote-my-user-configs-to-shared', [OAuthAppConfigController::class, 'promoteMyUserConfigsToShared']);
    Route::delete('oauth-app-configs/{provider}', [OAuthAppConfigController::class, 'destroy']);
});

// Public routes (legacy + spec aliases)
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

    Route::prefix('auth')->group(function () {
        Route::post('register', [RegisterController::class, 'register']);
        Route::post('login', [LoginController::class, 'login']);
        Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
        Route::post('reset-password', [ResetPasswordController::class, 'reset']);
    });
});

// Social login (public, browser redirect flow)
Route::get('auth/social/providers', [SocialAuthController::class, 'providers']);
Route::get('auth/social/{provider}', [SocialAuthController::class, 'redirect']);
Route::get('auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::post('auth/logout', [LogoutController::class, 'logout']);
    Route::get('auth/me', [ProfileController::class, 'me']);
    Route::put('auth/profile', [ProfileController::class, 'update']);
    Route::post('auth/refresh', [TokenRefreshController::class, 'refresh']);
    Route::post('auth/resend-verification', [EmailVerificationController::class, 'resend']);
    Route::post('auth/email/verify', [EmailVerificationController::class, 'verify']);
    Route::get('auth/export', [AccountController::class, 'export']);
    Route::delete('auth/account', [AccountController::class, 'destroy']);

    Route::get('setup/status', [SetupStatusController::class, 'show']);
    Route::get('capabilities', [CapabilitiesController::class, 'show']);

    Route::get('curator/feed', [CuratorFeedController::class, 'index']);

    Route::apiResource('campaigns', CampaignController::class);
    Route::post('campaigns/{campaign}/generate', [CampaignController::class, 'generate']);
    Route::get('content-packages', [ContentPackageController::class, 'index']);
    Route::post('content-packages/{contentPackage}/refine', [ContentPackageController::class, 'refine']);
    Route::patch('content-packages/{contentPackage}/status', [ContentPackageController::class, 'updateStatus']);
    Route::patch('content-packages/{contentPackage}/media', [ContentPackageController::class, 'updateMedia']);
    Route::patch('content-packages/{contentPackage}/caption', [ContentPackageController::class, 'updateCaption']);
    Route::get('content-packages/{contentPackage}/versions', [ContentPackageController::class, 'versions']);

    Route::get('content/brand-kits', [BrandKitController::class, 'index']);
    Route::get('content/brand-kits/{brandKit}', [BrandKitController::class, 'show']);
    Route::post('content/brand-kits', [BrandKitController::class, 'store']);
    Route::put('content/brand-kits/{brandKit}', [BrandKitController::class, 'update']);
    Route::delete('content/brand-kits/{brandKit}', [BrandKitController::class, 'destroy']);

    Route::get('content/templates', [ContentTemplateController::class, 'index']);
    Route::post('content/templates', [ContentTemplateController::class, 'store']);
    Route::put('content/templates/{contentTemplate}', [ContentTemplateController::class, 'update']);
    Route::delete('content/templates/{contentTemplate}', [ContentTemplateController::class, 'destroy']);

    Route::get('content/blocks', [ContentBlockController::class, 'index']);
    Route::post('content/blocks', [ContentBlockController::class, 'store']);
    Route::put('content/blocks/{contentBlock}', [ContentBlockController::class, 'update']);
    Route::delete('content/blocks/{contentBlock}', [ContentBlockController::class, 'destroy']);

    Route::post('inbox/sync', [InboxController::class, 'sync']);

    Route::get('content/assets', [AssetController::class, 'index']);
    Route::post('content/assets', [AssetController::class, 'store']);
    Route::delete('content/assets/{asset}', [AssetController::class, 'destroy']);

    Route::get('schedule/calendar', [ScheduleController::class, 'calendar']);
    Route::post('schedule', [ScheduleController::class, 'store']);
    Route::delete('schedule/{scheduledPost}', [ScheduleController::class, 'cancel']);
    Route::post('schedule/{scheduledPost}/retry', [ScheduleController::class, 'retry']);
    Route::get('publisher/queue', [ScheduleController::class, 'queue']);

    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('analytics/platforms/{platform}', [AnalyticsController::class, 'platform']);
    Route::get('analytics/insights', [AnalyticsController::class, 'insights']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('notifications/preferences', [NotificationController::class, 'updatePreferences']);

    Route::get('inbox', [InboxController::class, 'index']);
    Route::post('inbox', [InboxController::class, 'store']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::apiResource('users', AdminUserController::class)->except(['store']);
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::post('users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
    Route::post('users/{user}/activate', [AdminUserController::class, 'activate']);

    Route::get('activity-logs', [AdminActivityController::class, 'index']);

    Route::prefix('sync')->group(function () {
        Route::get('status', [AdminSyncController::class, 'status']);
        Route::get('logs', [AdminSyncController::class, 'logs']);
        Route::get('broken-credentials', [AdminSyncController::class, 'brokenCredentials']);
        Route::post('run-all', [AdminSyncController::class, 'runAll']);
        Route::post('credentials/{credential}/resync', [AdminSyncController::class, 'resyncCredential']);
        Route::post('feeds/{feed}', [AdminSyncController::class, 'syncFeed']);
    });

    Route::get('system/overview', [AdminSystemController::class, 'overview']);
    Route::get('integrations/health', [AdminSystemController::class, 'integrationHealth']);
    Route::get('trends', [TrendsController::class, 'index']);
    Route::get('posts/pending', [ModerationController::class, 'pending']);

    Route::get('dev-tools/commands', [DevToolsController::class, 'index']);
    Route::post('dev-tools/run', [DevToolsController::class, 'run']);
});
