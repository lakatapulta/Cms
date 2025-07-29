# 🎨 Guía Completa: Creación de Temas en FlexCMS

## 📋 **PROCESO RECOMENDADO PARA CREAR UN TEMA**

### **PASO 1: Especificaciones del Cliente**
El cliente proporciona las siguientes especificaciones:

```yaml
# Ejemplo de especificaciones
nombre_tema: "BusinessPro"
tipo_sitio: "Corporativo/Empresarial"
estilo: "Moderno y profesional"

colores:
  primario: "#2563eb"      # Azul empresarial
  secundario: "#64748b"    # Gris azulado
  accent: "#f59e0b"        # Dorado/Naranja
  fondo: "#ffffff"         # Blanco
  texto: "#1e293b"         # Gris oscuro

tipografias:
  headings: "Poppins"      # Para títulos
  body: "Inter"            # Para texto

logo:
  tipo: "Texto + Icono"
  descripcion: "Logo empresarial moderno"
  
layout:
  header: "Fijo con navegación horizontal"
  sidebar: "Sin sidebar en home, con sidebar en blog"
  footer: "3 columnas con links, contacto y redes"
  
caracteristicas:
  - "Hero section llamativo"
  - "Sección de servicios con iconos"
  - "Testimonios con carrusel"
  - "Blog integrado"
  - "Formulario de contacto"
  - "Animaciones suaves al scroll"
```

### **PASO 2: Análisis y Planificación**
- ✅ Revisar especificaciones
- ✅ Definir estructura de archivos
- ✅ Planificar componentes necesarios
- ✅ Establecer sistema de colores y tipografías

### **PASO 3: Creación de la Estructura**

```
themes/nombre-tema/
├── theme.json              # Configuración del tema
├── functions.php           # Funciones PHP del tema
├── screenshot.png          # Captura del tema
├── templates/              # Plantillas Twig
│   ├── layout.twig        # Layout base
│   ├── home.twig          # Página de inicio
│   ├── blog/              # Templates del blog
│   │   ├── index.twig     # Lista de posts
│   │   ├── single.twig    # Post individual
│   │   └── category.twig  # Categoría
│   ├── pages/             # Pages templates
│   │   ├── default.twig   # Página por defecto
│   │   ├── about.twig     # Página sobre nosotros
│   │   └── contact.twig   # Página de contacto
│   └── partials/          # Componentes reutilizables
│       ├── header.twig    # Header
│       ├── footer.twig    # Footer
│       ├── sidebar.twig   # Sidebar
│       └── hero.twig      # Hero section
├── assets/                # Archivos estáticos
│   ├── css/
│   │   ├── style.css      # CSS principal
│   │   └── components.css # Componentes
│   ├── js/
│   │   ├── app.js         # JavaScript principal
│   │   └── components.js  # JS de componentes
│   ├── images/            # Imágenes del tema
│   └── fonts/             # Fuentes personalizadas
└── README.md              # Documentación del tema
```

### **PASO 4: Implementación**

#### **4.1. Configuración del Tema (theme.json)**
```json
{
    "name": "BusinessPro",
    "description": "Tema profesional para empresas y corporaciones",
    "version": "1.0.0",
    "author": "FlexCMS Team",
    "screenshot": "screenshot.png",
    "supports": {
        "menus": true,
        "widgets": true,
        "post-thumbnails": true,
        "custom-headers": true,
        "custom-backgrounds": true,
        "custom-logo": true
    },
    "template_parts": {
        "header": "partials/header.twig",
        "footer": "partials/footer.twig",
        "sidebar": "partials/sidebar.twig"
    },
    "customizer": {
        "colors": {
            "primary": "#2563eb",
            "secondary": "#64748b", 
            "accent": "#f59e0b"
        },
        "typography": {
            "headings": "Poppins",
            "body": "Inter"
        },
        "layout": {
            "header_style": "fixed",
            "sidebar_position": "right",
            "footer_columns": 3
        }
    }
}
```

#### **4.2. CSS con Variables Personalizadas**
```css
:root {
    /* Colores del cliente */
    --color-primary: #2563eb;
    --color-secondary: #64748b;
    --color-accent: #f59e0b;
    --color-background: #ffffff;
    --color-text: #1e293b;
    
    /* Tipografías del cliente */
    --font-headings: 'Poppins', sans-serif;
    --font-body: 'Inter', sans-serif;
    
    /* Espaciados */
    --spacing-xs: 0.5rem;
    --spacing-sm: 1rem;
    --spacing-md: 2rem;
    --spacing-lg: 3rem;
    --spacing-xl: 4rem;
}

/* Componentes específicos del tema */
.hero-section {
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    color: white;
    padding: var(--spacing-xl) 0;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--spacing-md);
}

.testimonial-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: var(--spacing-md);
}
```

