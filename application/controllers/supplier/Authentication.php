<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Authentication extends REST_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
    }

    public function index_post()
    {
        foreach (array('phone', 'password', 'strategy', 'keep') as $col) {
            $$col = $this->post($col);
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (empty($phone) || empty($password)) {
            $this->response(array('message' => 'Nomor hp dan password harus diisi'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $key = $this->Auth_model->publicKeySupplier;

        $sql = "SELECT `id`, IFNULL(`id_supplier`,0) AS `id_supplier`, `fullname`, `password`, `role` FROM `user` WHERE `phone` = ? AND `deleted` = 0 LIMIT 1";
        $row = $this->db->query($sql, array($phone))->row();
        if ($row) {
            $row->id = intval($row->id);
            $row->fullname = trim($row->fullname);
            $row->password = trim($row->password);
            $row->role = intval($row->role);
            $row->id_supplier = intval($row->id_supplier);

            if ($row->id > 0 && $row->id_supplier > 0 && $row->role === $this->Auth_model->roles['supplier-admin']) {
                if (hash_equals($row->password, md5($password))) {
                    $sql2 = "SELECT `id`, `name` AS `company` FROM `supplier` WHERE `id` = ? AND `deleted` = 0";
                    $row2 = $this->db->query($sql2, array($row->id_supplier))->row();
                    if ($row2) {

                        $times = time();

                        $token = array();
                        $token['id'] = $row->id;
                        $token['iat'] = $times;
                        $token['exp'] = $times + (120 * 120 * 1);
                        $token['role'] = $row->role;
                        $token['fullname'] = $row->fullname;

                        $token['user'] = $row->id_supplier;
                        $token['company'] = $row2->company;

                        $logs = array(
                            'user_id' => $row->id,
                            'payload' => json_encode($token),
                            'status' => 1,
                        );

                        if ($this->db->insert('user_session', $logs)) {
                            $sessId = $this->db->insert_id();
                            $token['jti'] = $sessId;

                            $jwt = JWT::encode($token, $key);
                            $this->response(array('accessToken' => $jwt, 'message' => 'Login sukses'), REST_Controller::HTTP_OK);
                        } else {
                            $this->response(array('message' => 'Server error'), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                        }
                    }
                }
            }
        }
        $this->response(array('message' => 'Nomor hp atau password salah'), REST_Controller::HTTP_BAD_REQUEST);
    }
}
