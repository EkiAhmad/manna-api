<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Merchants extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
    }

    public function stores_get()
    {
        $id = intval(trim($this->get('id')));
        if ($id > 0) {
            $sql = "SELECT B.`id` AS `id_merchant_store`, A.`company`, B.`name`, B.`city`, IFNULL(B.`address`,'') AS `address` FROM `merchant` A, `merchant_store` B WHERE A.`id` = B.`id_merchant` AND A.`id` = ? AND A.`deleted` = 0 AND B.`deleted` = 0 ORDER BY A.`id`, B.`id`";
            $data = $this->db->query($sql, array($id))->result_array();
        } else {
            $sql = "SELECT B.`id` AS `id_merchant_store`, A.`company`, B.`name`, B.`city`, IFNULL(B.`address`,'') AS `address` FROM `merchant` A, `merchant_store` B WHERE A.`id` = B.`id_merchant` AND A.`deleted` = 0 AND B.`deleted` = 0 ORDER BY A.`id`, B.`id`";
            $data = $this->db->query($sql)->result_array();
        }
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function select_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`company` AS `label` FROM `merchant` A WHERE A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('merchants' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $sql = "SELECT A.`id`, A.`company`, A.`companyBusiness`, IFNULL(E.`label`,'') AS `companyState`,IFNULL(F.`label`,'') AS `companyCity`, A.`bank`, A.`accountName`, A.`accountNumber`, B.`fullname` AS `pic`, B.`email` AS `pic_email`, B.`phone` AS `pic_phone`
            FROM `merchant` A
                JOIN `user` B ON A.`id` = B.`id_merchant`
				LEFT JOIN `ref_state` E ON A.`companyState` = E.`value`
				LEFT JOIN `ref_city` F ON A.`companyCity` = F.`id`
            WHERE A.`deleted`=0 AND B.`deleted` = 0 AND B.`role`= ? ORDER BY A.`id` DESC";
            $data = $this->db->query($sql, array($this->Auth_model->roles['merchant-admin']))->result_array();
            $this->response(array('merchants' => $data), REST_Controller::HTTP_OK);
        } else {
            $data = array();
            $sql = "SELECT `id`, `company`, `companyBusiness`, `companyWebsite`, `companyAddress`, `companyState`, `companyCity`, `bank`, `accountName`, `accountNumber`, `subscription`, `joinDate`, `cutoffDate`, `sla`, `code`, `product_type` FROM `merchant` WHERE `id` = ?";
            $merchant = (array) $this->db->query($sql, array($id))->row();
            if ($merchant) {
                $sql = "SELECT `fullname`, `phone`, `email`, '' AS `password`, `role` FROM `user` WHERE `id_merchant` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
                $user = (array) $this->db->query($sql, array($merchant['id'], $this->Auth_model->roles['merchant-admin']))->row();

                $data = array_merge($merchant, $user);
            }
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function index_post()
    {
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
            'bank',
            'accountName',
            'accountNumber',
            'subscription',
            'joinDate',
            'cutoffDate',
            'sla',
            'product_type',
            'code',
        );

        foreach ($cols as $col) {
            $$col = trim($this->post($col));
        }

        $sql = "SELECT `id` FROM `user` WHERE `email` = ? AND `deleted` = 0 LIMIT 1";
        $exist = $this->db->query($sql, array($email))->row();
        if ($exist && $exist->id) {
            $this->response('Email already registered', REST_Controller::HTTP_BAD_REQUEST);
        }

        try {
            $joinDate = date('Y-m-d', strtotime($joinDate));
            $cutoffDate = date('Y-m-d', strtotime($cutoffDate));
        } catch (Exception $e) {
            $this->response('Invalid date', REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($code) {
            if (strlen($code) > 10) {
                $this->response('Company Code max-length 10 character', REST_Controller::HTTP_BAD_REQUEST);
            }
            $sql = "SELECT `id` FROM `merchant` WHERE `code` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($code))->row();
            if ($exist && $exist->id) {
                $this->response('Company Code already used', REST_Controller::HTTP_BAD_REQUEST);
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
                'bank',
                'accountName',
                'accountNumber',
                'subscription',
                'joinDate',
                'cutoffDate',
                'sla',
                'product_type',
                'code',
            ) as $col) {
                $merchant[$col] = $$col;
            }
            $merchant["createdBy"] = $this->userID;
            $this->db->insert('merchant', $merchant);

            $id_merchant = $this->db->insert_id();

            $user = array();
            $password = md5($password);
            $role = $this->Auth_model->roles['merchant-admin'];
            foreach (array(
                'id_merchant',
                'fullname',
                'phone',
                'email',
                'password',
                'role',
            ) as $col) {
                $user[$col] = $$col;
            }
            $user["createdBy"] = $this->userID;
            $this->db->insert('user', $user);

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($id_merchant, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_put($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Merchant', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`,IFNULL(`code`,'') AS `code` FROM `merchant` WHERE `id` = ? AND `deleted` = 0";
        $ori = $this->db->query($sql, array($id))->row();
        if (!$ori) {
            $this->response('Invalid Merchant', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`, `email` FROM `user` WHERE `id_merchant` = ? AND `role`= ? AND `deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($id, $this->Auth_model->roles['merchant-admin']))->row();
        if (!$oriUser) {
            $this->response('Invalid Merchant User Account', REST_Controller::HTTP_BAD_REQUEST);
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
            'bank',
            'accountName',
            'accountNumber',
            'subscription',
            'joinDate',
            'cutoffDate',
            'sla',
            'code',
        );

        foreach ($cols as $col) {
            $$col = trim($this->put($col));
        }

        if ($code) {
            if (strlen($code) > 10) {
                $this->response('Company Code max-length 10 character', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        if ($code && $ori->code != $code) {
            $sql = "SELECT `id` FROM `merchant` WHERE `id` != ? AND `code` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($id, $code))->row();
            if ($exist && $exist->id) {
                $this->response('Company Code already used', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($oriUser->email != $email) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `email` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($oriUser->id, $email))->row();
            if ($exist && $exist->id) {
                $this->response('Email already regsitered', REST_Controller::HTTP_BAD_REQUEST);
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

        try {
            $joinDate = date('Y-m-d', strtotime($joinDate));
            $cutoffDate = date('Y-m-d', strtotime($cutoffDate));
        } catch (Exception $e) {
            $this->response('Invalid date', REST_Controller::HTTP_BAD_REQUEST);
        }

        $id_merchant = $id;

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
                'bank',
                'accountName',
                'accountNumber',
                'subscription',
                'joinDate',
                'cutoffDate',
                'sla',
                'code',
            ) as $col) {
                $merchant[$col] = $$col;
            }
            $merchant["updatedBy"] = $this->userID;
            $this->db->update('merchant', $merchant, array('id' => $id));

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

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($id_merchant, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
