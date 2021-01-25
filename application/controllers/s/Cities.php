<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cities extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index_get()
    {
        $data = array();
        $state = $this->get('state');
        if ($state) {
            $state = preg_replace("/[^A-Z]/", "", $state);
        }
        if ($state) {
            $sql = "SELECT `id` AS `value`, `state`, `label` FROM `ref_city` WHERE `state` = ? ORDER BY `label`";
            $data = $this->db->query($sql, array($state))->result_array();

        } else {
            $sql = "SELECT `id` AS `value`, `state`, `label` FROM `ref_city` ORDER BY `label`";
            $data = $this->db->query($sql)->result_array();
        }

        $this->response(array('cities' => $data), REST_Controller::HTTP_OK);
    }
}
