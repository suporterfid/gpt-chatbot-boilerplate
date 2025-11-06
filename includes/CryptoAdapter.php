<?php
/**
 * Crypto Adapter for Encryption/Decryption
 * Provides AES-256-GCM encryption for audit content
 */

class CryptoAdapter {
    private $key;
    private $cipher = 'aes-256-gcm';
    
    public function __construct($config = []) {
        $encryptionKey = $config['encryption_key'] ?? '';
        
        if (empty($encryptionKey)) {
            throw new Exception('Encryption key is required for CryptoAdapter');
        }
        
        // Derive a proper 32-byte key from the provided key
        $this->key = hash('sha256', $encryptionKey, true);
    }
    
    /**
     * Encrypt data using AES-256-GCM
     * 
     * @param string $plaintext Data to encrypt
     * @return array Array with 'ciphertext', 'nonce', and 'tag'
     * @throws Exception
     */
    public function encrypt($plaintext) {
        if (empty($plaintext)) {
            return [
                'ciphertext' => '',
                'nonce' => '',
                'tag' => ''
            ];
        }
        
        // Generate a random nonce (12 bytes for GCM)
        $nonce = random_bytes(12);
        $tag = '';
        
        // Encrypt the data
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16 // Tag length
        );
        
        if ($ciphertext === false) {
            throw new Exception('Encryption failed');
        }
        
        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag)
        ];
    }
    
    /**
     * Decrypt data using AES-256-GCM
     * 
     * @param string $ciphertext Base64-encoded ciphertext
     * @param string $nonce Base64-encoded nonce
     * @param string $tag Base64-encoded authentication tag
     * @return string Decrypted plaintext
     * @throws Exception
     */
    public function decrypt($ciphertext, $nonce, $tag) {
        if (empty($ciphertext)) {
            return '';
        }
        
        $plaintext = openssl_decrypt(
            base64_decode($ciphertext),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            base64_decode($nonce),
            base64_decode($tag)
        );
        
        if ($plaintext === false) {
            throw new Exception('Decryption failed');
        }
        
        return $plaintext;
    }
    
    /**
     * Encode encrypted data as a JSON string for storage
     * 
     * @param array $encrypted Result from encrypt()
     * @return string JSON-encoded encrypted data
     */
    public function encodeForStorage(array $encrypted) {
        return json_encode([
            'c' => $encrypted['ciphertext'],
            'n' => $encrypted['nonce'],
            't' => $encrypted['tag']
        ]);
    }
    
    /**
     * Decode encrypted data from storage
     * 
     * @param string $encoded JSON-encoded encrypted data
     * @return array Array with 'ciphertext', 'nonce', and 'tag'
     */
    public function decodeFromStorage($encoded) {
        $data = json_decode($encoded, true);
        
        if (!$data || !isset($data['c'], $data['n'], $data['t'])) {
            throw new Exception('Invalid encrypted data format');
        }
        
        return [
            'ciphertext' => $data['c'],
            'nonce' => $data['n'],
            'tag' => $data['t']
        ];
    }
}
