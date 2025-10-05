<?php
// Configuración de Supabase
define('SUPABASE_URL', 'https://bytebxgazdrfxusfnlbi.supabase.co'); // Reemplaza con tu URL
define('SUPABASE_ANON_KEY', '***REMOVED***'); // Reemplaza con tu clave anónima
define('SUPABASE_SERVICE_ROLE_KEY', '***REMOVED***'); // Para operaciones administrativas

class SupabaseClient {
    private $url;
    private $headers;
    
    public function __construct($useServiceRole = false) {
        $this->url = SUPABASE_URL;
        
        $apiKey = $useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
        
        $this->headers = [
            'Content-Type: application/json',
            'apikey: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey
        ];
    }
    
    /**
     * Reenviar email de verificación usando Supabase Auth (para usuarios existentes)
     */
    public function sendVerificationEmail($email, $redirectTo = null): array {
        $endpoint = $this->url . '/auth/v1/resend';
        
        $data = [
            'type' => 'signup',
            'email' => $email
        ];
        
        if ($redirectTo) {
            $data['options'] = ['redirect_to' => $redirectTo];
        }
        
        return $this->makeRequest($endpoint, 'POST', $data);
    }
    
    /**
     * Verificar token de confirmación - probará diferentes formatos
     */
    public function verifyEmail($token, $type = 'signup'): array {
        $endpoint = $this->url . '/auth/v1/verify';
        
        // Primer intento: formato con token_hash (más común para signup)
        $data = [
            'token_hash' => $token,
            'type' => $type
        ];
        
        $response = $this->makeRequest($endpoint, 'POST', $data);
        
        // Si falla con token_hash, intentar con token
        if (!$response['success'] && $response['status_code'] === 400) {
            $data = [
                'token' => $token,
                'type' => $type
            ];
            
            $response = $this->makeRequest($endpoint, 'POST', $data);
        }
        
        return $response;
    }
    
    /**
     * Registrar usuario en Supabase Auth
     */
    public function signUpUser($email, $password, $options = []): array {
        $endpoint = $this->url . '/auth/v1/signup';
        
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        if (!empty($options)) {
            $data['options'] = $options;
        }
        
        return $this->makeRequest($endpoint, 'POST', $data);
    }
    
    /**
     * Iniciar sesión en Supabase Auth
     */
    public function signInUser($email, $password): array {
        $endpoint = $this->url . '/auth/v1/token?grant_type=password';
        
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        return $this->makeRequest($endpoint, 'POST', $data);
    }
    
    /**
     * Obtener usuario por token
     */
    public function getUser($token): array {
        $endpoint = $this->url . '/auth/v1/user';
        
        $headers = array_merge($this->headers, [
            'Authorization: Bearer ' . $token
        ]);
        
        return $this->makeRequest($endpoint, 'GET', null, $headers);
    }
    
    /**
     * Cerrar sesión
     */
    public function signOut($token): array {
        $endpoint = $this->url . '/auth/v1/logout';
        
        $headers = array_merge($this->headers, [
            'Authorization: Bearer ' . $token
        ]);
        
        return $this->makeRequest($endpoint, 'POST', [], $headers);
    }
    
    /**
     * Realizar petición HTTP (público para testing)
     */
    public function makeRequest($endpoint, $method = 'GET', $data = null, $customHeaders = null): array {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $customHeaders ?: $this->headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return ['error' => 'CURL Error: ' . $error, 'success' => false];
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status_code' => $httpCode,
            'data' => $decodedResponse,
            'raw_response' => $response
        ];
    }
    
    /**
     * Logging para debug
     */
    public static function logResponse($response, $action = 'API_CALL') {
        $logEntry = date('Y-m-d H:i:s') . " - SUPABASE {$action}: " . json_encode($response) . "\n";
        file_put_contents('supabase_log.txt', $logEntry, FILE_APPEND);
    }
}

/**
 * Funciones helper para facilitar el uso
 */

function getSupabaseClient($useServiceRole = false): SupabaseClient {
    return new SupabaseClient($useServiceRole);
}

function sendSupabaseVerificationEmail($email, $redirectUrl = null): array {
    $client = getSupabaseClient(true); // Usar service role para operaciones admin
    
    if (!$redirectUrl) {
        $redirectUrl = SITE_URL . '/verify-supabase.php';
    }
    
    $response = $client->sendVerificationEmail($email, $redirectUrl);
    SupabaseClient::logResponse($response, 'RESEND_VERIFICATION_EMAIL');
    
    return $response;
}

function verifySupabaseEmail($token, $type = 'signup'): array {
    $client = getSupabaseClient(true);
    
    $response = $client->verifyEmail($token, $type);
    SupabaseClient::logResponse($response, 'VERIFY_EMAIL_' . strtoupper($type));
    
    return $response;
}

function registerSupabaseUser($email, $password, $metadata = []): array {
    $client = getSupabaseClient(true);
    
    $options = [];
    if (!empty($metadata)) {
        $options['data'] = $metadata;
    }
    
    $response = $client->signUpUser($email, $password, $options);
    SupabaseClient::logResponse($response, 'REGISTER_USER');
    
    return $response;
}

/**
 * Verificar si Supabase está correctamente configurado
 */
function isSupabaseConfigured(): bool {
    return SUPABASE_URL !== 'https://your-project-id.supabase.co' &&
           SUPABASE_ANON_KEY !== 'your-anon-key' &&
           SUPABASE_SERVICE_ROLE_KEY !== 'your-service-role-key';
}

/**
 * Test de conectividad con Supabase
 */
function testSupabaseConnection(): array {
    if (!isSupabaseConfigured()) {
        return ['success' => false, 'error' => 'Supabase no configurado'];
    }
    
    try {
        $client = getSupabaseClient();
        $response = $client->makeRequest(SUPABASE_URL . '/rest/v1/', 'GET');
        
        return [
            'success' => $response['success'] || $response['status_code'] === 401,
            'status_code' => $response['status_code'],
            'configured' => true
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>