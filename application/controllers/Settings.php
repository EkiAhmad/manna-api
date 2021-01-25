<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Settings extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
    }

    public function wa_notif_store_get()
    {
        $sql = "SELECT * FROM `settings` WHERE value = 'wa-notif-store' LIMIT 1";
        $data = $this->db->query($sql)->row();
        $this->response(array('settings' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $this->response('Aku mencintaimu lebih dari yang kau tau...', REST_Controller::HTTP_OK);
    }

    public function wa_notif_store_post()
    {
        $sent_time = $this->post('sent_time');
        $messages = $this->post('messages');
        $active = $this->post('active');

        $messages = trim($messages);
        $active = intval($active);

        if ($active > 1) {
            $active = 1;
        }

        try {
            $sent_time = date('Y-m-d H:i:s', strtotime($sent_time));
        } catch (Exception $e) {
            $this->response('Invalid Sent Time', REST_Controller::HTTP_BAD_REQUEST);
        }

        $content = array(
            'sent_time' => $sent_time,
            'messages' => $messages,
        );
        $content = json_encode($content);

        $datas = array(
            'active' => $active,
            'content' => $content,
            'updatedAt' => date('Y-m-d H:i:s'),
        );

        if ($this->db->update('settings', $datas, array('value' => 'wa-notif-store'))) {
            $this->response("success", REST_Controller::HTTP_OK);
        }
        $this->response("Error update data", REST_Controller::HTTP_BAD_REQUEST);
    }
}
