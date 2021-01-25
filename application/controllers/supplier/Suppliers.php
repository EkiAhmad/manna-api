<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlysupplier.php';

class Suppliers extends Onlysupplier
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
    }

    public function index_get()
    {
        $data = array();
        $sql = "SELECT s.`id`,s.`name`, c.`state`, s.`city`, s.`address` FROM `supplier` s, `ref_city` c WHERE s.`city` = c.`id` AND s.`id` = ? AND s.`deleted` = 0 LIMIT 1";
        $supplier = (array) $this->db->query($sql, array($this->supplierID))->row();
        if ($supplier) {
            $sql = "SELECT `fullname`, `phone`, `email` FROM `user` WHERE `id_supplier` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
            $user = (array) $this->db->query($sql, array($this->supplierID, $this->Auth_model->roles['supplier-admin']))->row();

            $data = array_merge($supplier, $user);
        }
        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function index_post()
    {
        $sql = "SELECT `id` FROM `supplier` WHERE `id` = ? AND `deleted` = 0";
        $ori = $this->db->query($sql, array($this->supplierID))->row();
        if (!$ori) {
            $this->response('Data Supplier tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id`, `phone`, `password` FROM `user` WHERE `id` = ? AND `id_supplier` = ? AND `role` = ? AND `deleted` = 0 LIMIT 1";
        $oriUser = $this->db->query($sql, array($this->userID, $this->supplierID, $this->Auth_model->roles['supplier-admin']))->row();
        if (!$oriUser) {
            $this->response('Data Supplier tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'name',
            'city',
            'address',
            'fullname',
            'phone',
            'password',
            'oldpassword',
        );
        foreach ($cols as $col) {
            $$col = trim($this->post($col));
        }

        if (!$name) {
            $this->response('Nama Supplier harus diisi', REST_Controller::HTTP_BAD_REQUEST);
        }
        $phone = preg_replace('/\D/', '', $phone);
        if (!$phone) {
            $this->response('Nomor HP harus diisi', REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!$fullname || !$phone) {
            $this->response('Data Penanggung Jawab harus diisi', REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($city) {
            $city = intval($city);
            $sql = "SELECT `state` FROM `ref_city` WHERE `id` = ?";
            $getState = $this->db->query($sql, array($city))->row_array();
            if (!$getState) {
                $this->response('Kota tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        if ($oriUser->phone != $phone) {
            $sql = "SELECT `id` FROM `user` WHERE `id` != ? AND `phone` = ? AND `deleted` = 0 LIMIT 1";
            $exist = $this->db->query($sql, array($oriUser->id, $phone))->row();
            if ($exist && $exist->id) {
                $this->response('Nomor HP sudah terdaftar', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        if ($oldpassword) {
            if (hash_equals($oriUser->password, md5($oldpassword))) {
            } else {
                $this->response('Password lama salah', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $id_supplier = $this->supplierID;

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
            $supplier["updatedBy"] = $this->userID;
            $this->db->update('supplier', $supplier, array('id' => $this->supplierID));

            $user = array();
            $role = $this->Auth_model->roles['supplier-admin'];
            foreach (array(
                'id_supplier',
                'fullname',
                'phone',
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
                $this->response($this->supplierID, REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Gagal memproses data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
