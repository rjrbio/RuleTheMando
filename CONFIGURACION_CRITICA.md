# ⚙️ CONFIGURACIÓN CRÍTICA DE SUPABASE

## 🚨 PASOS OBLIGATORIOS EN SUPABASE DASHBOARD:

### 1. 📍 **Site URL y Redirect URLs**
Ve a: **Authentication > URL Configuration**

- **Site URL**: `http://localhost/RuleTheMando`
- **Redirect URLs**: 
  - `http://localhost/RuleTheMando/verify-supabase.php`
  - `http://localhost/RuleTheMando/**` (wildcard para desarrollo)

### 2. 📧 **Template de Email**
Ve a: **Authentication > Templates**

En "Confirm your signup", cambia el template a:

```html
<h2>Confirma tu registro en {{ .SiteName }}</h2>
<p>Haz clic en el enlace para verificar tu cuenta:</p>
<p><a href="{{ .SiteURL }}/verify-supabase.php?token={{ .TokenHash }}&type=signup">Verificar cuenta</a></p>
<p>O copia este enlace: {{ .SiteURL }}/verify-supabase.php?token={{ .TokenHash }}&type=signup</p>
```

### 3. 🔐 **Configuración de Auth**
Ve a: **Authentication > Settings**

- ✅ **Enable email confirmations**: ON
- ✅ **Double confirm email changes**: ON  
- ⏱️ **JWT expiry limit**: 3600 (1 hora)

### 4. 📬 **SMTP (Opcional pero recomendado)**
Ve a: **Authentication > Settings > SMTP Settings**

Configura tu proveedor de email para mejor entregabilidad.

## 🧪 **PARA PROBAR:**

1. **Verificar config**: Accede a `test-supabase.php` en tu navegador
2. **Probar registro**: Ve a `login.php` → Registrarse
3. **Verificar logs**: Revisa `supabase_log.txt`

## ⚠️ **PROBLEMAS COMUNES:**

### Error 404 en verificación:
- **Causa**: Redirect URL mal configurada en Supabase
- **Solución**: Verificar que las URLs coincidan exactamente

### Error 400 en registro:
- **Causa**: Template de email mal configurado
- **Solución**: Usar el template proporcionado arriba

### Emails no llegan:
- **Causa**: Filtros de spam o falta SMTP
- **Solución**: Configurar SMTP o revisar carpeta spam

### CORS errors:
- **Causa**: Site URL incorrecta
- **Solución**: Verificar Site URL en dashboard