# Contact Form API Documentation

## Endpoint
`POST /api/v1/submit-contact-form`

## Mô tả
API endpoint để gửi form liên hệ từ website. Khi người dùng gửi form, hệ thống sẽ gửi email đến địa chỉ admin được cấu hình.

## Request

### Method
`POST`

### Headers
```
Content-Type: application/json
```

### Request Body
```json
{
  "hoTen": "Nguyễn Văn A",
  "email": "user@example.com",
  "tieuDe": "Câu hỏi về dịch vụ",
  "noiDung": "Tôi muốn biết thêm thông tin về dịch vụ của công ty..."
}
```

### Các trường bắt buộc:
- `hoTen` (string): Họ tên người gửi
- `email` (string): Địa chỉ email hợp lệ
- `tieuDe` (string): Tiêu đề thư liên hệ
- `noiDung` (string): Nội dung thư liên hệ

## Response

### Thành công (200)
```json
{
  "status": "success",
  "message": "Thư liên hệ đã được gửi thành công"
}
```

### Lỗi Validation (400)
```json
{
  "status": "error",
  "message": "Trường hoTen là bắt buộc"
}
```

### Lỗi Email không hợp lệ (400)
```json
{
  "status": "error",
  "message": "Địa chỉ email không hợp lệ"
}
```

### Lỗi JSON không hợp lệ (400)
```json
{
  "status": "error",
  "message": "Dữ liệu JSON không hợp lệ"
}
```

### Lỗi Server (500)
```json
{
  "status": "error",
  "message": "Có lỗi xảy ra khi gửi thư liên hệ"
}
```

## Cách thức hoạt động

1. API nhận request POST với dữ liệu JSON
2. Validate các trường bắt buộc và định dạng email
3. Sử dụng Drupal Mail Manager để gửi email
4. Email được gửi đến `admin@dseza.gov.vn`
5. Tiêu đề email: "Thư liên hệ từ website: [Tiêu đề người dùng nhập]"
6. Nội dung email bao gồm tất cả thông tin người dùng đã nhập
7. Trả về response JSON với kết quả

## Ví dụ sử dụng với JavaScript

```javascript
const contactData = {
  hoTen: "Nguyễn Văn A",
  email: "user@example.com",
  tieuDe: "Câu hỏi về dịch vụ",
  noiDung: "Tôi muốn biết thêm thông tin về dịch vụ của công ty..."
};

fetch('/api/v1/submit-contact-form', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify(contactData)
})
.then(response => response.json())
.then(data => {
  if (data.status === 'success') {
    alert('Thư liên hệ đã được gửi thành công!');
  } else {
    alert('Lỗi: ' + data.message);
  }
})
.catch(error => {
  console.error('Error:', error);
  alert('Đã có lỗi xảy ra');
});
```

## Lưu ý
- API hỗ trợ CORS để có thể gọi từ frontend
- Tất cả các thông tin được gửi sẽ được log lại để theo dõi
- Email được gửi qua Drupal Mail Manager, cần cấu hình SMTP server phù hợp
- Có thể thay đổi địa chỉ email admin trong file Controller 