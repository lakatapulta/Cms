<?php

namespace FlexCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'cms_users';

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'display_name',
        'bio',
        'avatar',
        'role',
        'status',
        'email_verified_at',
        'email_verification_token',
        'password_reset_token',
        'remember_token',
        'last_login_at',
        'last_login_ip',
        'meta'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
        'password_reset_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'meta' => 'array'
    ];

    /**
     * Posts del usuario
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /**
     * Páginas del usuario
     */
    public function pages()
    {
        return $this->hasMany(Page::class, 'author_id');
    }

    /**
     * Comentarios del usuario
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function can($permission)
    {
        return app('roles')->userCan($this, $permission);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole($role)
    {
        return app('roles')->userHasRole($this, $role);
    }

    /**
     * Verificar si el usuario tiene alguno de los roles especificados
     */
    public function hasAnyRole(array $roles)
    {
        return app('roles')->userHasAnyRole($this, $roles);
    }

    /**
     * Verificar si el usuario puede realizar una acción en un recurso
     */
    public function canOnResource($permission, $resource)
    {
        return app('roles')->userCanOnResource($this, $permission, $resource);
    }

    /**
     * Verificar si es administrador
     */
    public function isAdmin()
    {
        return app('roles')->isAdmin($this);
    }

    /**
     * Verificar si puede acceder al panel de administración
     */
    public function canAccessAdmin()
    {
        return app('roles')->canAccessAdmin($this);
    }

    /**
     * Scope: Usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Usuarios verificados
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope: Por rol
     */
    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Administradores
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['super_admin', 'admin']);
    }

    /**
     * Accessor: Nombre completo
     */
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->username;
    }

    /**
     * Accessor: Iniciales
     */
    public function getInitialsAttribute()
    {
        $initials = '';
        
        if ($this->first_name) {
            $initials .= strtoupper(substr($this->first_name, 0, 1));
        }
        
        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }
        
        return $initials ?: strtoupper(substr($this->username, 0, 2));
    }

    /**
     * Accessor: Avatar URL
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return url('uploads/avatars/' . $this->avatar);
        }

        // Gravatar como fallback
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=150";
    }

    /**
     * Accessor: Está online
     */
    public function getIsOnlineAttribute()
    {
        if (!$this->last_login_at) {
            return false;
        }

        // Considerado online si ha hecho login en los últimos 5 minutos
        return $this->last_login_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Accessor: URL del perfil
     */
    public function getProfileUrlAttribute()
    {
        return url('/users/' . $this->username);
    }

    /**
     * Accessor: Información del rol
     */
    public function getRoleInfoAttribute()
    {
        return app('roles')->getRole($this->role);
    }

    /**
     * Accessor: Permisos del usuario
     */
    public function getPermissionsAttribute()
    {
        return app('roles')->getRolePermissions($this->role);
    }

    /**
     * Mutator: Email en minúsculas
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Mutator: Username en minúsculas
     */
    public function setUsernameAttribute($value)
    {
        $this->attributes['username'] = strtolower($value);
    }

    /**
     * Mutator: Display name por defecto
     */
    public function setDisplayNameAttribute($value)
    {
        $this->attributes['display_name'] = $value ?: $this->username;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Al crear usuario, establecer valores por defecto
        static::creating(function ($user) {
            if (!$user->display_name) {
                $user->display_name = $user->username;
            }
            
            if (!$user->role) {
                $user->role = 'user';
            }
            
            if (!$user->status) {
                $user->status = 'active';
            }
        });

        // Log cuando se crea un usuario
        static::created(function ($user) {
            logger()->info('User created', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ]);
        });

        // Log cuando se actualiza un usuario
        static::updated(function ($user) {
            $changes = $user->getChanges();
            
            if (!empty($changes)) {
                logger()->info('User updated', [
                    'user_id' => $user->id,
                    'changes' => array_keys($changes)
                ]);
            }
        });

        // Log cuando se elimina un usuario
        static::deleted(function ($user) {
            logger()->info('User deleted', [
                'user_id' => $user->id,
                'username' => $user->username
            ]);
        });
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getStats()
    {
        return [
            'posts_count' => $this->posts()->count(),
            'published_posts_count' => $this->posts()->where('status', 'published')->count(),
            'pages_count' => $this->pages()->count(),
            'comments_count' => $this->comments()->count(),
            'total_views' => $this->posts()->sum('views_count'),
            'member_since' => $this->created_at->diffForHumans(),
            'last_seen' => $this->last_login_at ? $this->last_login_at->diffForHumans() : 'Never'
        ];
    }

    /**
     * Verificar si el email está verificado
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Marcar email como verificado
     */
    public function markEmailAsVerified()
    {
        return $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null
        ]);
    }

    /**
     * Obtener usuarios sugeridos para seguir
     */
    public static function getSuggested($limit = 5)
    {
        return static::active()
            ->verified()
            ->whereIn('role', ['admin', 'editor', 'author'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Buscar usuarios
     */
    public static function search($query)
    {
        return static::where(function ($q) use ($query) {
            $q->where('username', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%")
              ->orWhere('first_name', 'like', "%{$query}%")
              ->orWhere('last_name', 'like', "%{$query}%")
              ->orWhere('display_name', 'like', "%{$query}%");
        });
    }
}