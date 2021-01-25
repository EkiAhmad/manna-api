<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlymerchant.php';

class Productcategories extends Onlymerchant
{
    public function __construct()
    {
        parent::__construct();
    }

    public function select_get()
    {
        // $sql = "SELECT IFNULL(B.`id`, A.`id`) AS `value`, IF( IFNULL(B.`id`,'') = '', A.`name`, CONCAT(A.`name`,' - ', B.`name`) ) AS `label`
        //     FROM `category_product` A
        //         LEFT JOIN `category_product` B ON ( A.`id` = B.`id_parent` AND B.`id_parent` IS NOT NULL)
        //     WHERE A.`id_parent` IS NULL ORDER BY 2";
        $data = array();

        $id_merchant = intval($this->get('id_merchant'));
        $id_merchant_store = intval($this->get('id_store'));
        $where = "";

        if ($id_merchant) {
            $where .= " AND `id_merchant` = $id_merchant";

            if ($id_merchant_store) {
                $where .= " AND `id_merchant_store` = $id_merchant_store";
            } else {
                $where .= " AND `id_merchant_store` IS NULL";
            }

            $sql = "SELECT `id` AS `value`, `name` AS `label` FROM `category_product` WHERE 1 $where AND `id_parent` IS NOT NULL ORDER BY 2";
            $data = $this->db->query($sql)->result_array();
        }

        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function group_get()
    {
        $sql = "SELECT `id` as `value`, `name` as `label` FROM `category_product` WHERE id_parent IS NULL AND `id_merchant` = ? AND `id_merchant_store` IS NULL ORDER BY 2";
        $data = $this->db->query($sql, array($this->merchantID))->result_array();
        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT b.id, b.id_parent, a.name AS label_parent, b.name AS label FROM category_product a JOIN category_product b ON a.id = b.id_parent WHERE b.id_parent IS NOT NULL AND b.id_parent= $id AND a.id_merchant = ? AND a.`id_merchant_store` IS NULL ORDER BY 4";
            $dataChild = $this->db->query($sql, array($this->merchantID))->result_array();
            $this->response(array('categories' => $dataChild), REST_Controller::HTTP_OK);
        } else {
            $sql = "SELECT b.id, b.id_parent, a.name AS label_parent, b.name AS label FROM category_product a JOIN category_product b ON a.id = b.id_parent WHERE b.id_parent IS NOT NULL AND a.id_merchant = ? AND a.`id_merchant_store` IS NULL ORDER BY 3";
            $dataChild = $this->db->query($sql, array($this->merchantID))->result_array();
            $this->response(array('categories' => $dataChild), REST_Controller::HTTP_OK);
        }
    }

    public function index_post()
    {
        if ($this->merchantProductType != "1") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }
        $id_parent = intval($this->post('id_parent'));
        $label = trim($this->post('label'));

        $sql = "SELECT id FROM category_product WHERE `id` = ? AND `id_parent` IS NULL AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
        $ori = $this->db->query($sql, array($id_parent, $this->merchantID))->row();
        if (!$ori) {
            $this->response('Invalid Group', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'id_parent' => $id_parent,
            'id_merchant' => $this->merchantID,
            'name' => $label,
            'createdBy' => $this->userID,
        );
        if ($this->db->insert('category_product', $cols)) {
            $id = $this->db->insert_id();
            $this->response($id, REST_Controller::HTTP_OK);
        }
        $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function index_put($id)
    {
        if ($this->merchantProductType != "1") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }

        $id = intval($id);
        $id_parent = intval($this->put('id_parent'));
        if ($id < 1) {
            $this->response('Invalid Category', REST_Controller::HTTP_BAD_REQUEST);
        }

        $sql = "SELECT id, id_parent FROM category_product WHERE `id` = ? AND `id_parent` IS NOT NULL AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
        $ori = $this->db->query($sql, array($id, $this->merchantID))->row();
        if (!$ori) {
            $this->response('Invalid Category', REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($id_parent != $ori->id_parent) {
            $sql = "SELECT id FROM category_product WHERE `id` = ? AND `id_parent` IS NULL AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
            $ori = $this->db->query($sql, array($id_parent, $this->merchantID))->row();
            if (!$ori) {
                $this->response('Invalid Group', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        $label = trim($this->put('label'));
        $cols = array(
            'id_parent' => $id_parent,
            'name' => $label,
            'updateBy' => $this->userID,
        );

        if ($this->db->update('category_product', $cols, array('id' => $id))) {
            $this->response($id, REST_Controller::HTTP_OK);
        }
        $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function group_post()
    {
        if ($this->merchantProductType != "1") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }

        $label = trim($this->post('label'));
        $cols = array(
            'name' => $label,
            'id_merchant' => $this->merchantID,
            'createdBy' => $this->userID,
        );
        if ($this->db->insert('category_product', $cols)) {
            $id = $this->db->insert_id();
            $this->response($id, REST_Controller::HTTP_OK);
        }
        $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function group_put($id)
    {
        if ($this->merchantProductType != "1") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }

        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Group', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT id FROM category_product WHERE `id` = ? AND `id_parent` IS NULL AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
        $ori = $this->db->query($sql, array($id, $this->merchantID))->row();
        if (!$ori) {
            $this->response('Invalid Group', REST_Controller::HTTP_BAD_REQUEST);
        }

        $label = trim($this->put('label'));
        $cols = array(
            'name' => $label,
            'updateBy' => $this->userID,
        );

        if ($this->db->update('category_product', $cols, array('id' => $id))) {
            $this->response($id, REST_Controller::HTTP_OK);
        }
        $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function slugify($str)
    {
        $search = array('Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ', 'ș', 'ț', 'î', 'â', 'ă', 'Î', 'Â', 'Ă', 'ë', 'Ë');
        $replace = array('s', 't', 's', 't', 's', 't', 's', 't', 'i', 'a', 'a', 'i', 'a', 'a', 'e', 'E');
        $str = str_ireplace($search, $replace, strtolower(trim($str)));
        $str = preg_replace('/[^\w\d\-\ ]/', '', $str);
        $str = str_replace(' ', '-', $str);

        return preg_replace('/\-{2,}/', '-', $str);
    }
}
