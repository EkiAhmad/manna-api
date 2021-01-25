<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Onlysupplier extends REST_Controller
{

    private $headers = array();
    public $userID = 0;
    public $supplierID = 0;
    public $userRole = 0;
    public $supplierStores = array();

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Auth_model');

        $this->headers = array();
        foreach (getallheaders() as $name => $value) { //get all parameter in header
            $this->headers[$name] = $value;
        }

        $key = $this->Auth_model->publicKeySupplier;

        $allowed = false;
        if (isset($this->headers['Authorization'])) {
            $jwt = $this->headers['Authorization'];

            try {
                $payload = JWT::decode($jwt, $key, array('HS256'));
                if ($payload) {
                    $payload = json_decode(json_encode($payload), true);
                    $payload['id'] = intval($payload['id']); // id user
                    $payload['user'] = intval($payload['user']); // id supplier
                    $payload['jti'] = intval($payload['jti']);
                    if ($payload['id'] > 0 && $payload['jti'] > 0) {
                        $sql = "SELECT `sid`, `user_id`, `payload`, `log_time`, `status` FROM `user_session` WHERE `sid` = ? LIMIT 1";
                        $logExist = $this->db->query($sql, array($payload['jti']))->row();
                        if ($logExist) {
                            $secret = json_decode($logExist->payload, true);
                            $now = time();
                            if ($secret['exp'] > $now && $payload['id'] === $secret['id'] && $payload['user'] === $secret['user']) {
                                $sql = "SELECT u.`id`, u.`role` FROM `user` u, `supplier` s WHERE u.`id_supplier` = s.id AND u.`id` = ? AND u.`id_supplier` = ? AND u.`deleted` = 0 AND s.`deleted` = 0 LIMIT 1";
                                $userExist = $this->db->query($sql, array($logExist->user_id, $secret['user']))->row();
                                if ($userExist) {
                                    $userRole = intval($userExist->role);
                                    if ($userRole === $this->Auth_model->roles['supplier-admin']) { // admin
                                        $sql_ = "SELECT `id_merchant_store` FROM `supplier_store` WHERE `id_supplier` = ?";
                                        $row_ = $this->db->query($sql_, array($secret['user']))->result_array();
                                        if ($row_) {
                                            $this->supplierStores = array_column($row_, 'id_merchant_store');
                                        }
                                        $this->userID = intval($secret['id']);
                                        $this->userRole = intval($secret['role']);

                                        $this->supplierID = intval($secret['user']);
                                        $allowed = true;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // seharunsya di response invalid signature, dan app-nya di logout
            }
        }

        if (!$allowed) {
            $this->response(array('message' => 'Invalid session'), REST_Controller::HTTP_UNAUTHORIZED);
        }
    }
}
