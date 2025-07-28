# User Registration API

API endpoint để đăng ký user mới trong hệ thống DSEZA.

## Endpoint

```
POST /api/v1/user/register
```

## Request Headers

```
Content-Type: application/json
```

## Request Body

```json
{
  "name": "Nguyễn Văn A",
  "email": "user@example.com",
  "password": "password123",
  "password_confirm": "password123"
}
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Tên người dùng (ít nhất 2 ký tự) |
| `email` | string | Yes | Địa chỉ email hợp lệ |
| `password` | string | Yes | Mật khẩu (ít nhất 6 ký tự) |
| `password_confirm` | string | Yes | Xác nhận mật khẩu (phải khớp với password) |

## Response

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Đăng ký thành công! Tài khoản của bạn đã được tạo.",
  "user_id": "123",
  "user_name": "Nguyễn Văn A",
  "user_role": "authenticated"
}
```

### Error Responses

#### 400 Bad Request - Validation Error

```json
{
  "success": false,
  "message": "Tên người dùng phải có ít nhất 2 ký tự"
}
```

#### 409 Conflict - Email Already Exists

```json
{
  "success": false,
  "message": "Email này đã được sử dụng"
}
```

#### 500 Internal Server Error

```json
{
  "success": false,
  "message": "Có lỗi xảy ra khi đăng ký. Vui lòng thử lại.",
  "error": "REGISTRATION_ERROR"
}
```

## Usage Examples

### JavaScript/TypeScript (Fetch)

```javascript
const registrationData = {
  name: "Nguyễn Văn A",
  email: "user@example.com",
  password: "password123",
  password_confirm: "password123"
};

try {
  const response = await fetch('/api/v1/user/register', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(registrationData)
  });

  const data = await response.json();

  if (data.success) {
    console.log('Registration successful:', data.message);
    // Redirect to login or home page
  } else {
    console.error('Registration failed:', data.message);
  }
} catch (error) {
  console.error('Network error:', error);
}
```

### cURL

```bash
curl -X POST "https://your-domain.com/api/v1/user/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Nguyễn Văn A",
    "email": "user@example.com",
    "password": "password123",
    "password_confirm": "password123"
  }'
```

## Security Notes

1. **No Auto-Login**: Endpoint không tự động đăng nhập user sau khi đăng ký thành công vì lý do bảo mật.
2. **Password Validation**: Mật khẩu phải có ít nhất 6 ký tự.
3. **Email Uniqueness**: Email phải là duy nhất trong hệ thống.
4. **CORS**: Endpoint hỗ trợ CORS headers cho cross-origin requests.

## Integration với Frontend

Endpoint này được thiết kế để tích hợp với React frontend sử dụng TanStack Query:

```typescript
// hooks/useRegisterUser.ts
import { useMutation } from '@tanstack/react-query';

interface RegistrationData {
  name: string;
  email: string;
  password: string;
  password_confirm: string;
}

export const useRegisterUser = () => {
  return useMutation({
    mutationFn: async (data: RegistrationData) => {
      const response = await fetch('/api/v1/user/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

1. **"Email này đã được sử dụng"**: Email đã tồn tại trong hệ thống. Sử dụng email khác hoặc đăng nhập.
2. **"Invalid JSON format"**: Request body không phải là JSON hợp lệ.
3. **"Mật khẩu xác nhận không khớp"**: password và password_confirm không giống nhau.
4. **500 Error**: Kiểm tra Drupal logs để xem chi tiết lỗi.

### Debug Steps

1. Kiểm tra Drupal watchdog logs: `/admin/reports/dblog`
2. Verify module dseza_api đã được enable
3. Clear Drupal cache: `drush cr`
4. Kiểm tra user permissions và roles configuration 