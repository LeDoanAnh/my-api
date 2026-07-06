# API Checklist theo code hiện tại

Tài liệu này được đối chiếu trực tiếp từ `routes/api.php` và các controller trong `app/Http/Controllers/Api`.

Quy ước:
- `Có`: đã thấy endpoint và controller xử lý trong code.
- `Bổ sung`: có trong code nhưng không nằm trong danh sách bạn gửi.
- `Chưa có`: chưa thấy endpoint riêng trong code hiện tại.

## a. Nhóm API xác thực

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Đăng nhập | `POST /api/login` | Có | Xác thực theo `username`, `password`, `request_token` |
| Đăng xuất | `POST /api/logout` | Có | Nằm trong middleware `auth:sanctum` |
| Đổi mật khẩu | `POST /api/change-password` | Có | Cần `current_password`, `password`, `password_confirmation` |
| Lấy thông tin người dùng | `GET /api/account?session_id=...` | Có | Trả profile, role, phòng ban, thống kê |
| Cập nhật FCM token | `POST /api/notifications/save-fcm-token` | Có | Nằm trong middleware `auth:sanctum` |
| Lấy request token | `GET /api/authentication/token/new` | Bổ sung | Phục vụ flow đăng nhập 2 bước |
| Tạo session | `POST /api/authentication/session/new` | Bổ sung | Đổi `request_token` sang `session_id` |
| Quên mật khẩu | `POST /api/forgot-password` | Bổ sung | Gửi mật khẩu mới qua email |

## b. Nhóm API tờ trình

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách tờ trình | `GET /api/v1/submissions?user_id=...&type=...` | Có | `type=pending_approval` dùng cho tab chờ duyệt |
| Tạo tờ trình | `POST /api/v1/submissions` | Có | Khi tạo xong sẽ khởi tạo dữ liệu bước duyệt và gửi thông báo |
| Xem chi tiết tờ trình | `GET /api/v1/submissions/{id}/detail` | Có | Có `status`, `logs`, `current_step`, `total_steps` |
| Xuất tờ trình PDF | `GET /api/v1/submissions/{id}/pdf` | Có | Dùng DomPDF |
| Lấy tờ trình gần đây | `GET /api/v1/submissions/recent` | Có | Trả 5 tờ trình gần nhất của user |
| Thống kê tờ trình | `GET /api/v1/user/statistics` | Có | Thống kê theo `user_id` |
| Cập nhật tờ trình | - | Chưa có | Chưa thấy endpoint riêng |
| Xóa/hủy tờ trình | - | Chưa có | Chưa thấy endpoint riêng |
| Gửi duyệt | - | Chưa có | Hiện trạng thái `pending` được tạo ngay trong `POST /submissions` |
| Theo dõi trạng thái | `GET /api/v1/submissions`, `GET /api/v1/submissions/{id}/detail` | Có phần | Trạng thái đã có trong response, nhưng chưa có route riêng chỉ để tra trạng thái |

## c. Nhóm API phê duyệt

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách tờ trình chờ duyệt | `GET /api/v1/submissions?type=pending_approval&user_id=...` | Có | Lọc theo role/phòng ban trong `SubmissionController@index` |
| Xem chi tiết để phê duyệt | `GET /api/v1/approver/submission/{submissionId}?dept_id=...` | Có | Trả `previous_approvals`, `pre_approval`, `my_decision` |
| Duyệt tờ trình | `POST /api/v1/approver/submission/{submissionId}/decide` | Có | Chỉ role 3, hỗ trợ `approved` |
| Từ chối tờ trình | `POST /api/v1/approver/submission/{submissionId}/decide` | Có | Cùng endpoint với duyệt, `action=rejected` |
| Yêu cầu chỉnh sửa | `POST /api/v1/approver/submission/{submissionId}/pre-sign` | Có | `action=revision_requested` |
| Lấy lịch sử phê duyệt | `GET /api/v1/approver/submission/{submissionId}?dept_id=...` | Có phần | Lịch sử nằm trong `previous_approvals` và `my_decision` |
| Lấy lịch sử phê duyệt riêng | - | Chưa có | Chưa thấy route tách riêng chỉ để lấy history |

## d. Nhóm API CSVC

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách CSVC | `GET /api/asset/list` | Có | Tương đương danh sách vật tư/thiết bị |
| Thêm CSVC | `POST /api/asset/create` | Có | Tạo vật tư mới |
| Cập nhật CSVC | `PUT /api/asset/update/{id}` | Có | Có cập nhật luôn `status` |
| Xóa CSVC | - | Chưa có | Chưa thấy endpoint xóa riêng |
| Xem chi tiết CSVC | `GET /api/asset/detail/{id}` | Có | Trả cả `current_request` và `history` |
| Cập nhật trạng thái thiết bị | `PUT /api/asset/update/{id}` | Có | Trạng thái đi qua payload `status` |
| Quản lý mượn/trả thiết bị | `GET /api/v1/borrow/list`, `POST /api/v1/borrow/{id}/confirm-receive`, `POST /api/v1/borrow/{id}/return` | Có | Xử lý người mượn xác nhận nhận và trả |
| Xác nhận thu hồi | `GET /api/v1/manager/recovery/list`, `POST /api/v1/manager/{id}/confirm-recovery` | Có | Dành cho phòng quản lý/đầu mối tài sản |
| Nhắc trả thiết bị | `POST /api/v1/manager/{id}/remind-return` | Có | Ghi notification nhắc trả |
| Lịch sử mượn/trả | `GET /api/v1/history/borrow`, `GET /api/v1/history/handover` | Có | Phục vụ tra cứu lịch sử |
| Danh sách task bàn giao | `GET /api/asset/asset-tasks`, `GET /api/asset/asset-tasks/{submissionId}` | Có | Hỗ trợ xử lý giao tài sản theo tờ trình |
| Bàn giao vật tư | `POST /api/asset/asset-tasks/{submissionId}/handover` | Có | Chuyển trạng thái sang `borrowed` |
| Tác vụ phòng ban vật tư | `GET /api/asset-submissions/tasks` | Có | Danh sách tờ trình đã duyệt theo phòng ban |

