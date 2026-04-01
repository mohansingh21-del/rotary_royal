# API Requirements Document (Laravel Backend)

This document outlines the API endpoints, request structures, and expected responses for the **Rotary Royals Emergency Bank** project. The backend will be built using **Laravel**.

## General Information
- **Base URL**: `{{BASE_URL}}/api/v1`
- **Content-Type**: `application/json`
- **Authentication**: Authentication is handled via a **Bearer Token**. For all protected endpoints, the token must be passed in the `Authorization: Bearer <token>` header.
- **Standards**:
    - Use standard HTTP status codes (200, 201, 204, 400, 401, 403, 404, 500).
    - Consistent response wrapper:
      ```json
      {
        "status": 200,
        "message": "Success message",
        "data": { ... }
      }
      ```

---

## 1. Authentication Module

### Login
- **Endpoint**: `POST /auth/login`
- **Body**:
  ```json
  { "email": "admin@example.com", "password": "password123" }
  ```
- **Response**:
  ```json
  {
    "status": 200,
    "token": "bearer_token_string",
    "token_type": "Bearer",
    "data": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@example.com",
      "role": { "name": "Super Admin" }
    }
  }
  ```

### Forgot Password
- **Endpoint**: `POST /auth/forgot-password`
- **Body**: `{ "email": "user@example.com" }`

### Reset Password
- **Endpoint**: `POST /auth/reset-password`
- **Body**: `{ "token": "abc", "password": "new_password", "password_confirmation": "new_password" }`

---

## 2. Assets Management (Inventory)

### List Assets
- **Endpoint**: `GET /admin/assets`
- **Query Params**: `search`, `page`, `limit` (default: 10)
- **Response**: `data` should contain an array of objects:
  ```json
  { "id": 1, "name": "Oxygen Cylinder", "category": "Asset", "quantity": 10, "bufferTime": 2, "price": 500, "status": "Available" }
  ```

### Create Asset
- **Endpoint**: `POST /admin/assets`
- **Body**:
  ```json
  { "name": "Machine A", "category": "Asset", "quantity": 5, "bufferTime": 4, "price": 1000, "status": "Available" }
  ```

### Update Asset
- **Endpoint**: `PUT /admin/assets/{id}`
- **Body**: Same as Create.

### Toggle Status
- **Endpoint**: `PATCH /admin/assets/{id}/status`
- **Body**: `{ "status": "Available" | "Not Available" }`

### Delete Asset
- **Endpoint**: `DELETE /admin/assets/{id}`

---

## 3. Booking Management

### List Bookings
- **Endpoint**: `GET /admin/bookings`
- **Query Params**: `search` (filters by Name, Phone, or Booking ID), `startDate`, `endDate`, `page`, `limit`.
- **Response Item**:
  ```json
  {
    "id": "BR-1001",
    "userName": "Rahul Sharma",
    "contact": "9876543210",
    "idType": "Aadhar Card",
    "idNumber": "1234-5678-9012",
    "idImageUrl": "url_to_image",
    "assetName": "Medical Bed",
    "timeSlot": "10:00 AM - 12:00 PM",
    "bookingDate": "2026-03-30",
    "status": "Approved"
  }
  ```

### Reject Booking & Notify User
- **Endpoint**: `POST /admin/bookings/{id}/reject`
- **Body**:
  ```json
  { "reason": "Identity document is not clear." }
  ```
- **Description**: This endpoint should update the booking status to "Rejected" and trigger an automated email to the user including the provided reason.

---

## 4. User Management (Member Listing)

### List Users
- **Endpoint**: `GET /admin/users`
- **Query Params**: `search`, `page`, `limit`.
- **Response Item**:
  ```json
  { "id": 1, "name": "Bhupesh", "phone": "1234567890", "email": "b@ex.com", "role": "Member", "status": "Active" }
  ```

---

## 5. Donation Management

### List Donations
- **Endpoint**: `GET /admin/donations`
- **Query Params**: `search`, `page`.
- **Response Item**:
  ```json
  { "id": 1, "donor": "Amit", "amount": 5000, "date": "2025-03-20", "foundation": "Foundation A", "is_marquee": true }
  ```

### Update Marquee Settings (Settings Tab)
- **Endpoint**: `POST /admin/settings/marquee`
- **Body**: `{ "commonMessage": "Our heartfelt thanks to ", "items": [id1, id2] }`

### Update Bank Details (Settings Tab)
- **Endpoint**: `POST /admin/settings/bank`
- **Body**:
  ```json
  {
    "accountName": "...",
    "accountNumber": "...",
    "ifscCode": "...",
    "bankName": "...",
    "qrCode": "file_or_base64"
  }
  ```

---

## Notes for Laravel Developer:
1. **File Storage**: Use **Local Storage** for Identity images (Bookings) and QR codes (Donations). The API must return the full, public URLs for these files in all responses.
2. **Middleware**: All paths starting with `/admin` must be protected by `auth:api`.
3. **Database**: Use Soft Deletes for Assets.
4. **Validation**: Use Request classes to enforce data types (Phone numbers, positive amounts, required fields).
