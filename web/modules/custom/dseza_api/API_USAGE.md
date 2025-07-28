# Dseza API - Hướng dẫn sử dụng

## API Endpoint: Gửi câu hỏi

### URL
```
POST /api/v1/submit-question
```

### Headers
```
Content-Type: application/json
```

### Dữ liệu gửi (JSON)

#### Các trường bắt buộc:
- `hoTen` (string): Họ và tên người gửi
- `email` (string): Địa chỉ email hợp lệ
- `tieuDe` (string): Tiêu đề câu hỏi
- `noiDung` (string): Nội dung câu hỏi

#### Các trường tùy chọn:
- `dienThoai` (string): Số điện thoại
- `congTy` (string): Tên công ty

### Ví dụ request:
```json
{
  "hoTen": "Nguyễn Văn A",
  "email": "nguyenvana@example.com",
  "tieuDe": "Câu hỏi về sản phẩm",
  "noiDung": "Tôi muốn biết thêm thông tin về sản phẩm XYZ",
  "dienThoai": "0123456789",
  "congTy": "Công ty ABC"
}
```

### Phản hồi thành công (201):
```json
{
  "status": "success",
  "message": "Câu hỏi của bạn đã được gửi thành công và đang chờ được duyệt",
  "question_id": 123
}
```

### Phản hồi lỗi (400):
```json
{
  "status": "error",
  "message": "Trường hoTen là bắt buộc"
}
```

### Phản hồi lỗi (500):
```json
{
  "status": "error",
  "message": "Có lỗi xảy ra trong quá trình xử lý. Vui lòng thử lại sau."
}
```

## Lưu ý
- Tất cả câu hỏi được gửi sẽ có trạng thái "unpublished" và cần được duyệt bởi admin
- API hỗ trợ CORS để có thể gọi từ frontend
- Tất cả dữ liệu đầu vào đều được validate và sanitize 