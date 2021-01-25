<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlymerchant.php';

class Tax extends Onlymerchant
{

    public function __construct()
    {
        parent::__construct();

        //general validation for tax merchant
        if ($this->merchantProductType != "1") {
            $this->response("Invalid Merchant", REST_Controller::HTTP_BAD_REQUEST);
        }
    }

    public function index_get($id = 0)
    {
        $id = intval($id);

        if ($id < 1) { // list merchant tax
            $sql = "SELECT
				A.`id`, A.`type`, A.`name`,
				IFNULL(B.`value_type`,A.`value_type`) AS `value_type`,
				IFNULL(B.`value`,A.`value`) AS `value`,
				IFNULL(B.`status`, '0') `status`
			FROM `tax` A
				LEFT JOIN (
					SELECT `id_tax`, `value_type`, `value`, `status` FROM `tax_setting` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL
				) AS `B`
			ON A.`id` = B.`id_tax` ORDER BY A.`type` ASC";
            $tax = $this->db->query($sql, array($this->merchantID))->result_array();

            $this->response(array("tax_setting" => $tax), REST_Controller::HTTP_OK);

        } else { // detail tax
            $sql = "SELECT
				A.`id`, A.`type`, A.`name`,
				IFNULL(B.`value_type`,A.`value_type`) AS `value_type`,
				IFNULL(B.`value`,A.`value`) AS `value`,
				IFNULL(B.`status`, '0') `status`
			FROM `tax` A
				LEFT JOIN (
					SELECT `id_tax`, `value_type`, `value`, `status` FROM `tax_setting` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL
				) AS `B`
			ON A.`id` = B.`id_tax` WHERE A.`id` = ?";
            $tax = $this->db->query($sql, array($this->merchantID, $id))->row();

            $this->response($tax, REST_Controller::HTTP_OK);
        }
    }

    public function index_post($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Tax', REST_Controller::HTTP_BAD_REQUEST);
        }

        // check original tax from admin
        $sql = "SELECT `id` FROM `tax` WHERE `id` = ?";
        $ori = $this->db->query($sql, array($id))->row();
        if (!$ori) {
            $this->response('Invalid Tax', REST_Controller::HTTP_BAD_REQUEST);
        }

        //cek if service then price inc tax must in OFF condition
        if ($ori->type == 'service') {
            $sql = "SELECT `active` FROM `settings` WHERE `value` = 'price-include-tax' AND `id_merchant` = ? AND `id_merchant_store` IS NULL LIMIT 1";
            $data = $this->db->query($sql, array($this->merchantID))->row();
            if ($data) {
                if ($data->active == "1") {
                    $this->response('Not Allowed, Price Inc Tax is ON', REST_Controller::HTTP_BAD_REQUEST);
                }
            }
        }

        // check already has or not
        $updateID = 0;
        $sql = "SELECT `id` FROM `tax_setting` WHERE `id_tax` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NULL LIMIT 1";
        $taxMerchant = $this->db->query($sql, array($id, $this->merchantID))->row();
        if ($taxMerchant) {
            $updateID = $taxMerchant->id;
        }

        // binding post request
        $cols = array('type', 'value_type', 'value', 'status');
        foreach ($cols as $col) {
            $$col = $this->post($col);
        }

        $status = intval($status);
        $value = intval($value);

        // use transaction because possible multiple query insert/update at once
        $this->db->trans_begin();
        try {
            $tax = array(
                'id_tax' => $id,
                'id_merchant' => $this->merchantID,
                'value_type' => $value_type,
                'value' => $value,
                'status' => $status,
            );
            if ($updateID) { // update
                $tax['updatedBy'] = $this->userID;
                $this->db->update('tax_setting', $tax, array('id' => $updateID));

            } else { // insert
                $tax['createdBy'] = $this->userID;
                $this->db->insert('tax_setting', $tax);
                $updateID = $this->db->insert_id();
            }

            // tax must has one status active
            if ($status && $type == 'tax') {
                $sql = "UPDATE `tax_setting` A, `tax` B SET `status` = 0 WHERE A.`id_tax` = B.`id` AND A.`id` != ? AND A.`id_merchant` = ? AND A.`id_merchant_store` IS NULL AND B.`type` = 'tax'";
                $this->db->query($sql, array($updateID, $this->merchantID));
            }

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($id, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
