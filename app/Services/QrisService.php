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
     */
    public function makeDynamic(string $staticPayload, int|float $amount): string
    {
        $payload = trim($staticPayload);
        if (empty($payload)) {
            return '';
        }

        // 1. Remove existing Tag 63 (Checksum) if present: 6304XXXX
        $data = preg_replace('/6304[0-9A-Fa-f]{4}$/', '', $payload);

        // 2. Change Tag 01 from 11 (static) to 12 (dynamic)
        $data = preg_replace('/^000201010211/', '000201010212', $data);

        // 3. Remove existing Tag 54 if already present in payload
        // TLV parsing ensures accurate removal without touching sub-tags
        $tags = $this->parse($data);
        $cleanData = '';
        $amountStr = (string) (int) $amount;
        $tag54 = '54' . str_pad(strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
        $inserted54 = false;

        foreach ($tags as $item) {
            $t = $item['tag'];
            $v = $item['value'];

            if ($t === '54') {
                // Skip old amount
                continue;
            }

            if ($t === '01' && $v === '11') {
                $v = '12';
            }

            // Insert Tag 54 right before Tag 58 (Country Code) or other upper tags
            if (!$inserted54 && (int)$t >= 58) {
                $cleanData .= $tag54;
                $inserted54 = true;
            }

            $cleanData .= $t . str_pad(strlen($v), 2, '0', STR_PAD_LEFT) . $v;
        }

        if (!$inserted54) {
            $cleanData .= $tag54;
        }

        // 4. Append Tag 6304 + calculated CRC16
        $toChecksum = $cleanData . '6304';
        $checksum = $this->calculateCrc16($toChecksum);

        return $toChecksum . $checksum;
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
