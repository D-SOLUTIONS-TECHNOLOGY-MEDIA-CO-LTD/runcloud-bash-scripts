# Runbook vận hành fleet

Quy trình vận hành fleet WordPress trên RunCloud của D-Solutions. Tài liệu này nói **khi nào làm gì và theo thứ tự nào** — mô tả từng script xem [README](../README.md).

Chạy được từ 2 nơi: **SSH thẳng vào server**, hoặc **dashboard** ([fleet-script-runner](https://github.com/D-SOLUTIONS-TECHNOLOGY-MEDIA-CO-LTD/fleet-script-runner)). Runbook ghi lệnh SSH vì đó là dạng gốc; trên dashboard là cùng lệnh đó.

---

## Nguyên tắc an toàn (áp dụng cho mọi quy trình)

1. **Đọc trước, sửa sau.** Luôn chạy bản `--dry-run` / `status` / audit trước khi chạy bản thật.
2. **Sao lưu trước khi xoá.** Đụng tới malware → `wp-malware-quarantine.sh` trước, xoá sau. Không có bằng chứng thì không điều tra được đợt tái nhiễm.
3. **Không chạy việc nặng giờ cao điểm.** Quét wp-cli, `tweak-mycnf.sh` (restart MariaDB), update core → làm đêm/sáng sớm.
4. **Một server một lần khi làm việc nguy hiểm.** Chỉ fan-out toàn fleet với script chỉ-đọc.
5. **Ghi lại.** Chạy qua dashboard thì tab HISTORY tự lưu; chạy qua SSH thì ghi log ra file.

---

## Nhịp vận hành định kỳ

| Tần suất | Việc | Lệnh / nơi làm |
|---|---|---|
| Tự động, 5 phút | Đẩy số liệu hệ thống | cron `server-metrics.sh --quick` |
| Tự động, hằng ngày | Quét WP version + số update | cron `server-metrics.sh` (bản đầy đủ) |
| Tự động, hằng ngày | Dọn cache/log | cron `cleanup-disk.sh --system-only` |
| **Hằng ngày** (người) | Xem dashboard: server `critical`, server `unreachable`, site nợ update nhiều | tab SERVERS + SITES |
| **Hằng tuần** | Quét malware toàn fleet | `wp-malware-scan.sh` (chỉ đọc, fan-out được) |
| **Hằng tuần** | Kiểm tra sức khoẻ site | `wp-health-check.sh` |
| **2 tuần/lần** | Quét CVE plugin/theme site quan trọng | `wp-vuln-check.sh --site=...` |
| **Hằng tháng** | Audit quyền sở hữu file | `wp-ownership-audit.sh` |
| **Hằng tháng** | Cửa sổ bảo trì: cập nhật WP | xem [Quy trình 5](#quy-trình-5--cửa-sổ-bảo-trì-cập-nhật-wordpress) |
| **Sau mỗi sự cố malware** | Quét lại sau 2 tuần | malware này hay quay lại theo đợt |

### Cron chuẩn trên mỗi server

```cron
# /etc/cron.d/ds-ops   (chạy user root)
# Số liệu nhẹ → dashboard
*/5 * * * * root WEBHOOK_URL=http://<runner>:8091/webhook/server-metrics WEBHOOK_SECRET=<secret> \
  /root/runcloud-bash-scripts/server-metrics.sh --quick >> /var/log/ds-metrics.log 2>&1

# Quét đầy đủ (wp-cli) — LỆCH PHÚT giữa các server
17 3 * * * root WEBHOOK_URL=http://<runner>:8091/webhook/server-metrics WEBHOOK_SECRET=<secret> \
  /root/runcloud-bash-scripts/server-metrics.sh >> /var/log/ds-metrics.log 2>&1

# Dọn rác hệ thống
0 2 * * * root /root/runcloud-bash-scripts/cleanup-disk.sh --system-only >> /var/log/ds-cleanup.log 2>&1
```

> **Không bật cron `self-update.sh`.** Tự `git pull` + `chmod +x` theo lịch là rủi ro chuỗi cung ứng. Deploy thủ công sau khi đã review diff.

---

## Quy trình 1 — Đưa server mới vào fleet

1. Cài scripts:
   ```bash
   ssh -p <port> root@<host> 'cd /root && git clone <repo-url> runcloud-bash-scripts && \
     cd runcloud-bash-scripts && chmod +x *.sh'
   ```
2. Copy SSH key của máy chạy dashboard lên server (để chạy script từ xa).
3. Cắm cron ở trên (đổi `<runner>`, `<secret>`, **lệch phút** dòng quét đầy đủ).
4. Kiểm tra push:
   ```bash
   ssh -p <port> root@<host> '/root/runcloud-bash-scripts/server-metrics.sh --quick --print' \
     | grep -o '"ssh_port":[0-9]*'
   ```
   Không thấy `ssh_port` → scripts còn cũ, `git pull` lại.
5. Đợi ≤5 phút → server phải xuất hiện trên dashboard.
6. Chạy baseline: `wp-ownership-audit.sh` + `wp-malware-scan.sh` để biết tình trạng ban đầu.

---

## Quy trình 2 — Site nghi nhiễm malware 🔴

Dấu hiệu: CPU cao bất thường, site lỗi 500, plugin lạ, khách báo bị chuyển hướng.

### B1. Xác nhận (chỉ đọc)
```bash
./wp-malware-scan.sh --site=<site> --verify-core
```
Quét theo **cấu trúc** 3 họ: fake-plugin nạp `.txt` + `eval`; backdoor XOR (`hex2bin` + `$_COOKIE`); webshell trong `uploads/`. `--verify-core` bắt file lạ nhét vào core.

Có chữ ký riêng của D-Solutions → thêm `--ioc-file=/root/ds-iocs.txt` (mỗi dòng 1 regex; **không** commit file này vào repo).

### B2. Khoanh vùng
Không cần tắt site nếu vẫn phục vụ bình thường — WAF/Cloudflare chặn đủ. Đánh giá phạm vi: chạy quét trên **toàn bộ site cùng server** (malware thường deploy hàng loạt).

### B3. Sao lưu bằng chứng — **bắt buộc, trước khi xoá**
```bash
./wp-malware-quarantine.sh --site=<site> \
  --paths="wp-content/plugins/<thumuc-la> wp-content/uploads/2023/05/<file>.php"
```
Đóng gói từng đường dẫn, dump DB, lưu `active_plugins` để rollback → `/root/malware-quarantine/<timestamp>/`. Tự đặt cron xoá sau 30 ngày.

### B4. Phân tích (tuỳ chọn, không bao giờ thực thi payload)
```bash
php wp-decode-payload.php <file-gia-anh>.png | grep -oE 'https?://[a-zA-Z0-9./_-]+'
```
Chỉ **in ra**, không `eval`. Dùng để lấy IOC (domain C2) rồi bổ sung vào file IOC riêng.

### B5. Dọn
1. Xoá thư mục/file malware (giữ lại `index.php` "Silence is golden" 28 byte — đó là file chuẩn của WP).
2. Dọn `active_plugins` trong DB (không thì WP cảnh báo mỗi request).
3. Thay core: `wp core download --force --skip-content` rồi `wp core verify-checksums`.
4. Kiểm tra "cắm rễ" trong DB: option chứa `eval|base64_decode|gzinflate|hex2bin`, user admin lạ, cron event lạ.
5. Vá lỗ vào: `./wp-vuln-check.sh --site=<site> --include-core` rồi cập nhật plugin dính CVE.

### B6. Xác minh & phòng tái nhiễm
```bash
./wp-malware-scan.sh --site=<site> --verify-core   # phải sạch
./wp-health-check.sh --site=<site>                 # site trả về bình thường
./wp-freeze.sh --site=<site> --action=freeze       # khoá filesystem sau khi dọn
```
**Quét lại sau 2 tuần.** Họ malware này quay lại theo đợt; sạch 1 lần không có nghĩa là xong.

> ⚠️ Quét **cả 3 họ** rồi mới kết luận sạch. Sự cố trước đó tái nhiễm nhiều đợt vì mỗi lần chỉ dọn 1 họ.

---

## Quy trình 3 — Site lỗi 500 / trắng trang

1. Xác nhận bằng PHP thật của site (không phải PHP CLI hệ thống):
   ```bash
   ./wp-health-check.sh --site=<site>
   ```
2. Đọc lỗi:
   - `DivisionByZeroError ... eval()'d code`, lỗi trong file `.txt` → **malware**, sang [Quy trình 2](#quy-trình-2--site-nghi-nhiễm-malware-).
   - Lỗi DB → kiểm creds trong `wp-config.php`, `wp db check`.
   - Fatal ở plugin/theme → tắt plugin nghi ngờ bằng wp-cli.
3. Nghi vấn quyền file (rất hay gặp sau khi upload tay / cập nhật core):
   ```bash
   ./wp-ownership-audit.sh --site=<site>     # xem trước
   ./wp-ownership-audit.sh --site=<site> --fix
   ./fix-permission-site.sh --site=<site>
   ```
4. Vẫn lỗi → `./debug-wp-cli.sh` để soi môi trường wp-cli.

---

## Quy trình 4 — Ổ đĩa gần đầy

Cảnh báo Telegram ở mức disk ≥ 85%.

1. Xem sẽ xoá gì (không xoá thật):
   ```bash
   ./cleanup-disk.sh --dry-run
   ```
2. Dọn hệ thống trước (an toàn nhất — swap LiteSpeed thường 5-15 GB):
   ```bash
   ./cleanup-disk.sh --system-only
   ```
3. Chưa đủ → dọn cache theo site: `./cleanup-disk.sh --site=<site>`
4. Vẫn đầy → tìm thủ phạm:
   ```bash
   du -sh /home/*/webapps/*/ | sort -h | tail -10
   du -sh /home/*/webapps/*/wp-content/uploads | sort -h | tail -5
   ```
   Hay gặp: backup cũ trong `wp-content`, log ứng dụng, ảnh chưa tối ưu.

Mọi thứ `cleanup-disk.sh` xoá đều tự sinh lại — không mất dữ liệu.

---

## Quy trình 5 — Cửa sổ bảo trì cập nhật WordPress

Site đã freeze thì **không tự cập nhật được** — phải mở khoá trong cửa sổ bảo trì.

```bash
# 1. Xem có gì cần cập nhật
./wp-update.sh --site=<site> --action=status

# 2. Mở khoá (nếu site đang freeze)
./wp-freeze.sh --site=<site> --action=unfreeze

# 3. Thử trước
./wp-update.sh --site=<site> --action=plugins --dry-run

# 4. Cập nhật thật (loại trừ plugin premium cần license)
./wp-update.sh --site=<site> --action=plugins --exclude=acf-pro,gravityforms
./wp-update.sh --site=<site> --action=core

# 5. Kiểm tra site còn sống
./wp-health-check.sh --site=<site>

# 6. Khoá lại
./wp-freeze.sh --site=<site> --action=freeze
```

Plugin premium (Crocoblock, Elementor Pro, WP Mail SMTP Pro…) **không** update qua wp.org — làm qua panel/license riêng.

---

## Quy trình 6 — Bàn giao / launch site mới

1. Migrate lên production: `./wp-migration.sh` hoặc `./wp-local-to-production.sh` (xem README cho tham số).
2. Sửa quyền: `./fix-permission-site.sh --site=<site>`
3. Baseline bảo mật:
   ```bash
   ./wp-malware-scan.sh --site=<site>
   ./wp-vuln-check.sh --site=<site> --include-core
   ```
4. Dọn git nếu site quản lý bằng git: `./wp-git-cleanup.sh --site=<site> --action=scan`
5. **Khoá site sau khi khách nghiệm thu**:
   ```bash
   ./wp-freeze.sh --site=<site> --action=freeze
   ```
   Sau khi khoá: đăng bài / upload media **vẫn được**; cài plugin, sửa file, quản lý user thì **không**.

---

## Quy trình 7 — Server tải cao / MySQL chậm

1. Xem chỉ số trên dashboard (CPU, load, RAM) — load 1m > số core là đang quá tải.
2. Loại trừ malware trước (đào coin/botnet hay làm CPU 100%): [Quy trình 2](#quy-trình-2--site-nghi-nhiễm-malware-).
3. Tinh chỉnh MariaDB:
   ```bash
   ./tweak-mycnf.sh --status     # xem cấu hình hiện tại
   ./tweak-mycnf.sh --dry-run    # xem sẽ đổi gì
   ./tweak-mycnf.sh              # áp dụng — CÓ RESTART MariaDB
   ./tweak-mycnf.sh --restore    # lùi lại nếu hỏng
   ```
   ⚠️ Restart MariaDB = downtime vài giây cho **mọi site** trên server đó. Làm ngoài giờ cao điểm. Script tự rollback nếu MariaDB không khởi động lại được.

---

## Xử lý khi có cảnh báo Telegram

| Cảnh báo | Việc đầu tiên |
|---|---|
| `disk ≥ 85%` | [Quy trình 4](#quy-trình-4--ổ-đĩa-gần-đầy) |
| `cpu ≥ 90%` | Kiểm malware trước ([QT 2](#quy-trình-2--site-nghi-nhiễm-malware-)), rồi [QT 7](#quy-trình-7--server-tải-cao--mysql-chậm) |
| `ram ≥ 90%` | Xem `tweak-mycnf.sh --status` (buffer pool đặt quá tay?), kiểm process rò rỉ |

Mỗi chỉ số chỉ báo **một lần** cho tới khi hồi phục — im lặng không có nghĩa là đã ổn, kiểm tra lại trên dashboard.

---

## Bẫy thường gặp

- **Không hardcode `/home/runcloud/`** — site nằm rải theo user: `/home/*/webapps/`.
- **wp-cli phải chạy đúng user của site** (`sudo -u <owner>`), chạy bằng root sinh file root-owned → hỏng site về sau.
- **`git checkout -- .` trước khi `git pull`** trên server: `chmod +x` tạo diff filemode chặn pull.
- **wp-cli không có trong PATH khi chạy cron** — script đã tự dò, đừng giả định.
- **`wp-vuln-check.sh` có dương tính giả**: dòng dạng `== <version cũ>` và `<= x (unfixed)` — đối chiếu version thật trước khi hành động.
- **`/tmp` bị dọn giữa chừng** — file chạy dài đặt ở `/root/`, không để `/tmp`.
- **Site đã freeze** sẽ khiến script sửa đổi thất bại — nhớ unfreeze trong cửa sổ bảo trì.

---

## Lưu trữ bằng chứng & log

| Nơi | Nội dung | Giữ bao lâu |
|---|---|---|
| `/root/malware-quarantine/<ts>/` | Mẫu malware + dump DB | 30 ngày (cron tự xoá) |
| `/var/log/ds-metrics.log` | Log đẩy số liệu | xoay vòng bằng logrotate |
| `/var/log/ds-cleanup.log` | Log dọn dẹp | xoay vòng bằng logrotate |
| Tab HISTORY trên dashboard | Mọi lần chạy qua giao diện + log | tới khi xoá thủ công |

Quarantine chứa mẫu malware sống + dump DB → chỉ `root` đọc (script tự set `700`). Không copy ra máy cá nhân.
