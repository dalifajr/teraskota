/**
 * Teras Kota POS - Offline Thermal Receipt Renderer
 * Generates thermal receipt layout (58mm/80mm) from local or server transaction snapshots.
 */
const ReceiptRenderer = (function () {

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number || 0);
    }

    /**
     * Generate HTML string for thermal receipt.
     */
    function renderHTML(transaction) {
        if (!transaction) return '';

        const isSynced = transaction.sync_status === 'synced';
        const trxNumber = transaction.transaction_number || transaction.local_number || transaction.sync_id.substring(0, 8);
        const cashierName = transaction.cashier?.name || (transaction.cashier_name || 'Kasir');
        const customerName = transaction.customer_name || '-';
        const dateStr = transaction.transaction_date || new Date().toISOString().split('T')[0];
        const timeStr = transaction.transaction_time ? transaction.transaction_time.substring(0, 5) : new Date().toTimeString().substring(0, 5);

        let itemsHtml = '';
        let totalQty = 0;
        let totalSales = 0;

        const items = transaction.details || transaction.items || [];
        items.forEach(item => {
            const name = item.menu_name_snapshot || item.name || item.menu_name || 'Menu';
            const price = Number(item.price_snapshot || item.price || 0);
            const qty = Number(item.quantity || 1);
            const subtotal = Number(item.subtotal || (price * qty));

            totalQty += qty;
            totalSales += subtotal;

            itemsHtml += `
                <div class="receipt-item-row" style="margin-bottom: 6px;">
                    <div style="font-weight: 600; font-size: 11px; color: #1e293b;">${name}</div>
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: #475569;">
                        <span>${qty} x ${formatRupiah(price)}</span>
                        <span style="font-weight: 600; color: #0f172a;">${formatRupiah(subtotal)}</span>
                    </div>
                </div>
            `;
        });

        // Use recorded total if available
        if (transaction.total_sales) {
            totalSales = Number(transaction.total_sales);
        }
        if (transaction.total_quantity) {
            totalQty = Number(transaction.total_quantity);
        }

        const cashTendered = Number(transaction.cash_tendered || totalSales);
        const changeReturned = Number(transaction.change_returned || (cashTendered - totalSales));
        const paymentMethod = (transaction.payment_method || 'tunai').toUpperCase();

        const syncBadge = !isSynced 
            ? `<div style="text-align: center; margin-bottom: 8px;"><span style="background: #fef08a; color: #854d0e; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; border: 1px dashed #ca8a04;">NOTA OFFLINE (PENDING SYNC)</span></div>`
            : '';

        return `
            <div class="receipt-card" style="font-family: 'Courier New', Courier, monospace; width: 100%; max-width: 320px; margin: 0 auto; padding: 12px; background: #ffffff; color: #1e293b; font-size: 12px; line-height: 1.4;">
                ${syncBadge}
                
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px dashed #94a3b8;">
                    <h2 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin: 0 0 2px 0; color: #0f172a;">TERAS KOTA</h2>
                    <p style="font-size: 10px; color: #64748b; margin: 0;">Berlian Makmur</p>
                    <p style="font-size: 9px; color: #94a3b8; margin: 0;">Tempat Santai & Kuliner Terbaik</p>
                </div>

                <!-- Meta -->
                <div style="margin-bottom: 8px; font-size: 11px; padding-bottom: 6px; border-bottom: 1px dashed #94a3b8;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">No. Nota:</span>
                        <span style="font-weight: 700; color: #0f172a;">${trxNumber}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Waktu:</span>
                        <span>${dateStr} ${timeStr}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Kasir:</span>
                        <span>${cashierName}</span>
                    </div>
                    ${customerName !== '-' ? `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Pelanggan:</span>
                        <span>${customerName}</span>
                    </div>` : ''}
                </div>

                <!-- Items List -->
                <div style="margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #94a3b8;">
                    ${itemsHtml}
                </div>

                <!-- Totals -->
                <div style="margin-bottom: 8px; padding-bottom: 6px; border-bottom: 1px dashed #94a3b8; font-size: 11px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Total Item:</span>
                        <span>${totalQty} pcs</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 13px; font-weight: 700; color: #0f172a;">
                        <span>TOTAL:</span>
                        <span>${formatRupiah(totalSales)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Metode Bayar:</span>
                        <span style="font-weight: 600;">${paymentMethod}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span style="color: #64748b;">Bayar:</span>
                        <span>${formatRupiah(cashTendered)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 600;">
                        <span style="color: #64748b;">Kembali:</span>
                        <span style="color: ${changeReturned >= 0 ? '#15803d' : '#b91c1c'};">${formatRupiah(changeReturned)}</span>
                    </div>
                </div>

                ${transaction.notes ? `
                <div style="margin-bottom: 8px; font-size: 10px; color: #64748b; font-style: italic; text-align: center;">
                    Catatan: ${transaction.notes}
                </div>` : ''}

                <!-- Footer -->
                <div style="text-align: center; font-size: 10px; color: #64748b; margin-top: 8px;">
                    <p style="margin: 0 0 2px 0; font-weight: 600;">Terima Kasih Atas Kunjungan Anda</p>
                    <p style="margin: 0; font-size: 9px; opacity: 0.8;">Simpan struk ini sebagai bukti pembayaran</p>
                </div>
            </div>
        `;
    }

    /**
     * Print receipt directly via an isolated printable iframe.
     */
    function printReceipt(transaction) {
        const receiptHtml = renderHTML(transaction);

        let printFrame = document.getElementById('receiptPrintFrame');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'receiptPrintFrame';
            printFrame.style.position = 'fixed';
            printFrame.style.top = '-9999px';
            printFrame.style.left = '-9999px';
            printFrame.style.width = '80mm';
            printFrame.style.height = '0';
            printFrame.style.border = 'none';
            document.body.appendChild(printFrame);
        }

        const frameDoc = printFrame.contentDocument || printFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Cetak Struk</title>
                <style>
                    @page {
                        size: 80mm auto;
                        margin: 0;
                    }
                    body {
                        margin: 0;
                        padding: 3mm 4mm;
                        font-family: 'Courier New', Courier, monospace;
                    }
                </style>
            </head>
            <body>
                ${receiptHtml}
                <script>
                    window.onload = function() {
                        window.focus();
                        window.print();
                    };
                <\/script>
            </body>
            </html>
        `);
        frameDoc.close();
    }

    return {
        formatRupiah,
        renderHTML,
        printReceipt,
    };
})();

// Export globally for browser usage
window.ReceiptRenderer = ReceiptRenderer;
