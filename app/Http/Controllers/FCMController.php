<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FCMController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send test notification to a device
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendTestNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fcmToken = $request->input('fcm_token');
        $title = $request->input('title');
        $body = $request->input('body');
        $data = $request->input('data', []);

        try {
            $result = $this->firebaseService->sendToDevice($fcmToken, $title, $body, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test notification sent successfully',
                    'data' => $result,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send notification to multiple devices
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_tokens' => 'required|array',
            'fcm_tokens.*' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fcmTokens = $request->input('fcm_tokens');
        $title = $request->input('title');
        $body = $request->input('body');
        $data = $request->input('data', []);

        try {
            $result = $this->firebaseService->sendToMultipleDevices($fcmTokens, $title, $body, $data);

            return response()->json([
                'success' => true,
                'message' => 'Notifications sent',
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send notification to a topic
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendToTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $topic = $request->input('topic');
        $title = $request->input('title');
        $body = $request->input('body');
        $data = $request->input('data', []);

        try {
            $result = $this->firebaseService->sendToTopic($topic, $title, $body, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent to topic',
                    'data' => $result,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subscribe device to topic
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribeTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'topic' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fcmToken = $request->input('fcm_token');
        $topic = $request->input('topic');

        try {
            $result = $this->firebaseService->subscribeToTopic($fcmToken, $topic);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unsubscribe device from topic
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsubscribeTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'topic' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fcmToken = $request->input('fcm_token');
        $topic = $request->input('topic');

        try {
            $result = $this->firebaseService->unsubscribeFromTopic($fcmToken, $topic);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe: ' . $e->getMessage(),
            ], 500);
        }
    }
}
