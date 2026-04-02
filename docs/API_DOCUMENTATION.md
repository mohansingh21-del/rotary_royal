# Rotary Royal App — API Documentation

> **Base URL:** `/api/v1`
> **Auth:** Laravel Sanctum (Bearer Token). Protected routes require `Authorization: Bearer {token}` header.

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Assets](#2-assets)
3. [Bookings](#3-bookings)
4. [Members (Users)](#4-members-users)
5. [Donations](#5-donations)
6. [Banners](#6-banners)
7. [Settings](#7-settings)
8. [Notifications](#8-notifications)
9. [Business Logic & Workflows](#9-business-logic--workflows)

---

## 1. Authentication

### POST `/api/v1/auth/login`
Authenticate as Super Admin and receive a Bearer token.

**Public** — No auth required.

**Request Body:**
| Field    | Type   | Required | Description         |
|----------|--------|----------|---------------------|
| email    | string | ✅        | Admin email address |
| password | string | ✅        | Admin password      |

**Response (200):**
```json
{
  "status": 200,
  "message": "Login successful",
  "data": {
    "token": "...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin", "email": "admin@example.com", "role": "Super Admin" }
  }
}
```

> ⚠️ Only users with role `Super Admin` can log in. Members will receive a `403` error.

---

### POST `/api/v1/auth/forgot-password`
Send a 4-digit OTP to the given email for password reset.

**Public** — No auth required. Rate limited to **3 attempts per minute** per email.

**Request Body:**
| Field | Type   | Required |
|-------|--------|----------|
| email | string | ✅        |

**Response (200):** `"OTP sent to your email"` (In local env, OTP is returned in the response for testing.)

---

### POST `/api/v1/auth/reset-password`
Reset the admin's password using the OTP received via email.

**Public** — No auth required. Rate limited to **5 attempts per minute**.

**Request Body:**
| Field             | Type   | Required | Description               |
|-------------------|--------|----------|---------------------------|
| email             | string | ✅        |                           |
| otp               | number | ✅        | 4-digit OTP               |
| password          | string | ✅        | Min 8 characters          |
| password_confirmation | string | ✅  | Must match password       |

> ⚠️ OTP expires after **15 minutes**.

---

### POST `/api/v1/admin/auth/logout` 🔒
Revoke the current Bearer token.

**Protected** — Requires auth.

**Response (200):** `"Logged out successfully"`

---

## 2. Assets

### GET `/api/v1/assets` (Public) or `/api/v1/admin/assets` 🔒

Retrieve a list of all assets. Automatically refreshes availability on each call (auto-completes expired bookings + recalculates stock).

**Query Params:**
| Param  | Type    | Description                              |
|--------|---------|------------------------------------------|
| search | string  | Filter by name or category               |
| limit  | integer | Items per page (enables pagination)      |
| page   | integer | Page number (default: 1)                 |

**Response Fields per asset:**
| Field          | Type    | Description                                                 |
|----------------|---------|-------------------------------------------------------------|
| id             | integer |                                                             |
| name           | string  |                                                             |
| category       | string  | `Asset` or `Consumable`                                     |
| quantity       | integer | Total units owned                                           |
| left_quantity  | integer | Units available (quantity − active approved bookings)       |
| buffer_time    | integer | Hours required after return before re-booking               |
| price          | number  | Rental price (0 = free)                                     |
| image          | string  | Image path                                                  |
| status         | integer | `1` = Available, `0` = Unavailable. **Auto-set to 0 if left_quantity is 0** |

---

### POST `/api/v1/admin/assets` 🔒
Create or update an asset. Send `id` in the form body to update.

**Body (multipart/form-data):**
| Field        | Type    | Required      | Notes                                         |
|--------------|---------|---------------|-----------------------------------------------|
| id           | integer | For update    | If provided, existing asset is updated        |
| name         | string  | ✅             | Unique per category                           |
| category     | string  | ✅             | `Asset` or `Consumable`                       |
| quantity     | integer | ✅             | Min 1                                         |
| buffer_time  | integer | ✅             | Hours, min 0                                  |
| price        | number  | ✅             | Min 0                                         |
| image        | file    | Required (create only) | 64×64px, max 2MB, jpeg/png/jpg/gif/svg/webp |

> 📝 On save: `left_quantity` is automatically recalculated. If `left_quantity > 0`, status is auto-set to `1`.

---

### PATCH `/api/v1/admin/assets/{id}/status` 🔒
Toggle asset status between `1` (available) and `0` (unavailable).

> ⚠️ **Cannot enable an asset if `left_quantity` is `0`.** Returns `422` error.

**Response:** Full asset object with integer `status` (0 or 1).

---

### DELETE `/api/v1/admin/assets/{id}` 🔒
Delete an asset.

> ⚠️ **Cannot delete if the asset is mapped to any booking.** Returns `422` error.

---

## 3. Bookings

### POST `/api/v1/bookings`
Create a new booking. Open to both authenticated users and guests.

**Body (multipart/form-data):**
| Field          | Type    | Required            | Notes                                   |
|----------------|---------|---------------------|-----------------------------------------|
| asset_id       | integer | ✅                   | Must exist in assets table              |
| user_name      | string  | ✅                   |                                         |
| user_phone     | string  | ✅                   | 10 digits                               |
| user_email     | string  | ✅                   | Booking confirmation sent here          |
| start_date     | datetime| ✅                   | Must be in the future                   |
| end_date       | datetime| ✅                   | Must be after start_date                |
| id_number      | string  | ✅                   | Government ID number                    |
| id_image_path  | file    | ✅                   | ID document image                       |
| payment_image  | file    | ✅ (if price > 0)    | Payment screenshot                      |
| reference      | string  | Optional             | Reference person user ID or free text   |

**Business Rules:**
- Checks for **overlapping approved bookings** in the same time slot. If all units are already booked, returns `422`.
- Booking ID is auto-generated in format `BR-1001`, `BR-1002`, etc.
- Booking is created with status `Approved` by default.
- `left_quantity` of the asset is **decremented by 1** on successful booking.
- Email notification sent to the booker and to all **Super Admins**.

---

### GET `/api/v1/admin/bookings` 🔒
Retrieve all bookings with optional filters.

**Query Params:**
| Param     | Type    | Description                           |
|-----------|---------|---------------------------------------|
| search    | string  | Filter by booking ID, phone, user name, or asset name |
| asset_id  | integer | Filter by asset                       |
| startDate | date    | Filter from date                      |
| endDate   | date    | Filter to date                        |
| limit     | integer | Enables pagination                    |
| page      | integer | Page number                           |

**Response Fields per booking:**
| Field          | Description                                              |
|----------------|----------------------------------------------------------|
| reference      | **Name of the referenced user** (looked up from users table via `reference` ID). Falls back to raw value if unresolvable. |

---

### POST `/api/v1/admin/bookings/reject` 🔒
Reject a booking and notify the user via email.

**Body:**
| Field  | Type   | Required |
|--------|--------|----------|
| id     | string | ✅        |
| reason | string | ✅        |

> 📝 On rejection: `left_quantity` of the associated asset is **incremented by 1** (capped at `quantity`).

---

### PATCH `/api/v1/admin/bookings/{booking}/status` 🔒
Manually update a booking's status to `Approved` or `Rejected`.

**Body:**
| Field  | Type   | Values               |
|--------|--------|----------------------|
| status | string | `Approved`, `Rejected` |

---

## 4. Members (Users)

### GET `/api/v1/members`
List all **active** members (role = `Member`, status = `1`). Public endpoint.

---

### GET `/api/v1/admin/users` 🔒
List all members with search and pagination.

**Query Params:** `search`, `limit`, `page`

> Only `Member` role users are returned. Search filters by name and email, correctly scoped to members only.

---

### POST `/api/v1/admin/users` 🔒
Create or update a member. Send `id` in body to update.

**Body:**
| Field | Type   | Notes                             |
|-------|--------|-----------------------------------|
| name  | string | Required                          |
| email | string | Required, unique                  |
| phone | string | Required, 10 digits, unique       |

> Password is set to a random unusable string (members cannot log in to the admin panel).

---

### GET `/api/v1/admin/users/{user}` 🔒
Get details of a specific member.

---

### DELETE `/api/v1/admin/users/{user}` 🔒
Delete a member (only if role is `Member`).

---

### PATCH `/api/v1/admin/users/{id}/status` 🔒
Toggle member's active status between `1` and `0`.

---

## 5. Donations

### POST `/api/v1/donations`
Record a new donation. **Public** — no auth required.

**Body:**
| Field           | Type   | Required |
|-----------------|--------|----------|
| donor_name      | string | ✅        |
| amount          | number | ✅, min 0 |
| date            | date   | Optional, defaults to today |
| foundation_name | string | Optional |

> Donations default to `is_marquee = true` (shown in the scrolling ticker).

---

### GET `/api/v1/admin/donations` 🔒
List donations with optional search by donor name. Supports pagination.

---

### PATCH `/api/v1/admin/donations/{donation}/marquee` 🔒
Toggle whether a donation appears in the marquee ticker.

---

### POST `/api/v1/admin/donations/marquee-message` 🔒
Set or update the global marquee text message.

**Body:**
| Field           | Type   | Required |
|-----------------|--------|----------|
| marquee_message | string | ✅        |

---

## 6. Banners

### GET `/api/v1/banners`
**Public.** Returns only active banners (`status = 1`), ordered by `order ASC`.

---

### GET `/api/v1/admin/banners` 🔒
Admin view of all banners with pagination and search.

---

### POST `/api/v1/admin/banners` 🔒
Create or update a banner. Send `id` to update.

**Body (multipart/form-data):**
| Field | Type    | Notes                            |
|-------|---------|----------------------------------|
| id    | integer | Optional, for update             |
| title | string  | Optional                         |
| link  | string  | Optional, must be valid URL      |
| order | integer | Display order (default 0)        |
| image | file    | Required on create; max 2MB      |

---

### PATCH `/api/v1/admin/banners/{id}/status` 🔒
Toggle banner visibility (`1` / `0`).

---

### DELETE `/api/v1/admin/banners/{id}` 🔒
Delete a banner and remove its image file from disk.

---

## 7. Settings

### GET `/api/v1/admin/settings/{key}` 🔒
Retrieve a setting by its key.

**Keys:**
| Key              | Description                   |
|------------------|-------------------------------|
| `bank_details`   | Bank account info for payment |
| `marquee_settings` | Marquee ticker configuration |

---

### POST `/api/v1/admin/settings/bank` 🔒
Update bank payment details.

**Body:**
| Field         | Type   | Required |
|---------------|--------|----------|
| accountName   | string | ✅        |
| accountNumber | string | ✅        |
| ifscCode      | string | ✅        |
| bankName      | string | ✅        |
| qrCode        | string | Optional |

---

### POST `/api/v1/admin/settings/marquee` 🔒
Update marquee settings.

**Body:**
| Field         | Type   | Required |
|---------------|--------|----------|
| commonMessage | string | ✅        |
| items         | array  | ✅        |

---

## 8. Notifications

All notification routes are **Protected** 🔒 and operate on the authenticated admin's notifications.

### GET `/api/v1/admin/notifications`
Returns all **unread** notifications for the logged-in admin.

---

### POST `/api/v1/admin/notifications/{id}/read`
Mark a single notification as read.

---

### POST `/api/v1/admin/notifications/read-all`
Mark all unread notifications as read.

**Notification Types:**
| Type                | Trigger                              |
|---------------------|--------------------------------------|
| `booking_confirmed` | New booking created (sent to admin)  |

---

## 9. Business Logic & Workflows

### 🔄 Asset Availability (Auto-Refresh)

Every time `GET /assets` is called, the system runs `refreshAssetAvailability()`:

1. **Auto-complete expired bookings:** Checks all `Approved` bookings. If `end_date + buffer_time` (in hours) has passed, the booking is automatically marked as `Completed`.
2. **Recalculate `left_quantity`:** For every asset, `left_quantity = quantity - count(Approved bookings)`. This is persisted to the database.

---

### 📦 left_quantity & Status Logic

| Situation                                | Result                                         |
|------------------------------------------|------------------------------------------------|
| New booking approved                     | Asset `left_quantity` decremented by 1         |
| Booking rejected                         | Asset `left_quantity` incremented by 1 (max = `quantity`) |
| Asset quantity updated by admin          | `left_quantity` recalculated; if > 0, `status` auto-set to 1 |
| `left_quantity` = 0 in API response      | `status` returned as `0` regardless of DB value |
| Admin tries to enable asset with `left_quantity` = 0 | `422` error returned; toggle blocked |

---

### 📧 Email Notifications

| Event             | Recipient              | Content                                              |
|-------------------|------------------------|------------------------------------------------------|
| Booking Created   | Booker's email         | Asset name, Start Date + Time, End Date + Time       |
| Booking Created   | All Super Admins       | New booking alert                                    |
| Booking Rejected  | Booker's email         | Asset name, rejection reason                         |
| Password Reset    | Admin email            | 4-digit OTP (expires in 15 minutes)                  |

---

### 🔐 Authentication Flow

1. Admin POSTs to `/auth/login` with email + password.
2. System validates credentials and confirms `role = Super Admin`.
3. Laravel Sanctum issues a **Bearer token**.
4. All `/admin/*` routes require this token in the `Authorization` header.
5. Logout (`POST /admin/auth/logout`) deletes the current token.

---

### 🔑 Password Reset Flow

1. Admin POSTs email to `/auth/forgot-password` → 4-digit OTP sent via email.
2. OTP stored as a **bcrypt hash** in `password_resets` table.
3. Admin POSTs email + OTP + new password to `/auth/reset-password`.
4. OTP is verified via `Hash::check()` and must be used within **15 minutes**.
5. On success, password updated and OTP record deleted.

---

### 🏷️ Booking ID Format

Auto-generated sequential IDs: `BR-1001`, `BR-1002`, etc.
Determined by reading the last booking ID and incrementing the numeric suffix.

---

### 📋 Pagination Response Format

All list endpoints return a consistent pagination structure:

```json
{
  "status": 200,
  "message": "...",
  "data": [...],
  "pagination": {
    "total": 50,
    "current_page": 1,
    "per_page": 10,
    "last_page": 5,
    "from": 1,
    "to": 10,
    "next_page_url": "http://...",
    "previous_page_url": null
  }
}
```

When no `limit` is passed, all records are returned and `next_page_url` / `previous_page_url` are `null`.
