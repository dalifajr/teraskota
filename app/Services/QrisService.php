<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Transaction;

class QrisService
{
    /**
     * Parse EMVCo MPM TLV format string.
     */
    public function parse(string $payload): array
    {
        $payload = trim($payload);
        $tags = [];
        $len = strlen($payload);
        $offset = 0;

        while ($offset < $len) {
            if ($offset + 4 > $len) {
                break;
            }
            $tag = substr($payload, $offset, 2);
            $length = (int) substr($payload, $offset + 2, 2);
            $offset += 4;

            if ($offset + $length > $len) {
                break;
            }
            $value = substr($payload, $offset, $length);
            $offset += $length;

            $tags[] = [
                'tag' => $tag,
                'length' => $length,
                'value' => $value,
            ];

            if ($tag === '63') {
                break;
            }
        }

        return $tags;
    }

    /**
     * Calculate CRC16-CCITT (0xFFFF, 0x1021) for EMVCo.
     */
    public function calculateCrc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return strtoupper(str_pad(dechex($crc & 0xFFFF), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Extract merchant info from payload.
     */
    public function getInfo(string $payload): array
    {
        $tags = $this->parse($payload);
        $info = [
            'merchant_name' => '',
            'merchant_city' => '',
            'postal_code' => '',
            'currency' => 'IDR',
            'is_valid' => false,
        ];

        foreach ($tags as $item) {
            if ($item['tag'] === '59') {
                $info['merchant_name'] = $item['value'];
            } elseif ($item['tag'] === '60') {
                $info['merchant_city'] = $item['value'];
            } elseif ($item['tag'] === '61') {
                $info['postal_code'] = $item['value'];
            } elseif ($item['tag'] === '00' && $item['value'] === '01') {
                $info['is_valid'] = true;
            }
        }

        return $info;
    }

    /**
     * Convert static QRIS payload into dynamic with transaction amount.
     * Uses EMVCo MPM TLV reconstruction to preserve all merchant account information,
     * tags (including Tip Tag 55), subtags, and spacing.
     */
    public function makeDynamic(string $staticPayload, int|float $amount): string
    {
        // Strip only outer whitespace, line breaks, and tabs. PRESERVE internal spaces!
        $payload = trim($staticPayload, " \t\n\r\0\x0B");
        $payload = str_replace(["\r\n", "\r", "\n", "\t"], '', $payload);
        if (empty($payload)) {
            return '';
        }

        $amountStr = (string) (int) $amount;
        $tags = $this->parse($payload);

        // If parsed into valid TLV tags, reconstruct via strict EMVCo TLV engine
        if (!empty($tags) && count($tags) >= 4) {
            $tag54Obj = [
                'tag' => '54',
                'length' => strlen($amountStr),
                'value' => $amountStr,
            ];

            $newTags = [];
            $inserted54 = false;

            foreach ($tags as $item) {
                $t = $item['tag'];
                $v = $item['value'];

                // Drop existing CRC Tag 63
                if ($t === '63') {
                    continue;
                }

                // Change Tag 01 from static "11" to dynamic "12"
                if ($t === '01') {
                    $v = '12';
                }

                // Drop old amount tag if present
                if ($t === '54') {
                    continue;
                }

                // Insert Tag 54 immediately after Currency Tag 53, or before Tag >= 55
                if (!$inserted54 && (int)$t > 53) {
                    $newTags[] = $tag54Obj;
                    $inserted54 = true;
                }

                $newTags[] = [
                    'tag' => $t,
                    'length' => strlen($v),
                    'value' => $v,
                ];
            }

            if (!$inserted54) {
                $newTags[] = $tag54Obj;
            }

            $body = '';
            foreach ($newTags as $item) {
                $body .= $item['tag'] . str_pad((string)$item['length'], 2, '0', STR_PAD_LEFT) . $item['value'];
            }

            $toChecksum = $body . '6304';
            return $toChecksum . $this->calculateCrc16($toChecksum);
        }

        // Fallback for non-standard payloads
        if (preg_match('/6304[0-9A-Fa-f]{4}$/', $payload)) {
            $qris = substr($payload, 0, -4);
        } else {
            $qris = $payload;
            if (!str_ends_with($qris, '6304')) {
                $qris .= '6304';
            }
        }

        $qris = preg_replace('/^(000201)?010211/', '${1}010212', $qris);
        $lenStr = str_pad((string) strlen($amountStr), 2, '0', STR_PAD_LEFT);
        $tag54 = '54' . $lenStr . $amountStr;

        if (strpos($qris, '5303360') !== false && strpos($qris, '5802ID') !== false) {
            $pattern = '/5303360(.*?)5802ID/';
            $qris = preg_replace($pattern, '5303360' . $tag54 . '5802ID', $qris, 1);
        } else {
            $parts = explode('5802ID', $qris, 2);
            if (count($parts) === 2) {
                $parts[0] = preg_replace('/54[0-9]{2}[0-9]+(\.[0-9]+)?$/', '', $parts[0]);
                $qris = $parts[0] . $tag54 . '5802ID' . $parts[1];
            } else {
                $qris = preg_replace('/6304$/', $tag54 . '6304', $qris);
            }
        }

        if (!str_ends_with($qris, '6304')) {
            $qris = preg_replace('/6304.*$/', '', $qris) . '6304';
        }

        return $qris . $this->calculateCrc16($qris);
    }

    /**
     * Generate collision-free unique 3-digit code for pending transactions.
     * Example: 1000 + 190 -> 1190
     */
    public function generateUniqueCode(int|float $baseAmount): array
    {
        $min = (int) Setting::getValue('qris_unique_min', 100);
        $max = (int) Setting::getValue('qris_unique_max', 999);
        if ($min > $max) {
            $min = 100;
            $max = 999;
        }

        // Get all currently pending and unexpired final amounts for QRIS
        $occupiedAmounts = Transaction::where('payment_method', 'qris')
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('qris_expired_at')
                      ->orWhere('qris_expired_at', '>', now());
            })
            ->pluck('final_amount')
            ->map(fn($v) => (int) $v)
            ->toArray();

        // Attempt up to 50 random trials
        for ($i = 0; $i < 50; $i++) {
            $candidateCode = rand($min, $max);
            $candidateAmount = (int) $baseAmount + $candidateCode;

            if (!in_array($candidateAmount, $occupiedAmounts, true)) {
                return [
                    'code' => $candidateCode,
                    'final_amount' => $candidateAmount,
                ];
            }
        }

        // Fallback: sequential linear scan
        for ($code = $min; $code <= $max; $code++) {
            $candidateAmount = (int) $baseAmount + $code;
            if (!in_array($candidateAmount, $occupiedAmounts, true)) {
                return [
                    'code' => $code,
                    'final_amount' => $candidateAmount,
                ];
            }
        }

        // Extreme fallback if all codes are exhausted
        $code = rand($min, $max);
        return [
            'code' => $code,
            'final_amount' => (int) $baseAmount + $code,
        ];
    }

    /**
     * Retrieve static QRIS payload from settings.
     */
    public function getStaticPayload(): ?string
    {
        return Setting::getValue('qris_payload');
    }

    /**
     * Retrieve shared secret for Android webhook.
     */
    public function getSecret(): string
    {
        $secret = Setting::getValue('qris_secret');
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(16));
            Setting::updateOrCreate(['key' => 'qris_secret'], ['value' => $secret]);
        }
        return $secret;
    }

    /**
     * Retrieve QRIS expiration in minutes.
     */
    public function getExpiryMinutes(): int
    {
        return (int) Setting::getValue('qris_expiry_minutes', 10);
    }
}
