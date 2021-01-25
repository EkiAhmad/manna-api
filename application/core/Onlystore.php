<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Onlystore extends REST_Controller
{

    private $headers = array();
    public $userID = 0;
    public $userRole = 0;
    public $merchantID = 0;
    public $storeID = 0;
    public $merchantProductType = 0;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Auth_model');

        $ismobile = false;
        $allowed = false;
        $payload = array();

        $this->headers = $this->input->request_headers();

        $key = $this->Auth_model->publicKeyStore;

        if (isset($this->headers['Authorization'])) {
            $jwt = $this->headers['Authorization'];

            try {
                $payload = JWT::decode($jwt, $key, array('HS256'));
            } catch (Exception $e) {
                // try {
                //     $payload = JWT::decode($jwt, "XXX", array('HS256'));
                //     $ismobile = true;
                // } catch (Exception $e) {
                //     // seharunsya di response invalid signature, dan app-nya di logout
                // }
            }

            try {
                if ($payload) {
                    if ($ismobile) {
                        $payload = json_decode(json_encode($payload), true);
                        if (!isset($payload['id'])) {
                            $this->response(array('success' => false, 'message' => 'SESSION_EXPIRED'), REST_Controller::HTTP_UNAUTHORIZED);
                        }
                        $_idUser = intval($payload['id']);
                        $_idMerchant = intval($payload['id_merchant']);
                        $sql = "SELECT u.`id`, u.`role`, u.`id_merchant`, us.`id_merchant_store` FROM `user` u, `user_store` us WHERE u.`id` = us.`id_user` AND u.`id` = ? AND u.`id_merchant` = ? AND u.`deleted` = 0 LIMIT 1";
                        $userExist = $this->db->query($sql, array($_idUser, $_idMerchant))->row();
                        if ($userExist) {
                            $this->userID = intval($userExist->id);
                            $this->userRole = intval($userExist->role);
                            $this->storeID = intval($userExist->id_merchant_store);
                            $this->merchantID = intval($userExist->id_merchant);
                            if ($this->merchantID) {
                                $sql = "SELECT `product_type` FROM `merchant` WHERE `id` = ?";
                                $merchantExist = $this->db->query($sql, array($_idMerchant))->row();
                                if ($merchantExist) {
                                    $this->merchantProductType = $merchantExist->product_type;
                                    $allowed = true;
                                }
                            }
                        }
                    } else {
                        $payload = json_decode(json_encode($payload), true);
                        $payload['id'] = intval($payload['id']); // id user
                        $payload['user'] = intval($payload['user']); // id store
                        $payload['merchant'] = intval($payload['merchant']); // id store
                        $payload['jti'] = intval($payload['jti']);
                        if ($payload['id'] > 0 && $payload['jti'] > 0) {
                            $sql = "SELECT `sid`, `user_id`, `payload`, `log_time`, `status` FROM `user_session` WHERE `sid` = ? LIMIT 1";
                            $logExist = $this->db->query($sql, array($payload['jti']))->row();
                            if ($logExist) {
                                $secret = json_decode($logExist->payload, true);
                                $now = time();
                                if ($secret['exp'] > $now && $payload['id'] === $secret['id'] && $payload['user'] === $secret['user']) {
                                    $sql = "SELECT `id`, `role`, `id_merchant` FROM `user` WHERE `id` = ? AND `id_merchant` = ? AND `deleted` = 0 LIMIT 1";
                                    $userExist = $this->db->query($sql, array($logExist->user_id, $secret['merchant']))->row();
                                    if ($userExist) {
                                        $userRole = intval($userExist->role);
                                        if ($userRole === $this->Auth_model->roles['store-admin']) { // admin
                                            $this->userID = $secret['id'];
                                            $this->userRole = $secret['role'];
                                            $this->storeID = $secret['user'];
                                            $this->merchantID = $secret['merchant'];
                                            $this->merchantProductType = $secret['user_product_type'];
                                            $allowed = true;
                                        }
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
            if ($ismobile) {
                $this->response(array(
                    'success' => false,
                    'message' => 'SESSION_EXPIRED'), REST_Controller::HTTP_UNAUTHORIZED);
            } else {
                $this->response(array('data' => null, 'message' => 'Session berakhir, silahkan login kembali'), REST_Controller::HTTP_UNAUTHORIZED);
            }
        }
    }
}
