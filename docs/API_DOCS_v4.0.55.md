# 📚 Connectix Panel API Documentation v4.0.55 ULTRA FINAL

## Base URL
```
https://your-panel.com/api/v1/
```

## Authentication
- **Panel API:** `Authorization: Bearer {reseller_api_key}` or `X-API-Key` header
- **App API:** `auth_token` from login
- **Metrics:** `?key={metrics_api_key}` or `X-API-Key`

---

## 🔐 Auth Endpoints

### POST /api/v1/app/login
Login for mobile app (multi-account support)

**Request:**
```json
{
  "username": "client_username",
  "password": "password",
  "panel_url": "https://vpbotn.ir (optional, auto from global config)",
  "api_key": "optional"
}
```

**Response:**
```json
{
  "success": true,
  "token": "jwt_token",
  "client": {
    "id": 123,
    "username": "user123",
    "traffic_used_bytes": 1073741824,
    "traffic_limit_bytes": 107374182400,
    "expire_at": "2026-11-17 12:00:00",
    "status": "active"
  },
  "branding": {
    "brand_name": "Connectix",
    "announcement": "Welcome"
  },
  "servers": [
    {
      "id": "1",
      "name": "Germany",
      "flag": "🇩🇪",
      "group": "VIP",
      "config_uri": "vless://...",
      "ping_ms": 45
    }
  ]
}
```

---

## 👥 Client Endpoints

### GET /api/v1/app/profile
Get client profile (requires auth_token)

**Headers:** `Authorization: Bearer {token}`

**Response:** Same as login client object

### GET /api/v1/app/configs
Get server configs (with caching, pagination)

**Query:** `?page=1&per_page=50`

**Response:**
```json
{
  "servers": [...],
  "total": 100,
  "page": 1,
  "per_page": 50
}
```

### POST /api/v1/app/feedback
Send feedback/crash report

**Request:**
```json
{
  "subject": "Crash Report",
  "message": "Stack trace...",
  "version": "4.0.55+88"
}
```

---

## 📊 Metrics & Monitoring (v4.0.54+)

### GET /api/metrics
Prometheus compatible metrics

**Query:** `?key={metrics_api_key}` (if set) or admin session or localhost

**Response (text/plain):**
```
# HELP connectix_clients_total Total clients
connectix_clients_total 1234
# HELP connectix_clients_active Active clients
connectix_clients_active 900
# HELP connectix_clients_online Online (last 10m)
connectix_clients_online 150
# HELP connectix_traffic_bytes Total traffic
connectix_traffic_bytes 1234567890
# HELP connectix_servers_total Total servers
connectix_servers_total 5
# HELP connectix_server_ping_ms Server ping
connectix_server_ping_ms{server_id="1",server_name="Germany"} 45
# HELP connectix_bot_orders_24h Orders 24h
connectix_bot_orders_24h 12
# HELP connectix_income_30d Income 30d
connectix_income_30d 5000000
# HELP connectix_apcu_hits APCu hits
connectix_apcu_hits 12345
```

**Grafana Setup:**
1. Prometheus config:
```yaml
scrape_configs:
  - job_name: 'connectix'
    metrics_path: '/api/metrics'
    params:
      key: ['YOUR_METRICS_KEY']
    static_configs:
      - targets: ['your-panel.com']
    scrape_interval: 30s
```
2. Import dashboard JSON from `/monitoring/grafana-dashboard.json`

---

## 🤖 Bot Endpoints

### POST /webhook.php?bot_token={token}
Telegram webhook (auto-handled)

**Features v4.0.54:**
- Delegates to BotCallbackHandler (toggle enforcement)
- Delegates to BotMessageHandler (reply keyboard fix for monitoring/financial/points/usage)
- Uses BotContextService with ApcuCache 60s
- Session management via BotSessionService

**Button Toggles (v4.0.52 FIX):**
All buttons respect `btn_{name}_enabled` setting:
- buy, my_accounts, trial, wallet, apps, support
- monitoring, financial, points, usage (was missing, now fixed)
- referral, wheel, guide, panel, reseller

---

## 🔧 Panel Location (v4.0.51+)

### GET /api/v1/app/panel-location
Auto-discover panel location

**Response:**
```json
{
  "panel_url": "https://vpbotn.ir/contax",
  "well_known": "https://vpbotn.ir/.well-known/connectix.json"
}
```

