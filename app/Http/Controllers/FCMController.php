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
        ], [
            'fcm_token.required' => 'FCM token là bắt buộc',
            'fcm_token.string' => 'FCM token phải là chuỗi',
            'title.required' => 'Tiêu đề thông báo là bắt buộc',
            'title.string' => 'Tiêu đề thông báo phải là chuỗi',
            'title.max' => 'Tiêu đề thông báo không được vượt quá 255 ký tự',
            'body.required' => 'Nội dung thông báo là bắt buộc',
            'body.string' => 'Nội dung thông báo phải là chuỗi',
            'data.array' => 'Dữ liệu bổ sung phải là mảng'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Send test notification validation failed', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
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
        ], [
            'fcm_tokens.required' => 'Danh sách FCM token là bắt buộc',
            'fcm_tokens.array' => 'Danh sách FCM token phải là mảng',
            'fcm_tokens.*.required' => 'Mỗi FCM token là bắt buộc',
            'fcm_tokens.*.string' => 'Mỗi FCM token phải là chuỗi',
            'title.required' => 'Tiêu đề thông báo là bắt buộc',
            'title.string' => 'Tiêu đề thông báo phải là chuỗi',
            'title.max' => 'Tiêu đề thông báo không được vượt quá 255 ký tự',
            'body.required' => 'Nội dung thông báo là bắt buộc',
            'body.string' => 'Nội dung thông báo phải là chuỗi',
            'data.array' => 'Dữ liệu bổ sung phải là mảng'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Send to multiple devices validation failed', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
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
        ], [
            'topic.required' => 'Chủ đề thông báo là bắt buộc',
            'topic.string' => 'Chủ đề thông báo phải là chuỗi',
            'title.required' => 'Tiêu đề thông báo là bắt buộc',
            'title.string' => 'Tiêu đề thông báo phải là chuỗi',
            'title.max' => 'Tiêu đề thông báo không được vượt quá 255 ký tự',
            'body.required' => 'Nội dung thông báo là bắt buộc',
            'body.string' => 'Nội dung thông báo phải là chuỗi',
            'data.array' => 'Dữ liệu bổ sung phải là mảng'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Send to topic validation failed', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
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
        ], [
            'fcm_token.required' => 'FCM token là bắt buộc',
            'fcm_token.string' => 'FCM token phải là chuỗi',
            'topic.required' => 'Chủ đề là bắt buộc',
            'topic.string' => 'Chủ đề phải là chuỗi'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Subscribe to topic validation failed', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
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
        ], [
            'fcm_token.required' => 'FCM token là bắt buộc',
            'fcm_token.string' => 'FCM token phải là chuỗi',
            'topic.required' => 'Chủ đề là bắt buộc',
            'topic.string' => 'Chủ đề phải là chuỗi'
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Unsubscribe from topic validation failed', [
                'errors' => $validator->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
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
