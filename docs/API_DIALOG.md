# API Documentation - Dialog Controller

## Base URL
```
/api/dialogs
```

## Authorization
- **Header**: `Authorization: Bearer {token}`
- **Roles**: `student`, `advisor`
- **Auto-detect**: Backend tự động xác định student_id/advisor_id từ token

---

## 1. Get Conversations List

### Endpoint
```http
GET /api/dialogs/conversations
```

### Description
Lấy danh sách cuộc hội thoại. Backend tự động xác định dựa trên role trong token.

### Access Control
- **Student**: Xem hội thoại với cố vấn của lớp mình
- **Advisor**: Xem hội thoại với tất cả sinh viên trong các lớp mình phụ trách

### Response (Student)
```json
{
  "success": true,
  "data": [
    {
      "conversation_id": 1,
      "partner_id": 1,
      "partner_name": "ThS. Trần Văn An",
      "partner_avatar": "https://example.com/avatar.jpg",
      "partner_type": "advisor",
      "last_message": "Chào Hùng, em chuẩn bị tốt cho kỳ thi nhé",
      "last_message_time": "2025-11-14T15:30:00.000000Z",
      "unread_count": 2
    }
  ],
  "message": "Lấy danh sách hội thoại thành công"
}
```

### Response (Advisor)
```json
{
  "success": true,
  "data": [
    {
      "conversation_id": 1,
      "partner_id": 1,
      "partner_code": "210001",
      "partner_name": "Nguyễn Văn Hùng",
      "partner_avatar": null,
      "partner_type": "student",
      "class_name": "DH21CNTT",
      "last_message": "Dạ em cảm ơn thầy",
      "last_message_time": "2025-11-14T16:00:00.000000Z",
      "unread_count": 1
    },
    {
      "conversation_id": 2,
      "partner_id": 2,
      "partner_code": "210002",
      "partner_name": "Trần Thị Thu Cẩm",
      "partner_avatar": null,
      "partner_type": "student",
      "class_name": "DH21CNTT",
      "last_message": "Thầy ơi, em bị cảnh cáo học vụ HK1...",
      "last_message_time": "2025-11-13T10:00:00.000000Z",
      "unread_count": 0
    }
  ],
  "message": "Lấy danh sách hội thoại thành công"
}
```

### Response Fields
| Field | Type | Description |
|-------|------|-------------|
| conversation_id | integer | ID cuộc hội thoại = partner_id |
| partner_id | integer | ID người đối thoại (advisor_id hoặc student_id) |
| partner_name | string | Tên người đối thoại |
| partner_avatar | string/null | Avatar người đối thoại |
| partner_type | string | "advisor" hoặc "student" |
| partner_code | string | Mã sinh viên (chỉ có khi partner_type = "student") |
| class_name | string | Tên lớp (chỉ có khi partner_type = "student") |
| last_message | string/null | Nội dung tin nhắn cuối |
| last_message_time | datetime/null | Thời gian tin nhắn cuối |
| unread_count | integer | Số tin nhắn chưa đọc |

### Error Responses
- **404 Not Found** (Student without advisor):
```json
{
  "success": false,
  "message": "Lớp của bạn chưa có cố vấn"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/dialogs/conversations" \
  -H "Authorization: Bearer {token}"
```

```javascript
// Frontend example
const response = await fetch('/api/dialogs/conversations', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
```

---

## 2. Get Messages in Conversation

### Endpoint
```http
GET /api/dialogs/messages
```

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| partner_id | integer | Yes | ID người đối thoại (advisor_id hoặc student_id) |

### Description
Lấy lịch sử tin nhắn với một người cụ thể. Backend tự động xác định student_id/advisor_id dựa vào role.

### Access Control
- **Student**: Chỉ xem tin nhắn với cố vấn lớp mình (partner_id = advisor_id)
- **Advisor**: Chỉ xem tin nhắn với sinh viên trong lớp mình (partner_id = student_id)

### Auto Mark as Read
Khi lấy tin nhắn, hệ thống tự động đánh dấu tin nhắn của đối phương gửi cho mình là đã đọc.

