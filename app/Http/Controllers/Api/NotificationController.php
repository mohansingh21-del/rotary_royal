<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * List all unread notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $notifications = $request->user()->unreadNotifications;
            return $this->successResponse($notifications, 'Notifications retrieved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch notifications: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $notification = $request->user()->notifications()->findOrFail($id);
            $notification->markAsRead();

            return $this->successResponse(null, 'Notification marked as read');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Notification not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to mark notification as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $request->user()->unreadNotifications->markAsRead();
            return $this->successResponse(null, 'All notifications marked as read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse("Validation error", 422, $e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Failed to mark all notifications as read: ' . $e->getMessage(), 500);
        }
    }
}
