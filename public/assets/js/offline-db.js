/**
 * Teras Kota POS - Offline Database Module (IndexedDB)
 * Handles durable local storage for categories, menus, transactions, and sync queue.
 */
const OfflineDB = (function () {
    const DB_NAME = 'TerasKotaPOS_DB';
    const DB_VERSION = 1;
    let dbInstance = null;

    /**
     * Open or initialize the IndexedDB database.
     */
    function openDB() {
        if (dbInstance) {
            return Promise.resolve(dbInstance);
        }

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // 1. Metadata store (device_id, settings, cashier info)
                if (!db.objectStoreNames.contains('metadata')) {
                    db.createObjectStore('metadata', { keyPath: 'key' });
                }

                // 2. Categories store
                if (!db.objectStoreNames.contains('categories')) {
                    const catStore = db.createObjectStore('categories', { keyPath: 'id' });
                    catStore.createIndex('name', 'name', { unique: false });
                }

                // 3. Menus store
                if (!db.objectStoreNames.contains('menus')) {
                    const menuStore = db.createObjectStore('menus', { keyPath: 'id' });
                    menuStore.createIndex('category_id', 'category_id', { unique: false });
                    menuStore.createIndex('name', 'name', { unique: false });
                }

                // 4. Local transactions store
                if (!db.objectStoreNames.contains('transactions')) {
                    const txStore = db.createObjectStore('transactions', { keyPath: 'sync_id' });
                    txStore.createIndex('sync_status', 'sync_status', { unique: false });
                    txStore.createIndex('transaction_date', 'transaction_date', { unique: false });
                    txStore.createIndex('created_at', 'created_at', { unique: false });
                }

                // 5. Outbound sync queue
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const qStore = db.createObjectStore('sync_queue', { keyPath: 'sync_id' });
                    qStore.createIndex('status', 'status', { unique: false });
                    qStore.createIndex('next_attempt', 'next_attempt', { unique: false });
                }
            };

            request.onsuccess = (event) => {
                dbInstance = event.target.result;
                resolve(dbInstance);
            };

            request.onerror = (event) => {
                console.error('[OfflineDB] Error opening database:', event.target.error);
                reject(event.target.error);
            };
        });
    }

    /**
     * Generic helper for read/write transactions.
     */
    async function getStore(storeName, mode = 'readonly') {
        const db = await openDB();
        const tx = db.transaction(storeName, mode);
        return tx.objectStore(storeName);
    }

    /**
     * Get or create a persistent unique Device ID.
     */
    async function getDeviceId() {
        const store = await getStore('metadata', 'readwrite');
        return new Promise((resolve) => {
            const req = store.get('device_id');
            req.onsuccess = () => {
                if (req.result && req.result.value) {
                    resolve(req.result.value);
                } else {
                    const newId = 'TK-' + (crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).substring(2, 15));
                    store.put({ key: 'device_id', value: newId, updated_at: new Date().toISOString() });
                    resolve(newId);
                }
            };
            req.onerror = () => {
                resolve('TK-FALLBACK-' + Date.now());
            };
        });
    }

    /**
     * Save bootstrap master data (categories, menus, settings, cashier).
     */
    async function saveMasterData({ categories = [], menus = [], settings = {}, cashier = {} }) {
        const db = await openDB();
        const tx = db.transaction(['metadata', 'categories', 'menus'], 'readwrite');

        const metaStore = tx.objectStore('metadata');
        const catStore = tx.objectStore('categories');
        const menuStore = tx.objectStore('menus');

        // Save metadata
        metaStore.put({ key: 'settings', value: settings, updated_at: new Date().toISOString() });
        metaStore.put({ key: 'cashier', value: cashier, updated_at: new Date().toISOString() });
        metaStore.put({ key: 'last_bootstrap', value: new Date().toISOString() });

        // Refresh categories
        catStore.clear();
        for (const cat of categories) {
            catStore.put(cat);
        }

        // Refresh menus
        menuStore.clear();
        for (const menu of menus) {
            menuStore.put(menu);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    }

    /**
     * Get all active categories from IndexedDB.
     */
    async function getCategories() {
        const store = await getStore('categories', 'readonly');
        return new Promise((resolve, reject) => {
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Get all active menus from IndexedDB (optional category filter).
     */
    async function getMenus(categoryId = null) {
        const store = await getStore('menus', 'readonly');
        return new Promise((resolve, reject) => {
            if (categoryId) {
                const index = store.index('category_id');
                const req = index.getAll(Number(categoryId));
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            } else {
                const req = store.getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            }
        });
    }

    /**
     * Get a specific menu item by ID.
     */
    async function getMenuById(id) {
        const store = await getStore('menus', 'readonly');
        return new Promise((resolve, reject) => {
            const req = store.get(Number(id));
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Save an offline checkout transaction locally.
     * Transaction is written to both `transactions` and `sync_queue`.
     */
    async function saveTransaction(txData) {
        const db = await openDB();
        const tx = db.transaction(['transactions', 'sync_queue'], 'readwrite');

        const txStore = tx.objectStore('transactions');
        const qStore = tx.objectStore('sync_queue');

        // Ensure critical fields
        const now = new Date();
        const syncId = txData.sync_id || (crypto.randomUUID ? crypto.randomUUID() : 'offline-' + Date.now());
        const localNumber = txData.local_number || ('OFF-' + syncId.substring(0, 8).toUpperCase());

        const transactionRecord = {
            ...txData,
            sync_id: syncId,
            local_number: localNumber,
            sync_status: txData.sync_status || 'pending_sync',
            created_at: txData.created_at || now.toISOString(),
            transaction_date: txData.transaction_date || now.toISOString().split('T')[0],
            transaction_time: txData.transaction_time || now.toTimeString().split(' ')[0],
        };

        const queueItem = {
            sync_id: syncId,
            status: 'pending_sync',
            attempts: 0,
            next_attempt: Date.now(),
            created_at: now.toISOString(),
            payload: {
                sync_id: syncId,
                source_device_id: transactionRecord.source_device_id,
                transaction_date: transactionRecord.transaction_date,
                transaction_time: transactionRecord.transaction_time,
                payment_method: transactionRecord.payment_method,
                cash_tendered: transactionRecord.cash_tendered,
                change_returned: transactionRecord.change_returned,
                customer_name: transactionRecord.customer_name,
                notes: transactionRecord.notes,
                items: transactionRecord.items.map(it => ({
                    menu_id: it.menu_id,
                    quantity: it.quantity,
                })),
            }
        };

        txStore.put(transactionRecord);
        qStore.put(queueItem);

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(transactionRecord);
            tx.onerror = () => reject(tx.error);
        });
    }

    /**
     * Get transaction by sync_id.
     */
    async function getTransaction(syncId) {
        const store = await getStore('transactions', 'readonly');
        return new Promise((resolve, reject) => {
            const req = store.get(syncId);
            req.onsuccess = () => resolve(req.result || null);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Get all pending items in sync queue.
     */
    async function getPendingQueue() {
        const store = await getStore('sync_queue', 'readonly');
        return new Promise((resolve, reject) => {
            const req = store.getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    /**
     * Get count of pending items.
     */
    async function getPendingCount() {
        const store = await getStore('sync_queue', 'readonly');
        return new Promise((resolve) => {
            const req = store.count();
            req.onsuccess = () => resolve(req.result || 0);
            req.onerror = () => resolve(0);
        });
    }

    /**
     * Mark a transaction as successfully synced.
     * Updates `transactions` store and removes from `sync_queue`.
     */
    async function markSynced(syncId, serverResult = {}) {
        const db = await openDB();
        const tx = db.transaction(['transactions', 'sync_queue'], 'readwrite');

        const txStore = tx.objectStore('transactions');
        const qStore = tx.objectStore('sync_queue');

        return new Promise((resolve, reject) => {
            const getReq = txStore.get(syncId);
            getReq.onsuccess = () => {
                const record = getReq.result;
                if (record) {
                    record.sync_status = 'synced';
                    record.server_id = serverResult.server_id || record.server_id;
                    record.transaction_number = serverResult.transaction_number || record.transaction_number;
                    record.synced_at = serverResult.synced_at || new Date().toISOString();
                    txStore.put(record);
                }
                qStore.delete(syncId);
            };

            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    }

    /**
     * Mark a queue item as failed with retry delay.
     */
    async function markFailed(syncId, errorMsg = '', isConflict = false) {
        const db = await openDB();
        const tx = db.transaction(['transactions', 'sync_queue'], 'readwrite');

        const txStore = tx.objectStore('transactions');
        const qStore = tx.objectStore('sync_queue');

        return new Promise((resolve, reject) => {
            const qReq = qStore.get(syncId);
            qReq.onsuccess = () => {
                const qItem = qReq.result;
                if (qItem) {
                    qItem.attempts = (qItem.attempts || 0) + 1;
                    qItem.last_error = errorMsg;
                    qItem.status = isConflict ? 'conflict' : 'failed_retryable';

                    // Exponential backoff: 5s, 15s, 45s, max 2 min
                    const delayMs = Math.min(120000, 5000 * Math.pow(3, qItem.attempts - 1));
                    qItem.next_attempt = Date.now() + delayMs;

                    qStore.put(qItem);
                }
            };

            const tReq = txStore.get(syncId);
            tReq.onsuccess = () => {
                const tItem = tReq.result;
                if (tItem) {
                    tItem.sync_status = isConflict ? 'conflict' : 'failed_retryable';
                    tItem.last_sync_error = errorMsg;
                    txStore.put(tItem);
                }
            };

            tx.oncomplete = () => resolve(true);
            tx.onerror = () => reject(tx.error);
        });
    }

    /**
     * Get transactions made today on this device (for Cashier Summary).
     */
    async function getTodayTransactions(todayDateStr) {
        const targetDate = todayDateStr || new Date().toISOString().split('T')[0];
        const store = await getStore('transactions', 'readonly');

        return new Promise((resolve, reject) => {
            const index = store.index('transaction_date');
            const req = index.getAll(targetDate);
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    return {
        openDB,
        getDeviceId,
        saveMasterData,
        getCategories,
        getMenus,
        getMenuById,
        saveTransaction,
        getTransaction,
        getPendingQueue,
        getPendingCount,
        markSynced,
        markFailed,
        getTodayTransactions,
    };
})();

// Export globally for browser usage
window.OfflineDB = OfflineDB;
