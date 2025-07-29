<?php

namespace FlexCMS\Services;

use FlexCMS\Models\User;

class RoleService
{
    /**
     * Roles disponibles en el sistema
     */
    protected $roles = [
        'super_admin' => [
            'name' => 'Super Administrator',
            'description' => 'Full system access',
            'permissions' => ['*'] // Todos los permisos
        ],
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Administrative access',
            'permissions' => [
                'manage_users',
                'manage_posts',
                'manage_pages',
                'manage_categories',
                'manage_modules',
                'manage_themes',
                'manage_settings',
                'view_analytics',
                'manage_files'
            ]
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Content management access',
            'permissions' => [
                'manage_posts',
                'manage_pages',
                'manage_categories',
                'upload_files',
                'view_analytics'
            ]
        ],
        'author' => [
            'name' => 'Author',
            'description' => 'Content creation access',
            'permissions' => [
                'create_posts',
                'edit_own_posts',
                'delete_own_posts',
                'upload_files'
            ]
        ],
        'contributor' => [
            'name' => 'Contributor',
            'description' => 'Limited content access',
            'permissions' => [
                'create_posts',
                'edit_own_posts'
            ]
        ],
        'user' => [
            'name' => 'User',
            'description' => 'Basic user access',
            'permissions' => [
                'view_posts',
                'comment_posts',
                'edit_profile'
            ]
        ]
    ];

    /**
     * Permisos disponibles en el sistema
     */
    protected $permissions = [
        // Usuarios
        'manage_users' => 'Manage all users',
        'create_users' => 'Create new users',
        'edit_users' => 'Edit user accounts',
        'delete_users' => 'Delete users',
        'view_users' => 'View user list',
        
        // Posts
        'manage_posts' => 'Manage all posts',
        'create_posts' => 'Create new posts',
        'edit_posts' => 'Edit any posts',
        'edit_own_posts' => 'Edit own posts',
        'delete_posts' => 'Delete any posts',
        'delete_own_posts' => 'Delete own posts',
        'publish_posts' => 'Publish posts',
        'view_posts' => 'View posts',
        
        // Páginas
        'manage_pages' => 'Manage all pages',
        'create_pages' => 'Create new pages',
        'edit_pages' => 'Edit pages',
        'delete_pages' => 'Delete pages',
        
        // Categorías
        'manage_categories' => 'Manage categories',
        'create_categories' => 'Create categories',
        'edit_categories' => 'Edit categories',
        'delete_categories' => 'Delete categories',
        
        // Comentarios
        'manage_comments' => 'Manage all comments',
        'moderate_comments' => 'Moderate comments',
        'comment_posts' => 'Comment on posts',
        
        // Archivos
        'manage_files' => 'Manage all files',
        'upload_files' => 'Upload files',
        'delete_files' => 'Delete files',
        
        // Módulos
        'manage_modules' => 'Manage modules',
        'install_modules' => 'Install modules',
        'activate_modules' => 'Activate/deactivate modules',
        
        // Temas
        'manage_themes' => 'Manage themes',
        'install_themes' => 'Install themes',
        'activate_themes' => 'Activate themes',
        'customize_themes' => 'Customize themes',
        
        // Configuraciones
        'manage_settings' => 'Manage system settings',
        'manage_general_settings' => 'Manage general settings',
        'manage_security_settings' => 'Manage security settings',
        
        // Analytics
        'view_analytics' => 'View analytics',
        'export_data' => 'Export data',
        
        // Perfil
        'edit_profile' => 'Edit own profile',
        'change_password' => 'Change own password',
        
        // Sistema
        'view_logs' => 'View system logs',
        'manage_system' => 'Manage system',
        'backup_system' => 'Backup system'
    ];

    /**
     * Verificar si un usuario tiene un permiso específico
     */
    public function userCan(User $user, $permission)
    {
        // Super admin tiene todos los permisos
        if ($user->role === 'super_admin') {
            return true;
        }

        // Obtener permisos del rol
        $rolePermissions = $this->getRolePermissions($user->role);

        // Verificar permiso directo
        if (in_array($permission, $rolePermissions)) {
            return true;
        }

        // Verificar permisos de "own" (propios)
        if (strpos($permission, '_own_') !== false) {
            $basePermission = str_replace('_own_', '_', $permission);
            if (in_array($basePermission, $rolePermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si un usuario tiene un rol específico
     */
    public function userHasRole(User $user, $role)
    {
        return $user->role === $role;
    }

    /**
     * Verificar si un usuario tiene alguno de los roles especificados
     */
    public function userHasAnyRole(User $user, array $roles)
    {
        return in_array($user->role, $roles);
    }

    /**
     * Verificar si un usuario puede realizar una acción en un recurso específico
     */
    public function userCanOnResource(User $user, $permission, $resource = null)
    {
        // Verificar permiso básico
        if (!$this->userCan($user, $permission)) {
            return false;
        }

        // Si no hay recurso específico, el permiso básico es suficiente
        if (!$resource) {
            return true;
        }

        // Verificar permisos de "own" (propios)
        if (strpos($permission, '_own_') !== false) {
            // Verificar si el usuario es el propietario del recurso
            if (method_exists($resource, 'getAuthorId')) {
                return $resource->getAuthorId() === $user->id;
            } elseif (isset($resource->author_id)) {
                return $resource->author_id === $user->id;
            } elseif (isset($resource->user_id)) {
                return $resource->user_id === $user->id;
            }
        }

        return true;
    }

    /**
     * Obtener permisos de un rol
     */
    public function getRolePermissions($role)
    {
        if (!isset($this->roles[$role])) {
            return [];
        }

        $permissions = $this->roles[$role]['permissions'];

        // Si tiene permiso "*", devolver todos los permisos
        if (in_array('*', $permissions)) {
            return array_keys($this->permissions);
        }

        return $permissions;
    }

    /**
     * Obtener todos los roles disponibles
     */
    public function getAllRoles()
    {
        return $this->roles;
    }

    /**
     * Obtener información de un rol específico
     */
    public function getRole($role)
    {
        return $this->roles[$role] ?? null;
    }

    /**
     * Obtener todos los permisos disponibles
     */
    public function getAllPermissions()
    {
        return $this->permissions;
    }

    /**
     * Asignar rol a un usuario
     */
    public function assignRole(User $user, $role)
    {
        if (!isset($this->roles[$role])) {
            throw new \InvalidArgumentException("Role '{$role}' does not exist.");
        }

        $oldRole = $user->role;
        $user->update(['role' => $role]);

        logger()->info('Role assigned', [
            'user_id' => $user->id,
            'old_role' => $oldRole,
            'new_role' => $role
        ]);

        return true;
    }

    /**
     * Verificar si un rol es superior a otro
     */
    public function isRoleHigher($role1, $role2)
    {
        $hierarchy = [
            'super_admin' => 6,
            'admin' => 5,
            'editor' => 4,
            'author' => 3,
            'contributor' => 2,
            'user' => 1
        ];

        $level1 = $hierarchy[$role1] ?? 0;
        $level2 = $hierarchy[$role2] ?? 0;

        return $level1 > $level2;
    }

    /**
     * Verificar si un usuario puede gestionar a otro usuario
     */
    public function canManageUser(User $manager, User $target)
    {
        // Un usuario no puede gestionarse a sí mismo para ciertos cambios
        if ($manager->id === $target->id) {
            return false;
        }

        // Super admin puede gestionar a todos
        if ($manager->role === 'super_admin') {
            return true;
        }

        // Admin puede gestionar roles inferiores
        if ($manager->role === 'admin') {
            return !in_array($target->role, ['super_admin', 'admin']);
        }

        return false;
    }

    /**
     * Obtener roles que un usuario puede asignar
     */
    public function getAssignableRoles(User $user)
    {
        if ($user->role === 'super_admin') {
            return $this->roles;
        }

        if ($user->role === 'admin') {
            return array_filter($this->roles, function($role, $key) {
                return !in_array($key, ['super_admin']);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return [];
    }

    /**
     * Verificar capacidades de gestión de contenido
     */
    public function canManageContent(User $user, $contentType = null)
    {
        $managePermissions = [
            'posts' => 'manage_posts',
            'pages' => 'manage_pages',
            'categories' => 'manage_categories',
            'comments' => 'manage_comments'
        ];

        if ($contentType && isset($managePermissions[$contentType])) {
            return $this->userCan($user, $managePermissions[$contentType]);
        }

        // Verificar si puede gestionar algún tipo de contenido
        foreach ($managePermissions as $permission) {
            if ($this->userCan($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar capacidades administrativas
     */
    public function isAdmin(User $user)
    {
        return in_array($user->role, ['super_admin', 'admin']);
    }

    /**
     * Verificar si puede acceder al panel de administración
     */
    public function canAccessAdmin(User $user)
    {
        return $this->isAdmin($user) || $this->canManageContent($user);
    }

    /**
     * Middleware helper para verificar permisos
     */
    public function requirePermission($permission)
    {
        return function($request, $next) use ($permission) {
            $user = auth()->user();
            
            if (!$user || !$this->userCan($user, $permission)) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Insufficient permissions'], 403);
                }
                
                return redirect('/login')->with('error', 'You do not have permission to access this area.');
            }
            
            return $next($request);
        };
    }

    /**
     * Middleware helper para verificar roles
     */
    public function requireRole($role)
    {
        return function($request, $next) use ($role) {
            $user = auth()->user();
            
            if (!$user || !$this->userHasRole($user, $role)) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Insufficient role'], 403);
                }
                
                return redirect('/login')->with('error', 'You do not have the required role to access this area.');
            }
            
            return $next($request);
        };
    }
}