<?php

class Encryption {
    private $key;
    private $cipher = "AES-256-CBC";

    public function __construct() {
        $envKey = $_ENV['ENCRYPTION_KEY'] ?? null;
        if (!$envKey) {
            throw new Exception('Encryption key not found in environment variables');
        }

        // Ensure the key is 32 bytes for AES-256
        $this->key = hex2bin(substr($envKey, 0, 64));
    }

    public function encrypt(array $data): string {
        try {
            // Log original data
            error_log("Original data to encrypt: " . json_encode($data));

            // Generate IV
            $iv = openssl_random_pseudo_bytes(16);
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new Exception('Failed to encode data to JSON');
            }

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $jsonData,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }

            // Combine IV and encrypted data, then base64 encode
            $result = base64_encode($iv . $encrypted);

            // Log encrypted data
            error_log("Encrypted data: " . $result);

            return $result;
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            throw $e;
        }
    }

    public function decrypt(string $data): array {
        try {
            // Log encrypted data
            error_log("Encrypted data to decrypt: " . $data);

            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                throw new Exception('Failed to decode base64 data');
            }

            // Extract IV and encrypted data
            $iv = substr($decoded, 0, 16);
            $ciphertext = substr($decoded, 16);

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $ciphertext,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }

            $result = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON after decryption');
            }

            // Log decrypted data
            error_log("Decrypted data: " . json_encode($result));

            return $result;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw $e;
        }
    }
}