#### **4.3. Templates Twig Personalizados**
```twig
{# templates/home.twig #}
{% extends "layout.twig" %}

{% block content %}
    {# Hero Section personalizado #}
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">{{ config('site.tagline') }}</h1>
                <p class="hero-subtitle">{{ config('site.description') }}</p>
                <a href="/contact" class="btn btn-accent btn-lg">Contáctanos</a>
            </div>
        </div>
    </section>

    {# Servicios #}
    <section class="services-section">
        <div class="container">
            <h2 class="section-title">Nuestros Servicios</h2>
            <div class="services-grid">
                {# Servicios dinámicos desde configuración #}
                {% for service in theme.services %}
                    <div class="service-card">
                        <i class="fas {{ service.icon }} service-icon"></i>
                        <h3>{{ service.title }}</h3>
                        <p>{{ service.description }}</p>
                    </div>
                {% endfor %}
            </div>
        </div>
    </section>
{% endblock %}
```

### **PASO 5: Funcionalidades PHP Personalizadas**

```php
// functions.php
<?php

// Configurar servicios del tema
function businesspro_setup() {
    // Registrar menús
    register_nav_menus([
        'primary' => 'Menú Principal',
        'footer' => 'Menú Footer'
    ]);
    
    // Configurar servicios (ejemplo)
    add_theme_support('custom-services', [
        [
            'title' => 'Desarrollo Web',
            'description' => 'Creamos sitios web modernos y funcionales',
            'icon' => 'fa-code'
        ],
        [
            'title' => 'Marketing Digital',
            'description' => 'Estrategias para hacer crecer tu negocio',
            'icon' => 'fa-chart-line'
        ]
    ]);
}

// Hook para configuración
add_action('after_setup_theme', 'businesspro_setup');

// Enqueue scripts y styles
function businesspro_scripts() {
    wp_enqueue_style('businesspro-style', get_stylesheet_uri());
    wp_enqueue_script('businesspro-script', get_template_directory_uri() . '/assets/js/app.js', [], '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'businesspro_scripts');
```

## 🎯 **EJEMPLO PRÁCTICO: Dime tus especificaciones**

**Para crear tu tema, necesito que me digas:**

### 📝 **INFORMACIÓN BÁSICA**
1. **¿Qué tipo de sitio es?** (blog personal, empresa, portfolio, tienda, etc.)
2. **¿Qué nombre quieres para el tema?**
3. **¿Qué estilo buscas?** (moderno, clásico, minimalista, corporativo, creativo)

### 🎨 **DISEÑO Y COLORES**
4. **¿Tienes colores específicos?** (hex codes o nombres de colores)
5. **¿Qué tipografías prefieres?** (ej: Roboto, Open Sans, Poppins)
6. **¿Tienes logo o descripción del logo?**

### 📐 **LAYOUT Y ESTRUCTURA**
7. **¿Cómo quieres el header?** (fijo, transparente, con menú hamburguesa)
8. **¿Qué debe tener la página principal?** (hero, servicios, testimonios, etc.)
9. **¿Quieres sidebar en el blog?**
10. **¿Qué debe tener el footer?**

### ✨ **CARACTERÍSTICAS ESPECIALES**
11. **¿Quieres animaciones?** (suaves, llamativas, ninguna)
12. **¿Alguna funcionalidad específica?** (carrusel, filtros, etc.)

---

## 💡 **EJEMPLO DE RESPUESTA DEL CLIENTE**

```
"Quiero un tema para mi blog de tecnología llamado 'TechBlog'. 
Estilo moderno y limpio. Colores: azul #3b82f6 y gris #6b7280. 
Tipografía: Poppins para títulos e Inter para texto.
Header fijo con menú horizontal. 
Página principal con hero grande, posts destacados y sidebar.
Footer simple con redes sociales.
Animaciones suaves al hacer scroll."
```

**Con esta información, yo creo el tema completo en 10-15 minutos.** 🚀

---

## 🤔 **¿Cuál opción prefieres?**

1. **📝 Me das especificaciones** → Yo creo todo
2. **📄 Me das HTML/CSS** → Yo lo convierto a FlexCMS
3. **🖼️ Me das referencia** → Yo replico el diseño
4. **🛠️ Trabajamos juntos** → Paso a paso

**¡Dime qué opción prefieres y empezamos!** ✨