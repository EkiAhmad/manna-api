<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Suppliers extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
    }

    public function stores_get($id = 0)
    {
        $data = array();
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT
			B.`id`, B.`name`, RS.`label` AS `state`, RC.`label` AS `city`,
			IFNULL(B.`address`,'') AS `address`, M.`company`
			FROM `supplier_store` A, `merchant_store` B, `merchant` M, `ref_state` RS, `ref_city` RC
			WHERE
				A.`id_merchant_store` = B.`id` AND
				B.`id_merchant` = M.`id` AND
				B.`state` = RS.`value` AND
				B.`city` = RC.`id` AND
				A.`id_supplier` = ? AND M.`deleted` = 0 AND B.`deleted`= 0  ORDER BY B.`id`";
            $data = $this->db->query($sql, array($id))->result_array();
        } else {
            $sql = "SELECT B.`id`, B.`name`, RS.`label` AS `state`, RC.`label` AS `city`,
			IFNULL(B.`address`,'') AS `address`, M.`company`
			FROM `merchant_store` B, `merchant` M, `ref_state` RS, `ref_city` RC
			WHERE
				B.`id_merchant` = M.`id` AND
				B.`state` = RS.`value` AND
				B.`city` = RC.`id` AND
				M.`deleted` = 0 AND B.`deleted`= 0 ORDER BY B.`id`";
            $data = $this->db->query($sql)->result_array();
        }
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $id = intval($id);
        //get supplier list and get detil supplier
        if ($id > 0) {
            $sql = "SELECT s.`id`, s.`name`, s.`city`, rc.`state`, s.`address` FROM `supplier` s, `ref_city` rc WHERE s.`city` = rc.`id` AND s.`id` = ? AND s.`deleted` = 0";
            $data = $this->db->query($sql, array($id))->row_array();
            if ($data) {
                $sql = "SELECT u.`fullname`, IFNULL(u.`email`,'') AS `email`, u.`phone` FROM `user` u WHERE u.`id_supplier` = ? AND u.`role` = ? AND u.`deleted` = 0 LIMIT 1";
                $userSupplier = $this->db->query($sql, array($id, $this->Auth_model->roles['supplier-admin']))->row_array();
                if ($userSupplier) {
                    $data = array_merge($data, $userSupplier);
                }
                // get store supplier
                $sql = "SELECT ms.`id`, ms.`name`, rc.`label` AS `city`, rs.`label` AS `state`, IFNULL(ms.`address`,'') AS `address`, m.company
					FROM `supplier_store` ss
						JOIN `merchant_store` ms ON ss.`id_merchant_store` = ms.`id`
						JOIN `merchant` m ON ms.`id_merchant` = m.`id`
						JOIN `ref_state` rs ON ms.`state` = rs.`value`
						JOIN `ref_city` rc ON ms.`city` = rc.`id`
					WHERE ss.`id_supplier` = ? AND ms.`deleted`= 0 AND m.`deleted` = 0 ORDER BY ss.`id`";
                $stores = $this->db->query($sql, array($id))->result_array();
                $data['stores'] = $stores;
                $this->response($data, REST_Controller::HTTP_OK);
            }
            $this->response("Invalid Supplier", REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $sql = "SELECT x.id AS id, x.name AS name, b.label AS city, s.label AS `state`, IFNULL(x.`address`,'') AS `address`, c.fullname AS fullname, c.phone AS phone, x.total_store
				FROM
    		(SELECT a.id AS id, a.name AS name, a.city, IFNULL(a.`address`,'') AS `address`, IFNULL(COUNT(a.id),0) AS total_store FROM `supplier` a LEFT JOIN supplier_store d ON d.id_supplier = a.id WHERE a.deleted = 0 GROUP BY 1) AS x
				JOIN ref_city b ON x.city = b.id
				JOIN ref_state s ON s.value = b.state
				JOIN user c ON c.id_supplier = x.id
			WHERE c.role = ? ORDER BY 1 DESC";
            $data = $this->db->query($sql, array($this->Auth_model->roles['supplier-admin']))->result_array();
            $this->response(array('suppliers' => $data), REST_Controller::HTTP_OK);
        }
    }

    public function index_post()
    {
        $cols = array(
            'name',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
            'stores',
        );
        foreach ($cols as $col) {
            if ($col == 'stores') {
                $$col = $this->post($col);
            } else {
                $$col = trim($this->post($col));
            }
        }

        if (!is_array($stores)) {
            $this->response("Invalid Stores", REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!$password) {
            $this->response("Password can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $password = md5($password);
        }
        $phone = preg_replace('/\D/', '', $phone);
        if (!$phone) {
            $this->response("Phone Number can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!$email) {
            $email = null;
        }
        $sql = "SELECT `id` FROM `user` WHERE `phone` = ? AND `deleted` = 0";
        $exist = $this->db->query($sql, array($phone))->row();
        if ($exist && $exist->id) {
            $this->response('Phone Number already regsitered', REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->db->trans_begin();
        try {
            $supplier = array();
            foreach (array(
                'name',
                'city',
                'address',
            ) as $col) {
                $supplier[$col] = $$col;
            }
            $supplier["createdBy"] = $this->userID;
            $this->db->insert('supplier', $supplier);
            $lastID = $this->db->insert_id();

            $user = array();
            foreach (array(
                'fullname',
                'phone',
                'email',
                'password',
            ) as $col) {
                $user[$col] = $$col;
            }
            $user['id_supplier'] = $lastID;
            $user['role'] = $this->Auth_model->roles['supplier-admin'];
            $user["createdBy"] = $this->userID;
            $this->db->insert('user', $user);

            $supplier_stores = array();
            foreach ($stores as $s) {
                $supplier_stores[] = array(
                    'id_supplier' => $lastID,
                    'id_merchant_store' => intval(trim($s['id'])),
                );
            }
            if ($supplier_stores) {
                $this->db->insert_batch('supplier_store', $supplier_stores);
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($lastID, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_put($id = 0)
    {
        $id = intval($id);
        if ($id < 0) {
            $this->response('Invalid Supplier', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id` FROM `supplier` WHERE `id` = ?";
        $ori = $this->db->query($sql, array($id))->row_array();
        if (!$ori) {
            $this->response('Invalid Supplier', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`, `phone` FROM `user` WHERE `id_supplier` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($ori['id'], $this->Auth_model->roles['supplier-admin']))->row_array();
        if (!$oriUser) {
            $this->response('Invalid Supplier PIC', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'name',
            'city',
            'address',
            'fullname',
            'phone',
            'email',
            'password',
            'stores',
        );
        foreach ($cols as $col) {
            if ($col == 'stores') {
                $$col = $this->put($col);
            } else {
                $$col = trim($this->put($col));
            }
        }

        if (!is_array($stores)) {
            $this->response("Invalid Stores", REST_Controller::HTTP_BAD_REQUEST);
        }
        $phone = preg_replace('/\D/', '', $phone);
        if (!$phone) {
            $this->response("Phone Number can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!$email) {
            $email = null;
        }
        if ($oriUser['phone'] != $phone) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `phone` = ? AND `deleted` = 0";
            $exist = $this->db->query($sql, array($oriUser['id'], $phone))->row();
            if ($exist && $exist->id) {
                $this->response('Phone Number already regsitered', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $this->db->trans_begin();
        try {
            $supplier = array();
            foreach (array(
                'name',
                'city',
                'address',
            ) as $col) {
                $supplier[$col] = $$col;
            }
            $supplier['updatedBy'] = $this->userID;
            $this->db->update('supplier', $supplier, array('id' => $id));

            $user = array();
            foreach (array(
                'fullname',
                'phone',
                'email',
                'password',
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
            $user['role'] = $this->Auth_model->roles['supplier-admin'];
            $user['updatedBy'] = $this->userID;
            $this->db->update('user', $user, array('id' => $oriUser['id']));

            $supplier_stores = array();

            $this->db->delete('supplier_store', array('id_supplier' => $ori['id']));
            foreach ($stores as $s) {
                $supplier_stores[] = array(
                    'id_supplier' => $id,
                    'id_merchant_store' => intval(trim($s['id'])),
                );
            }
            if ($supplier_stores) {
                $this->db->insert_batch('supplier_store', $supplier_stores);
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

}
