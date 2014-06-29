<?php
/* Pandea.fr 
 * We override product class to add a getRandomSpecialx on promotions products
 * 
 */
class Product extends ProductCore
{
	/**
	* Get prices drop
	*
	* @param integer $id_lang Language id
	* @param integer $pageNumber Start from (optional)
	* @param integer $nbProducts Number of products to return (optional)
	* @param boolean $count Only in order to get total number (optional)
	* @return array Prices drop
	*/
	public static function getPricesDropR($id_lang, $page_number = 0, $nb_products = 10, $count = false,
		$order_by = null, $order_way = null, $beginning = false, $ending = false, Context $context = null)
	{
		if (!Validate::isBool($count))
			die(Tools::displayError());

		if (!$context) $context = Context::getContext();
		if ($page_number < 0) $page_number = 0;
		if ($nb_products < 1) $nb_products = 10;
		if (empty($order_by) || $order_by == 'position') $order_by = 'price';
		if (empty($order_way)) $order_way = 'DESC';
		if ($order_by == 'id_product' || $order_by == 'random' || $order_by == 'price' || $order_by == 'date_add'  || $order_by == 'date_upd')
			$order_by_prefix = 'p';
		else if ($order_by == 'name')
			$order_by_prefix = 'pl';
                else if ($order_by == 'random') {
			$order_by = $order_by_prefix.'rand()';
                        $order_way=null;
                }
		if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way))
			die (Tools::displayError());
		$current_date = date('Y-m-d H:i:s');
		$ids_product = Product::_getProductIdByDate((!$beginning ? $current_date : $beginning), (!$ending ? $current_date : $ending), $context);
                
		$tab_id_product = array();
		foreach ($ids_product as $product)
			if (is_array($product))
				$tab_id_product[] = (int)$product['id_product'];
			else
				$tab_id_product[] = (int)$product;

		$front = true;
		if (!in_array($context->controller->controller_type, array('front', 'modulefront')))
			$front = false;

		$groups = FrontController::getCurrentCustomerGroups();
		$sql_groups = (count($groups) ? 'IN ('.implode(',', $groups).')' : '= 1');

		if ($count)
		{
			$sql = 'SELECT COUNT(DISTINCT p.`id_product`) AS nb
					FROM `'._DB_PREFIX_.'product` p
					'.Shop::addSqlAssociation('product', 'p').'
					WHERE product_shop.`active` = 1
						AND product_shop.`show_price` = 1
						'.($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '').'
						'.((!$beginning && !$ending) ? 'AND p.`id_product` IN('.((is_array($tab_id_product) && count($tab_id_product)) ? implode(', ', $tab_id_product) : 0).')' : '').'
						AND p.`id_product` IN (
							SELECT cp.`id_product`
							FROM `'._DB_PREFIX_.'category_group` cg
							LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_category` = cg.`id_category`)
							WHERE cg.`id_group` '.$sql_groups.'
						)';
			$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
			return (int)$result['nb'];
		}
		if (strpos($order_by, '.') > 0)
		{
			$order_by = explode('.', $order_by);
			$order_by = pSQL($order_by[0]).'.`'.pSQL($order_by[1]).'`';
		}
		$sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, pl.`description`, pl.`description_short`, product_attribute_shop.id_product_attribute,
					pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`,
					pl.`name`, image_shop.`id_image`, il.`legend`, m.`name` AS manufacturer_name,
					DATEDIFF(
						p.`date_add`,
						DATE_SUB(
							NOW(),
							INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).' DAY
						)
					) > 0 AS new
				FROM `'._DB_PREFIX_.'product` p
				'.Shop::addSqlAssociation('product', 'p').'
				LEFT JOIN '._DB_PREFIX_.'product_attribute pa ON (pa.id_product = p.id_product)
				'.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.default_on=1').'
				'.Product::sqlStock('p', 0, false, $context->shop).'
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (
					p.`id_product` = pl.`id_product`
					AND pl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('pl').'
				)
				LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = p.`id_product`)'.
				Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1').'
				LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$id_lang.')
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
				WHERE product_shop.`active` = 1
				AND product_shop.`show_price` = 1
				AND ((image_shop.id_image IS NOT NULL OR i.id_image IS NULL) OR (image_shop.id_image IS NULL AND i.cover=1))
				'.($front ? ' AND p.`visibility` IN ("both", "catalog")' : '').'
				'.((!$beginning && !$ending) ? ' AND p.`id_product` IN ('.((is_array($tab_id_product) && count($tab_id_product)) ? implode(', ', $tab_id_product) : 0).')' : '').'
				AND p.`id_product` IN (
					SELECT cp.`id_product`
					FROM `'._DB_PREFIX_.'category_group` cg
					LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_category` = cg.`id_category`)
					WHERE cg.`id_group` '.$sql_groups.'
				)
				AND (pa.id_product_attribute IS NULL OR product_attribute_shop.default_on = 1)
				ORDER BY '.(isset($order_by_prefix) ? pSQL($order_by_prefix).'.' : '') . ( $order_by != 'rand' ? '`'.pSQL($order_by).' '.pSQL($order_way):'rand()' ).'
				LIMIT '.(int)($page_number * $nb_products).', '.(int)$nb_products;

		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

		if ($order_by == 'price')
			Tools::orderbyPrice($result, $order_way);

		if (!$result)
			return false;

		return Product::getProductsProperties($id_lang, $result);
	}

	/**
	* Get a random special multiple result
	*
	* @param integer $id_lang Language id
	* @return array Special
	*/
	public static function getRandomSpecialx($id_lang, $beginning = false, $ending = false,$nbr=1)
        {
                
                $currentDate = date('Y-m-d H:i:s');
                $ids_product = self::_getProductIdByDate((!$beginning ? $currentDate : $beginning), (!$ending ? $currentDate : $ending));

                $groups = FrontController::getCurrentCustomerGroups();
                $sqlGroups = (count($groups) ? 'IN ('.implode(',', $groups).')' : '= 1');

                // Please keep 2 distinct queries because RAND() is an awful way to achieve this result
                $sql = '
                SELECT p.id_product
                FROM '._DB_PREFIX_.'product p
                WHERE 1
                AND p.active = 1';
                if($ids_product):
                    $sql.=' AND p.id_product IN ('.implode(', ', $ids_product).')';
                endif;
                $sql.='
                AND p.id_product IN (
                        SELECT cp.id_product
                        FROM '._DB_PREFIX_.'category_group cg
                        LEFT JOIN '._DB_PREFIX_.'category_product cp ON (cp.id_category = cg.id_category)
                        WHERE cg.`id_group` '.$sqlGroups.'
                )
                ORDER BY RAND() limit 0,'.$nbr;
                $id_products = Db::getInstance()->ExecuteS($sql);
                //$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
                if (!$id_product = $result['id_product'])
				return false;
                else {
                $row = array(); 
                $i = 0;
                
                foreach ($id_products as $id_product)
                {
                        $row[$i] = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
                        SELECT p.*, pl.`description`, pl.`description_short`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, p.`ean13`,  p.`upc`,
                                i.`id_image`, il.`legend`, t.`rate`
                        FROM `'._DB_PREFIX_.'product` p
                        LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = '.(int)($id_lang).')
                        LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = p.`id_product` AND i.`cover` = 1)
                        LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)($id_lang).')
                        LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (p.`id_tax_rules_group` = tr.`id_tax_rules_group`
                                                                                                           AND tr.`id_country` = '.(int)Country::getDefaultCountryId().'
                                                                                                                  AND tr.`id_state` = 0)
                        LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.`id_tax` = tr.`id_tax`)
                        WHERE p.id_product = '.(int)$id_product[id_product]);
                        $row[$i] = Product::getProductProperties($id_lang, $row[$i]);
                        $i++;
                }
                
                return $row;
                }
        }
        
        

}


