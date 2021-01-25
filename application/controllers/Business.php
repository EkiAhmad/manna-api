<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Business extends REST_Controller {

	public function __construct()
  {
		parent::__construct();
	}

	public function index_get() {
		$sql = "SELECT `value`, `label` FROM `ref_business` ORDER BY `label`";
		$data = $this->db->query($sql)->result_array();
		$this->response(array('business'=>$data), REST_Controller::HTTP_OK);
	}
}
