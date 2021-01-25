<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Merchants extends Onlystore
{

    public function __construct()
    {
        parent::__construct();

        $this->load->model('Auth_model');
    }

    public function select_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`company` AS `label` FROM `merchant` A WHERE A.`id` = ? AND A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql, array($this->merchantID))->result_array();
        $this->response(array('merchants' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $this->response("Cinta adalah misteri", REST_Controller::HTTP_OK);
    }
}
