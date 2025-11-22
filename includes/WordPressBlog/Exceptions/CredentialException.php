<?php
/**
 * Credential Exception
 *
 * Thrown when credential operations fail (encryption, decryption, validation).
 * Not retryable as credential issues require intervention.
 *
 * @package WordPressBlog\Exceptions
 */

require_once __DIR__ . '/WordPressBlogException.php';

class CredentialException extends WordPressBlogException {
    protected $retryable = false; // Credential errors are not retryable

    public function getUserMessage() {
        return 'Credential error: Please check your API keys and credentials';
    }

    /**
     * Set credential type context
     *
     * @param string $credentialType Type of credential (e.g., 'wordpress_api_key', 'openai_api_key')
     * @return self
     */
    public function setCredentialType($credentialType) {
        $this->addContext('credential_type', $credentialType);
        return $this;
    }
}
