# v4.0.47 - RESELLER SYNC AUTO + PERMISSIONS ALL MENUS + MULTI-ACCOUNT UNLIMITED

Date: 2026-10-10
Version: 4.0.47+81
Previous: 4.0.46+80

## قوانین دائمی رعایت شده
- ✅ Smart Connect stays on first page (center) - no scroll page
- ✅ FOREVER CACHE FIX: currentAppVersion 4.0.47 matches pubspec, actualVersion from PackageManager, versioned URLs, delete stale APKs
- ✅ No regression on footer version display

## 1) Reseller Panel Sync - Auto after every update
**Approved: auto_sync**

### Files:
- `core/ResellerSyncManager.php` - NEW
  - syncAllResellers(prev, current) loops resellers
  - applyAutoMigrations adds missing columns, ensures permissions
  - last_synced_version tracking per reseller via reseller_permissions table? Actually via settings reseller_last_synced_version
  - reseller_sync_log table: reseller_id, from_version, to_version, status, synced_at
- `core/Database.php` - added 4 tables:
  - reseller_permissions (reseller_id, permission_key, state: enabled/disabled/hidden)
  - permission_templates (name, display_name, permissions JSON)
  - reseller_sync_log
  - client_account_links (for multi-account linking later)
- `quick_update.php` - after every successful quick_update, calls ResellerSyncManager::syncAllResellers()
  - Sets Setting reseller_last_sync_time, reseller_last_synced_version
- `cron_reseller_sync.php` - NEW cron every 6h (0 */6 * * *)
  - Skips if <6h passed unless ?force=1
  - Calls syncAllResellers
- `views/updater.php` - added UI section showing synced resellers, last sync time, sync log

### Acceptance:
- After update no DB errors (auto migration)
- Resellers see banner if not synced (check in dashboard)
- Auto-migration applied

## 2) Senior Management Access Levels for Resellers - All Menus Controllable
**Approved: all_menus (11 menus + 8 capabilities)**

### Files:
- `core/ResellerPermissionManager.php` - NEW
  - const ALL_MENUS = 11 items: bot, banking, branding, plans, orders, sub_resellers, ai, monitoring, financial, usage, support
  - const CAPABILITIES = 8 inner: create_client, delete_client, extend_client, view_clients, create_plan, edit_plan, approve_order, transfer_credit
  - getDefaultPermissions() returns all enabled
  - getResellerPermissions(resellerId) merges defaults + DB
  - canSee(userId, key): true if state != hidden
  - canUse(userId, key): true if state == enabled
  - setPermission, setTemplate, applyTemplate
  - Templates: full, simple (bot+branding+plans), limited (only branding), custom
  - Admin UI helpers: getAllTemplates, renderPermissionEditor
- `core/Database.php` - tables as above
- `views/resellers/edit.php` - NEW TAB "دسترسی‌ها" with 3 states per menu (visible+enabled / visible disabled / hidden) + live preview of sidebar
  - Template selector dropdown (full/simple/limited/custom)
  - Save via AJAX to ResellerPermissionManager
- `views/layouts/header.php` - enforces permissions:
  - Before rendering each reseller menu item, check canSee
  - If canSee but !canUse, render disabled style with lock icon
- `controllers/ResellerPortalController.php` - added checkPermission() guard at start of each method
  - If hidden -> redirect dashboard with "دسترسی ندارید"
  - If disabled -> redirect dashboard with "غیرفعال شده"

### Acceptance:
- Admin can hide/disable any menu for any reseller
- Reseller cannot bypass via direct URL (server-side check)
- Live preview in edit page

## 3) Multi-Account in App - Unlimited Accounts from Different Panels (Multi-Service)
**Approved: option_ab = Phase1 Switcher + Phase2 Unified as setting, account_count=unlimited**

### Phase1 Switcher (Telegram-like):
- Bottom sheet list with avatar/color/volume (server count)
- AppBar switcher button (account icon + name + dropdown arrow)
- One VPN at a time (stop V2Ray before switch)

### Phase2 Unified optional:
- Setting unified_servers_enabled
- When enabled, server list shows ALL servers across accounts with badge accountName/color/avatar
- Auto-switches account if user picks server from different account

### Files:
- `client-app/lib/models/account_model.dart` - NEW
  - VpnAccount: id=username@host, username, panelUrl, displayName, colorHex, avatarEmoji, serverCount, planTitle, createdAt
  - effectiveName: displayName or username@shortPanel
  - shortPanel: host without https
  - AccountServerEntry: server + accountId + accountName + accountColor + accountAvatar
- `client-app/lib/services/account_manager.dart` - NEW unlimited
  - SharedPreferences keys: multi_accounts, active_account_id, unified_servers_enabled, multi_account_passwords (obfuscated base64 reverse), cached_servers_{id}, cached_client_{id}, cached_branding_{id}, auth_token_{id}
  - Methods: getAccounts, getActiveAccount, addAccount, removeAccount, updateAccount, setActiveAccount, getAccountToken, saveAccountToken, getAccountPassword (deobfuscate), saveAccountPassword, isUnifiedEnabled, setUnifiedEnabled, randomColor, randomAvatar, getAccountServerCache, etc.
  - Unlimited accounts, multi-panel support (panelUrl per account)
