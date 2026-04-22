<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BannerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\MembersController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\CategoryController;

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 */

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('auth/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('auth/resend-otp', [AuthController::class, 'resendOtp']);

    Route::post('donations', [DonationController::class, 'store']); // Public Donation Creator
    Route::post('bookings', [BookingController::class, 'store']); // Public Booking Creator (Guest Friendly)
    Route::get('assets', [AssetController::class, 'index']);
    Route::get('banners', [BannerController::class, 'publicBanners']);
    Route::get('members', [UserController::class, 'activeMembers']);
    Route::get('projects', [ProjectController::class, 'index']);
    Route::get('projects/{id}', [ProjectController::class, 'show']);
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{id}', [EventController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'publicCategories']);

    // Protected Admin Routes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(
        function () {

            Route::post('/auth/logout', [AuthController::class, 'logout']);

            // Assets
            Route::get('assets', [AssetController::class, 'index']);
            Route::post('assets', [AssetController::class, 'store']);
            Route::patch('assets/{id}/status', [AssetController::class, 'toggleStatus']);
            Route::delete('assets/{id}', [AssetController::class, 'destroy']);

            // Bookings
            Route::get('bookings', [BookingController::class, 'index']);
            Route::post('bookings/reject', [BookingController::class, 'reject']);
            Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus']);

            // Donations
            Route::get('donations', [DonationController::class, 'index']);
            Route::post('donations/marquee-message', [DonationController::class, 'storeMarqueeMessage']);
            Route::patch('donations/{donation}/marquee', [DonationController::class, 'toggleMarquee']);

            // Settings
            Route::get('settings/{key}', [SettingController::class, 'getSetting']);
            Route::post('settings/bank', [SettingController::class, 'updateBankDetails']);
            Route::post('settings/marquee', [SettingController::class, 'updateMarqueeSettings']);

            // Notifications
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

            // Members and Non-Members
            Route::get('users', [MembersController::class, 'index']);
            Route::get('non-members', [MembersController::class, 'nonMembers']);
            Route::patch('users/{id}/status', [MembersController::class, 'toggleStatus']);
            Route::get('users/{id}', [MembersController::class, 'show']);
            Route::post('users', [MembersController::class, 'store']);
            Route::patch('non-member/{id}/status', [MembersController::class, 'toggleStatusNonMembers']);

            // Banners
            Route::get('banners', [BannerController::class, 'index']);
            Route::post('banners', [BannerController::class, 'store']);
            Route::patch('banners/{id}/status', [BannerController::class, 'toggleStatus']);
            Route::delete('banners/{id}', [BannerController::class, 'destroy']);

            // Projects
            Route::get('projects', [ProjectController::class, 'index']);
            Route::post('projects', [ProjectController::class, 'store']);
            Route::get('projects/{id}', [ProjectController::class, 'show']);
            Route::delete('projects/{id}', [ProjectController::class, 'destroy']);
            Route::patch('projects/{id}/status', [ProjectController::class, 'toggleStatus']);

            // Events
            Route::get('events', [EventController::class, 'index']);
            Route::post('events', [EventController::class, 'store']);
            Route::get('events/{id}', [EventController::class, 'show']);
            Route::delete('events/{id}', [EventController::class, 'destroy']);
            Route::patch('events/{id}/status', [EventController::class, 'toggleStatus']);

            // Categories
            Route::get('categories', [CategoryController::class, 'index']);
            Route::post('categories', [CategoryController::class, 'store']);
            Route::get('categories/{id}', [CategoryController::class, 'show']);
            Route::patch('categories/{id}/status', [CategoryController::class, 'toggleStatus']);
            Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
        }
    );

    Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
        Route::get('profile', [ProfileController::class, 'profile']);
        Route::post('profile/update', [ProfileController::class, 'updateProfile']);
        Route::patch('profile/deactivate', [ProfileController::class, 'deactivate']);
        Route::get('user-bookings', [BookingController::class, 'userBookings']);
    });
});




