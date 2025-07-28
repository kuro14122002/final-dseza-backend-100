# Change Password API

API endpoint để đổi mật khẩu cho user đã đăng nhập trong hệ thống DSEZA.

## Endpoint

```
POST /api/v1/user/change-password
```

## Authentication

⚠️ **Yêu cầu xác thực**: Endpoint này yêu cầu user phải đăng nhập. Sử dụng một trong các phương thức sau:

1. **Session-based authentication** (cookies)
2. **Token-based authentication** (Bearer token trong header)

## Request Headers

```
Content-Type: application/json
Authorization: Bearer <token> (optional, nếu sử dụng token auth)
```

## Request Body

```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword456",
  "confirm_password": "newpassword456"
}
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `current_password` | string | Yes | Mật khẩu hiện tại của user |
| `new_password` | string | Yes | Mật khẩu mới (ít nhất 6 ký tự) |
| `confirm_password` | string | Yes | Xác nhận mật khẩu mới (phải khớp với new_password) |

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Đổi mật khẩu thành công! Mật khẩu của bạn đã được cập nhật.",
  "user_id": "123",
  "timestamp": 1702857600
}
```

### Error Responses

#### 401 Unauthorized - User Not Logged In

```json
{
  "success": false,
  "message": "Bạn phải đăng nhập để thực hiện thao tác này",
  "error": "UNAUTHORIZED"
}
```

#### 400 Bad Request - Current Password Incorrect

```json
{
  "success": false,
  "message": "Mật khẩu hiện tại không chính xác"
}
```

#### 400 Bad Request - Password Mismatch

```json
{
  "success": false,
  "message": "Mật khẩu mới và xác nhận không khớp"
}
```

#### 400 Bad Request - Same Password

```json
{
  "success": false,
  "message": "Mật khẩu mới phải khác mật khẩu hiện tại"
}
```

#### 400 Bad Request - Password Too Short

```json
{
  "success": false,
  "message": "Mật khẩu mới phải có ít nhất 6 ký tự"
}
```

#### 404 Not Found - User Not Found

```json
{
  "success": false,
  "message": "Không tìm thấy thông tin người dùng"
}
```

#### 500 Internal Server Error

```json
{
  "success": false,
  "message": "Có lỗi xảy ra khi đổi mật khẩu. Vui lòng thử lại.",
  "error": "PASSWORD_CHANGE_ERROR"
}
```

## Usage Examples

### JavaScript/TypeScript (Fetch) - với Session Auth

```javascript
const changePasswordData = {
  current_password: "oldpassword123",
  new_password: "newpassword456",
  confirm_password: "newpassword456"
};

try {
  const response = await fetch('/api/v1/user/change-password', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    credentials: 'include', // Include cookies for session auth
    body: JSON.stringify(changePasswordData)
  });

  const data = await response.json();

  if (data.success) {
    console.log('Password changed successfully:', data.message);
    // Show success message to user
  } else {
    console.error('Password change failed:', data.message);
    // Show error message to user
  }
} catch (error) {
  console.error('Network error:', error);
}
```

### JavaScript/TypeScript (Fetch) - với Token Auth

```javascript
const token = localStorage.getItem('authToken');

try {
  const response = await fetch('/api/v1/user/change-password', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(changePasswordData)
  });

  const data = await response.json();
  // Handle response...
} catch (error) {
  console.error('Error:', error);
}
```

### cURL - với Session Auth

```bash
curl -X POST "https://your-domain.com/api/v1/user/change-password" \
  -H "Content-Type: application/json" \
  -H "Cookie: SESS123abc=def456ghi789" \
  -d '{
    "current_password": "oldpassword123",
    "new_password": "newpassword456",
    "confirm_password": "newpassword456"
  }'
```

### cURL - với Token Auth

```bash
curl -X POST "https://your-domain.com/api/v1/user/change-password" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-jwt-token" \
  -d '{
    "current_password": "oldpassword123",
    "new_password": "newpassword456",
    "confirm_password": "newpassword456"
  }'
```

## Security Features

1. **Authentication Required**: Chỉ user đã đăng nhập mới có thể đổi mật khẩu
2. **Current Password Verification**: Phải xác thực mật khẩu hiện tại
3. **Password Strength**: Mật khẩu mới phải có ít nhất 6 ký tự
4. **Password Confirmation**: Xác nhận mật khẩu để tránh lỗi gõ
5. **Password History**: Mật khẩu mới phải khác mật khẩu cũ
6. **Secure Hashing**: Mật khẩu được hash bằng Drupal's secure password API
7. **Activity Logging**: Tất cả thay đổi mật khẩu được ghi log

## Integration với Frontend

Endpoint này được thiết kế để tích hợp với React frontend sử dụng TanStack Query:

```typescript
// hooks/useChangePassword.ts
import { useMutation } from '@tanstack/react-query';

interface ChangePasswordData {
  current_password: string;
  new_password: string;
  confirm_password: string;
}

export const useChangePassword = () => {
  return useMutation({
    mutationFn: async (data: ChangePasswordData) => {
      const token = localStorage.getItem('authToken');
      const headers: HeadersInit = {
        'Content-Type': 'application/json',
      };
      
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
      
      const response = await fetch('/api/v1/user/change-password', {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify(data)
      });
      
      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message);
      }
      
      return response.json();
    }
  });
};
```

## Troubleshooting

### Common Issues

1. **"Bạn phải đăng nhập"**: User session đã hết hạn hoặc không có quyền truy cập
2. **"Mật khẩu hiện tại không chính xác"**: User nhập sai mật khẩu cũ
3. **"Mật khẩu mới và xác nhận không khớp"**: Lỗi gõ trong confirm password
4. **"Mật khẩu mới phải khác mật khẩu hiện tại"**: User cố gắng đặt lại mật khẩu cũ
5. **"Không tìm thấy thông tin người dùng"**: User account có thể đã bị xóa

### Debug Steps

1. **Kiểm tra authentication:**
   - Verify user đã đăng nhập
   - Check session hoặc token còn hợp lệ
   
2. **Kiểm tra Drupal logs:** `/admin/reports/dblog`

3. **Verify module và routes:**
   ```bash
   drush en dseza_api
   drush cr
   ```

4. **Test với cURL:**
   ```bash
   # Test authentication
   curl -H "Cookie: SESS..." /api/v1/user/change-password
   ```

5. **Check user permissions:**
   - User cần permission 'access content'
   - User phải có role 'authenticated user' trở lên

## Best Practices

1. **Frontend Validation**: Validate tất cả fields trước khi gửi request
2. **Secure Storage**: Không lưu mật khẩu trong localStorage
3. **User Feedback**: Hiển thị progress indicator khi đang xử lý
4. **Error Handling**: Xử lý tất cả error cases và hiển thị message phù hợp
5. **Session Management**: Auto-logout user nếu session hết hạn 