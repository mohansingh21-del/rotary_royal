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

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 */

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class , 'login']);
    Route::post('auth/forgot-password', [AuthController::class , 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class , 'resetPassword']);
    Route::post('donations', [DonationController::class , 'store']); // Public Donation Creator
    Route::post('bookings', [BookingController::class , 'store']); // Public Booking Creator (Guest Friendly)
    Route::get('assets', [AssetController::class , 'index']);
    Route::get('banners', [BannerController::class , 'publicBanners']);

    // Protected Admin Routes
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

            // Auth
            Route::post('auth/logout', [AuthController::class , 'logout']);

            // Assets
            Route::get('assets', [AssetController::class , 'index']);
            Route::post('assets', [AssetController::class , 'store']);
            Route::patch('assets/{id}/status', [AssetController::class , 'toggleStatus']);
            Route::delete('assets/{id}', [AssetController::class , 'destroy']);

            // Bookings
            Route::get('bookings', [BookingController::class , 'index']);
            Route::post('bookings/reject', [BookingController::class , 'reject']);
            Route::patch('bookings/{booking}/status', [BookingController::class , 'updateStatus']);

            // Donations
            Route::get('donations', [DonationController::class , 'index']);
            Route::post('donations/marquee-message', [DonationController::class , 'storeMarqueeMessage']);
            Route::patch('donations/{donation}/marquee', [DonationController::class , 'toggleMarquee']);

            // Settings
            Route::get('settings/{key}', [SettingController::class , 'getSetting']);
            Route::post('settings/bank', [SettingController::class , 'updateBankDetails']);
            Route::post('settings/marquee', [SettingController::class , 'updateMarqueeSettings']);

            // Notifications
            Route::get('notifications', [NotificationController::class , 'index']);
            Route::post('notifications/{id}/read', [NotificationController::class , 'markAsRead']);
            Route::post('notifications/read-all', [NotificationController::class , 'markAllAsRead']);

            // Members
            Route::apiResource('users', UserController::class);
            Route::patch('users/{id}/status', [UserController::class , 'toggleStatus']);

            // Banners
            Route::get('banners', [BannerController::class , 'index']);
            Route::post('banners', [BannerController::class , 'store']);
            Route::patch('banners/{id}/status', [BannerController::class , 'toggleStatus']);
            Route::delete('banners/{id}', [BannerController::class , 'destroy']);
        }
        );
    });