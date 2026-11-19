<?php

namespace App\Http\Controllers;

use App\Models\PaymentListenerLog;
use App\Models\Setting;
use App\Services\QrisService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class QrisSettingController extends Controller
{
    protected QrisService $qrisService;

    public function __construct(QrisService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    public function index()
    {
        $payload = Setting::getValue('qris_payload', '');
        $secret = $this->qrisService->getSecret();
        $uniqueMin = (int) Setting::getValue('qris_unique_min', 100);
        $uniqueMax = (int) Setting::getValue('qris_unique_max', 999);
        $expiryMinutes = (int) Setting::getValue('qris_expiry_minutes', 10);
        $merchantName = Setting::getValue('qris_merchant_name', '');
        $merchantCity = Setting::getValue('qris_merchant_city', '');

        if (!empty($payload) && (empty($merchantName) || empty($merchantCity))) {
            $info = $this->qrisService->getInfo($payload);
            if (!empty($info['merchant_name'])) {
                $merchantName = $info['merchant_name'];
                Setting::setValue('qris_merchant_name', $merchantName);
            }
            if (!empty($info['merchant_city'])) {
                $merchantCity = $info['merchant_city'];
                Setting::setValue('qris_merchant_city', $merchantCity);
            }
        }

        $tenant = TenantContext::current();
        $webhookUrl = $tenant ? url('/listener/' . $tenant->slug . '/payment') : url('/listener/payment');
        $testUrl = $tenant ? url('/listener/' . $tenant->slug . '/test-connection') : url('/listener/test-connection');

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

        if (!empty($payload) && str_starts_with($payload, '000201')) {
            if (str_contains($payload, '6304')) {
                $body = preg_replace('/6304[0-9A-Fa-f]{0,4}$/', '6304', $payload);
                $calculatedCrc = $this->qrisService->calculateCrc16($body);
                $payload = $body . $calculatedCrc;
            }
        }

        Setting::setValue('qris_payload', $payload);
        Setting::setValue('qris_secret', trim($validated['qris_secret']));
        Setting::setValue('qris_unique_min', $validated['qris_unique_min']);
        Setting::setValue('qris_unique_max', $validated['qris_unique_max']);
        Setting::setValue('qris_expiry_minutes', $validated['qris_expiry_minutes']);

        if (!empty($payload)) {
            $info = $this->qrisService->getInfo($payload);
            if (!empty($info['merchant_name'])) {
                Setting::setValue('qris_merchant_name', $info['merchant_name']);
            }
            if (!empty($info['merchant_city'])) {
                Setting::setValue('qris_merchant_city', $info['merchant_city']);
            }
        }

        return redirect()->route('settings.qris.index')
            ->with('success', 'Pengaturan QRIS & Webhook berhasil diperbarui!');
    }

    public function regenerateSecret()
    {
        $newSecret = bin2hex(random_bytes(16));
        Setting::setValue('qris_secret', $newSecret);

        return redirect()->route('settings.qris.index')
            ->with('success', 'Secret Key Webhook berhasil diperbarui: ' . $newSecret);
    }

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
        Setting::setValue('qris_secret', $newSecret);

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
