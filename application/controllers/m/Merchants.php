<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlymerchant.php';

class Merchants extends Onlymerchant
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->model('Upload_model');
    }

    public function stores_get()
    {
        $sql = "SELECT B.`id` AS `id_merchant_store`, A.`company`, B.`name`, B.`city`, IFNULL(B.`address`,'') AS `address` FROM `merchant` A, `merchant_store` B WHERE A.`id` = B.`id_merchant` AND A.`id` = ? AND A.`deleted` = 0 AND B.`deleted` = 0 ORDER BY A.`id`, B.`id`";
        $data = $this->db->query($sql, array($this->merchantID))->result_array();

        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function select_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`company` AS `label` FROM `merchant` A WHERE A.`id` = ? AND A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql, array($this->merchantID))->result_array();
        $this->response(array('merchants' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $this->response("Cinta adalah misteri", REST_Controller::HTTP_OK);
    }

    public function profile_get()
    {
        $data = array();
        $sql = "SELECT `id`,`code`, `company`, `companyBusiness`, `companyWebsite`, `companyAddress`, `companyState`,`companyCity`, IFNULL(`companyLogo`,'') AS `companyLogo` FROM `merchant` WHERE `id` = ? AND `deleted` = 0 LIMIT 1";
        $merchant = (array) $this->db->query($sql, array($this->merchantID))->row();
        if ($merchant) {
            if ($merchant['companyLogo']) {
                $companyPath = base_url() . $this->Upload_model->path['merchant'];
                $merchant['companyLogo'] = $companyPath . $merchant['companyLogo'];
            }
            $sql = "SELECT `fullname`, `phone`, `email` FROM `user` WHERE `id_merchant` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
            $user = (array) $this->db->query($sql, array($this->merchantID, $this->Auth_model->roles['merchant-admin']))->row();

            $data = array_merge($merchant, $user);
        }
        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function profile_post()
    {
        $sql = "SELECT `id`, IFNULL(`companyLogo`,'') AS `companyLogo` FROM `merchant` WHERE `id` = ? AND `deleted` = 0";
        $ori = $this->db->query($sql, array($this->merchantID))->row();
        if (!$ori) {
            $this->response('Invalid Merchant', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`, `email`, `password` FROM `user` WHERE `id` = ? AND `id_merchant` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($this->userID, $this->merchantID, $this->Auth_model->roles['merchant-admin']))->row();
        if (!$oriUser) {
            $this->response('Invalid Merchant', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'company',
            'companyBusiness',
            'companyWebsite',
            'companyAddress',
            'companyState',
            'companyCity',
            'fullname',
            'phone',
            'email',
            'password',
            'oldpassword',
        );
        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            if (!$data) {
                $this->response("Please fill form", REST_Controller::HTTP_BAD_REQUEST);
            }
            foreach ($cols as $col) {
                $$col = isset($data[$col]) ? trim($data[$col]) : '';
            }
        } else {
            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
        }

        if ($companyCity) {
            $companyCity = intval($companyCity);
            $sql = "SELECT `state` FROM `ref_city` WHERE `id` = ?";
            $getState = $this->db->query($sql, array($companyCity))->row_array();
            if ($getState) {
                $companyState = $getState['state'];
            } else {
                $this->response('Invalid City', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        if ($oriUser->email != $email) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `email` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($oriUser->id, $email))->row();
            if ($exist && $exist->id) {
                $this->response('Email already registered', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($oldpassword) {
            if (hash_equals($oriUser->password, md5($oldpassword))) {
            } else {
                $this->response('Invalid Current Password', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $id_merchant = $this->merchantID;

        $this->db->trans_begin();
        try {
            $merchant = array();
            foreach (array(
                'company',
                'companyBusiness',
                'companyWebsite',
                'companyAddress',
                'companyState',
                'companyCity',
            ) as $col) {
                $merchant[$col] = $$col;
            }
            $merchant["updatedBy"] = $this->userID;

            $user = array();
            $role = $this->Auth_model->roles['merchant-admin'];
            foreach (array(
                'id_merchant',
                'fullname',
                'phone',
                'email',
                'password',
                'role',
            ) as $col) {
                if ($col == 'password') {
                    if ($password) {
                        $password = md5($password);
                        $user[$col] = $$col;
                    }
                } else {
                    $user[$col] = $$col;
                }
            }
            $user["updatedBy"] = $this->userID;
            $this->db->update('user', $user, array('id' => $oriUser->id));

            if ($_FILES) {
                $upload = $this->Upload_model->receipt('merchant', $company);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $merchant['companyLogo'] = $upload['result']['file_name'];
                    if ($ori && $ori->companyLogo) {
                        @unlink(FCPATH . $this->Upload_model->path['merchant'] . $ori->image);
                    }
                }
            }
            $this->db->update('merchant', $merchant, array('id' => $this->merchantID));

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($this->merchantID, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function summary_get($store = 0)
    {
        $end_date = date('Y-m-d');
        $start_month = date('Y-m-01');
        $start_year = date('Y-01-01');
        $N = date('N', strtotime($end_date)) - 1;
        $start_week = date('Y-m-d', strtotime("$end_date -$N DAY"));

        $store = intval($store);
        $filterStrStore = $store > 0 ? " AND A.`id_merchant_store` = $store" : "";

//today
        $sql = "SELECT
			IFNULL(SUM(A.`total_price_product`),0) AS `total_modal`,
			IFNULL(SUM(A.`total_price_sales`),0) AS `total`
		FROM `sales` A, `merchant_store` M
			WHERE
		A.`id_merchant_store` = M.`id` AND
		M.`id_merchant` = ? AND A.`date` = ? $filterStrStore AND `status` = 1";
        $_today = $this->db->query($sql, array($this->merchantID, $end_date))->row();
        $today = intval($_today->total);
        $today_profit = $today - (intval($_today->total_modal));

//this week
        $sql = "SELECT
			IFNULL(SUM(A.`total_price_product`),0) AS `total_modal`,
			IFNULL(SUM(A.`total_price_sales`),0) AS `total`
		FROM `sales` A, `merchant_store` M
			WHERE
		A.`id_merchant_store` = M.`id` AND
		M.`id_merchant` = ? AND A.`date` BETWEEN ? AND ? $filterStrStore AND A.`status` = 1";
        $_this_week = $this->db->query($sql, array($this->merchantID, $start_week, $end_date))->row();
        $this_week = intval($_this_week->total);
        $this_week_profit = $this_week - (intval($_this_week->total_modal));

//this month
        $sql = "SELECT
			IFNULL(SUM(A.`total_price_product`),0) AS `total_modal`,
			IFNULL(SUM(A.`total_price_sales`),0) AS `total`
		FROM `sales` A, `merchant_store` M
			WHERE
		A.`id_merchant_store` = M.`id` AND
		M.`id_merchant` = ? AND A.`date` BETWEEN ? AND ? $filterStrStore AND A.`status` = 1";
        $_this_month = $this->db->query($sql, array($this->merchantID, $start_month, $end_date))->row();
        $this_month = intval($_this_month->total);
        $this_month_profit = $this_month - (intval($_this_month->total_modal));

//this year
        $sql = "SELECT
			IFNULL(SUM(A.`total_price_product`),0) AS `total_modal`,
			IFNULL(SUM(A.`total_price_sales`),0) AS `total`
		FROM `sales` A, `merchant_store` M
			WHERE
		A.`id_merchant_store` = M.`id` AND
		M.`id_merchant` = ? AND A.`date` BETWEEN ? AND ? $filterStrStore AND A.`status` = 1";
        $_this_year = $this->db->query($sql, array($this->merchantID, $start_year, $end_date))->row();
        $this_year = intval($_this_year->total);
        $this_year_profit = $this_year - (intval($_this_year->total_modal));

        $this->response(
            array(
                'today' => intval($today),
                'this_week' => intval($this_week),
                'this_month' => intval($this_month),
                'this_year' => intval($this_year),
                'today_profit' => intval($today_profit),
                'this_week_profit' => intval($this_week_profit),
                'this_month_profit' => intval($this_month_profit),
                'this_year_profit' => intval($this_year_profit),
            ), REST_Controller::HTTP_OK);

    }

    public function summary_old_get()
    {
        sleep(2);
        $result = array(
            'store' => array(
                'total' => 0,
            ),
            'product' => array(
                'total' => 0,
            ),
            'sales' => array(
                'sales' => 0,
                'product_sales' => 0,
                'omzet' => 0,
            ),
        );

        $sql = "SELECT DISTINCT A.`id` FROM `merchant_store` A,`user_store` B WHERE A.`id` = B.`id_merchant_store` AND A.`id_merchant` = ? AND A.`deleted` = 0";
        $stores = $this->db->query($sql, array($this->merchantID))->result_array();

        $sql = "SELECT COUNT(DISTINCT A.`id`) AS `total` FROM `merchant_store` A,`user_store` B WHERE A.`id` = B.`id_merchant_store` AND A.`id_merchant` = ? AND A.`deleted` = 0";
        $store = $this->db->query($sql, array($this->merchantID))->row();
        $result['store']['total'] = intval($store->total);

        if ($this->merchantProductType == "1") {
            $sql = "SELECT COUNT(*) AS `total` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `id_category_product` IS NOT NULL AND `deleted` = 0";
        } else {
            $sql = "SELECT COUNT(*) AS `total` FROM `product` WHERE `id_merchant` = ? AND `id_category_product` IS NOT NULL AND `deleted` = 0";
        }
        $product = $this->db->query($sql, array($this->merchantID))->row();
        $result['product']['total'] = intval($product->total);

        if ($stores) {
            $sql = "SELECT COUNT(*) AS `sales`, SUM(`total_price_sales`) AS `omzet`, SUM(`total_quantity`) AS `product_sales` FROM `sales` WHERE `id_merchant_store` IN ? AND `status` = 1 AND `deleted` = 0";
            $sales = $this->db->query($sql, array(array_column($stores, 'id')))->row();
            $result['sales']['sales'] = intval($sales->sales);
            $result['sales']['product_sales'] = floatval($sales->product_sales);
            $result['sales']['omzet'] = intval($sales->omzet);
        }

        $this->response($result, REST_Controller::HTTP_OK);
    }
}
