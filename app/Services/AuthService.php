<?php

namespace FlexCMS\Services;

use FlexCMS\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuthService
{
    /**
     * Intentos de login fallidos por IP
     */
    protected $failedAttempts = [];

    /**
     * Máximo de intentos permitidos
     */
    protected $maxAttempts = 5;

    /**
     * Tiempo de bloqueo en minutos
     */
    protected $lockoutTime = 15;

    /**
     * Autenticar usuario
     */
    public function attempt($credentials, $remember = false)
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';

        // Verificar rate limiting
        if ($this->hasTooManyAttempts()) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }

        // Buscar usuario
        $user = User::where('email', $email)
            ->where('status', 'active')
            ->first();

        if (!$user || !$this->verifyPassword($password, $user->password)) {
            $this->incrementAttempts();
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        // Verificar si el email está verificado
        if (config('auth.require_email_verification', true) && !$user->email_verified_at) {
            return ['success' => false, 'message' => 'Please verify your email address.'];
        }

        // Login exitoso
        $this->clearAttempts();
        $this->login($user, $remember);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Hacer login del usuario
     */
    public function login(User $user, $remember = false)
    {
        // Iniciar sesión
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['login_time'] = time();

        // Remember me token
        if ($remember) {
            $token = $this->generateRememberToken();
            $user->remember_token = hash('sha256', $token);
            $user->save();

            // Cookie por 30 días
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }

        // Actualizar último login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $this->getClientIp()
        ]);

        logger()->info('User logged in', ['user_id' => $user->id, 'ip' => $this->getClientIp()]);
    }

    /**
     * Registrar nuevo usuario
     */
    public function register($data)
    {
        // Validar datos
        $validation = $this->validateRegistration($data);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        try {
            // Crear usuario
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $this->hashPassword($data['password']),
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'display_name' => $data['display_name'] ?? $data['username'],
                'role' => 'user', // Rol por defecto
                'status' => 'active',
                'email_verification_token' => $this->generateEmailToken()
            ]);

            // Enviar email de verificación
            if (config('auth.require_email_verification', true)) {
                $this->sendEmailVerification($user);
            } else {
                $user->update(['email_verified_at' => now()]);
            }

            logger()->info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

            return ['success' => true, 'user' => $user];

        } catch (\Exception $e) {
            logger()->error('Registration failed', ['error' => $e->getMessage(), 'data' => $data]);
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Logout del usuario
     */
    public function logout()
    {
        $userId = $_SESSION['user_id'] ?? null;

        // Limpiar sesión
        session_destroy();

        // Limpiar remember token
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            
            if ($userId) {
                User::where('id', $userId)->update(['remember_token' => null]);
            }
        }

        logger()->info('User logged out', ['user_id' => $userId]);
    }

    /**
     * Verificar si el usuario está autenticado
     */
    public function check()
    {
        return isset($_SESSION['user_id']) && $this->validateSession();
    }

    /**
     * Obtener usuario autenticado
     */
    public function user()
    {
        if (!$this->check()) {
            return null;
        }

        return User::find($_SESSION['user_id']);
    }

    /**
     * Verificar contraseña
     */
    protected function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash de contraseña
     */
    protected function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iteraciones
            'threads' => 3          // 3 threads
        ]);
    }

    /**
     * Validar datos de registro
     */
    protected function validateRegistration($data)
    {
        $errors = [];

        // Username
        if (empty($data['username'])) {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters.';
        } elseif (User::where('username', $data['username'])->exists()) {
            $errors['username'] = 'Username already exists.';
        }

        // Email
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        } elseif (User::where('email', $data['email'])->exists()) {
            $errors['email'] = 'Email already exists.';
        }

        // Password
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $data['password'])) {
            $errors['password'] = 'Password must contain uppercase, lowercase, number and special character.';
        }

        // Confirm password
        if ($data['password'] !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validar sesión
     */
    protected function validateSession()
    {
        // Verificar timeout de sesión
        $sessionLifetime = config('session.lifetime', 120) * 60; // minutos a segundos
        $loginTime = $_SESSION['login_time'] ?? 0;

        if (time() - $loginTime > $sessionLifetime) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Rate limiting
     */
    protected function hasTooManyAttempts()
    {
        $ip = $this->getClientIp();
        $attempts = $this->failedAttempts[$ip] ?? ['count' => 0, 'time' => 0];

        // Reset si ha pasado el tiempo de bloqueo
        if (time() - $attempts['time'] > ($this->lockoutTime * 60)) {
            unset($this->failedAttempts[$ip]);
            return false;
        }

        return $attempts['count'] >= $this->maxAttempts;
    }

    /**
     * Incrementar intentos fallidos
     */
    protected function incrementAttempts()
    {
        $ip = $this->getClientIp();
        
        if (!isset($this->failedAttempts[$ip])) {
            $this->failedAttempts[$ip] = ['count' => 0, 'time' => time()];
        }

        $this->failedAttempts[$ip]['count']++;
        $this->failedAttempts[$ip]['time'] = time();
    }

    /**
     * Limpiar intentos
     */
    protected function clearAttempts()
    {
        $ip = $this->getClientIp();
        unset($this->failedAttempts[$ip]);
    }

    /**
     * Obtener IP del cliente
     */
    protected function getClientIp()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Generar token para remember me
     */
    protected function generateRememberToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generar token para verificación de email
     */
    protected function generateEmailToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Enviar email de verificación
     */
    protected function sendEmailVerification(User $user)
    {
        // Implementar envío de email
        // Por ahora solo log
        logger()->info('Email verification sent', ['user_id' => $user->id]);
    }

    /**
     * Verificar email
     */
    public function verifyEmail($token)
    {
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid verification token.'];
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null
        ]);

        logger()->info('Email verified', ['user_id' => $user->id]);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(User $user, $currentPassword, $newPassword)
    {
        if (!$this->verifyPassword($currentPassword, $user->password)) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $user->update(['password' => $this->hashPassword($newPassword)]);

        logger()->info('Password changed', ['user_id' => $user->id]);

        return ['success' => true];
    }

    /**
     * Reset de contraseña
     */
    public function resetPassword($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Por seguridad, no revelar si el email existe
            return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
        }

        $token = $this->generateEmailToken();
        $user->update(['password_reset_token' => $token]);

        // Enviar email (implementar)
        logger()->info('Password reset requested', ['user_id' => $user->id]);

        return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
    }
}