## e. Nhóm API phòng ban

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách phòng ban | `GET /api/department/list` | Có | Có hỗ trợ search |
| Thêm phòng ban | `POST /api/department/create` | Có | Tạo mới đơn vị |
| Sửa phòng ban | `PUT /api/department/{id}` | Có | Cập nhật thông tin phòng ban |
| Xóa phòng ban | `DELETE /api/department/{id}` | Có | Thực tế đang chuyển `status` sang `inactive` |
| Xem chi tiết phòng ban | `GET /api/department/{id}/detail` | Có | Có kèm users, assets, locations, submissions_count |
| Tài nguyên phòng ban | `GET /api/v1/departments/resources` | Có | Trả assets + locations + users_count |

## f. Nhóm API luồng phê duyệt

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách luồng phê duyệt | `GET /api/workflow/list` | Có | Trả danh sách `submission_categories` |
| Tạo luồng phê duyệt | `POST /api/workflow/store` | Có | Tạo category + approval steps |
| Xem chi tiết luồng phê duyệt | `GET /api/workflow/detail/{id}` | Có | Trả đầy đủ bước duyệt |
| Cập nhật luồng phê duyệt | `PUT /api/workflow/update/{id}` | Có | Xóa steps cũ rồi sync lại |
| Xóa luồng phê duyệt | - | Chưa có | Chưa thấy endpoint xóa riêng |

## g. Nhóm API thông báo

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy danh sách thông báo | `GET /api/notifications/list` | Có | Lọc theo `user_id` |
| Đánh dấu đã đọc | `POST|PATCH /api/notifications/{id}/read` | Có | Hỗ trợ cả `POST` và `PATCH` |
| Đánh dấu tất cả đã đọc | `POST /api/notifications/read_all/{user_id}` | Có | Cập nhật hàng loạt |
| Gửi thông báo | `POST /api/send-test-notification` | Có | Endpoint test, nằm trong `auth:sanctum` |
| Cập nhật FCM token | `POST /api/notifications/save-fcm-token` | Có | Dùng để lưu token thiết bị |

## h. Nhóm API thống kê và xuất PDF

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Thống kê tờ trình | `GET /api/v1/user/statistics` | Có | Thống kê theo người dùng |
| Thống kê theo phòng ban | `GET /api/department/{id}/detail` | Có phần | Có `users_count`, `assets_count`, `submissions_count` |
| Xuất tờ trình PDF | `GET /api/v1/submissions/{id}/pdf` | Có | Tạo file PDF từ view `pdf.submission` |
| Thống kê riêng theo phòng ban | - | Chưa có | Chưa thấy route thống kê tách riêng chỉ cho report |

## API bổ trợ hiện có trong code

| Mục | Endpoint | Trạng thái | Ghi chú |
|---|---|---:|---|
| Lấy lịch tờ trình | `GET /api/submissions/calendar` | Có | Trả event theo tháng/năm |
| Tìm kiếm nhanh | `GET /api/v1/search` | Có | Tìm tờ trình, vật tư, user, phòng ban |
| Lấy role | `GET /api/role/list` | Có | Trả danh sách vai trò |
| Danh sách nhân sự | `GET /api/users` | Có | Nằm trong `auth:sanctum` |
| CRUD actor | `GET/POST/PUT/DELETE /api/actor/*` | Có | Quản lý người dùng theo route legacy |
| CRUD location | `GET/POST/PUT /api/location/*` | Có | Quản lý địa điểm |

## Ghi chú rà soát

- `POST /api/v1/submissions` hiện đang là điểm tạo tờ trình và khởi tạo luồng duyệt, nên nếu tài liệu nghiệp vụ cần một nút “gửi duyệt” riêng thì hiện code chưa tách route riêng.
- `GET /api/v1/approver/submission/{submissionId}` đã bao gồm lịch sử phê duyệt theo bước, nên về mặt dữ liệu lịch sử đã có, chỉ là chưa tách thành endpoint độc lập.
- Các endpoint quản trị như `actor`, `department`, `asset`, `workflow`, `location` hiện đang khai báo ngoài `auth:sanctum` trong `routes/api.php`; nếu đây là môi trường thật thì nên rà soát lại phân quyền.
