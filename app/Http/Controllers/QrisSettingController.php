<?php

namespace App\Http\Controllers;

use App\Models\PaymentListenerLog;
use App\Models\Setting;
use App\Services\QrisService;
use Illuminate\Http\Request;

class QrisSettingController extends Controller
{
    protected QrisService $qrisService;

    public function __construct(QrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    /**
     * Display QRIS Settings & Android Listener Documentation.
     */
    public function index()
    {
        $payload = Setting::getValue('qris_payload', '');
        $secret = $this->qrisService->getSecret();
        $uniqueMin = (int) Setting::getValue('qris_unique_min', 100);
        $uniqueMax = (int) Setting::getValue('qris_unique_max', 999);
        $expiryMinutes = (int) Setting::getValue('qris_expiry_minutes', 10);
        $merchantName = Setting::getValue('qris_merchant_name', '');
        $merchantCity = Setting::getValue('qris_merchant_city', '');

        // Parse merchant info if not already saved
        if (!empty($payload) && (empty($merchantName) || empty($merchantCity))) {
            $info = $this->qrisService->getInfo($payload);
            if (!empty($info['merchant_name'])) {
                $merchantName = $info['merchant_name'];
                Setting::updateOrCreate(['key' => 'qris_merchant_name'], ['value' => $merchantName]);
            }
            if (!empty($info['merchant_city'])) {
                $merchantCity = $info['merchant_city'];
                Setting::updateOrCreate(['key' => 'qris_merchant_city'], ['value' => $merchantCity]);
            }
        }

        $webhookUrl = url('/listener/payment');
        $testUrl = url('/listener/test-connection');

        // Recent listener logs
        $logs = PaymentListenerLog::with('transaction')
            ->latest()
            ->take(15)
            ->get();

        return view('settings.qris', compact(
            'payload',
            'secret',
            'uniqueMin',
            'uniqueMax',
            'expiryMinutes',
            'merchantName',
            'merchantCity',
            'webhookUrl',
            'testUrl',
            'logs'
        ));
    }

    /**
     * Update QRIS settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'qris_payload' => ['nullable', 'string'],
            'qris_secret' => ['required', 'string', 'min:8'],
            'qris_unique_min' => ['required', 'integer', 'min:1', 'max:999'],
            'qris_unique_max' => ['required', 'integer', 'min:10', 'max:9999', 'gte:qris_unique_min'],
            'qris_expiry_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $payload = trim($validated['qris_payload'] ?? '', " \t\n\r\0\x0B");
        $payload = str_replace(["\r\n", "\r", "\n", "\t"], '', $payload);

        // Auto-validate and auto-repair CRC16 if standard EMVCo payload format is detected
        if (!empty($payload) && str_starts_with($payload, '000201')) {
            if (str_contains($payload, '6304')) {
                $body = preg_replace('/6304[0-9A-Fa-f]{0,4}$/', '6304', $payload);
                $calculatedCrc = $this->qrisService->calculateCrc16($body);
                $payload = $body . $calculatedCrc;
            }
        }

        Setting::updateOrCreate(['key' => 'qris_payload'], ['value' => $payload]);
        Setting::updateOrCreate(['key' => 'qris_secret'], ['value' => trim($validated['qris_secret'])]);
        Setting::updateOrCreate(['key' => 'qris_unique_min'], ['value' => $validated['qris_unique_min']]);
        Setting::updateOrCreate(['key' => 'qris_unique_max'], ['value' => $validated['qris_unique_max']]);
        Setting::updateOrCreate(['key' => 'qris_expiry_minutes'], ['value' => $validated['qris_expiry_minutes']]);

        if (!empty($payload)) {
            $info = $this->qrisService->getInfo($payload);
            if (!empty($info['merchant_name'])) {
                Setting::updateOrCreate(['key' => 'qris_merchant_name'], ['value' => $info['merchant_name']]);
            }
            if (!empty($info['merchant_city'])) {
                Setting::updateOrCreate(['key' => 'qris_merchant_city'], ['value' => $info['merchant_city']]);
            }
        }

        return redirect()->route('settings.qris.index')
            ->with('success', 'Pengaturan QRIS & Webhook berhasil diperbarui!');
    }

    /**
     * Regenerate webhook shared secret.
     */
    public function regenerateSecret()
    {
        $newSecret = bin2hex(random_bytes(16));
        Setting::updateOrCreate(['key' => 'qris_secret'], ['value' => $newSecret]);

        return redirect()->route('settings.qris.index')
            ->with('success', 'Secret Key Webhook berhasil diperbarui: ' . $newSecret);
    }

    /**
     * Update webhook shared secret key with a custom user-defined value.
     */
    public function updateCustomSecret(Request $request)
    {
        $validated = $request->validate([
            'custom_secret' => ['required', 'string', 'min:8', 'max:128'],
        ], [
            'custom_secret.required' => 'Secret key tidak boleh kosong.',
            'custom_secret.min' => 'Secret key minimal 8 karakter demi keamanan.',
            'custom_secret.max' => 'Secret key maksimal 128 karakter.',
        ]);

        $newSecret = trim($validated['custom_secret']);
        Setting::updateOrCreate(['key' => 'qris_secret'], ['value' => $newSecret]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Secret Key Webhook berhasil diperbarui!',
                'secret' => $newSecret,
            ]);
        }

        return redirect()->route('settings.qris.index')
            ->with('success', 'Secret Key Webhook berhasil diperbarui: ' . $newSecret);
    }
}