### GET /.well-known/connectix.json
Well-known panel location (for auto-discovery)

**Response:**
```json
{
  "panel_url": "https://vpbotn.ir/contax",
  "version": "4.0.54",
  "api_base": "https://vpbotn.ir/contax/api/v1/"
}
```

---

## 📱 App Global Config (v4.0.51+)

### GET /api/v1/app/global-config
Get global app config (managed mode)

**Response:**
```json
{
  "default_panel_url": "https://vpbotn.ir",
  "hide_manual_panel_url": "1",
  "hide_manual_api_key": "1",
  "force_managed_mode": "0",
  "auto_fetch_servers": "1",
  "default_api_key": ""
}
```

---

## 🔄 Client Management (v4.0.52+)

### POST /clients/soft-delete
Soft delete (local only, keeps on main server)

**Request:** `client_id=123`

**Response:** Redirect with success

### POST /clients/restore
Restore from soft delete

### GET/POST /clients/resync
Resync single client from main server (re-read traffic, expiry, status)

### POST /clients/resync-server
Resync all clients from a server (full re-import)

**Query:** `server_id=1`

---

## 📄 Pagination (v4.0.53+)

All list endpoints now support pagination:

**Query Params:**
- `page=1` (default 1)
- `per_page=50` (default 50, min 10, max 200)

**Response Headers/Body includes:**
- `totalClients`, `totalPages`, `page`, `perPage`

**Example:**
```
GET /clients?page=2&per_page=100
```

---

## 🧪 Testing

### Run Tests
```bash
php tests/ci_smoke.php
php tests/BotServicesTest.php
php tests/BotE2ETest.php
```

### Flutter Tests
```bash
cd client-app
flutter test
```

---

## 📦 Version History

- **v4.0.55 (88):** MVVM + Provider + Design System Full + Widget Tests + PWA Sync + API Docs + Grafana alerting
- **v4.0.54 (88):** Bot split 12 services, PWA offline, Grafana 9 panels, Metrics, Tests, MetricsController
- **v4.0.53 (87):** Bypass 130→20, Lite button, V2Ray init, pagination, security, ApcuCache, design system, MVVM ViewModel
- **v4.0.52 (86):** Bot buttons toggle enforcement, reply keyboard fix, soft delete, resync
- **v4.0.51 (85):** Global app config from web panel, no manual paths
- **v4.0.50 (84):** Multi-account unlimited, reseller sync auto

---

## 🔐 Security Notes v4.0.53+

- All `pdo->query("SELECT ... WHERE id = $var")` converted to prepared statements
- 18 direct query injections fixed
- TransactionTooLarge fixed via bypass limit 25
- V2Ray init guard prevents unauthorized access
- Metrics endpoint protected by api key or admin or localhost

---

## 📱 Mobile App Architecture v4.0.55

```
lib/
  design/
    app_colors.dart - Colors + pingColor()
    app_text_styles.dart - Typography
    app_spacing.dart - Spacing, radius, durations
    design.dart - Barrel
  viewmodels/
    dashboard_viewmodel.dart - MVVM ViewModel (ChangeNotifier)
  screens/
    dashboard_screen.dart - 3579 lines legacy (to be deprecated)
    dashboard_screen_mvvm.dart - NEW MVVM + Provider + Design System (300 lines)
    advanced_settings_screen.dart - Lite toggle + Extended bypass
    login_screen.dart - Multi-account + managed mode
  widgets/
    connect_button_lite.dart - LITE 1 anim, 60fps, opaque hitTest
    connect_button_ultimate.dart - ULTIMATE 10 anim (legacy)
  services/
    api_service.dart - 6-layer panel location resolver
    account_manager.dart - Multi-account unlimited
```

**Provider Setup in main.dart:**
```dart
MultiProvider(
  providers: [
    ChangeNotifierProvider(create: (_) => DashboardViewModel()),
  ],
  child: ConnectixApp()
)
```

**Design System Usage:**
```dart
import '../design/design.dart';

Container(
  color: AppColors.bgCard,
  padding: AppSpacing.paddingCard,
  child: Text('Hello', style: AppTextStyles.h3)
)
```

---

## 🔜 Roadmap v4.0.56

- Full migration to dashboard_screen_mvvm.dart (deprecate old)
- Provider for all screens
- Background sync for offline client creation
- Grafana alerting rules
- More widget tests
- API v2 with OpenAPI spec
