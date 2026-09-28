/**
 * Teras Kota POS - Background Synchronization Manager
 * Orchestrates connection detection, automatic retry, batch payload sync, and UI status updates.
 */
const SyncManager = (function () {
    let isOnline = navigator.onLine;
    let isSyncing = false;
    let syncIntervalId = null;

    const eventCallbacks = {
        networkChange: [],
        queueChange: [],
        syncComplete: [],
    };

    /**
     * Subscribe to SyncManager events.
     */
    function on(event, callback) {
        if (eventCallbacks[event]) {
            eventCallbacks[event].push(callback);
        }
    }

    function emit(event, data) {
        if (eventCallbacks[event]) {
            eventCallbacks[event].forEach(cb => {
                try { cb(data); } catch (e) { console.error(`[SyncManager] Error in ${event} callback:`, e); }
            });
        }
    }

    /**
     * Get CSRF token from page meta tag.
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Update network status badge in UI.
     */
    function updateNetworkUI(online) {
        const badge = document.getElementById('posNetworkBadge');
        const icon = document.getElementById('posNetworkIcon');
        const text = document.getElementById('posNetworkText');

        if (!badge) return;

        if (online) {
            badge.classList.remove('offline');
            badge.classList.add('online');
            badge.title = 'Terhubung ke Server';
            if (icon) icon.className = 'fa-solid fa-wifi';
            if (text) text.textContent = 'Online';
        } else {
            badge.classList.remove('online');
            badge.classList.add('offline');
            badge.title = 'Mode Offline: Transaksi tersimpan lokal di perangkat';
            if (icon) icon.className = 'fa-solid fa-wifi-slash';
            if (text) text.textContent = 'Offline';
        }
    }

    /**
     * Update pending sync badge in UI.
     */
    async function updateQueueBadge() {
        const count = await OfflineDB.getPendingCount();
        const badge = document.getElementById('posSyncQueueBadge');
        const icon = document.getElementById('posSyncIcon');

        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        if (icon) {
            if (isSyncing) {
                icon.className = 'fa-solid fa-rotate fa-spin text-warning';
            } else {
                icon.className = count > 0 ? 'fa-solid fa-cloud-arrow-up text-warning' : 'fa-solid fa-cloud-arrow-up text-info';
            }
        }

        emit('queueChange', count);
        return count;
    }

    /**
     * Active heartbeat check to verify actual server reachability.
     */
    async function checkReachability() {
        if (!navigator.onLine) {
            setOnlineStatus(false);
            return false;
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 4000);

            const res = await fetch('/pos/bootstrap', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            const healthy = (res.status === 200 || res.status === 401);
            setOnlineStatus(healthy);
            return healthy;
        } catch (e) {
            setOnlineStatus(false);
            return false;
        }
    }

    function setOnlineStatus(newStatus) {
        if (isOnline !== newStatus) {
            isOnline = newStatus;
            updateNetworkUI(isOnline);
            emit('networkChange', isOnline);

            if (isOnline) {
                // Connection restored: immediately trigger sync
                triggerSync();
            }
        }
    }

    /**
     * Pull latest master data from server and refresh IndexedDB.
     */
    async function refreshMasterData() {
        if (!isOnline) {
            console.log('[SyncManager] Cannot refresh master data while offline');
            return false;
        }

        try {
            const res = await fetch('/pos/bootstrap', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();
            if (data.success) {
                await OfflineDB.saveMasterData({
                    categories: data.categories || [],
                    menus: data.menus || [],
                    settings: data.settings || {},
                    cashier: data.cashier || {},
                });
                console.log('[SyncManager] Master data updated in IndexedDB');
                return true;
            }
        } catch (err) {
            console.warn('[SyncManager] Failed to refresh master data:', err);
        }
        return false;
    }

    /**
     * Trigger synchronization of all pending offline transactions.
     */
    async function triggerSync() {
        if (isSyncing) return;

        const queue = await OfflineDB.getPendingQueue();
        if (!queue || queue.length === 0) {
            await updateQueueBadge();
            return;
        }

        const now = Date.now();
        const eligibleItems = queue.filter(item => {
            return item.status !== 'conflict' && (!item.next_attempt || item.next_attempt <= now);
        });

        if (eligibleItems.length === 0) {
            await updateQueueBadge();
            return;
        }

        isSyncing = true;
        await updateQueueBadge();

        try {
            const payload = {
                transactions: eligibleItems.map(item => item.payload)
            };

            const response = await fetch('/pos/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.results && Array.isArray(data.results)) {
                for (const res of data.results) {
                    if (res.status === 'synced') {
                        await OfflineDB.markSynced(res.sync_id, res);
                    } else if (res.status === 'conflict') {
                        await OfflineDB.markFailed(res.sync_id, res.message || 'Konflik validasi server', true);
                    } else {
                        await OfflineDB.markFailed(res.sync_id, res.message || 'Sinkronisasi gagal');
                    }
                }
            } else if (!response.ok) {
                for (const item of eligibleItems) {
                    await OfflineDB.markFailed(item.sync_id, data.message || `Server error ${response.status}`);
                }
            }

            emit('syncComplete', data);
        } catch (err) {
            console.warn('[SyncManager] Network error during sync:', err);
            setOnlineStatus(false);
            for (const item of eligibleItems) {
                await OfflineDB.markFailed(item.sync_id, 'Koneksi terputus saat sinkronisasi');
            }
        } finally {
            isSyncing = false;
            await updateQueueBadge();
        }
    }

    /**
     * Initialize SyncManager lifecycle and listeners.
     */
    async function init() {
        await OfflineDB.openDB();
        await OfflineDB.getDeviceId();

        // Browser online/offline event listeners
        window.addEventListener('online', () => {
            checkReachability();
        });

        window.addEventListener('offline', () => {
            setOnlineStatus(false);
        });

        // Manual sync button click in navbar
        const btnSync = document.getElementById('btnSyncQueue');
        if (btnSync) {
            btnSync.addEventListener('click', async () => {
                const count = await OfflineDB.getPendingCount();
                if (count === 0) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Semua Transaksi Tersinkron',
                            text: 'Tidak ada transaksi lokal yang menunggu pengiriman.',
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    }
                    return;
                }

                if (!isOnline) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sedang Offline',
                            text: `Terdapat ${count} transaksi tersimpan lokal. Sinkronisasi akan otomatis berjalan saat koneksi internet kembali.`,
                        });
                    }
                    return;
                }

                // Trigger sync immediately
                if (window.Swal) {
                    Swal.fire({
                        title: 'Menyinkronkan...',
                        text: `Mengirim ${count} transaksi offline ke server...`,
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                    });
                }

                await triggerSync();

                if (window.Swal) {
                    const remaining = await OfflineDB.getPendingCount();
                    if (remaining === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sinkronisasi Berhasil!',
                            text: 'Semua transaksi offline telah tercatat di server.',
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Sinkronisasi Sebagian',
                            text: `${remaining} transaksi masih belum tersinkron. Sistem akan mencoba lagi secara otomatis.`,
                        });
                    }
                }
            });
        }

        // Initial setup
        updateNetworkUI(isOnline);
        await updateQueueBadge();

        // Check actual connection & refresh master data if online
        checkReachability().then(online => {
            if (online) {
                refreshMasterData();
            }
        });

        // Periodic heartbeat & auto-sync attempt every 20 seconds
        if (syncIntervalId) clearInterval(syncIntervalId);
        syncIntervalId = setInterval(() => {
            checkReachability().then(online => {
                if (online) {
                    triggerSync();
                }
            });
        }, 20000);
    }

    return {
        init,
        on,
        isOnline: () => isOnline,
        checkReachability,
        refreshMasterData,
        triggerSync,
        updateQueueBadge,
    };
})();

// Export globally for browser usage
window.SyncManager = SyncManager;
