import CryptoJS from 'crypto-js';

export class EncryptionService {
    private key: CryptoJS.lib.WordArray;

    constructor() {
        const envKey = import.meta.env.VITE_PUBLIC_ENCRYPTION_KEY || '';
        if (!envKey) {
            throw new Error('Encryption key is not available.');
        }

        // Convert hex key to bytes
        this.key = CryptoJS.enc.Hex.parse(envKey);
    }

    // Encrypt function
    encrypt(data: Record<string, unknown>): string {
        try {
            console.log('Original data to encrypt:', data);  // Log original data
            
            const iv = CryptoJS.lib.WordArray.random(16);
            const jsonStr = JSON.stringify(data);

            const encrypted = CryptoJS.AES.encrypt(jsonStr, this.key, {
                iv: iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7,
            });

            const combined = iv.concat(encrypted.ciphertext);
            const encryptedData = CryptoJS.enc.Base64.stringify(combined);

            console.log('Encrypted data:', encryptedData);  // Log encrypted data
            
            return encryptedData;
        } catch (error) {
            console.error('Encryption error:', error);
            throw new Error('Failed to encrypt data.');
        }
    }

    // Decrypt function
    decrypt(encryptedData: string): Record<string, unknown> {
        try {
            console.log( encryptedData);  // Log encrypted data

            const combined = CryptoJS.enc.Base64.parse(encryptedData);

            const iv = CryptoJS.lib.WordArray.create(combined.words.slice(0, 4));
            const ciphertext = CryptoJS.lib.WordArray.create(
                combined.words.slice(4),
                combined.sigBytes - 16
            );

            const decrypted = CryptoJS.AES.decrypt(
                { ciphertext: ciphertext },
                this.key,
                {
                    iv: iv,
                    mode: CryptoJS.mode.CBC,
                    padding: CryptoJS.pad.Pkcs7,
                }
            );

            const decryptedStr = decrypted.toString(CryptoJS.enc.Utf8);

            // Removed decrypted data from logs
            return JSON.parse(decryptedStr);
        } catch (error) {
            console.error('Decryption error:', error);
            throw new Error('Failed to decrypt data.');
        }
    }
}

export const encryptionService = new EncryptionService();
