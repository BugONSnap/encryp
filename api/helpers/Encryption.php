<?php

class Encryption {
    private $key;
    private $cipher = "AES-256-CBC";

    public function __construct() {
        $envKey = $_ENV['ENCRYPTION_KEY'] ?? null;
        if (!$envKey) {
            throw new Exception('Encryption key not found in environment variables');
        }
        $this->key = hex2bin(substr($envKey, 0, 64));
    }

    public function encrypt($data) {
        if (!is_array($data)) {
            throw new Exception('Data must be an array');
        }

        try {
            $iv = openssl_random_pseudo_bytes(16);
            
            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new Exception('Failed to encode data to JSON');
            }

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

            return base64_encode($iv . $encrypted);

        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            throw $e;
        }
    }

    public function decrypt($data) {
        if (!is_string($data)) {
            throw new Exception('Invalid encrypted data format');
        }

        try {
            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                throw new Exception('Failed to decode base64 data');
            }

            $iv = substr($decoded, 0, 16);
            $ciphertext = substr($decoded, 16);

            $decrypted = openssl_decrypt(
                $ciphertext,
                $this->cipher,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new Exception('Decryption failed: ' . openssl_error_string());
            }

            $result = json_decode($decrypted, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON after decryption: ' . json_last_error_msg());
            }

            return $result;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw $e;
        }
    }
} 