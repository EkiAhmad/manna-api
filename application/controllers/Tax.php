<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Tax extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index_get($id = 0)
    {
        $data = array();

        $id = intval($id);
        if ($id < 1) {
            $sql = "SELECT `id`, `type`, `name`, `value_type`, `value` FROM `tax` WHERE `deleted` = 0 ORDER BY `type`";
            $data = $this->db->query($sql)->result_array();
            $this->response(array('tax' => $data), REST_Controller::HTTP_OK);
        } else {
            $sql = "SELECT `id`, `type`, `name`, `value_type`, `value` FROM `tax` WHERE `id` = ? AND `deleted` = 0";
            $data = $this->db->query($sql, array($id))->row();
            $this->response($data, REST_Controller::HTTP_OK);
        }
    }

    public function index_post()
    {
        $cols = array(
            'type',
            'value',
            'name',
            'value_type',
        );

        foreach ($cols as $col) {

            $$col = trim($this->post($col));
        }
        $value = intval($value);

        if (empty($name)) {
            $this->response('Name can\'t be empty', REST_Controller::HTTP_BAD_REQUEST);
        }

        $tax = array(
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'value_type' => $value_type,
            'createdBy' => $this->userID,
        );

        if ($this->db->insert('tax', $tax)) {
            $lastInsertID = $this->db->insert_id();
            $this->response($lastInsertID, REST_Controller::HTTP_OK);
        } else {
            $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_put($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Tax', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id` FROM `tax` WHERE `id` = ? AND `deleted` = 0";
        $ori = $this->db->query($sql, array($id))->row();
        if (!$ori) {
            $this->response('Invalid Tax', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'type',
            'value',
            'name',
            'value_type',
        );

        foreach ($cols as $col) {
            $$col = trim($this->put($col));
        }
        $value = intval($value);

        if (empty($name)) {
            $this->response('Name can\'t be empty', REST_Controller::HTTP_BAD_REQUEST);
        }

        $tax = array(
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'value_type' => $value_type,
            'updatedBy' => $this->userID,
        );

        if ($this->db->update('tax', $tax, array('id' => $id))) {
            $this->response($id, REST_Controller::HTTP_OK);
        } else {
            $this->response("Error while processing data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
