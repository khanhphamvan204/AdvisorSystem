# Quick Start Guide - Docker cho Advisor System

## Khởi động ứng dụng

```bash
# Lần đầu tiên HOẶC khi muốn reset database
docker-compose down -v  # Xóa volumes
docker-compose up -d    # Start lại

# Lần sau chỉ cần
docker-compose up -d
```

## Cách hoạt động

1. **MySQL** start và auto-import `advisor_system.sql` (lần đầu)
2. **App container** check database, nếu rỗng → import SQL
3. **Queue worker** start sau khi app ready
4. **Nginx** serve requests tại http://localhost:8000

## Các lệnh hữu ích

```bash
# Xem logs
docker-compose logs -f app

# Vào container
docker-compose exec app sh

# Chạy artisan commands
docker-compose exec app php artisan cache:clear

# Stop tất cả
docker-compose down

# Reset toàn bộ (XÓA DATABASE!)
docker-compose down -v
docker-compose up -d
```

## Troubleshooting

**Database rỗng?**

```bash
docker-compose down -v
docker-compose up -d
```

**Port conflict?**
Sửa port trong `docker-compose.yml`:

-   MySQL: `3307:3306` → `33060:3306`
-   App: `8000:80` → `8080:80`

**Logs lỗi?**

```bash
docker-compose logs app
docker-compose logs mysql
```
