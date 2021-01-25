<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlymerchant.php';

class Stores extends Onlymerchant
{

    public function __construct()
    {
        parent::__construct();
    }

    public function select_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`name` AS `label` FROM `merchant_store` A WHERE A.`id_merchant` = ? AND A.`app_mmenu` = 1 AND A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql, array($this->merchantID))->result_array();
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $data = array();

        $id = intval($id);
        if ($id < 1) {
            $sql = "SELECT `id`, `company`, `deleted` FROM `merchant`";
            $_merchants = $this->db->query($sql)->result_array();
            $merchants = array();
            foreach ($_merchants as $v) {
                $merchants[$v['id']] = array('company' => $v['company'], 'deleted' => $v['deleted']);
            }
            $_merchants = array(); // reset

            $sql = "SELECT A.`id`, IFNULL(A.`id_merchant`, '') AS `id_merchant`,
            '' AS `merchant_name`, A.`name` AS `company`, F.`label` AS `city`,
            D.`label` AS `business`, E.`label` AS `state`,
            C.`fullname` AS `pic`, IFNULL(C.`email`,'-') AS `pic_email`, C.`phone` AS `pic_phone`
            FROM `merchant_store` A
                JOIN `user_store` B ON A.`id` = B.`id_merchant_store`
                JOIN `user` C ON B.`id_user`=C.`id`
                JOIN `ref_business` D ON A.`business` = D.`value`
                JOIN `ref_state` E ON A.`state` = E.`value`
                JOIN `ref_city` F ON A.`city` = F.`id`
            WHERE A.`app_mmenu` = 1 AND A.`deleted`=0 AND A.`id_merchant` = ? AND C.`deleted` = 0 AND C.`role`= ? ORDER BY 1 DESC";
            $data = $this->db->query($sql, array($this->merchantID, $this->Auth_model->roles['store-admin']))->result_array();
            foreach ($data as $k => $v) {
                if ($v['id_merchant']) {
                    if (isset($merchants[$v['id_merchant']])) {
                        if ($merchants[$v['id_merchant']]['deleted'] == "1") {
                            unset($data[$k]);
                            continue;
                        } else {
                            $data[$k]['merchant_name'] = $merchants[$v['id_merchant']]['company'];
                        }
                    }
                }
            }
            $this->response(array('stores' => array_values($data)), REST_Controller::HTTP_OK);
        } else {
            $sql = "SELECT A.`id`, IFNULL(A.`id_merchant`, '') AS `id_merchant`,
            A.`name`, A.`state`, A.`business`, A.`city`, A.`address`
            FROM `merchant_store` A
            WHERE A.`id` = ? AND A.`app_mmenu` = 1 AND A.`id_merchant` = ? AND A.`deleted`=0 LIMIT 1";
            $store = $this->db->query($sql, array($id, $this->merchantID))->row_array();
            if ($store) {
                $sql = "SELECT A.`fullname`, A.`phone`, A.`email` FROM `user` A, `user_store` B WHERE A.`id` = B.`id_user` AND A.`id_merchant` = ? AND B.`id_merchant_store` = ? AND A.`role` = ? AND A.`deleted` = 0 LIMIT 1";
                $user = (array) $this->db->query($sql, array($this->merchantID, $store['id'], $this->Auth_model->roles['store-admin']))->row();
                $data = array_merge($store, $user);
            }
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function index_post()
    {
        $cols = array(
            'id_merchant',
            'name',
            'business',
            'state',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
        );

        foreach ($cols as $col) {
            $$col = trim($this->post($col));
        }

        $sql = "SELECT `id` FROM `user` WHERE `phone` = ? AND `deleted` = 0";
        $exist = $this->db->query($sql, array($phone))->row();
        if ($exist && $exist->id) {
            $this->response('Phone Number already regsitered', REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($email) {
            $sql = "SELECT `id` FROM `user` WHERE `email` = ? AND `deleted` = 0";
            $exist = $this->db->query($sql, array($email))->row();
            if ($exist && $exist->id) {
                $this->response('Email already regsitered', REST_Controller::HTTP_BAD_REQUEST);
            }
        } else {
            $email = null;
        }

        if ($city) {
            $city = intval($city);
            $sql = "SELECT `state` FROM `ref_city` WHERE `id` = ?";
            $getState = $this->db->query($sql, array($city))->row_array();
            if ($getState) {
                $state = $getState['state'];
            } else {
                $this->response('Invalid City', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($password) {
            $password = $this->Auth_model->storeHashPassword($password);
        }
        if (!$password) {
            $this->response('Invalid password', REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->db->trans_begin();
        try {
            $app_mmenu = 1;
            $store = array();

            foreach (array(
                'id_merchant',
                'name',
                'business',
                'state',
                'city',
                'address',
                'app_mmenu',
            ) as $col) {
                $store[$col] = $$col;
            }
            $store['id_merchant'] = $this->merchantID;
            $store["createdBy"] = $this->userID;
            $this->db->insert('merchant_store', $store);
            $id_merchant_store = $this->db->insert_id();

            $user = array();
            $role = $this->Auth_model->roles['store-admin'];
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
            $user['id_merchant'] = $this->merchantID;
            $user["createdBy"] = $this->userID;
            $this->db->insert('user', $user);
            $id_user = $this->db->insert_id();

            $this->db->insert('user_store', array(
                'id_user' => $id_user,
                'id_merchant_store' => $id_merchant_store,
            ));

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
            $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id` FROM `merchant_store` WHERE `id` = ? AND `id_merchant` = ? AND `app_mmenu` = 1 AND `deleted` = 0";
        $ori = $this->db->query($sql, array($id, $this->merchantID))->row();
        if (!$ori) {
            $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT A.`id`, A.`phone`, A.`email` FROM `user` A, `user_store` B WHERE A.`id` = B.`id_user` AND `id_merchant` = ? AND B.`id_merchant_store` = ? AND A.`role`= ? AND A.`deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($this->merchantID, $ori->id, $this->Auth_model->roles['store-admin']))->row();
        if (!$oriUser) {
            $this->response('Invalid Store User Account', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'id_merchant',
            'name',
            'business',
            'state',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
        );

        foreach ($cols as $col) {
            $$col = trim($this->put($col));
        }

        if ($oriUser->phone != $phone) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `email` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($oriUser->id, $phone))->row();
            if ($exist && $exist->id) {
                $this->response('Phone already regsitered', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($email) {
            if ($oriUser->email != $email) {
                $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `email` = ? AND `deleted` = 0 LIMIT 1";
                $exist = $this->db->query($sql, array($oriUser->id, $email))->row();
                if ($exist && $exist->id) {
                    $this->response('Email already regsitered', REST_Controller::HTTP_BAD_REQUEST);
                }
            }
        } else {
            $email = null;
        }
        if ($city) {
            $city = intval($city);
            $sql = "SELECT `state` FROM `ref_city` WHERE `id` = ?";
            $getState = $this->db->query($sql, array($city))->row_array();
            if ($getState) {
                $state = $getState['state'];
            } else {
                $this->response('Invalid City', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($password) {
            $password = $this->Auth_model->storeHashPassword($password);
        }

        $this->db->trans_begin();
        try {
            $app_mmenu = 1;
            $store = array();
            foreach (array(
                'id_merchant',
                'name',
                'business',
                'state',
                'city',
                'address',
                'app_mmenu',
            ) as $col) {
                $store[$col] = $$col;
            }
            $store["id_merchant"] = $this->merchantID;
            $store["updatedBy"] = $this->userID;
            $this->db->update('merchant_store', $store, array('id' => $id));

            $user = array();
            $role = $this->Auth_model->roles['store-admin'];
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
                        $user[$col] = $$col;
                    }
                } else {
                    $user[$col] = $$col;
                }
            }
            $user["id_merchant"] = $this->merchantID;
            $user["updatedBy"] = $this->userID;
            $this->db->update('user', $user, array('id' => $oriUser->id));

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($id, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
