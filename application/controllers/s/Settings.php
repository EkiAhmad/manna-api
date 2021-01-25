<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Settings extends Onlystore
{
    public $readOnly = true;
    public function __construct()
    {
        parent::__construct();
        if ($this->merchantProductType == "2") {
            $this->readOnly = false;
        }
    }

    public function price_inc_tax_get()
    {
        $status = "0";
        if ($this->readOnly) {
            $sql = "SELECT `active` FROM `settings` WHERE `value` = 'price-include-tax' AND `id_merchant` = ? AND `id_merchant_store` IS NULL LIMIT 1";
            $data = $this->db->query($sql, array($this->merchantID))->row();
            if ($data) {
                $status = $data->active;
            }
        } else {
            $sql = "SELECT `active` FROM `settings` WHERE `value` = 'price-include-tax' AND `id_merchant` = ? AND `id_merchant_store` = ? LIMIT 1";
            $data = $this->db->query($sql, array($this->merchantID, $this->storeID))->row();
            if ($data) {
                $status = $data->active;
            }
        }

        $this->response(array('price_include_tax' => $status), REST_Controller::HTTP_OK);
    }

    public function price_inc_tax_post()
    {
        if ($this->readOnly) {
            $this->response("Not Allowed", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
        $status = $this->post('status');
        $status = intval($status);

        $updateID = 0;
        $sql = "SELECT `id` FROM `settings` WHERE `value` = 'price-include-tax' AND `id_merchant` = ? AND `id_merchant_store` = ? LIMIT 1";
        $ori = $this->db->query($sql, array($this->merchantID, $this->storeID))->row();
        if ($ori) {
            $updateID = $ori->id;
        }

        $this->db->trans_begin();
        try {
            $settings = array(
                'value' => 'price-include-tax',
                'id_merchant' => $this->merchantID,
                'id_merchant_store' => $this->storeID,
                'label' => 'Price Include Tax for Merchant / Store',
                'active' => $status,
                'updatedAt' => date('Y-m-d H:i:s'),
            );
            if ($updateID) { // update
                $this->db->update('settings', $settings, array('id' => $updateID));
            } else { // insert
                $this->db->insert('settings', $settings);
                $updateID = $this->db->insert_id();
            }

            // tax must has one status active
            if ($status) {
                $sql = "UPDATE `tax_setting` A, `tax` B SET A.`status` = 0 WHERE A.`id_tax` = B.`id` AND A.`id_merchant` = ? AND A.`id_merchant_store` = ? AND B.`type` = 'service'";
                $this->db->query($sql, array($this->merchantID, $this->storeID));
            }

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($updateID, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Terjadi error di server', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_get($id = 0)
    {
        $this->response('Aku mencintaimu lebih dari yang kau tau...', REST_Controller::HTTP_OK);
    }
}
