<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Promo_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getDefaultProductCategory($product_source, $id_merchant, $id_merchant_store = "", $user_id = 0)
    {
        $id_merchant = intval($id_merchant);
        $id_merchant_store = intval($id_merchant_store);
        $user_id = intval($user_id);

        if ($product_source == "2") {
            if ($id_merchant_store < 1) {
                return false;
            }
        } else {
            $id_merchant_store = null;
        }
        $strMerchantStore = $id_merchant_store ? " AND `id_merchant_store` = $id_merchant_store" : " AND `id_merchant_store` IS NULL";

        // get group category
        $sql = "SELECT `id` FROM `category_product` WHERE `id_merchant` = $id_merchant $strMerchantStore AND `name` = 'Tanpa Kategori' LIMIT 1";
        $parent = $this->db->query($sql)->row_array();
        if (!$parent) { // create new group and category
            $this->db->trans_begin();
            try {
                $id_category = 0;
                $group = array(
                    'name' => 'Tanpa Kategori',
                    'id_merchant' => $id_merchant,
                    'id_merchant_store' => $id_merchant_store,
                    'createdBy' => ($user_id ? $user_id : 1),
                );
                if ($this->db->insert('category_product', $group)) {
                    $id_parent = $this->db->insert_id();
                    $category = array(
                        'name' => 'Tanpa Kategori',
                        'id_parent' => $id_parent,
                        'id_merchant' => $id_merchant,
                        'id_merchant_store' => $id_merchant_store,
                        'createdBy' => ($user_id ? $user_id : 1),
                    );
                    if ($this->db->insert('category_product', $category)) {
                        $id_category = $this->db->insert_id();
                    }
                }
                if ($this->db->trans_status() === false) {
                    $this->db->trans_rollback();
                    return false;
                } else {
                    $this->db->trans_commit();
                    return $id_category;
                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                return false;
            }
        }

        $sql = "SELECT `id` FROM `category_product` WHERE `id_parent` = ? AND `name` LIKE 'lain-%' OR 'other%' LIMIT 1";
        $child = $this->db->query($sql, array($parent['id']))->row_array();
        if (!$child) {
            try {
                $category = array(
                    'name' => 'Tanpa Kategori',
                    'id_parent' => $parent['id'],
                    'id_merchant' => $id_merchant,
                    'id_merchant_store' => $id_merchant_store,
                    'createdBy' => ($user_id ? $user_id : 1),
                );
                if ($this->db->insert('category_product', $category)) {
                    return $this->db->insert_id();
                }
            } catch (Exception $e) {
                return false;
            }
        } else {
            return $child['id'];
        }
    }

    public function getRandomSKU($product_source)
    {
        $prefix = "P";
        
        $timestamps = microtime(true);
        list($x, $middle) = explode('.', $timestamps);
        $suffix = mt_rand(1, 999999);
        return $prefix . "-" . $middle . "-" . $suffix;
    }
}
