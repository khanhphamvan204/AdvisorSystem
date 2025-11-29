<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Gmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    private $client;
    private $service;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Khởi tạo Google Client
     */
    private function initializeClient()
    {
        try {
            $this->client = new Client();
            $this->client->setApplicationName('Class Meeting Manager');
            $this->client->setScopes([
                Calendar::CALENDAR,
                Gmail::GMAIL_SEND  // Cho phép gửi email mời
            ]);

            // Đường dẫn đến file credentials
            $credentialsPath = storage_path('app/google/credentials.json');
            $tokenPath = storage_path('app/google/token.json');

            if (!file_exists($credentialsPath)) {
                throw new \Exception('Không tìm thấy file credentials.json');
            }

            $this->client->setAuthConfig($credentialsPath);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('select_account consent');

            // Load token nếu có
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($accessToken);
            }

            // Refresh token nếu hết hạn
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                } else {
                    throw new \Exception('Token đã hết hạn, cần xác thực lại');
                }
            }

            $this->service = new Calendar($this->client);
        } catch (\Exception $e) {
            Log::error('Google Calendar init error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Tạo ID cho cuộc họp dựa trên meeting_id
     */
    private function generateEventId($meetingId)
    {
        // Tạo ID hợp lệ: chỉ chứa a-z, 0-9, độ dài 5-1024 ký tự
        $prefix = 'meet';
        $eventId = $prefix . str_pad($meetingId, 10, '0', STR_PAD_LEFT);
        return strtolower($eventId);
    }

    /**
     * Tạo cuộc họp trên Google Calendar
     * 
     * @param int $meetingId - ID cuộc họp trong database
     * @param string $title - Tiêu đề cuộc họp
     * @param string $description - Mô tả
     * @param Carbon $startTime - Thời gian bắt đầu
     * @param Carbon $endTime - Thời gian kết thúc
     * @param array $attendeeEmails - Danh sách email người tham dự
     * @return array - Trả về thông tin cuộc họp đã tạo
     */
    public function createMeeting($meetingId, $title, $description, $startTime, $endTime, $attendeeEmails = [])
    {
        try {
            $eventId = $this->generateEventId($meetingId);

            // Tạo danh sách attendees
            $attendees = [];
            foreach ($attendeeEmails as $email) {
                $attendee = new EventAttendee();
                $attendee->setEmail($email);
                $attendees[] = $attendee;
            }

            // Tạo event
            $event = new Event([
                'id' => $eventId,
                'summary' => $title,
                'description' => $description,
                'start' => [
                    'dateTime' => $startTime->toRfc3339String(),
                    'timeZone' => 'Asia/Ho_Chi_Minh',
                ],
                'end' => [
                    'dateTime' => $endTime->toRfc3339String(),
                    'timeZone' => 'Asia/Ho_Chi_Minh',
                ],
                'attendees' => $attendees,
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => 'req-' . time() . '-' . $meetingId,
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ]
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60],
                        ['method' => 'popup', 'minutes' => 30],
                    ],
                ],
            ]);

            // Tạo event trên Google Calendar
            $createdEvent = $this->service->events->insert(
                'primary',
                $event,
                [
                    'conferenceDataVersion' => 1,
                    'sendUpdates' => 'all' // Gửi email mời cho tất cả người tham dự
                ]
            );

            // Lấy link Google Meet
            $meetLink = '';
            if ($createdEvent->getConferenceData()) {
                $entryPoints = $createdEvent->getConferenceData()->getEntryPoints();
                if (!empty($entryPoints)) {
                    $meetLink = $entryPoints[0]->getUri();
                }
            }

            return [
                'success' => true,
                'event_id' => $createdEvent->getId(),
                'meet_link' => $meetLink,
                'html_link' => $createdEvent->getHtmlLink(),
                'attendees_count' => count($attendeeEmails)
            ];
        } catch (\Exception $e) {
            Log::error('Create Google Meet error: ' . $e->getMessage());

            // Xử lý lỗi ID đã tồn tại
            if (strpos($e->getMessage(), '409') !== false) {
                return [
                    'success' => false,
                    'error' => 'ID cuộc họp đã tồn tại trên Google Calendar'
                ];
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cập nhật cuộc họp
     */
    public function updateMeeting($meetingId, $title = null, $description = null, $startTime = null, $endTime = null, $attendeeEmails = null)
    {
        try {
            $eventId = $this->generateEventId($meetingId);

            // Lấy event hiện tại
            $event = $this->service->events->get('primary', $eventId);

            // Cập nhật các trường nếu có
            if ($title) {
                $event->setSummary($title);
            }

            if ($description) {
                $event->setDescription($description);
            }

            if ($startTime) {
                $start = new EventDateTime();
                $start->setDateTime($startTime->toRfc3339String());
                $start->setTimeZone('Asia/Ho_Chi_Minh');
                $event->setStart($start);
            }

            if ($endTime) {
                $end = new EventDateTime();
                $end->setDateTime($endTime->toRfc3339String());
                $end->setTimeZone('Asia/Ho_Chi_Minh');
                $event->setEnd($end);
            }

            if ($attendeeEmails !== null) {
                $attendees = [];
                foreach ($attendeeEmails as $email) {
                    $attendee = new EventAttendee();
                    $attendee->setEmail($email);
                    $attendees[] = $attendee;
                }
                $event->setAttendees($attendees);
            }

            // Cập nhật event
            $updatedEvent = $this->service->events->update(
                'primary',
                $eventId,
                $event,
                ['sendUpdates' => 'all']
            );

            return [
                'success' => true,
                'event_id' => $updatedEvent->getId()
            ];
        } catch (\Exception $e) {
            Log::error('Update Google Meet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Xóa cuộc họp
     */
    public function deleteMeeting($meetingId)
    {
        try {
            $eventId = $this->generateEventId($meetingId);

            $this->service->events->delete(
                'primary',
                $eventId,
                ['sendUpdates' => 'all']
            );

            return [
                'success' => true
            ];
        } catch (\Exception $e) {
            Log::error('Delete Google Meet error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Kiểm tra trạng thái phản hồi của người tham dự
     * 
     * @param int $meetingId
     * @return array - Danh sách người tham dự và trạng thái của họ
     */
    public function getAttendanceStatus($meetingId)
    {
        try {
            $eventId = $this->generateEventId($meetingId);

            // Lấy thông tin event
            $event = $this->service->events->get('primary', $eventId);

            $attendees = $event->getAttendees();

            if (!$attendees) {
                return [
                    'success' => true,
                    'attendees' => [],
                    'summary' => [
                        'total' => 0,
                        'accepted' => 0,
                        'declined' => 0,
                        'tentative' => 0,
                        'needsAction' => 0
                    ]
                ];
            }

            $result = [];
            $summary = [
                'total' => 0,
                'accepted' => 0,
                'declined' => 0,
                'tentative' => 0,
                'needsAction' => 0
            ];

            foreach ($attendees as $attendee) {
                $email = $attendee->getEmail();
                $status = $attendee->getResponseStatus();

                $result[] = [
                    'email' => $email,
                    'response_status' => $status,
                    'display_name' => $attendee->getDisplayName(),
                    'organizer' => $attendee->getOrganizer() ?? false,
                    'self' => $attendee->getSelf() ?? false,
                    'comment' => $attendee->getComment()
                ];

                $summary['total']++;

                switch ($status) {
                    case 'accepted':
                        $summary['accepted']++;
                        break;
                    case 'declined':
                        $summary['declined']++;
                        break;
                    case 'tentative':
                        $summary['tentative']++;
                        break;
                    case 'needsAction':
                    default:
                        $summary['needsAction']++;
                        break;
                }
            }

            return [
                'success' => true,
                'attendees' => $result,
                'summary' => $summary,
                'event_link' => $event->getHtmlLink()
            ];
        } catch (\Exception $e) {
            Log::error('Get attendance status error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Lấy OAuth URL để xác thực lần đầu
     */
    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Xử lý callback từ OAuth
     */
    public function handleAuthCallback($code)
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                throw new \Exception($accessToken['error_description']);
            }

            $tokenPath = storage_path('app/google/token.json');
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }

            file_put_contents($tokenPath, json_encode($accessToken));

            return [
                'success' => true,
                'message' => 'Xác thực thành công'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
