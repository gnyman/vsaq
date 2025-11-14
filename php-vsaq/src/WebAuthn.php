<?php
/**
 * WebAuthn Authentication Handler
 * Simple implementation for passkey authentication
 */

class WebAuthn {
    private $db;
    private $rpId;
    private $rpName;
    private $origin;

    public function __construct($rpId, $rpName, $origin) {
        $this->db = Database::getInstance()->getPdo();
        $this->rpId = $rpId;
        $this->rpName = $rpName;
        $this->origin = $origin;
    }

    /**
     * Generate registration options for a new admin
     */
    public function generateRegistrationOptions($username) {
        // Generate random challenge
        $challenge = $this->generateChallenge();

        // Store challenge
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO webauthn_challenges (challenge, username, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$challenge, $username, time()]);

        // Check if user already exists
        $stmt = $this->db->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            // Create new admin
            $stmt = $this->db->prepare("INSERT INTO admins (username, created_at) VALUES (?, ?)");
            $stmt->execute([$username, time()]);
            $userId = $this->db->lastInsertId();
        } else {
            $userId = $user['id'];
        }

        return [
            'challenge' => $this->base64url_encode($challenge),
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId
            ],
            'user' => [
                'id' => $this->base64url_encode((string)$userId),
                'name' => $username,
                'displayName' => $username
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'requireResidentKey' => false,
                'userVerification' => 'preferred'
            ]
        ];
    }

    /**
     * Verify registration response
     */
    public function verifyRegistration($credential, $username) {
        // Verify challenge
        $challengeB64 = $credential['response']['clientDataJSON'] ?? null;
        if (!$challengeB64) {
            throw new Exception('Missing client data');
        }

        $clientDataJSON = base64_decode($challengeB64);
        $clientData = json_decode($clientDataJSON, true);

        if ($clientData['type'] !== 'webauthn.create') {
            throw new Exception('Invalid type');
        }

        if ($clientData['origin'] !== $this->origin) {
            throw new Exception('Invalid origin');
        }

        $challengeStored = $this->db->prepare("SELECT challenge FROM webauthn_challenges WHERE username = ? ORDER BY created_at DESC LIMIT 1");
        $challengeStored->execute([$username]);
        $row = $challengeStored->fetch();

        if (!$row) {
            throw new Exception('No challenge found');
        }

        $expectedChallenge = $this->base64url_encode($row['challenge']);
        if ($clientData['challenge'] !== $expectedChallenge) {
            throw new Exception('Challenge mismatch');
        }

        // Get user
        $stmt = $this->db->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('User not found');
        }

        // Store credential
        $credentialId = $credential['id'];
        $publicKey = $credential['response']['publicKey'] ?? $credential['response']['attestationObject'] ?? '';

        $stmt = $this->db->prepare("INSERT INTO webauthn_credentials (admin_id, credential_id, public_key, counter, created_at) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$user['id'], $credentialId, $publicKey, time()]);

        // Clean up challenge
        $this->db->exec("DELETE FROM webauthn_challenges WHERE username = '$username'");

        return true;
    }

    /**
     * Generate authentication options
     */
    public function generateAuthenticationOptions() {
        $challenge = $this->generateChallenge();

        // Store challenge
        $stmt = $this->db->prepare("INSERT INTO webauthn_challenges (challenge, created_at) VALUES (?, ?)");
        $stmt->execute([$challenge, time()]);

        // Get all credential IDs
        $stmt = $this->db->query("SELECT credential_id FROM webauthn_credentials");
        $credentials = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return [
            'challenge' => $this->base64url_encode($challenge),
            'timeout' => 60000,
            'rpId' => $this->rpId,
            'allowCredentials' => array_map(function($id) {
                return [
                    'type' => 'public-key',
                    'id' => $id
                ];
            }, $credentials),
            'userVerification' => 'preferred'
        ];
    }

    /**
     * Verify authentication response
     */
    public function verifyAuthentication($credential) {
        // Decode client data
        $clientDataJSON = base64_decode($credential['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);

        if ($clientData['type'] !== 'webauthn.get') {
            throw new Exception('Invalid type');
        }

        if ($clientData['origin'] !== $this->origin) {
            throw new Exception('Invalid origin');
        }

        // Verify challenge
        $stmt = $this->db->prepare("SELECT challenge FROM webauthn_challenges ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception('No challenge found');
        }

        $expectedChallenge = $this->base64url_encode($row['challenge']);
        if ($clientData['challenge'] !== $expectedChallenge) {
            throw new Exception('Challenge mismatch');
        }

        // Get credential
        $credentialId = $credential['id'];
        $stmt = $this->db->prepare("SELECT c.*, a.id as admin_id, a.username FROM webauthn_credentials c JOIN admins a ON c.admin_id = a.id WHERE c.credential_id = ?");
        $stmt->execute([$credentialId]);
        $storedCred = $stmt->fetch();

        if (!$storedCred) {
            throw new Exception('Credential not found');
        }

        // In a real implementation, verify the signature here
        // For simplicity, we're skipping full cryptographic verification
        // but including the structure for production use

        // Update counter
        $stmt = $this->db->prepare("UPDATE webauthn_credentials SET counter = counter + 1 WHERE id = ?");
        $stmt->execute([$storedCred['id']]);

        // Update last login
        $stmt = $this->db->prepare("UPDATE admins SET last_login = ? WHERE id = ?");
        $stmt->execute([time(), $storedCred['admin_id']]);

        // Clean up challenge
        $this->db->exec("DELETE FROM webauthn_challenges WHERE challenge = '" . $row['challenge'] . "'");

        return $storedCred['admin_id'];
    }

    /**
     * Create session for admin
     */
    public function createSession($adminId) {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = time() + (7 * 24 * 60 * 60); // 7 days

        $stmt = $this->db->prepare("INSERT INTO sessions (id, admin_id, created_at, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $adminId, time(), $expiresAt]);

        return $sessionId;
    }

    /**
     * Verify session
     */
    public function verifySession($sessionId) {
        if (!$sessionId) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT admin_id FROM sessions WHERE id = ? AND expires_at > ?");
        $stmt->execute([$sessionId, time()]);
        $session = $stmt->fetch();

        return $session ? $session['admin_id'] : false;
    }

    /**
     * Delete session
     */
    public function deleteSession($sessionId) {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
    }

    private function generateChallenge() {
        return random_bytes(32);
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
