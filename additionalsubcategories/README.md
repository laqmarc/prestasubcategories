# Additional Subcategories Module for Prestashop 8

Este módulo permite asignar subcategorías adicionales a una categoría padre, además de sus hijos naturales.

## Características

- Multiselector en la página de edición de categorías del backoffice
- Las subcategorías adicionales se muestran junto con las subcategorías naturales
- Compatible con Prestashop 8
- Interfaz amigable con jQuery Chosen para mejor experiencia de usuario

## Instalación

1. Copia la carpeta `additionalsubcategories` a la carpeta `modules` de tu Prestashop
2. Ve a Módulos > Gestor de Módulos en tu backoffice
3. Busca "Additional Subcategories" y haz clic en Instalar

## Uso

1. Ve a Catálogo > Categorías
2. Edita cualquier categoría padre
3. Desplázate hacia abajo y encontrarás el campo "Additional Subcategories"
4. Selecciona las subcategorías adicionales que quieres mostrar
5. Guarda los cambios

En el frontend, las subcategorías adicionales aparecerán en la página de la categoría.

## Estructura de archivos

```
additionalsubcategories/
├── additionalsubcategories.php          # Archivo principal del módulo (Lógica y Hooks)
├── config.xml                           # Configuración del módulo
└── README.md                             # Este archivo
```

## Base de datos

El módulo crea una tabla adicional `category_additional_subcategories` para almacenar las relaciones entre categorías padres y subcategorías adicionales.

## Hooks utilizados

- `actionCategoryFormBuilderModifier`: Modifica el formulario de edición de categorías
- `actionAfterUpdateCategoryFormHandler`: Procesa los datos del formulario
- `actionCategorySubcategoriesModifier`: Inyecta subcategorías adicionales nativamente en el core