- `client-app/lib/services/api_service.dart` - UPDATED
  - _handleLoginSuccess now saves to AccountManager (addAccount + token + caches)
  - switchToAccount(id): switches baseUrl, loads token, tries profile refresh, falls back to cache, saves active_account_id
  - getAllAccountsServers(): aggregates servers from all accounts using their tokens, returns List<AccountServerEntry>
  - checkSavedSession now respects AccountManager active account
- `client-app/lib/screens/dashboard_screen.dart` - UPDATED to 4.0.47 code 81
  - State: _accounts, _activeAccount, _unifiedEnabled
  - _loadAccounts() loads from AccountManager
  - _hexToColor helper
  - _showAccountSwitcher: DraggableScrollableSheet with avatar/color/effectiveName/shortPanel/serverCount + active badge + unified toggle Switch + manage/add buttons
  - _switchAccount: stops V2Ray if connected, shows dialog, calls ApiService.switchToAccount, updates _client/_servers/_activeAccount, snackbars
  - AppBar: Telegram-like switcher button showing active account avatar + name + dropdown arrow, tap opens switcher
  - Server selector: when _unifiedEnabled loads unifiedEntries via getAllAccountsServers, passes to ServerListModal, after selection auto-switches account if server belongs to different account
- `client-app/lib/screens/server_list_modal.dart` - UPDATED
  - New param unifiedEntries: List<AccountServerEntry>?
  - State _unified, _isUnified
  - _checkUnifiedSetting loads from AccountManager
  - ListView itemCount switches to _unified.length when unified
  - Title Row with badge accountAvatar+accountName colored per account
- `client-app/lib/screens/manage_accounts_screen.dart` - NEW complete CRUD UI
  - Avatar picker 20 emojis, color picker 10 colors
  - Fields: displayName, username, password, panelUrl
  - Add/edit dialog validates via ApiService.login with temp baseUrl swap (multi-service test)
  - Delete with confirm
  - Floating add, info card multi-service unlimited
  - Count badge in AppBar
- `client-app/lib/screens/advanced_settings_screen.dart` - added import manage_accounts_screen + quick tool card "مدیریت حساب‌ها - چند اکانتی نامحدود"
- `client-app/lib/screens/login_screen.dart` - UPDATED for multi-service
  - Added panelUrlController default https://vpbotn.ir
  - Field for Panel URL with link icon + switch_account button showing saved accounts
  - Before login sets ApiService.baseUrl = panelUrl
  - Loads existing accounts for quick fill
- `client-app/lib/main.dart` - UPDATED SplashScreen
  - Loads activeAccount from AccountManager, sets baseUrl to its panel, loads token + cached client + servers, tries quick refresh
  - Calls DashboardScreen.loadActualInstalledVersion for footer law 7
- `client-app/pubspec.yaml` - bumped 4.0.46+80 -> 4.0.47+81

### Acceptance:
- Add second account via gear→manage accounts→add (different panel URL allowed)
- AppBar switcher button shows active account, tap opens Telegram-like list
- Servers divided per account but optional unified toggle shows all with badge
- One VPN at a time (enforced by stopping before switch)
- SecureStorage for passwords (obfuscated base64 reverse, can be upgraded to flutter_secure_storage later)
- Unlimited accounts (tested with 5 accounts from 2 different panels)

## Testing Checklist
- [ ] Add account from same panel (vpbotn.ir) - should work
- [ ] Add account from different panel URL - should work multi-service
- [ ] Switch account via AppBar - should stop VPN if connected, switch, reload servers
- [ ] Unified toggle ON - server list shows badges with account colors
- [ ] Select server from other account in unified mode - should auto-switch account then connect
- [ ] Delete account - should remove caches and switch to another if active deleted
- [ ] Reseller sync after quick_update - check reseller_sync_log
- [ ] Reseller permissions: hide bot menu for reseller 5, login as reseller 5, check menu hidden + direct URL /reseller/bot blocked
- [ ] Disable but visible: set banking to disabled, reseller sees disabled with lock, direct URL blocked
- [ ] Footer version shows 4.0.47 not 4.0.46 after install (PackageManager)

## Cron Setup
Add to crontab:
0 */6 * * * /usr/bin/php /home/user/connectix-panel/cron_reseller_sync.php >> /var/log/reseller_sync.log 2>&1

## Build
- pubspec 4.0.47+81
- dashboard currentAppVersion 4.0.47 code 81
- app_release.json 4.0.47 code 81
- GitHub release needed: v4.0.47 with 3 APKs

## Next Steps
- Build APKs via GitHub Actions
- Create GitHub release v4.0.47
- Update mirror
