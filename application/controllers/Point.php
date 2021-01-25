<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Point extends Onlyus {

	public function __construct()
  	{
		parent::__construct();
        $this->load->model('Upload_model');

	}

	public function level_get($id = 0) {
        $id = intval($id);
		if ($id < 1) {
			$sql = "SELECT A.`id`, A.`name`, A.`cashback`
				FROM `loyalty` A ORDER BY A.`id` DESC";
		}else {
			$sql = "SELECT A.`id`, A.`name`, A.`cashback`
				FROM `loyalty` A 
				WHERE A.`id` = $id ORDER BY A.`id` DESC";
		}
		$data = $this->db->query($sql)->result_array();
		$this->response(array('level'=>$data), REST_Controller::HTTP_OK);
	}

	public function level_put($id = 0) {
        $id = intval($id);
		if ($id < 1) {
            $this->response('Kategori tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
		}
        $value = trim($this->put('cashback'));
		$cols = array(
			'cashback' => $value,
		);

		if ($this->db->update('loyalty', $cols, array('id' => $id))) {
			$this->response($id, REST_Controller::HTTP_OK);
		}
        $this->response("Gagal memproses data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
	}

	public function index_get($id = 0) {
		$filters = array('start_date', 'end_date', 'store', 'status');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
		$store = intval($store);
		$status = intval($status);
        $filterStrStore = $store > 0 ? " AND A.`id_merchant_store` = $store" : "";
		$filterStrStatus = $status != 2 ? " AND A.`status_redeem` = $status" : "";
		
		$start_date = date('Ymd', strtotime($start_date));
        $end_date = date('Ymd', strtotime($end_date));
        $id = intval($id);
        $productPath = base_url() . $this->Upload_model->path['payment'];
		if ($id < 1) {
			$sql = "SELECT A.`id`, A.`id_merchant_store`,A.`total_point_redeem`,
				IF(IFNULL(A.`bukti_redeem`,'') != '', CONCAT('" . $productPath . "','',A.`bukti_redeem`), '') AS `bukti_redeem`,
				A.`status_redeem`,B.`name`, CONCAT(A.`date_request`,' ', A.`time_request`) AS `date_request`,
				CONCAT(A.`date_success`,' ', A.`time_success`) AS `date_success`,DATE_FORMAT(A.`date_request`, '%Y-%m-%d') AS `params`
			FROM `loyalty_request_payment` A, `merchant_store` B
				WHERE A.`id_merchant_store` = B.`id` AND DATE_FORMAT(A.`date_request`, '%Y%m%d') BETWEEN $start_date AND $end_date $filterStrStore $filterStrStatus AND A.`deleted` = 0 ORDER BY A.`id` DESC";
		}else {
				$sql = "SELECT A.`id`, A.`id_merchant_store`,A.`total_point_redeem`,
				IF(IFNULL(A.`bukti_redeem`,'') != '', CONCAT('" . $productPath . "','',A.`bukti_redeem`), '') AS `bukti_redeem`,
				A.`status_redeem`,B.`name`, CONCAT(A.`date_request`,' ', A.`time_request`) AS `date_request`,
				CONCAT(A.`date_success`,' ', A.`time_success`) AS `date_success`
			FROM `loyalty_request_payment` A, `merchant_store` B
				WHERE A.`id_merchant_store` = B.`id` AND A.`id` = $id AND A.`deleted` = 0 ORDER BY A.`id` DESC";
		}
		$data = $this->db->query($sql)->result_array();

		$this->response(array('point'=>$data), REST_Controller::HTTP_OK);
	}

	public function index_post($id = 0) {
        $id = intval($id);
		if ($id < 1) {
            $this->response('Request tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
		}
		if ($id) {
			$sql = "SELECT `id`, IFNULL(`bukti_redeem`,'') AS `bukti_redeem` FROM `loyalty_request_payment` WHERE `id` = ? AND `deleted` = 0 LIMIT 1";
            $ori = $this->db->query($sql, array($id))->row();
            if (!$ori) {
                $this->response('Request not found', REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $id = intval($ori->id);
            }
		}
			
		$cols = array(
			'status_redeem',
        );
		if ($_FILES) {
			$data = json_decode($this->post('data'), true);
            if (!$data) {
                $this->response("Please fill form", REST_Controller::HTTP_BAD_REQUEST);
            }
            foreach ($cols as $col) {
                $$col = isset($data[$col]) ? trim($data[$col]) : '';
			}
			
        } else {
            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
        }

        $this->db->trans_begin();
        try {
			$point = array();
            foreach ($cols as $col) {
                $point[$col] = $$col;
            }
            if ($id) {
                $point["updatedBy"] = $this->userID;
                $point["date_success"] = date('Y-m-d');
                $point["time_success"] = date('H:i:s');
                $this->db->update('loyalty_request_payment', $point, array('id' => $id));
            } else {
                // $product["createdBy"] = $this->userID;
                // $this->db->insert('product', $product);
                // $id = $this->db->insert_id();
            }

			if ($_FILES) {
                $upload = $this->Upload_model->payment($status_redeem);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('loyalty_request_payment', array('bukti_redeem' => $upload['result']['file_name']), array('id' => $id));
                    if ($ori && $ori->bukti_redeem) {
                        @unlink(FCPATH . $this->Upload_model->path['payment'] . $ori->bukti_redeem);
                    }
                }
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

	public function reset_get($id = 0) {
        $id = intval($id);
		$sql = "SELECT A.`id`, A.`fullname`, A.`total_point`, B.`name`
				FROM `member` A,`loyalty` B WHERE A.`id_loyalty` = B.`id` ORDER BY A.`id` DESC";
		$data = $this->db->query($sql)->result_array();
		$this->response(array('member'=>$data), REST_Controller::HTTP_OK);
	}

	public function reset_put() {
        $member = "SELECT `id` FROM `member` WHERE `deleted` = 0";
		$member_result = $this->db->query($member)->result_array();
		
		$upd_member = array();
		if ($member_result) {
			foreach ($member_result as $b) {
				$b_id = isset($b['id']) ? intval(trim($b['id'])) : 0;
				if ($b_id > 0) {
					$upd_member[] = array( 
						'id' => $b_id,
						'id_loyalty' => 1, 
					);
				}
			}
		}
        
		$this->db->trans_begin();
        try {
			if ($upd_member) {
                $this->db->update_batch('member', $upd_member, 'id');
            }

			if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response('Success', REST_Controller::HTTP_OK);
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
	}

	public function select_store_get() {
		$sql = "SELECT C.`id` as `value`, C.`name` AS `label` FROM `merchant_store` C WHERE  C.`deleted`= 0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
	}
}
