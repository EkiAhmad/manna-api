<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Stores extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->model('Upload_model');
    }

    public function select_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`name` AS `label` FROM `merchant_store` A WHERE A.`app_mmenu` = 1 AND A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function ipay_get($id)
    {
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT
                        merchant_store.id,
                        merchant_store.id_merchant,
                        merchant_store.deleted,
                        merchant_store_ipay.id_merchant_store,
                        merchant_store_ipay.pos_merchant_code,
                        merchant_store_ipay.pos_merchant_key,
                        merchant_store_ipay.pos_merchant_code_test,
                        merchant_store_ipay.pos_merchant_key_test,
                        merchant_store_ipay.is_production 
                    FROM
                        merchant_store
                        RIGHT JOIN merchant_store_ipay ON merchant_store.id = merchant_store_ipay.id_merchant_store 
                    WHERE
                        merchant_store.id = ? 
                        AND merchant_store.deleted = 0";
            $data = $this->db->query($sql, array($id))->row();
            $this->response($data, REST_Controller::HTTP_OK);
        }
        $this->response('Invalid iPay', REST_Controller::HTTP_BAD_REQUEST);
    }

    public function suppliers_get($id = 0)
    {
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT s.`id`, s.`name`, rc.`label` AS `city`, rs.`label` AS `state`, IFNULL(s.`address`,'') AS `address`
			FROM `supplier` s, `supplier_store` ss, `ref_city` rc, `ref_state` rs
			WHERE s.`id` = ss.`id_supplier` AND
				s.`city` = rc.`id` AND
				rc.`state` = rs.`value` AND
				s.`deleted` = 0 AND ss.`id_merchant_store` = ? ORDER BY 1";
            $data = $this->db->query($sql, array($id))->result_array();
            $this->response(array('suppliers' => $data), REST_Controller::HTTP_OK);
        }
        $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
    }

    public function index_get($id = 0)
    {
        $data = array();
        $productPath = base_url() . $this->Upload_model->path['store'];

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
			C.`fullname` AS `pic`, IFNULL(C.`email`,'-') AS `pic_email`, C.`phone` AS `pic_phone`,
			IF(IFNULL(A.`slug`,'') != '', CONCAT('https://manna.asia/menu/','',A.`slug`),'') AS `slug`,
            IF(IFNULL(A.`bg_store_promo_surprize`,'') != '', CONCAT('" . $productPath . "','',A.`bg_store_promo_surprize`), '') AS `image`

			FROM `merchant_store` A
				JOIN `user_store` B ON A.`id` = B.`id_merchant_store`
				JOIN `user` C ON B.`id_user`=C.`id`
				JOIN `ref_business` D ON A.`business` = D.`value`
				JOIN `ref_state` E ON A.`state` = E.`value`
				JOIN `ref_city` F ON A.`city` = F.`id`
			WHERE A.`app_mmenu` = 1 AND A.`deleted`=0 AND C.`deleted` = 0 AND C.`role`= ? ORDER BY 1 DESC, 2 DESC";
            $data = $this->db->query($sql, array($this->Auth_model->roles['store-admin']))->result_array();
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
			A.`name`, A.`state`, A.`business`, A.`city`, A.`address`, IFNULL(A.`slug`,'') AS `slug`,
			IF(IFNULL(A.`slug`,'') != '', CONCAT('https://manna.asia/menu/','',A.`slug`),'') AS `url`,
            IF(IFNULL(A.`bg_store_promo_surprize`,'') != '', CONCAT('" . $productPath . "','',A.`bg_store_promo_surprize`), '') AS `image`
			FROM `merchant_store` A
			WHERE A.`id` = ? AND A.`app_mmenu` = 1 AND A.`deleted`=0 LIMIT 1";
            $store = (array) $this->db->query($sql, array($id))->row();
            if ($store) {
                $sql = "SELECT A.`fullname`, A.`phone`, A.`email` FROM `user` A, `user_store` B WHERE A.`id` = B.`id_user` AND B.`id_merchant_store` = ? AND A.`role` = ? AND A.`deleted` = 0 LIMIT 1";
                $user = (array) $this->db->query($sql, array($store['id'], $this->Auth_model->roles['store-admin']))->row();
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
            'slug',
            'business',
            'state',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
            'pos_merchant_code',
            'pos_merchant_code_test',
            'pos_merchant_key',
            'pos_merchant_key_test',
            'is_production',
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
        // foreach ($cols as $col) {
        //     $$col = trim($this->post($col));
        // }

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

        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if (!$slug) {
            $this->response('Invalid URL Alias', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id` FROM `merchant_store` WHERE `slug` = ? AND `deleted` = 0";
        $exist = $this->db->query($sql, array($slug))->row();
        if ($exist && $exist->id) {
            $this->response('URL Alias already regsitered', REST_Controller::HTTP_BAD_REQUEST);
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
                'slug',
                'name',
                'business',
                'state',
                'city',
                'address',
                'app_mmenu',
            ) as $col) {
                $store[$col] = $$col;
            }
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
            $user["createdBy"] = $this->userID;
            $this->db->insert('user', $user);
            $id_user = $this->db->insert_id();

            $this->db->insert('user_store', array(
                'id_user' => $id_user,
                'id_merchant_store' => $id_merchant_store,
            ));

            $ipay = [];
            foreach (array(
                'id_merchant_store',
                'pos_merchant_code',
                'pos_merchant_code_test',
                'pos_merchant_key',
                'pos_merchant_key_test',
                'is_production',
            ) as $col) {
                $ipay[$col] = $$col;
            }
            $ipay["id_merchant_store"] = $id_merchant_store;
            $this->db->insert('merchant_store_ipay', $ipay);

            if ($_FILES) {
                $upload = $this->Upload_model->storeImage('bg_store_promo_surprize-' . $id_merchant_store);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('merchant_store', array('bg_store_promo_surprize' => $upload['result']['file_name']), array('id' => $id_merchant_store));
                    // if ($ori && $ori->image) {
                    //     @unlink(FCPATH . $this->Upload_model->path['store'] . $ori->image);
                    // }
                }
            }

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

    public function store_post($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT MS.`id`, MS.`id_merchant`, IFNULL(MS.`slug`,'') AS `slug`, MS.`bg_store_promo_surprize` FROM `merchant_store` MS WHERE MS.`id` = ? AND MS.`app_mmenu` = 1 AND MS.`deleted` = 0";
        $ori = $this->db->query($sql, array($id))->row();
        if (!$ori) {
            $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT A.`id`, A.`phone`, A.`email` FROM `user` A, `user_store` B WHERE A.`id` = B.`id_user` AND B.`id_merchant_store` = ? AND A.`role`= ? AND A.`deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($ori->id, $this->Auth_model->roles['store-admin']))->row();
        if (!$oriUser) {
            $this->response('Invalid Store User Account', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT
                        merchant_store.id,
                        merchant_store.id_merchant,
                        merchant_store.deleted,
                        merchant_store_ipay.id,
                        merchant_store_ipay.id_merchant_store,
                        merchant_store_ipay.pos_merchant_code,
                        merchant_store_ipay.pos_merchant_key,
                        merchant_store_ipay.pos_merchant_code_test,
                        merchant_store_ipay.pos_merchant_key_test,
                        merchant_store_ipay.is_production 
                    FROM
                        merchant_store
                        RIGHT JOIN merchant_store_ipay ON merchant_store.id = merchant_store_ipay.id_merchant_store 
                    WHERE
                        merchant_store.id = ? 
                        AND merchant_store.deleted = 0";
        $data_ipay = $this->db->query($sql, array($id))->result_array();
        if (empty($data_ipay)) {
            $this->response('Data iPay Setting is empty', REST_Controller::HTTP_BAD_REQUEST);
        }
        $cols = array(
            'name',
            'slug',
            'business',
            'state',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
            'pos_merchant_code',
            'pos_merchant_code_test',
            'pos_merchant_key',
            'pos_merchant_key_test',
            'is_production',
        );
        
        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            // $this->response($data, REST_Controller::HTTP_BAD_REQUEST);

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
        // foreach ($cols as $col) {
        //     $$col = trim($this->put($col));
        // }
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

        if (!$ori->slug) {
            if ($slug) {
                $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
            }
            if (!$slug) {
                $this->response('Invalid URL Alias', REST_Controller::HTTP_BAD_REQUEST);
            }

            $sql = "SELECT `id` FROM `merchant_store` WHERE `id` != ? AND `slug` = ? AND `deleted` = 0";
            $exist = $this->db->query($sql, array($id, $slug))->row();
            if ($exist && $exist->id) {
                $this->response('URL Alias already regsitered', REST_Controller::HTTP_BAD_REQUEST);
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
                'slug',
                'name',
                'business',
                'state',
                'city',
                'address',
                'app_mmenu',
            ) as $col) {
                if ($col == 'slug') {
                    if ($ori->slug) {
                        continue;
                    }
                }
                $store[$col] = $$col;
            }
            $store["updatedBy"] = $this->userID;
            $this->db->update('merchant_store', $store, array('id' => $id));

            $user = array();
            $role = $this->Auth_model->roles['store-admin'];
            foreach (array(
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
            $user["updatedBy"] = $this->userID;
            $this->db->update('user', $user, array('id' => $oriUser->id));

            $ipay = [];
            foreach (array(
                'pos_merchant_code',
                'pos_merchant_code_test',
                'pos_merchant_key',
                'pos_merchant_key_test',
                'is_production',
            ) as $col) {
                $ipay[$col] = $$col;
            }
            $this->db->update('merchant_store_ipay', $ipay, array('id' => $data_ipay[0]['id']));

            if ($_FILES) {
                $upload = $this->Upload_model->storeImage('bg_store_promo_surprize-' . $id);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('merchant_store', array('bg_store_promo_surprize' => $upload['result']['file_name']), array('id' => $id));
                    if ($ori && $ori->bg_store_promo_surprize) {
                        @unlink(FCPATH . $this->Upload_model->path['store'] . $ori->bg_store_promo_surprize);
                    }
                }
            }

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
    // public function upload_post($id = 0)
    // {
    //     $id = intval($id);
    //     if ($id < 1) {
    //         $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
    //     }
    //     $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`bg_store`,'') AS `bg_store`, IFNULL(`bg_store_menu`,'') AS `bg_store_menu`, IFNULL(`bg_store_promo`,'') AS `bg_store_promo`, IFNULL(`bg_store_promo_surprize`,'') AS `bg_store_promo_surprize` FROM `merchant_store` WHERE `id` = ? AND `app_mmenu` = 1 AND `deleted` = 0";
    //     $ori = $this->db->query($sql, array($id))->row_array();
    //     if (!$ori) {
    //         $this->response('Toko tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
    //     }
    //     $data = $this->post('data');
    //     $data = trim($data);

    //     if ($_FILES) {
    //         if ($data && isset($ori[$data])) {
    //             $upload = $this->Upload_model->storeImage($data . '-' . $ori['id']);
    //             if ($upload['error']) {
    //                 $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
    //                 $this->response($errMsg, REST_Controller::HTTP_BAD_REQUEST);
    //             } else {
    //                 if ($ori[$data]) {
    //                     @unlink(FCPATH . $this->Upload_model->path['store'] . $ori[$data]);
    //                 }

    //                 $stores = array($data => $upload['result']['file_name']);
    //                 if ($this->db->update('merchant_store', $stores, array('id' => $this->storeID))) {
    //                     $storeURLPath = base_url() . $this->Upload_model->path['store'] . $upload['result']['file_name'];
    //                     $this->response($storeURLPath, REST_Controller::HTTP_OK);
    //                 }
    //             }
    //         }
    //     }

    //     $this->response('Gagal memproses data', REST_Controller::HTTP_BAD_REQUEST);
    // }
}
