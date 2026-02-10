<?php
/**
 * 2007-2025 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Quexulo
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version   Release: $Revision$
 */

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdditionalSubcategories extends Module
{
    public function __construct()
    {
        $this->name = 'additionalsubcategories';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Quexulo';
        $this->need_instance = 0;
        $this->bootstrap = true;
        
        parent::__construct();

        $this->displayName = $this->l('Additional Subcategories');
        $this->description = $this->l('Add additional subcategories to parent categories beyond their natural children');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Create database table
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'category_additional_subcategories` (
            `id_category_parent` INT(11) NOT NULL,
            `id_category_additional` INT(11) NOT NULL,
            PRIMARY KEY (`id_category_parent`, `id_category_additional`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        // Register hooks
        return $this->registerHook([
            'actionCategoryFormBuilderModifier',
            'actionAfterUpdateCategoryFormHandler',
            'actionCategorySubcategoriesModifier',
            'displayFooter'
        ]);
    }

    public function uninstall()
    {
        // Remove database table
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'category_additional_subcategories`';
        Db::getInstance()->execute($sql);

        return parent::uninstall();
    }

    public function hookActionCategoryFormBuilderModifier($params)
    {
        $formBuilder = $params['form_builder'];
        $id_category = (int)Tools::getValue('id_category');
        
        // Get current additional subcategories
        $additional_subcategories = [];
        if ($id_category) {
            $sql = 'SELECT id_category_additional 
                    FROM ' . _DB_PREFIX_ . 'category_additional_subcategories 
                    WHERE id_category_parent = ' . $id_category;
            $results = Db::getInstance()->executeS($sql);
            foreach ($results as $row) {
                $additional_subcategories[] = $row['id_category_additional'];
            }
        }

        // Get all categories except current one and its children
        $all_categories = $this->getAllCategories($id_category);

        $formBuilder->add('additional_subcategories', ChoiceType::class, [
            'label' => $this->l('Additional Subcategories'),
            'help' => $this->l('Select additional subcategories to display in this category'),
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

        // Clear existing additional subcategories
        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'category_additional_subcategories 
                WHERE id_category_parent = ' . $id_category;
        Db::getInstance()->execute($sql);

        // Insert new additional subcategories
        if (isset($form_data['additional_subcategories']) && is_array($form_data['additional_subcategories'])) {
            foreach ($form_data['additional_subcategories'] as $id_additional) {
                $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'category_additional_subcategories 
                        (id_category_parent, id_category_additional) 
                        VALUES (' . $id_category . ', ' . (int)$id_additional . ')';
                Db::getInstance()->execute($sql);
            }
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

        // We inject a script that checks if the categories are already there.
        // If not (because the theme doesn't support the modifier hook), it injects them.
        $json_categories = json_encode($additional_categories);
        $base_uri = Tools::getShopProtocol() . Tools::getShopDomain() . __PS_BASE_URI__;
        $img_cat_dir = _THEME_CAT_DIR_;

        $script = '
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                var additionalCats = ' . $json_categories . ';
                var $list = document.querySelector("#subcategories .subcategories-list");
                
                if (!$list) {
                    return;
                }

                // Check if they are already there (avoid duplicates)
                additionalCats.forEach(function(cat) {
                    var exists = !!$list.querySelector(\'a[href*="id_category=\' + cat.id_category + \'"]\') || 
                                 !!$list.querySelector(\'a[href*="/\' + cat.id_category + \'-\"]\');
                    
                    if (!exists) {
                        var categoryLink = "' . $base_uri . 'index.php?id_category=" + cat.id_category + "&controller=category";
                        var imageUrl = "' . $img_cat_dir . '" + cat.id_category + "-category_default.jpg";
                        
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
        </script>';

        return $script;
    }

    private function getAdditionalCategoriesData($params)
    {
        if (!isset($params['id_category'])) {
            return [];
        }

        $id_category = (int)$params['id_category'];
        $id_lang = (int)$this->context->language->id;

        // Get additional subcategories with image ID (same as category ID)
        $sql = 'SELECT c.*, cl.name, cl.description, cl.link_rewrite, c.id_category as id_image
                FROM ' . _DB_PREFIX_ . 'category c
                INNER JOIN ' . _DB_PREFIX_ . 'category_additional_subcategories cas 
                ON c.id_category = cas.id_category_additional
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl 
                ON c.id_category = cl.id_category AND cl.id_lang = ' . $id_lang . '
                WHERE cas.id_category_parent = ' . $id_category . '
                AND c.active = 1
                ORDER BY c.position ASC';
        
        return Db::getInstance()->executeS($sql);
    }


    private function getAllCategories($exclude_id = 0)
    {
        $categories = [];
        
        $sql = 'SELECT c.id_category, cl.name, cl.id_lang
                FROM ' . _DB_PREFIX_ . 'category c
                INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl ON c.id_category = cl.id_category
                WHERE c.id_category != ' . $exclude_id . '
                AND c.id_category != 1
                AND cl.id_lang = ' . (int)$this->context->language->id . '
                ORDER BY cl.name ASC';
        
        $results = Db::getInstance()->executeS($sql);
        
        foreach ($results as $row) {
            $categories[$row['name']] = $row['id_category'];
        }
        
        return $categories;
    }
}