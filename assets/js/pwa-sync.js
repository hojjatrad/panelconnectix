/**
 * v4.0.55 PWA Background Sync - Offline client creation queue
 * Queues client creation when offline, syncs when online
 */

class PWASyncManager {
  constructor() {
    this.dbName = 'connectix_offline_queue';
    this.storeName = 'client_queue';
    this.db = null;
    this.initDB();
    this.setupOnlineListener();
  }

  async initDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, 1);
      request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(this.storeName)) {
          const store = db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
          store.createIndex('timestamp', 'timestamp', { unique: false });
          store.createIndex('synced', 'synced', { unique: false });
        }
      };
      request.onsuccess = (e) => {
        this.db = e.target.result;
        console.log('PWA Sync DB initialized');
        resolve(this.db);
        this.syncQueued(); // Try sync on init
      };
      request.onerror = (e) => {
        console.error('PWA Sync DB error', e);
        reject(e);
      };
    });
  }

  async queueClientCreation(clientData) {
    if (!this.db) await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction([this.storeName], 'readwrite');
      const store = tx.objectStore(this.storeName);
      const item = {
        data: clientData,
        timestamp: Date.now(),
        synced: false,
        attempts: 0
      };
      const req = store.add(item);
      req.onsuccess = () => {
        console.log('Client queued for offline sync', item);
        // Show UI feedback
        this.showOfflineQueuedToast();
        resolve(req.result);
      };
      req.onerror = () => reject(req.error);
    });
  }

  async getQueuedClients() {
    if (!this.db) await this.initDB();
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction([this.storeName], 'readonly');
      const store = tx.objectStore(this.storeName);
      const index = store.index('synced');
      const req = index.getAll(false);
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async syncQueued() {
    if (!navigator.onLine) {
      console.log('Offline, skipping sync');
      return;
    }
    const queued = await this.getQueuedClients();
    if (queued.length === 0) return;

    console.log(`Syncing ${queued.length} queued clients...`);
    for (const item of queued) {
      try {
        const formData = new FormData();
        Object.keys(item.data).forEach(key => {
          formData.append(key, item.data[key]);
        });

        const response = await fetch('/clients/store', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (response.ok) {
          // Mark as synced
          await this.markSynced(item.id);
          console.log('Synced queued client', item.id);
        } else {
          await this.incrementAttempts(item.id);
        }
      } catch (e) {
        console.error('Sync failed for', item.id, e);
        await this.incrementAttempts(item.id);
      }
    }

    const remaining = await this.getQueuedClients();
    if (remaining.length === 0) {
      this.showSyncCompleteToast();
    }
  }

  async markSynced(id) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction([this.storeName], 'readwrite');
      const store = tx.objectStore(this.storeName);
      const req = store.get(id);
      req.onsuccess = () => {
        const item = req.result;
        if (item) {
          item.synced = true;
          store.put(item);
        }
        resolve();
      };
      req.onerror = () => reject(req.error);
    });
  }

  async incrementAttempts(id) {
    return new Promise((resolve) => {
      const tx = this.db.transaction([this.storeName], 'readwrite');
      const store = tx.objectStore(this.storeName);
      const req = store.get(id);
      req.onsuccess = () => {
        const item = req.result;
        if (item) {
          item.attempts = (item.attempts || 0) + 1;
          // If too many attempts, mark synced to avoid infinite loop (user can retry manually)
          if (item.attempts > 5) item.synced = true;
          store.put(item);
        }
        resolve();
      };
      req.onerror = () => resolve();
    });
  }

  setupOnlineListener() {
    window.addEventListener('online', () => {
      console.log('Online, syncing queued clients');
      this.syncQueued();
    });

    // Also try periodic sync every 30s when online
    setInterval(() => {
      if (navigator.onLine) this.syncQueued();
    }, 30000);

    // Register background sync if available
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      navigator.serviceWorker.ready.then(reg => {
        reg.sync.register('sync-clients').catch(() => {});
      });
    }
  }

  showOfflineQueuedToast() {
    this.showToast('📦 کلاینت در صف آفلاین ذخیره شد - هنگام آنلاین شدن همگام می‌شود', 'warning');
  }

  showSyncCompleteToast() {
    this.showToast('✅ همگام‌سازی آفلاین کامل شد', 'success');
  }

  showToast(message, type = 'info') {
    // Create toast if not exists
    let container = document.getElementById('pwa-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'pwa-toast-container';
      container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const bg = type === 'success' ? '#10B981' : type === 'warning' ? '#F59E0B' : '#6366F1';
    toast.style.cssText = `background:${bg};color:white;padding:12px 16px;border-radius:12px;font-size:12px;max-width:300px;box-shadow:0 10px 30px rgba(0,0,0,0.3);animation:slideIn 0.3s;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  async getQueueCount() {
    const queued = await this.getQueuedClients();
    return queued.length;
  }

  async clearSynced() {
    if (!this.db) await this.initDB();
    return new Promise((resolve) => {
      const tx = this.db.transaction([this.storeName], 'readwrite');
      const store = tx.objectStore(this.storeName);
      const index = store.index('synced');
      const req = index.openCursor(true);
      req.onsuccess = (e) => {
        const cursor = e.target.result;
        if (cursor) {
          cursor.delete();
          cursor.continue();
        } else {
          resolve();
        }
      };
    });
  }
}

// Initialize globally
window.pwaSyncManager = new PWASyncManager();

// Hook into client creation forms
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form[action*=\"clients/store\"], form[action*=\"clients/bulk-store\"]');
  forms.forEach(form => {
    form.addEventListener('submit', async (e) => {
      if (!navigator.onLine) {
        e.preventDefault();
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => data[key] = value);
        await window.pwaSyncManager.queueClientCreation(data);
        // Optionally show offline page or keep form data
        return false;
      }
    });
  });

  // Show queue count in UI if any
  setTimeout(async () => {
    const count = await window.pwaSyncManager.getQueueCount();
    if (count > 0) {
      const badge = document.createElement('div');
      badge.style.cssText = 'position:fixed;top:70px;right:20px;background:#F59E0B;color:white;padding:8px 12px;border-radius:20px;font-size:11px;z-index:9998;cursor:pointer;';
      badge.textContent = `📦 ${count} در صف آفلاین`;
      badge.onclick = () => window.pwaSyncManager.syncQueued();
      document.body.appendChild(badge);
    }
  }, 2000);
});

// CSS for animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn { from { transform: translateX(100%); opacity:0 } to { transform: translateX(0); opacity:1 } }
@keyframes slideOut { from { transform: translateX(0); opacity:1 } to { transform: translateX(100%); opacity:0 } }
`;
document.head.appendChild(style);
