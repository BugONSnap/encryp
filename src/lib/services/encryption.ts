import CryptoJS from 'crypto-js';
import { PUBLIC_ENCRYPTION_KEY } from '$env/static/public';

export class EncryptionService {
    private key: string | null = null;

    constructor() {
        this.key = PUBLIC_ENCRYPTION_KEY;
    }

    async encrypt(data: unknown): Promise<string> {
        if (!this.key) {
            throw new Error('Encryption key not available');
        }

        try {
            // Convert hex key to bytes
            const keyBytes = CryptoJS.enc.Hex.parse(this.key);
            
            // Generate random IV
            const iv = CryptoJS.lib.WordArray.random(16);
            
            // Convert data to string
            const jsonStr = JSON.stringify(data);

            // Encrypt
            const encrypted = CryptoJS.AES.encrypt(jsonStr, keyBytes, {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            });

            // Combine IV and ciphertext
            const combined = iv.concat(encrypted.ciphertext);
            
            // Convert to base64
            return CryptoJS.enc.Base64.stringify(combined);
        } catch (error) {
            console.error('Encryption error:', error);
            throw error;
        }
    }

    async decrypt(encryptedData: string): Promise<unknown> {
        if (!this.key) {
            throw new Error('Encryption key not available');
        }

        try {
            // Convert hex key to bytes
            const keyBytes = CryptoJS.enc.Hex.parse(this.key);
            
            // Decode base64
            const combined = CryptoJS.enc.Base64.parse(encryptedData);
            
            // Split IV and ciphertext
            const iv = CryptoJS.lib.WordArray.create(combined.words.slice(0, 4));
            const ciphertext = CryptoJS.lib.WordArray.create(
                combined.words.slice(4),
                combined.sigBytes - 16
            );

            // Create cipher params
            const cipherParams = CryptoJS.lib.CipherParams.create({
                ciphertext: ciphertext
            });

            // Decrypt
            const decrypted = CryptoJS.AES.decrypt(
                cipherParams,
                keyBytes,
                {
                    iv: iv,
                    mode: CryptoJS.mode.CBC,
                    padding: CryptoJS.pad.Pkcs7
                }
            );

            // Convert to string and parse JSON
            const decryptedStr = decrypted.toString(CryptoJS.enc.Utf8);
            if (!decryptedStr) {
                throw new Error('Decryption produced empty result');
            }

            return JSON.parse(decryptedStr);
        } catch (error) {
            console.error('Decryption error:', error);
            throw error;
        }
    }
}

export const encryptionService = new EncryptionService(); 