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

        $password = trim($password);
        if (!$password) {
            $this->response(array('message' => 'Nomor hp atau password salah'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $key = $this->Auth_model->publicKeyStore;

        $sql = "SELECT U.`id`, U.`id_merchant`, U.`fullname`, U.`password`, U.`role`,
		M.`product_type`, MS.`id` AS `id_store`, MS.`name` AS `store`,
		IF(IFNULL(MS.`slug`,'') != '', CONCAT('https://manna.asia/menu/','',MS.`slug`),'') AS `url`
		FROM `user` U, `user_store` US, `merchant` M, `merchant_store` MS
		WHERE
		U.`id` = US.`id_user` AND
		U.`id_merchant` = M.`id` AND
		US.`id_merchant_store` = MS.`id` AND
		U.`phone` = ? AND U.`deleted` = 0 AND
		MS.`id_merchant` IS NOT NULL AND MS.`app_mmenu` = 1 LIMIT 1";
        $row = $this->db->query($sql, array($phone))->row();
        if ($row) {
            $row->id = intval($row->id);
            $row->fullname = trim($row->fullname);
            $row->password = trim($row->password);
            $row->role = intval($row->role);
            $row->id_merchant = intval($row->id_merchant);
            $row->id_store = intval($row->id_store);

            if ($row->id > 0 && $row->id_merchant > 0 && $row->id_store > 0 && $row->role === $this->Auth_model->roles['store-admin']) {
                if (password_verify($password, $row->password)) {

                    $times = time();

                    $token = array();
                    $token['id'] = $row->id;
                    $token['iat'] = $times;
                    $token['exp'] = $times + 120 * 120 * 1;
                    $token['role'] = $row->role;
                    $token['fullname'] = $row->fullname;
                    $token['url'] = $row->url;

                    $token['user'] = $row->id_store;
                    $token['merchant'] = $row->id_merchant;
                    $token['company'] = $row->store;
                    $token['user_product_type'] = intval($row->product_type);

                    $logs = array(
                        'user_id' => $row->id,
                        'payload' => json_encode($token),
                        'status' => 1,
                    );

                    if ($this->db->insert('user_session', $logs)) {
                        $sessId = $this->db->insert_id();
                        $token['jti'] = $sessId;

                        $jwt = JWT::encode($token, $key);
                        $this->response(array('accessToken' => $jwt, 'message' => 'Login successfully'), REST_Controller::HTTP_OK);
                    }
                }
            }
        }

        $this->response(array('message' => 'Nomor hp atau password salah'), REST_Controller::HTTP_BAD_REQUEST);
    }
}