### Response
```json
{
  "success": true,
  "data": [
    {
      "message_id": 1,
      "student_id": 2,
      "advisor_id": 1,
      "sender_type": "student",
      "content": "Thầy ơi, em bị cảnh cáo học vụ HK1, giờ em phải làm sao ạ?",
      "attachment_path": null,
      "is_read": true,
      "sent_at": "2025-03-11T09:00:00.000000Z"
    },
    {
      "message_id": 2,
      "student_id": 2,
      "advisor_id": 1,
      "sender_type": "advisor",
      "content": "Chào Cẩm, em cần đăng ký học lại ngay môn IT001 trong HK2 này nhé.",
      "attachment_path": null,
      "is_read": true,
      "sent_at": "2025-03-11T09:05:00.000000Z"
    },
    {
      "message_id": 3,
      "student_id": 2,
      "advisor_id": 1,
      "sender_type": "student",
      "content": "Dạ em đăng ký học lại rồi ạ. Em cảm ơn thầy.",
      "attachment_path": null,
      "is_read": true,
      "sent_at": "2025-03-11T09:10:00.000000Z"
    }
  ],
  "message": "Lấy tin nhắn thành công"
}
```

### Notes
- Tin nhắn được sắp xếp theo thời gian tăng dần (từ cũ đến mới)
- Tự động đánh dấu đã đọc khi lấy tin nhắn

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "partner_id": ["Cần chọn người đối thoại"]
  }
}
```

- **403 Forbidden** (Student):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể xem tin nhắn với cố vấn của lớp mình"
}
```

- **403 Forbidden** (Advisor):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể xem tin nhắn với sinh viên trong lớp mình phụ trách"
}
```

### Example
```bash
# Student lấy tin nhắn với advisor_id = 1
curl -X GET "http://localhost:8000/api/dialogs/messages?partner_id=1" \
  -H "Authorization: Bearer {token}"

# Advisor lấy tin nhắn với student_id = 2
curl -X GET "http://localhost:8000/api/dialogs/messages?partner_id=2" \
  -H "Authorization: Bearer {token}"
```

```javascript
// Frontend example - không cần biết role
const response = await fetch(`/api/dialogs/messages?partner_id=${partnerId}`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
```

---

## 3. Send Message

### Endpoint
```http
POST /api/dialogs/messages
```

### Request Body
```json
{
  "partner_id": 1,
  "content": "Thầy ơi, em muốn hỏi về kết quả học tập ạ",
  "attachment_path": null
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| partner_id | integer | Yes | ID người nhận (advisor_id hoặc student_id) |
| content | string | Yes | Nội dung tin nhắn |
| attachment_path | string(255) | No | Đường dẫn file đính kèm |

### Description
Gửi tin nhắn cho người đối thoại. Backend tự động xác định sender dựa vào role.

### Access Control
- **Student**: Chỉ gửi cho cố vấn lớp mình (partner_id = advisor_id)
- **Advisor**: Chỉ gửi cho sinh viên trong lớp mình (partner_id = student_id)

### Response
```json
{
  "success": true,
  "data": {
    "message_id": 4,
    "student_id": 2,
    "advisor_id": 1,
    "sender_type": "student",
    "content": "Thầy ơi, em muốn hỏi về kết quả học tập ạ",
    "attachment_path": null,
    "is_read": false,
    "sent_at": "2025-11-15T10:30:00.000000Z"
  },
  "message": "Gửi tin nhắn thành công"
}
```

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "content": ["Nội dung tin nhắn không được để trống"],
    "partner_id": ["Cần chọn người nhận tin nhắn"]
  }
}
```

- **403 Forbidden** (Student):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể nhắn tin với cố vấn của lớp mình"
}
```

- **403 Forbidden** (Advisor):
```json
{
  "success": false,
  "message": "Bạn chỉ có thể nhắn tin với sinh viên trong lớp mình phụ trách"
}
```

### Example
```bash
curl -X POST "http://localhost:8000/api/dialogs/messages" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "partner_id": 1,
    "content": "Thầy ơi, em muốn hỏi về kết quả học tập ạ"
  }'
```

