# Docker Setup - Hệ thống Quản lý Cố vấn Học tập

Hướng dẫn sử dụng Docker cho ứng dụng Laravel Advisor System.

## Yêu cầu

-   Docker Engine 20.10+
-   Docker Compose v2.0+
-   Ít nhất 4GB RAM khả dụng
-   Port 8000, 3306, 27017, 6379 chưa được sử dụng

## Cấu trúc Services

Hệ thống bao gồm 6 containers:

-   **app**: Laravel PHP-FPM application
-   **queue**: Queue worker xử lý background jobs
-   **nginx**: Web server
-   **mysql**: MySQL 8.0 database
-   **mongodb**: MongoDB 7 database
-   **redis**: Redis cache & queue

## Cài đặt & Khởi chạy

### 1. Clone repository và cấu hình

```bash
cd advisor_system
```

### 2. Tạo file .env (nếu chưa có)

Copy file `.env` hiện tại hoặc tạo mới với các biến sau:

```env
APP_NAME="Advisor System"
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=db_advisorsystem
DB_USERNAME=root
DB_PASSWORD=secret

MONGODB_DSN=mongodb://mongodb:27017

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### 3. Build và khởi động containers

```bash
# Build images
docker-compose build

# Khởi động tất cả services
docker-compose up -d

# Xem logs
docker-compose logs -f
```

### 4. Generate application key (lần đầu)

```bash
docker-compose exec app php artisan key:generate
```

### 5. Chạy migrations

```bash
# Database sẽ tự động được import từ advisor_system.sql
# Nếu cần chạy migrations:
docker-compose exec app php artisan migrate --force
```

### 6. Tạo symbolic link cho storage

```bash
docker-compose exec app php artisan storage:link
```

## Truy cập ứng dụng

-   **Application**: http://localhost:8000
-   **MySQL**: localhost:3306
-   **MongoDB**: localhost:27017
-   **Redis**: localhost:6379

## Các lệnh thường dùng

### Container Management

```bash
# Xem trạng thái containers
docker-compose ps

# Dừng tất cả services
docker-compose down

# Dừng và xóa volumes (XÓA TOÀN BỘ DỮ LIỆU!)
docker-compose down -v

# Restart một service
docker-compose restart app
docker-compose restart queue
```

### Laravel Commands

```bash
# Chạy artisan commands
docker-compose exec app php artisan <command>

# Ví dụ:
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:list

# Chạy composer
docker-compose exec app composer install
docker-compose exec app composer update
```

### Database Commands

```bash
# Connect vào MySQL
docker-compose exec mysql mysql -u root -psecret db_advisorsystem

# Export database
docker-compose exec mysql mysqldump -u root -psecret db_advisorsystem > backup.sql

# Import database
docker-compose exec -T mysql mysql -u root -psecret db_advisorsystem < backup.sql

# Connect vào MongoDB
docker-compose exec mongodb mongosh
```

### Logs & Debugging

```bash
# Xem logs của tất cả containers
docker-compose logs -f

# Xem logs của một service cụ thể
docker-compose logs -f app
docker-compose logs -f mysql
docker-compose logs -f queue

# Xem 100 dòng logs cuối
docker-compose logs --tail=100 app

# Truy cập vào container
docker-compose exec app sh
docker-compose exec mysql bash
```

### Queue Management

```bash
# Restart queue worker
docker-compose restart queue

# Xem queue jobs
docker-compose exec app php artisan queue:monitor

# Clear failed jobs
docker-compose exec app php artisan queue:flush
```

## Development Workflow

### 1. Code thay đổi

Khi bạn thay đổi code, không cần restart container vì:

-   Source code được mount vào container qua volumes
-   PHP-FPM sẽ tự động load code mới

### 2. Thay đổi .env

```bash
# Clear cache config
docker-compose exec app php artisan config:clear

# Hoặc restart container
docker-compose restart app
```

### 3. Cài package mới

```bash
# Cài Composer package
docker-compose exec app composer require vendor/package

# Cài package và rebuild autoload
docker-compose exec app composer dump-autoload
```

### 4. Database changes

```bash
# Tạo migration mới
docker-compose exec app php artisan make:migration create_table_name

# Chạy migrations
docker-compose exec app php artisan migrate

# Rollback migration
docker-compose exec app php artisan migrate:rollback
```

## Troubleshooting

### Container không start

```bash
# Xem logs để biết lỗi
docker-compose logs app

# Kiểm tra port conflicts
netstat -ano | findstr :8000
netstat -ano | findstr :3306
```

### Permission errors

```bash
# Fix permissions cho storage và cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Database connection failed

```bash
# Kiểm tra MySQL đang chạy
docker-compose ps mysql

# Kiểm tra MySQL logs
docker-compose logs mysql

# Test connection
docker-compose exec app php artisan db:show
```

### Queue không chạy

```bash
# Restart queue worker
docker-compose restart queue

# Xem queue logs
docker-compose logs -f queue

# Clear và restart
docker-compose exec app php artisan queue:restart
docker-compose restart queue
```

### Reset toàn bộ (XÓA DỮ LIỆU!)

```bash
# Dừng và xóa tất cả
docker-compose down -v

# Xóa images
docker-compose down --rmi all

# Build lại từ đầu
docker-compose build --no-cache
docker-compose up -d
```

## Production Deployment

Để deploy production, thay đổi các biến môi trường:

1. Sửa `docker-compose.yml`:

    - Đổi `target: development` thành `target: production`
    - Set `APP_ENV=production`
    - Set `APP_DEBUG=false`

2. Build production image:

```bash
docker-compose build --no-cache
```

3. Khởi động với production config:

```bash
docker-compose up -d
```

## Backup & Restore

### Backup

```bash
# Backup MySQL
docker-compose exec mysql mysqldump -u root -psecret db_advisorsystem > mysql_backup_$(date +%Y%m%d).sql

# Backup MongoDB
docker-compose exec mongodb mongodump --out=/tmp/mongodb_backup
docker cp advisor_system_mongodb:/tmp/mongodb_backup ./mongodb_backup_$(date +%Y%m%d)

# Backup uploaded files
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/app
```

### Restore

```bash
# Restore MySQL
docker-compose exec -T mysql mysql -u root -psecret db_advisorsystem < mysql_backup_20250202.sql

# Restore MongoDB
docker cp mongodb_backup_20250202 advisor_system_mongodb:/tmp/
docker-compose exec mongodb mongorestore /tmp/mongodb_backup_20250202

# Restore uploaded files
tar -xzf storage_backup_20250202.tar.gz
```

## Liên hệ & Hỗ trợ

Nếu gặp vấn đề, vui lòng:

1. Kiểm tra logs: `docker-compose logs`
2. Xem lại các bước cài đặt
3. Đảm bảo ports không bị conflict
4. Đảm bảo đủ RAM và disk space

---

**Lưu ý**: File này là cho môi trường development. Production deployment cần cấu hình bảo mật và performance tuning bổ sung.
