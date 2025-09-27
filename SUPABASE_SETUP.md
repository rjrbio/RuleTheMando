# 🚀 Configuración de Supabase para Rule the Mando

## 📋 Pasos para configurar Supabase:

### 1. Crear proyecto en Supabase
1. Ve a [https://supabase.com](https://supabase.com)
2. Crea una cuenta o inicia sesión
3. Crea un nuevo proyecto
4. Anota tu **URL del proyecto** y **claves API**

### 2. Configurar las credenciales
Edita el archivo `supabase-config.php` y reemplaza:

```php
define('SUPABASE_URL', 'https://tu-project-id.supabase.co');
define('SUPABASE_ANON_KEY', 'tu-clave-anonima');
define('SUPABASE_SERVICE_ROLE_KEY', 'tu-clave-service-role');
```

### 3. Configurar Auth en Supabase Dashboard

#### En el panel de Auth > Settings:
- **Site URL**: `http://localhost/rule_the_mando` (desarrollo) o tu dominio
- **Redirect URLs**: 
  - `http://localhost/rule_the_mando/verify-supabase.php`
  - Tu dominio en producción

#### Templates de Email:
- Ve a Auth > Templates
- Personaliza el template de "Confirm your signup"
- Cambia la redirect URL a: `{{ .SiteURL }}/verify-supabase.php?token={{ .TokenHash }}&type=signup`

### 4. Configurar SMTP (Opcional pero recomendado)
En Auth > Settings > SMTP Settings:
- Configura tu proveedor SMTP (Gmail, SendGrid, etc.)
- Esto mejorará la entregabilidad de emails

## 🔧 Funcionalidades implementadas:

### ✅ Sistema híbrido Local + Supabase:
- **Base de datos local**: MySQL para usuarios y datos del juego
- **Supabase Auth**: Gestión de verificación de emails
- **Fallback**: Sistema local si Supabase falla

### ✅ Login con Username:
- Login usa `username` en lugar de email
- Username debe ser único y tener 3-50 caracteres
- Solo letras, números, guiones y guiones bajos

### ✅ Verificación de emails mejorada:
- Emails enviados vía Supabase (mejor entregabilidad)
- Templates profesionales
- Verificación automática al hacer clic

## 📁 Archivos modificados:

- `login.php` - Login con username + integración Supabase
- `supabase-config.php` - Cliente y configuración Supabase
- `verify-supabase.php` - Verificación de emails vía Supabase
- `resend-verification.php` - Reenvío usando Supabase

## 🚀 Para usar en producción:

1. **Cambiar SITE_URL** en `config.php` a tu dominio real
2. **Configurar SUPABASE_URL** con tu proyecto real
3. **Configurar redirect URLs** en Supabase Dashboard
4. **Configurar SMTP** en Supabase para mejor entregabilidad

## 🐛 Debug:

- Logs de Supabase en: `supabase_log.txt`
- Logs de emails locales en: `email_log.txt`
- Información de debug en `verify-supabase.php` (solo localhost)

## 🔗 URLs importantes:

- Login: `login.php`
- Verificación: `verify-supabase.php`
- Reenvío: `resend-verification.php`
- Debug emails: `dev-email-viewer.php`