```javascript
// Frontend example - đơn giản và thống nhất
const response = await fetch('/api/dialogs/messages', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    partner_id: partnerId,
    content: messageContent,
    attachment_path: attachmentPath // optional
  })
});
const data = await response.json();
```

---

## 4. Mark Message as Read

### Endpoint
```http
PUT /api/dialogs/messages/{id}/read
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Message ID |

### Description
Đánh dấu một tin nhắn cụ thể là đã đọc (thường không cần dùng vì API getMessages tự động đánh dấu đã đọc).

### Access Control
- **Student**: Chỉ đánh dấu tin nhắn từ advisor
- **Advisor**: Chỉ đánh dấu tin nhắn từ student

### Response
```json
{
  "success": true,
  "message": "Đánh dấu đã đọc thành công"
}
```

### Error Responses
- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy tin nhắn"
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn không có quyền đánh dấu tin nhắn này"
}
```

### Example
```bash
curl -X PUT "http://localhost:8000/api/dialogs/messages/5/read" \
  -H "Authorization: Bearer {token}"
```

---

## 5. Delete Message

### Endpoint
```http
DELETE /api/dialogs/messages/{id}
```

### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Message ID |

### Description
Xóa tin nhắn. Chỉ người gửi mới có thể xóa tin nhắn của mình.

### Access Control
- **Student**: Chỉ xóa tin nhắn do mình gửi
- **Advisor**: Chỉ xóa tin nhắn do mình gửi

### Response
```json
{
  "success": true,
  "message": "Xóa tin nhắn thành công"
}
```

### Error Responses
- **404 Not Found**:
```json
{
  "success": false,
  "message": "Không tìm thấy tin nhắn"
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn chỉ có thể xóa tin nhắn do mình gửi"
}
```

### Example
```bash
curl -X DELETE "http://localhost:8000/api/dialogs/messages/5" \
  -H "Authorization: Bearer {token}"
```

```javascript
// Frontend example
const response = await fetch(`/api/dialogs/messages/${messageId}`, {
  method: 'DELETE',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
```

---

## 6. Get Unread Message Count

### Endpoint
```http
GET /api/dialogs/unread-count
```

### Description
Lấy tổng số tin nhắn chưa đọc. Backend tự động xác định dựa vào role.

### Access Control
- **Student**: Đếm tin nhắn chưa đọc từ cố vấn lớp mình
- **Advisor**: Đếm tin nhắn chưa đọc từ tất cả sinh viên trong lớp mình phụ trách

### Response
```json
{
  "success": true,
  "data": {
    "unread_count": 5
  },
  "message": "Lấy số tin nhắn chưa đọc thành công"
}
```

### Notes
- Student: Đếm tin nhắn có `sender_type = 'advisor'` và `is_read = false`
- Advisor: Đếm tin nhắn có `sender_type = 'student'` và `is_read = false`

### Example
```bash
curl -X GET "http://localhost:8000/api/dialogs/unread-count" \
  -H "Authorization: Bearer {token}"
```

```javascript
// Frontend example - dùng để hiển thị badge
const response = await fetch('/api/dialogs/unread-count', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
const unreadCount = data.data.unread_count;
```

---

## 7. Search Messages

### Endpoint
```http
GET /api/dialogs/messages/search
```

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| partner_id | integer | Yes | ID người đối thoại |
| keyword | string | Yes | Từ khóa tìm kiếm (min: 1 ký tự) |

### Description
Tìm kiếm tin nhắn trong một cuộc hội thoại cụ thể.

### Access Control
- **Student**: Chỉ tìm kiếm tin nhắn với cố vấn lớp mình
- **Advisor**: Chỉ tìm kiếm tin nhắn với sinh viên trong lớp mình phụ trách

### Response
```json
{
  "success": true,
  "data": [
    {
      "message_id": 1,
      "student_id": 2,
      "advisor_id": 1,
      "sender_type": "student",
      "content": "Thầy ơi, em bị cảnh cáo học vụ HK1, giờ em phải làm sao ạ?",
      "attachment_path": null,
      "is_read": true,
      "sent_at": "2025-03-11T09:00:00.000000Z"
    }
  ],
  "message": "Tìm kiếm thành công"
}
```

