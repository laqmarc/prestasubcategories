<?php
/**
 * Additional Subcategories Module - Optimized Version
 * 
 * @author    Quexulo
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version   1.1.0
 */

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdditionalSubcategories extends Module
{
    const CACHE_KEY_PREFIX = 'additional_subcats_';
    
    public function __construct()
    {
        $this->name = 'additionalsubcategories';
        $this->tab = 'front_office_features';
        $this->version = '1.1.0';
        $this->author = 'Quexulo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        
        parent::__construct();

        $this->displayName = $this->l('Subcategorías Adicionales');
        $this->description = $this->l('Añade subcategorías adicionales a categorías padre más allá de sus hijos naturales');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        try {
            if (!parent::install()) {
                throw new Exception($this->l('Error al instalar el módulo padre'));
            }

            // Create database table with indexes
            $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'category_additional_subcategories` (
                `id_category_parent` INT(11) NOT NULL,
                `id_category_additional` INT(11) NOT NULL,
                PRIMARY KEY (`id_category_parent`, `id_category_additional`),
                KEY `idx_parent` (`id_category_parent`),
                KEY `idx_additional` (`id_category_additional`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

            if (!Db::getInstance()->execute($sql)) {
                throw new Exception($this->l('Error al crear la tabla de base de datos'));
            }

            // Register hooks
            if (!$this->registerHook([
                'actionCategoryFormBuilderModifier',
                'actionAfterUpdateCategoryFormHandler',
                'actionCategorySubcategoriesModifier',
                'displayFooter'
            ])) {
                throw new Exception($this->l('Error al registrar los hooks'));
            }

            return true;
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            return false;
        }
    }

    public function uninstall()
    {
        try {
            // Remove database table
            $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'category_additional_subcategories`';
            Db::getInstance()->execute($sql);

            // Clear all cache
            $this->clearModuleCache();

            return parent::uninstall();
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            return false;
        }
    }

    public function hookActionCategoryFormBuilderModifier($params)
    {
        $formBuilder = $params['form_builder'];
        $id_category = (int)Tools::getValue('id_category');
        
        // Get current additional subcategories using DbQuery
        $additional_subcategories = [];
        if ($id_category && Validate::isLoadedObject(new Category($id_category))) {
            $query = new DbQuery();
            $query->select('id_category_additional')
                  ->from('category_additional_subcategories')
                  ->where('id_category_parent = ' . (int)$id_category);
            
            $results = Db::getInstance()->executeS($query);
            foreach ($results as $row) {
                $additional_subcategories[] = (int)$row['id_category_additional'];
            }
        }

        // Get all categories except current one and its children
        $all_categories = $this->getAllCategories($id_category);

        $formBuilder->add('additional_subcategories', ChoiceType::class, [
            'label' => $this->l('Subcategorías Adicionales'),
            'help' => $this->l('Selecciona las subcategorías adicionales que quieras mostrar en esta categoría'),
            'choices' => $all_categories,
            'multiple' => true,
            'expanded' => false,
            'required' => false,
            'data' => $additional_subcategories,
            'attr' => [
                'class' => 'chosen-select',
            ],
        ]);
    }

    public function hookActionAfterUpdateCategoryFormHandler($params)
    {
        $id_category = (int)$params['id'];
        $form_data = $params['form_data'];

        try {
            // Clear existing additional subcategories using DbQuery
            $delete = 'DELETE FROM `' . _DB_PREFIX_ . 'category_additional_subcategories` 
                      WHERE `id_category_parent` = ' . (int)$id_category;
            Db::getInstance()->execute($delete);

            // Insert new additional subcategories using bulk insert
            if (isset($form_data['additional_subcategories']) && is_array($form_data['additional_subcategories'])) {
                $values = [];
                foreach ($form_data['additional_subcategories'] as $id_additional) {
                    $id_additional = (int)$id_additional;
                    if ($id_additional > 0 && $id_additional != $id_category) {
                        $values[] = '(' . (int)$id_category . ', ' . $id_additional . ')';
                    }
                }
                
                if (!empty($values)) {
                    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'category_additional_subcategories` 
                            (`id_category_parent`, `id_category_additional`) 
                            VALUES ' . implode(',', $values);
                    Db::getInstance()->execute($sql);
                }
            }

            // Clear cache for this category
            $this->clearCategoryCache($id_category);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'AdditionalSubcategories: Error saving - ' . $e->getMessage(),
                3,
                null,
                'Category',
                $id_category
            );
        }
    }

    public function hookActionCategorySubcategoriesModifier($params)
    {
        $additional_categories = $this->getAdditionalCategoriesData($params);
        
        if (!empty($additional_categories)) {
            $params['subcategories'] = array_merge($params['subcategories'], $additional_categories);
        }
    }

    public function hookDisplayFooter($params)
    {
        if ($this->context->controller->php_self !== 'category') {
            return;
        }

        $id_category = (int)Tools::getValue('id_category');
        $params_mock = ['id_category' => $id_category, 'subcategories' => []];
        $additional_categories = $this->getAdditionalCategoriesData($params_mock);

        if (empty($additional_categories)) {
            return;
        }

        // Encode data for JS
        $json_categories = json_encode($additional_categories);
        $base_uri = Tools::getShopProtocol() . Tools::getShopDomain() . __PS_BASE_URI__;
        $img_cat_dir = _THEME_CAT_DIR_;

        // Inject inline script (more reliable than external file)
        return '<script type="text/javascript">
        (function() {
            var additionalCats = ' . $json_categories . ';
            var baseUri = "' . $base_uri . '";
            var imgCatDir = "' . $img_cat_dir . '";
            
            document.addEventListener("DOMContentLoaded", function() {
                var $list = document.querySelector("#subcategories .subcategories-list");
                
                if (!$list) {
                    return;
                }
                
                additionalCats.forEach(function(cat) {
                    var exists = !!$list.querySelector(\'a[href*="id_category=\' + cat.id_category + \'"]\') || 
                                 !!$list.querySelector(\'a[href*="/\' + cat.id_category + \'-"]\');
                    
                    if (!exists) {
                        var categoryLink = baseUri + "index.php?id_category=" + cat.id_category + "&controller=category";
                        var imageUrl = imgCatDir + cat.id_category + "-category_default.jpg";
                        
                        var html = "<li>" +
                            "<div class=\\"subcategory-image\\">" +
                                "<a href=\\"" + categoryLink + "\\" title=\\"" + cat.name + "\\" class=\\"img\\">" +
                                    "<img class=\\"img-fluid\\" src=\\"" + imageUrl + "\\" alt=\\"" + cat.name + "\\" loading=\\"lazy\\">" +
                                "</a>" +
                            "</div>" +
                            "<h5>" +
                                "<a class=\\"subcategory-name\\" href=\\"" + categoryLink + "\\">" + cat.name + "</a>" +
                            "</h5>" +
                        "</li>";
                        
                        $list.insertAdjacentHTML("beforeend", html);
                    }
                });
            });
        })();
        </script>';
    }

    private function getAdditionalCategoriesData($params)
    {
        if (!isset($params['id_category'])) {
            return [];
        }

        $id_category = (int)$params['id_category'];
        $id_lang = (int)$this->context->language->id;

        // Check cache first
        $cache_key = self::CACHE_KEY_PREFIX . $id_category . '_' . $id_lang;
        
        if (Cache::isStored($cache_key)) {
            return Cache::retrieve($cache_key);
        }

        // Get additional subcategories with image ID using DbQuery
        $query = new DbQuery();
        $query->select('c.*, cl.name, cl.description, cl.link_rewrite, c.id_category as id_image')
              ->from('category', 'c')
              ->innerJoin('category_additional_subcategories', 'cas', 'c.id_category = cas.id_category_additional')
              ->leftJoin('category_lang', 'cl', 'c.id_category = cl.id_category AND cl.id_lang = ' . (int)$id_lang)
              ->where('cas.id_category_parent = ' . (int)$id_category)
              ->where('c.active = 1')
              ->orderBy('c.position ASC');
        
        $data = Db::getInstance()->executeS($query);
        
        // Store in cache
        Cache::store($cache_key, $data);
        
        return $data;
    }

    private function getAllCategories($exclude_id = 0)
    {
        $categories = [];
        $id_lang = (int)$this->context->language->id;
        
        // Get children to exclude (prevent circular references)
        $exclude_ids = [(int)$exclude_id, 1]; // Always exclude root category
        
        if ($exclude_id > 0) {
            $children = Category::getChildren($exclude_id, $id_lang, false);
            if (is_array($children)) {
                foreach ($children as $child) {
                    $exclude_ids[] = (int)$child['id_category'];
                }
            }
        }
        
        // Build query using DbQuery
        $query = new DbQuery();
        $query->select('c.id_category, cl.name')
              ->from('category', 'c')
              ->innerJoin('category_lang', 'cl', 'c.id_category = cl.id_category')
              ->where('c.id_category NOT IN (' . implode(',', array_map('intval', $exclude_ids)) . ')')
              ->where('cl.id_lang = ' . (int)$id_lang)
              ->where('c.active = 1')
              ->orderBy('cl.name ASC');
        
        $results = Db::getInstance()->executeS($query);
        
        foreach ($results as $row) {
            $categories[$row['name']] = (int)$row['id_category'];
        }
        
        return $categories;
    }

    private function clearCategoryCache($id_category)
    {
        // Clear cache for all languages
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $cache_key = self::CACHE_KEY_PREFIX . (int)$id_category . '_' . (int)$lang['id_lang'];
            Cache::clean($cache_key);
        }
    }

    private function clearModuleCache()
    {
        Cache::clean(self::CACHE_KEY_PREFIX . '*');
    }
}