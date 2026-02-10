# Módulo de Subcategorías Adicionales para PrestaShop 8

Este módulo permite asignar subcategorías adicionales a una categoría padre, además de sus hijos naturales.

## ✨ Características

- Multiselector en la página de edición de categorías del backoffice
- Las subcategorías adicionales se muestran junto con las subcategorías naturales
- Compatible con PrestaShop 8+
- Interfaz amigable con jQuery Chosen para mejor experiencia de usuario
- **Sistema de caché** para máximo rendimiento
- **Prevención de referencias circulares**
- **Queries optimizadas** con DbQuery builder
- **Seguridad mejorada** con validación de datos
- **Inyección inline** para máxima compatibilidad con temas

## 📦 Instalación

1. Copia la carpeta `additionalsubcategories` a la carpeta `modules` de tu PrestaShop
2. Ve a Módulos > Gestor de Módulos en tu backoffice
3. Busca "Subcategorías Adicionales" y haz clic en Instalar

## 🚀 Uso

1. Ve a Catálogo > Categorías
2. Edita cualquier categoría padre
3. Desplázate hacia abajo y encontrarás el campo "Subcategorías Adicionales"
4. Selecciona las subcategorías adicionales que quieres mostrar
5. Guarda los cambios

En el frontend, las subcategorías adicionales aparecerán automáticamente en la página de la categoría.

## 📁 Estructura de archivos

```
additionalsubcategories/
├── additionalsubcategories.php          # Archivo principal del módulo
├── config.xml                           # Configuración del módulo
└── README.md                             # Este archivo
```

## 🗄️ Base de datos

El módulo crea una tabla adicional `category_additional_subcategories` con índices optimizados para almacenar las relaciones entre categorías padres y subcategorías adicionales.

## 🔌 Hooks utilizados

- `actionCategoryFormBuilderModifier`: Modifica el formulario de edición de categorías
- `actionAfterUpdateCategoryFormHandler`: Procesa los datos del formulario
- `actionCategorySubcategoriesModifier`: Inyecta subcategorías adicionales nativamente en el core
- `displayFooter`: Inyección inline de JavaScript como fallback universal

## ⚡ Optimizaciones v1.1.0

- **DbQuery Builder**: Todas las queries usan el builder de PrestaShop
- **Sistema de Caché**: Caché automático con invalidación inteligente
- **Bulk INSERT**: Inserciones múltiples en una sola query
- **Validación**: Validación de objetos antes de usar
- **Logging**: Registro de errores con PrestaShopLogger
- **Prevención Circular**: No permite seleccionar hijos de la categoría actual
- **Índices BD**: Índices optimizados para queries rápidas
- **Script Inline**: Inyección inline para máxima compatibilidad con cualquier tema