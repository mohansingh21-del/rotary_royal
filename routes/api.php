<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MembersController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public Authentication ---
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('send-otp', [AuthController::class, 'sendOtp']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
    });

    // --- Public Content Routes ---
    Route::get('assets', [AssetController::class, 'index']);
    Route::get('banners', [BannerController::class, 'publicBanners']);
    Route::get('categories', [CategoryController::class, 'publicCategories']);
    Route::get('events', [EventController::class, 'publicEvents']);
    Route::get('events/{id}', [EventController::class, 'show']);
    Route::get('members', [UserController::class, 'activeMembers']);
    Route::get('members/{id}', [UserController::class, 'showMember']);
    Route::get('projects', [ProjectController::class, 'publicProjects']);
    Route::get('projects/{id}', [ProjectController::class, 'show']);
    Route::get('marquee-message', [DonationController::class, 'getMarqueeMessagePublic']);
    Route::get('settings/donation', [SettingController::class, 'getDonationSettings']);
    Route::get('recent-donors', [DonationController::class, 'getDonors']);

    // --- Public Interaction Routes ---
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('donations', [DonationController::class, 'store']);

    // --- Shared Authenticated Routes (all users) ---
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    // --- Protected Admin Routes ---
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

        // Assets Management
        Route::prefix('assets')->group(function () {
            Route::get('/', [AssetController::class, 'index']);
            Route::post('/', [AssetController::class, 'store']);
            Route::patch('{id}/status', [AssetController::class, 'toggleStatus']);
            Route::delete('{id}', [AssetController::class, 'destroy']);
        });

        // Bookings Management
        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::post('reject', [BookingController::class, 'reject']);
            Route::patch('{booking}/status', [BookingController::class, 'updateStatus']);
        });

        // Donations Management
        Route::prefix('donations')->group(function () {
            Route::get('/', [DonationController::class, 'index']);
            Route::get('marquee-message', [DonationController::class, 'getMarqueeMessage']);
            Route::post('marquee-message', [DonationController::class, 'storeMarqueeMessage']);
            Route::patch('{donation}/marquee', [DonationController::class, 'toggleMarquee']);
        });

        // Banners Management
        Route::prefix('banners')->group(function () {
            Route::get('/', [BannerController::class, 'index']);
            Route::post('/', [BannerController::class, 'store']);
            Route::patch('{id}/status', [BannerController::class, 'toggleStatus']);
            Route::delete('{id}', [BannerController::class, 'destroy']);
        });

        // Projects Management
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectController::class, 'index']);
            Route::post('/', [ProjectController::class, 'store']);
            Route::get('{id}', [ProjectController::class, 'show']);
            Route::delete('{id}', [ProjectController::class, 'destroy']);
            Route::patch('{id}/status', [ProjectController::class, 'toggleStatus']);
        });

        // Events Management
        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index']);
            Route::post('/', [EventController::class, 'store']);
            Route::get('{id}', [EventController::class, 'show']);
            Route::delete('{id}', [EventController::class, 'destroy']);
            Route::patch('{id}/status', [EventController::class, 'toggleStatus']);
        });

        // Categories Management
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);
            Route::post('/', [CategoryController::class, 'store']);
            Route::get('{id}', [CategoryController::class, 'show']);
            Route::patch('{id}/status', [CategoryController::class, 'toggleStatus']);
            Route::delete('{id}', [CategoryController::class, 'destroy']);
        });

        // Member Management
        Route::prefix('members')->group(function () {
            Route::get('/', [MembersController::class, 'index']);
            Route::get('{id}', [MembersController::class, 'show']);
            Route::post('/', [MembersController::class, 'store']);
            Route::patch('{id}/status', [MembersController::class, 'toggleStatus']);
        });
        Route::get('non-members', [MembersController::class, 'nonMembers']);
        Route::patch('non-member/{id}/status', [MembersController::class, 'toggleStatusNonMembers']);

        // System Settings
        Route::prefix('settings')->group(function () {
            Route::get('donation', [SettingController::class, 'getDonationSettings']);
            Route::post('donation', [SettingController::class, 'updateDonationSettings']);
            Route::get('{key}', [SettingController::class, 'getSetting']);
            Route::post('bank', [SettingController::class, 'updateBankDetails']);
            Route::post('marquee', [SettingController::class, 'updateMarqueeSettings']);
        });

        // System Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
        });
    });

    // --- Protected User Routes ---
    Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
        Route::get('profile', [ProfileController::class, 'profile']);

        Route::post('profile/update', [ProfileController::class, 'updateProfile']);
        Route::patch('profile/deactivate', [ProfileController::class, 'deactivate']);
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
        });
        Route::get('user-bookings', [BookingController::class, 'userBookings']);
        Route::get('user-bookings/{id}', [BookingController::class, 'showBooking']);
        Route::get('my-donations', [DonationController::class, 'myDonations']);
        Route::get('my-donations/{id}', [DonationController::class, 'showMyDonation']);
        Route::get('my-donations/{id}/receipt', [DonationController::class, 'generateReceipt']);
    });
});
