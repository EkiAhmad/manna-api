<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWT.php';
use \Firebase\JWT\JWT;

class Authentication extends REST_Controller {

  public function __construct()
  {
    parent::__construct();
		$this->load->model('Auth_model');
  }

  public function index_post()
  {
    foreach (array('email','password','strategy','keep') as $col) {
      $$col = $this->post($col);
    }

    if (empty($email) || empty($password) || $strategy !== 'local' ) {
      $this->response(array('message' => 'Invalid parameters'), REST_Controller::HTTP_BAD_REQUEST);
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false ) {
      $this->response(array('message' => 'Invalid email'), REST_Controller::HTTP_BAD_REQUEST);
    }

		$key = $this->Auth_model->publicKeyAdmin;

    $sql = "SELECT `id`, `fullname`, `password`, `role` FROM `user` WHERE `email` = ? AND `deleted` = 0 LIMIT 1";
    $row = $this->db->query($sql,array($email))->row();
    if ($row) {
      $row->id = intval($row->id);
      $row->fullname = trim($row->fullname);
      $row->password = trim($row->password);
      $row->role = intval($row->role);

      if ($row->id > 0 && $row->role === 10) {
        if ( hash_equals($row->password, md5($password)) ) {

					$times = time();

					$token = array();
					$token['id'] = $row->id;
					$token['iat'] = $times;
					$token['exp'] = $times + 120*120*1;
					$token['role'] = $row->role;
					$token['fullname'] = $row->fullname;

					$logs = array(
						'user_id' => $row->id,
						'payload' => json_encode($token),
						'status' => 1
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

   $this->response(array('message' => 'Invalid email or password'), REST_Controller::HTTP_BAD_REQUEST);
  }
}
