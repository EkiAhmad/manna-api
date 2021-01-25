<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Onlyus extends REST_Controller
{

    private $headers = array();
    public $userID = 0;
    public $userRole = 0;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Auth_model');

        $this->headers = array();
        foreach (getallheaders() as $name => $value) { //get all parameter in header
            $this->headers[$name] = $value;
        }

        $key = $this->Auth_model->publicKeyAdmin;

        $allowed = false;
        if (isset($this->headers['Authorization'])) {
            $jwt = $this->headers['Authorization'];
            try {
                $payload = JWT::decode($jwt, $key, array('HS256'));
                if ($payload) {
                    $payload = json_decode(json_encode($payload), true);
                    $payload['id'] = intval($payload['id']); // id user
                    $payload['jti'] = intval($payload['jti']);
                    if ($payload['id'] > 0 && $payload['jti'] > 0) {
                        $sql = "SELECT `sid`, `user_id`, `payload`, `log_time`, `status` FROM `user_session` WHERE `sid` = ? LIMIT 1";
                        $logExist = $this->db->query($sql, array($payload['jti']))->row();
                        if ($logExist) {
                            $sql = "SELECT `id`, `role` FROM `user` WHERE `id` = ? AND `deleted` = 0 LIMIT 1";
                            $userExist = $this->db->query($sql, array($logExist->user_id))->row();
                            if ($userExist) {
                                $userRole = intval($userExist->role);
                                if ($userRole === 10) { // admin
                                    $secret = json_decode($logExist->payload, true);
                                    $now = time();
                                    if ($secret['exp'] > $now) {
                                        $this->userID = $secret['id'];
                                        $this->userRole = $secret['role'];
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
            $this->response(array('data' => null, 'message' => 'Invalid session'), REST_Controller::HTTP_UNAUTHORIZED);
        }
    }
}
