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
use App\Http\Controllers\PostController;
use App\Http\Controllers\SocialConnectController;
use App\Http\Controllers\SocialCredentialController;
use App\Http\Controllers\UserSyncSummaryController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\OAuthAppConfigController;
use App\Http\Controllers\FeedPublishController;
use App\Http\Controllers\PublicFeedController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\ActivityController as AdminActivityController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SyncController as AdminSyncController;

// OAuth callbacks (public, no auth)
Route::get('social/callback/youtube', [SocialConnectController::class, 'callbackYouTube']);
Route::get('social/callback/google', [SocialConnectController::class, 'callbackGoogle']);
Route::get('social/callback/facebook', [SocialConnectController::class, 'callbackFacebook']);
Route::get('social/callback/twitter', [SocialConnectController::class, 'callbackTwitter']);
Route::get('social/callback/tiktok', [SocialConnectController::class, 'callbackTikTok']);
Route::get('social/callback/threads', [SocialConnectController::class, 'callbackThreads']);

// Public publish endpoints (no auth)
Route::get('public/feeds/{publicKey}/posts', [PublicFeedController::class, 'posts']);
Route::get('embed/{publicKey}.js', [EmbedController::class, 'js']);
Route::get('embed/{publicKey}.css', [EmbedController::class, 'css']);

// Sanctum authentication routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/user/sync-summary', [UserSyncSummaryController::class, 'show']);
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::apiResource('workspaces.feeds', FeedController::class);
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
    Route::apiResource('workspaces.feeds.posts', PostController::class)->only(['index', 'update', 'destroy']);

    Route::apiResource('social-credentials', SocialCredentialController::class);
    Route::put('social-credentials/{socialCredential}/label', [SocialCredentialController::class, 'label']);
    Route::post('social/connect', [SocialConnectController::class, 'connect']);
    Route::post('social/disconnect', [SocialConnectController::class, 'disconnect']);

    Route::get('activity-logs', [ActivityLogController::class, 'index']);

    Route::get('oauth-app-configs', [OAuthAppConfigController::class, 'index']);
    Route::post('oauth-app-configs', [OAuthAppConfigController::class, 'upsert']);
    Route::post('oauth-app-configs/promote-my-user-configs-to-shared', [OAuthAppConfigController::class, 'promoteMyUserConfigsToShared']);
    Route::delete('oauth-app-configs/{provider}', [OAuthAppConfigController::class, 'destroy']);
});

// Public routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// Social login (public, browser redirect flow)
Route::get('auth/social/providers', [SocialAuthController::class, 'providers']);
Route::get('auth/social/{provider}', [SocialAuthController::class, 'redirect']);
Route::get('auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
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
});
