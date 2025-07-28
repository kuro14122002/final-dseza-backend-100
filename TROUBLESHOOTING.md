# API Troubleshooting Guide

## Vấn đề phổ biến và cách khắc phục

### 1. Enable Module dseza_api

**Cách duy nhất (Qua Drupal Admin):**
1. Truy cập: `https://dseza-backend.lndo.site/admin/modules`
2. Đăng nhập với tài khoản admin
3. Tìm module **"Dseza API"** 
4. Tick checkbox và click **"Install"**
5. **Configuration > Performance > Clear all caches**

### 2. Tạo Content Type "Question"

Nếu gặp lỗi 500, có thể do chưa có content type:

1. **Structure > Content types** (`/admin/structure/types`)
2. **Add content type**: 
   - Name: `Question`
   - Machine name: `question`
3. **Save and manage fields**
4. Thêm các fields sau:

   | Field Label | Machine Name | Type | Required |
   |-------------|--------------|------|----------|
   | Người gửi | `field_nguoi_gui` | Text (plain) | Yes |
   | Email | `field_email` | Email | Yes |
   | Nội dung câu hỏi | `field_noi_dung_cau_hoi` | Text (formatted, long) | Yes |
   | Điện thoại | `field_dien_thoai` | Text (plain) | No |
   | Công ty | `field_cong_ty` | Text (plain) | No |

### 3. Test API

**Quick Test:**
```javascript
// Test endpoint
fetch('https://dseza-backend.lndo.site/api/v1/test')
  .then(r => r.json())
  .then(console.log);

// Test submit
fetch('https://dseza-backend.lndo.site/api/v1/submit-question', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    hoTen: "Test User",
    email: "test@example.com",
    tieuDe: "Test Question",
    noiDung: "Test content"
  })
}).then(r => r.json()).then(console.log);
```

**Hoặc test trong Browser Console:**
- Mở Dev Tools và dán code test vào Console

### 4. Lỗi thường gặp

| Error | Nguyên nhân | Giải pháp |
|-------|-------------|-----------|
| **404** | Module chưa enable | Enable module + clear cache |
| **500** | Thiếu content type/fields | Tạo content type "question" + fields |
| **CORS** | Browser blocking request | API đã được config CORS |

### 5. Environment Configuration

**Frontend API URL:**
- Default: `https://dseza-backend.lndo.site`
- Override: Tạo `frontend/.env.local` với `VITE_API_TARGET=your-url`

### 6. Logs để debug

- **Drupal logs:** Reports > Recent log messages
- **Browser Network tab:** Kiểm tra request/response
- **Console logs:** Xem chi tiết lỗi

---

**Tóm tắt checklist:**
1. ✅ Module "Dseza API" đã enable?
2. ✅ Content type "question" đã tạo?
3. ✅ Các fields required đã có?
4. ✅ Cache đã clear?
5. ✅ Test endpoint `/api/v1/test` trả về success? 