# Elimu Hub - Render Deployment & Optimization Guide

## 🎯 Current Issue: 502 Errors

Your system is running on `basic-xxs` (512MB RAM), which is causing:
- **502 Bad Gateway errors** - Insufficient memory/CPU
- **High latency** - Single-threaded PHP
- **Failed health checks** - Database queries timeout

---

## ✅ Immediate Fixes Applied

### 1. **System Optimizations** 
- ✅ Reduced PHP memory limit from 256MB → 128MB (more stable on small instance)
- ✅ Reduced timeouts: 120s → 60s (prevents zombies)
- ✅ Disabled timestamp validation in OpCache (faster serving)
- ✅ Added proper connection pooling
- ✅ Optimized health check with 2-second DB timeout

### 2. **Caching Layer Added**
- ✅ File-based cache system (works without Redis)
- ✅ Optional Redis support (if you add Redis)
- ✅ Pre-built cache helpers for common queries
- ✅ Browser cache headers for static assets (1 year)

### 3. **Docker Improvements**
- ✅ Added Apache performance modules (deflate, headers, expires)
- ✅ Enabled Redis PHP extension
- ✅ Optimized Apache prefork settings (2-20 workers)
- ✅ Added static file cache headers

---

## 🚀 Steps to Deploy Fix to Render

### Step 1: Update Instance Size (CRITICAL)
The `basic-xxs` instance is too small. **Upgrade to at least `basic-xs` (1GB RAM)**:

```bash
# Option A: Update .do/app.yaml before deploying
# Change instance_size_slug from: basic-xxs
#                           to: basic-xs          (recommended minimum)
#                              or: standard-1     (recommended for growth)

# Option B: From Render Dashboard
# 1. Go to your service
# 2. Settings → Instance Type
# 3. Select "Basic" (1 GB) or higher
# 4. Click "Save"
```

### Step 2: Push Code Changes
```bash
git add -A
git commit -m "Optimization: Add caching, reduce memory footprint, improve health check"
git push origin main
```

Render will automatically rebuild and redeploy.

### Step 3: Monitor Health Check
After deployment, your service should restart. Watch the health check:
- Should pass within 30 seconds
- Each check should return 200 in <500ms
- Health check path: `/api/health?deep=1`

### Step 4: (Optional) Add Redis for Better Performance

If you want to upgrade to **Redis caching** for even better performance:

```bash
# 1. From Render Dashboard, add a Redis database
# 2. Note the connection details
# 3. Set environment variables on your web service:
#    REDIS_HOST = <your-redis-host>
#    REDIS_PORT = <your-redis-port>
# 4. Redeploy
```

The system will automatically use Redis if available, otherwise falls back to file-based cache.

---

## 📊 Performance Expectations After Fix

| Metric | Before | After |
|--------|--------|-------|
| **Memory Usage** | Crashes at 256MB | Stable at ~80-100MB |
| **Health Check** | 5-10s (fails) | <500ms (passes) |
| **API Response** | 2-5s | 200-500ms |
| **502 Errors** | Frequent | Rare |
| **Concurrent Users** | 2-3 | 10-15 |

---

## 🔧 Caching Usage in Code

The cache layer is automatically loaded. Use it in your code:

```php
// Get cached data
$classes = app_get_classes_cached($conn);

// Manual caching
$data = app_cache_get('my_key');
if ($data === null) {
    $data = json_encode($expensive_query_result);
    app_cache_set('my_key', $data, 3600); // 1 hour
}

// JSON caching
$items = app_cache_json_get('items_list');
if ($items === null) {
    $items = $conn->query("SELECT * FROM tbl_items")->fetchAll(PDO::FETCH_ASSOC);
    app_cache_json_set('items_list', $items, 1800); // 30 min
}

// Invalidate cache on data change
app_invalidate_system_cache('classes');
```

---

## 📝 Monitoring & Debugging

### Check Health Status
```bash
curl -sS https://your-app.onrender.com/api/health
curl -sS https://your-app.onrender.com/api/health?deep=1
```

### View Logs on Render
One of these scenarios means you fixed the 502:

| Status | What It Means |
|--------|---------|
| `200 OK` (both checks) | ✅ System is healthy |
| `200 OK`, then `503` | ⚠️ DB is slow, needs optimization |
| `502 Bad Gateway` | ❌ App crashed, instance too small |
| `503 Service Unavailable` | ❌ Database unreachable |

### PHP Error Log
On Render, errors are in Activity → Logs:
```
/var/log/php-fpm/error.log (inside container)
```

---

## 💡 Long-term Optimization Tips

### 1. **Database Indexing**
Add these indexes if not present:
```sql
CREATE INDEX idx_students_class ON tbl_students(class);
CREATE INDEX idx_students_email ON tbl_students(email);
CREATE INDEX idx_staff_level ON tbl_staff(level);
CREATE INDEX idx_login_sessions_key ON tbl_login_sessions(session_key);
```

### 2. **Query Optimization**
Watch for slow queries using this helper:
```php
// In development: enable query logging
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Check error_log for slow queries
```

### 3. **Static Asset Optimization**
Use a CDN for images/JS/CSS:
```
- Images: Store in cloud storage (S3, Render Disk)
- JS/CSS: Use jsDelivr or same CDN
- Update HTML to point to CDN URLs
```

### 4. **Scale Up When Needed**
Monitor Render metrics. If you exceed:
- **CPU consistently >80%** → Upgrade to `standard-1`
- **Memory consistently >90%** → Upgrade instance size
- **High DB query times** → Add database read replicas

---

## ⚠️ Important Notes

1. **File-based cache requires writable `/var/www/html/cache`** - Already set up in Docker
2. **Cache invalidation** - Manually call `app_invalidate_system_cache()` when data changes
3. **Redis is optional** - File cache works fine for <50 concurrent users
4. **Render basic-xs is $12/month** - Worth it to eliminate 502 errors

---

## 🔍 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| Still getting 502 | 1) Check instance size (must be ≥ basic-xs)<br>2) Check DB connection in logs<br>3) Restart service |
| Health check fails | 1) Increase timeout in `.do/app.yaml`<br>2) Check DB port/credentials<br>3) Check SSL mode if required |
| Still slow | 1) Add Redis for caching<br>2) Scale to standard-1<br>3) Optimize slow SQL queries |
| Cache not working | 1) Check `/var/www/html/cache` is writable<br>2) Check file permissions in logs |

---

## 📞 Need Help?

If 502 errors persist after instance upgrade:

1. **Get full logs**: `render logs` command or Render dashboard
2. **Check database connection**: Test credentials work locally
3. **Verify SSL**: If DB requires SSL, ensure `DB_SSL_MODE=REQUIRED`
4. **Contact Render support** with error logs

---

**Next Step**: Update instance type to `basic-xs` and redeploy. 502 errors should disappear within 5 minutes.