### Notes
- Tìm kiếm trong nội dung tin nhắn (`content`)
- Kết quả được sắp xếp theo thời gian giảm dần (từ mới đến cũ)
- Hỗ trợ tìm kiếm partial match (like %keyword%)

### Error Responses
- **422 Validation Error**:
```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {
    "keyword": ["Từ khóa không được để trống"],
    "partner_id": ["Cần chọn người đối thoại"]
  }
}
```

- **403 Forbidden**:
```json
{
  "success": false,
  "message": "Bạn chỉ có thể tìm kiếm tin nhắn của mình"
}
```

### Example
```bash
curl -X GET "http://localhost:8000/api/dialogs/messages/search?partner_id=1&keyword=cảnh%20cáo" \
  -H "Authorization: Bearer {token}"
```

```javascript
// Frontend example
const response = await fetch(
  `/api/dialogs/messages/search?partner_id=${partnerId}&keyword=${encodeURIComponent(keyword)}`,
  {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);
const data = await response.json();
```

---

## Common Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Token không hợp lệ hoặc đã hết hạn"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Bạn không có quyền truy cập"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Lỗi: {error_message}"
}
```

---

## Communication Rules

### For Students:
1. Chỉ có thể nhắn tin với cố vấn của lớp mình
2. Không thể xem tin nhắn của sinh viên khác
3. Chỉ có thể xóa tin nhắn do mình gửi
4. `partner_id` luôn là `advisor_id`

### For Advisors:
1. Chỉ có thể nhắn tin với sinh viên trong các lớp mình phụ trách
2. Có thể xem tất cả hội thoại với sinh viên của mình
3. Chỉ có thể xóa tin nhắn do mình gửi
4. `partner_id` luôn là `student_id`

### Auto Mark as Read:
- Khi lấy tin nhắn (`GET /api/dialogs/messages`)
- Hệ thống tự động đánh dấu tin nhắn của đối phương là đã đọc
- Không cần gọi API mark as read riêng trong hầu hết trường hợp

---

## Integration Example

### Complete Chat Flow

```javascript
// 1. Load conversations list
async function loadConversations() {
  const response = await fetch('/api/dialogs/conversations', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  return data.data; // Array of conversations
}

// 2. Open a conversation and load messages
async function openConversation(partnerId) {
  const response = await fetch(`/api/dialogs/messages?partner_id=${partnerId}`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  return data.data; // Array of messages (auto-marked as read)
}

// 3. Send a message
async function sendMessage(partnerId, content, attachmentPath = null) {
  const response = await fetch('/api/dialogs/messages', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      partner_id: partnerId,
      content: content,
      attachment_path: attachmentPath
    })
  });
  const data = await response.json();
  return data.data; // New message object
}

// 4. Delete a message
async function deleteMessage(messageId) {
  const response = await fetch(`/api/dialogs/messages/${messageId}`, {
    method: 'DELETE',
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return await response.json();
}

// 5. Get unread count for badge
async function getUnreadCount() {
  const response = await fetch('/api/dialogs/unread-count', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  return data.data.unread_count;
}

// 6. Search messages
async function searchMessages(partnerId, keyword) {
  const response = await fetch(
    `/api/dialogs/messages/search?partner_id=${partnerId}&keyword=${encodeURIComponent(keyword)}`,
    {
      headers: { 'Authorization': `Bearer ${token}` }
    }
  );
  const data = await response.json();
  return data.data;
}
```

---

## Key Improvements

### ✅ Simplified Parameters
- Chỉ cần `partner_id` thay vì cả `student_id` và `advisor_id`
- Backend tự động xác định based on role và token

### ✅ Consistent Interface
- Tất cả endpoints đều dùng `partner_id`
- Frontend không cần logic phức tạp để xử lý role

### ✅ Better Security
- Client không thể fake student_id/advisor_id
- Tất cả quyền truy cập được kiểm tra ở backend

### ✅ Easier Integration
- API trực quan và dễ hiểu
- Ít bug hơn khi develop frontend
- Code frontend gọn gàng và maintainable