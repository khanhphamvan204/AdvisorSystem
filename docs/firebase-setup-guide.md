# 🔥 Firebase Cloud Messaging (FCM) Setup Guide

**Version:** Firebase Messaging v16.0.4 with FCM v1 API  
**Project:** fcm_test  
**Last Updated:** December 2025

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Backend Setup (Laravel)](#backend-setup-laravel)
4. [Flutter App Setup](#flutter-app-setup)
5. [Testing Notifications](#testing-notifications)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

This guide covers the complete setup for Firebase Cloud Messaging (FCM) in your Flutter app and Laravel backend using the latest Firebase v1 API.

### Architecture

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Laravel Backend│  ────>  │  Firebase FCM v1 │  ────>  │   Flutter App   │
│  (PHP Service)  │         │      API         │         │  (Mobile Client)│
└─────────────────┘         └──────────────────┘         └─────────────────┘
```

---

## ✅ Prerequisites

### Required Tools

-   Flutter SDK 3.10.1+
-   Firebase Project (Create at [console.firebase.google.com](https://console.firebase.google.com))
-   Laravel 10+
-   PHP 8.1+

### Firebase Console Setup

1. **Create Firebase Project**

    - Go to [Firebase Console](https://console.firebase.google.com)
    - Create new project or select existing one
    - Enable **Cloud Messaging** API

2. **Generate Service Account Key**

    - Go to **Project Settings** → **Service Accounts**
    - Click **Generate new private key**
    - Download the JSON file
    - Rename to `service-account.json`

3. **Download Configuration Files**
    - **Android:** Download `google-services.json`
    - **iOS:** Download `GoogleService-Info.plist`

---

## 🖥️ Backend Setup (Laravel)

### Step 1: Configure Firebase Credentials

> **Note:** No additional packages required! Laravel already includes HTTP client (Guzzle) by default.

**File:** `config/firebase.php`

```php
<?php

return [
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
];
```

**File:** `.env`

```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
```

### Step 2: Place Service Account File

```
storage/
└── app/
    └── firebase/
        └── service-account.json  ← Place your downloaded JSON here
```

### Step 3: Create Firebase Service

**File:** `app/Services/FirebaseService.php`

The service is already implemented with FCM v1 API support including:

-   `sendToDevice()` - Send to single device
-   `sendToMultipleDevices()` - Send to multiple devices
-   `sendToTopic()` - Send to topic subscribers
-   `subscribeToTopic()` - Subscribe device to topic
-   `unsubscribeFromTopic()` - Unsubscribe device from topic

### Step 4: Setup Routes

**File:** `routes/api.php`

```php
use App\Http\Controllers\FCMController;

Route::middleware(['auth.api'])->prefix('fcm')->group(function () {
    Route::post('/send-test', [FCMController::class, 'sendTestNotification']);
    Route::post('/send-multiple', [FCMController::class, 'sendToMultiple']);
    Route::post('/send-topic', [FCMController::class, 'sendToTopic']);
    Route::post('/subscribe', [FCMController::class, 'subscribeTopic']);
    Route::post('/unsubscribe', [FCMController::class, 'unsubscribeTopic']);
});
```

### Step 5: Auto-send FCM on Notification Creation

The system automatically sends push notifications when creating new notifications via `NotificationController`.

#### Configuration

Add your default FCM token to `.env`:

```env
FCM_TOKEN=your_device_fcm_token_here
```

This token will be used for testing/development. In production, you should store FCM tokens per user in the database.

#### update config/firebase.php already includes:

```php
'default_fcm_token' => env('FCM_TOKEN'),
```

#### How It Works

When an advisor creates a notification via `POST /api/notifications`, the system:

1. ✅ Creates the notification in database
2. ✅ Sends email to students (queued)
3. ✅ **Automatically sends FCM push notification** to the configured device
4. ✅ Logs the result

**Example:**

```json
POST /api/notifications
{
  "title": "Important Announcement",
  "summary": "Exam schedule has been updated",
  "type": "announcement",
  "class_ids": [1, 2]
}
```

**Result:**

-   Notification created in DB
-   Email queued for students
-   FCM push sent to device with token from `FCM_TOKEN`
-   Success logged: `FCM notification sent for notification_id: 123`

**Error Handling:**

If FCM fails, it will:

-   ✅ Log the error
-   ✅ **NOT fail the notification creation** (graceful degradation)
-   ✅ Still create the notification and send emails

**Production Recommendation:**

For production, instead of using a single `FCM_TOKEN`, you should:

1. Add `fcm_token` column to `Students` table
2. Store each student's FCM token when they login from mobile app
3. Modify `NotificationController::store()` to send to all affected students:

```php
// Get students with FCM tokens
$students = Student::whereIn('class_id', $request->class_ids)
    ->whereNotNull('fcm_token')
    ->get();

// Send to each student
foreach ($students as $student) {
    $this->firebaseService->sendToDevice(
        $student->fcm_token,
        $notification->title,
        $notification->summary,
        ['notification_id' => $notification->notification_id]
    );
}
```

---

## 📱 Flutter App Setup

### Step 1: Update `pubspec.yaml`

```yaml
name: fcm_test
description: "A new Flutter project."
publish_to: "none"
version: 0.1.0

environment:
    sdk: ^3.10.1

dependencies:
    flutter:
        sdk: flutter
    firebase_core: ^4.2.1
    firebase_messaging: ^16.0.4
    flutter_local_notifications: ^17.2.3

dev_dependencies:
    flutter_test:
        sdk: flutter
    flutter_lints: ^6.0.0

flutter:
    uses-material-design: true
```

Run:

```bash
flutter pub get
```

### Step 2: Configure Android

**File:** `android/app/build.gradle`

```gradle
plugins {
    id "com.android.application"
    id "kotlin-android"
    id "dev.flutter.flutter-gradle-plugin"
    id "com.google.gms.google-services"  // ← Add this
}

android {
    compileSdk = 34  // Use SDK 34+

    defaultConfig {
        minSdk = 21
        targetSdk = 34
    }
}

dependencies {
    implementation(platform("com.google.firebase:firebase-bom:33.7.0"))
    implementation("com.google.firebase:firebase-messaging")
}
```

**File:** `android/build.gradle` (Project level)

```gradle
buildscript {
    dependencies {
        classpath 'com.google.gms:google-services:4.4.2'  // ← Add this
    }
}
```

**File:** `android/app/src/main/AndroidManifest.xml`

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    <!-- Permissions -->
    <uses-permission android:name="android.permission.INTERNET"/>
    <uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
    <uses-permission android:name="android.permission.VIBRATE"/>

    <application
        android:label="fcm_test"
        android:icon="@mipmap/ic_launcher">

        <!-- FCM Default Notification Channel -->
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="high_importance_channel" />

        <!-- Rest of your application config -->
    </application>
</manifest>
```

**Place `google-services.json`:**

```
android/
└── app/
    └── google-services.json  ← Place here
```

### Step 3: Configure iOS (Optional)

**File:** `ios/Runner/AppDelegate.swift`

```swift
import UIKit
import Flutter
import FirebaseCore
import FirebaseMessaging

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    FirebaseApp.configure()

    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self
    }

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
```

**Place `GoogleService-Info.plist`:**

```
ios/
└── Runner/
    └── GoogleService-Info.plist  ← Place here
```

### Step 4: Implement FCM in Flutter

**File:** `lib/main.dart`

```dart
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

// ========================================
// BACKGROUND MESSAGE HANDLER
// Must be top-level function
// ========================================
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print('🔔 Background Message: ${message.notification?.title}');
}

// ========================================
// MAIN
// ========================================
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  // Register background message handler
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'FCM Test',
      theme: ThemeData(primarySwatch: Colors.blue),
      home: const FCMTokenScreen(),
    );
  }
}

// ========================================
// FCM TOKEN SCREEN
// ========================================
class FCMTokenScreen extends StatefulWidget {
  const FCMTokenScreen({super.key});

  @override
  State<FCMTokenScreen> createState() => _FCMTokenScreenState();
}

class _FCMTokenScreenState extends State<FCMTokenScreen> {
  String _fcmToken = 'Getting token...';
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  @override
  void initState() {
    super.initState();
    _initializeFCM();
  }

  Future<void> _initializeFCM() async {
    // ========================================
    // 1. Initialize Local Notifications
    // ========================================
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (details) {
        print('🔔 Notification tapped: ${details.payload}');
      },
    );

    // Create Android notification channel
    const androidChannel = AndroidNotificationChannel(
      'high_importance_channel',
      'High Importance Notifications',
      description: 'This channel is used for important notifications.',
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(androidChannel);

    // ========================================
    // 2. Request Permission
    // ========================================
    NotificationSettings settings = await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('✅ Permission granted');
    } else {
      print('❌ Permission denied');
    }

    // ========================================
    // 3. Get FCM Token
    // ========================================
    String? token = await FirebaseMessaging.instance.getToken();
    setState(() {
      _fcmToken = token ?? 'Failed to get token';
    });
    print('🔑 FCM Token: $token');

    // Listen for token refresh
    FirebaseMessaging.instance.onTokenRefresh.listen((newToken) {
      print('🔄 Token refreshed: $newToken');
      setState(() {
        _fcmToken = newToken;
      });
    });

    // ========================================
    // 4. FOREGROUND MESSAGE HANDLER
    // ========================================
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('🔔 Foreground Message!');
      print('Title: ${message.notification?.title}');
      print('Body: ${message.notification?.body}');
      print('Data: ${message.data}');

      // Show notification when app is in foreground
      if (message.notification != null) {
        _showLocalNotification(message);
      }
    });

    // ========================================
    // 5. BACKGROUND TAP HANDLER
    // ========================================
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      print('🔔 Notification opened from background');
      print('Data: ${message.data}');
    });

    // Check if app was opened from terminated state
    RemoteMessage? initialMessage =
        await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) {
      print('🔔 App opened from notification (terminated state)');
      print('Data: ${initialMessage.data}');
    }
  }

  // ========================================
  // SHOW LOCAL NOTIFICATION
  // ========================================
  Future<void> _showLocalNotification(RemoteMessage message) async {
    const androidDetails = AndroidNotificationDetails(
      'high_importance_channel',
      'High Importance Notifications',
      channelDescription: 'This channel is used for important notifications.',
      importance: Importance.high,
      priority: Priority.high,
      showWhen: true,
      icon: '@mipmap/ic_launcher',
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    const details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      message.hashCode,
      message.notification?.title ?? 'New Notification',
      message.notification?.body ?? '',
      details,
      payload: message.data.toString(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('FCM Token Tester'),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(
              Icons.notification_important,
              size: 80,
              color: Colors.blue,
            ),
            const SizedBox(height: 20),
            const Text(
              'Firebase Cloud Messaging',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 10),
            const Text(
              'Your FCM device token:',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey[300]!),
              ),
              child: SelectableText(
                _fcmToken,
                style: const TextStyle(
                  fontSize: 12,
                  fontFamily: 'monospace',
                ),
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: () {
                print('📋 FCM Token: $_fcmToken');
              },
              icon: const Icon(Icons.copy),
              label: const Text('Print Token to Console'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
            ),
            const SizedBox(height: 30),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.green[50],
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.green),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Listening for notifications...',
                      style: TextStyle(
                        color: Colors.green,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            const Divider(),
            const SizedBox(height: 10),
            const Text(
              '📝 Test Instructions:',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 10),
            _buildInstructionItem('1', 'Copy the FCM token above'),
            _buildInstructionItem('2', 'Use Postman to test with your Laravel API'),
            _buildInstructionItem('3', 'Send test notification to this token'),
          ],
        ),
      ),
    );
  }

  Widget _buildInstructionItem(String number, String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: Colors.blue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Center(
              child: Text(
                number,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}
```

---

## 🧪 Testing Notifications

### Test 1: Send Test Notification (Postman)

**Endpoint:** `POST /api/fcm/send-test`

**Headers:**

```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

**Body:**

```json
{
    "fcm_token": "YOUR_DEVICE_FCM_TOKEN_HERE",
    "title": "Test Notification",
    "body": "This is a test message from Laravel backend!",
    "data": {
        "screen": "home",
        "user_id": "123"
    }
}
```

**Expected Response:**

```json
{
    "success": true,
    "message": "Test notification sent successfully",
    "data": {
        "success": true,
        "message": "Notification sent successfully",
        "result": {
            "name": "projects/YOUR_PROJECT/messages/MESSAGE_ID"
        }
    }
}
```

### Test 2: Send to Multiple Devices

**Endpoint:** `POST /api/fcm/send-multiple`

**Body:**

```json
{
    "fcm_tokens": ["token1_here", "token2_here"],
    "title": "Broadcast Notification",
    "body": "Message for all devices"
}
```

### Test 3: Send to Topic

**Endpoint:** `POST /api/fcm/send-topic`

**Body:**

```json
{
    "topic": "all_students",
    "title": "Important Announcement",
    "body": "Meeting tomorrow at 10 AM"
}
```

### Test 4: Subscribe to Topic

**Endpoint:** `POST /api/fcm/subscribe`

**Body:**

```json
{
    "fcm_token": "YOUR_DEVICE_TOKEN",
    "topic": "all_students"
}
```

---

## 🐛 Troubleshooting

### Issue: No notification appears

**Check:**

1. ✅ FCM token is valid and not expired
2. ✅ App has notification permission enabled
3. ✅ Test with app in **background** (swipe away)
4. ✅ Notification channel is created (Android 8.0+)
5. ✅ Check Logcat for errors

**Debug:**

```dart
// Add this to see logs
FirebaseMessaging.onMessage.listen((message) {
  print('📱 Message received!');
  print('Title: ${message.notification?.title}');
  print('Body: ${message.notification?.body}');
});
```

### Issue: Token not generated

**Fix:**

```bash
# Clean and rebuild
flutter clean
flutter pub get
flutter run
```

### Issue: Background handler not working

**Check:**

1. Handler must be **top-level function**
2. Must have `@pragma('vm:entry-point')`
3. Registered before `runApp()`

### Issue: Backend returns 401 Unauthorized

**Fix:**

1. Check `service-account.json` exists
2. Verify `FIREBASE_PROJECT_ID` in `.env`
3. Clear Laravel config: `php artisan config:clear`

---

## 📚 API Reference

### Laravel FCM Service Methods

| Method                    | Description               | Parameters                           |
| ------------------------- | ------------------------- | ------------------------------------ |
| `sendToDevice()`          | Send to single device     | `$fcmToken, $title, $body, $data`    |
| `sendToMultipleDevices()` | Send to multiple devices  | `$fcmTokens[], $title, $body, $data` |
| `sendToTopic()`           | Send to topic subscribers | `$topic, $title, $body, $data`       |
| `subscribeToTopic()`      | Subscribe device to topic | `$fcmToken, $topic`                  |
| `unsubscribeFromTopic()`  | Unsubscribe from topic    | `$fcmToken, $topic`                  |

### Flutter FCM Events

| Event                                            | Description                      |
| ------------------------------------------------ | -------------------------------- |
| `FirebaseMessaging.onMessage`                    | App in foreground                |
| `FirebaseMessaging.onMessageOpenedApp`           | Notification tapped (background) |
| `FirebaseMessaging.onBackgroundMessage`          | App in background/terminated     |
| `FirebaseMessaging.instance.getInitialMessage()` | App opened from notification     |

---

## 🔒 Security Best Practices

1. **Never commit `service-account.json`** to git
2. **Use environment variables** for sensitive data
3. **Validate FCM tokens** before sending
4. **Rate limit** notification endpoints
5. **Log all notification activities**

---

## ✅ Checklist

-   [ ] Firebase project created
-   [ ] Service account JSON downloaded
-   [ ] Laravel backend configured
-   [ ] Flutter dependencies installed
-   [ ] Android `google-services.json` added
-   [ ] iOS `GoogleService-Info.plist` added (if iOS)
-   [ ] Notification permissions requested
-   [ ] Test notification sent successfully
-   [ ] Foreground notifications working
-   [ ] Background notifications working

---

## 🎉 Conclusion

Your FCM setup is complete! You can now send push notifications from your Laravel backend to Flutter mobile apps using Firebase Cloud Messaging v1 API.

For more information:

-   [Firebase Documentation](https://firebase.google.com/docs/cloud-messaging)
-   [Flutter Fire](https://firebase.flutter.dev)
-   [FCM HTTP v1 API](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages)
