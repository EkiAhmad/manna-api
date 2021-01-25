<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Lotteries extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index_get($id = 0)
    {
        $data = array();
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT A.`id`, A.`name`, A.`start_date`, A.`end_date`, A.`desc`, '' AS `stores`
			FROM `lottery` A
				WHERE A.`id` = ? AND A.`deleted` = 0 ";
            $data = $this->db->query($sql, array($id))->row_array();
            if ($data) {
                $sql = "SELECT
				B.`id` AS `id_store`,
				B.`name`, RS.`label` AS `state`, RC.`label` AS `city`, IFNULL(B.`address`,'') AS `address`,
				M.`company`
				FROM `lottery_store` A, `merchant_store` B, `merchant` M, `ref_state` RS, `ref_city` RC
				WHERE
					A.`id_merchant_store` = B.`id` AND
					B.`id_merchant` = M.`id` AND
					B.`state` = RS.`value` AND
					B.`city` = RC.`id` AND
					A.`id_lottery` = ? AND B.`deleted`=0 AND M.`deleted`=0 ORDER BY B.`id_merchant`, B.`id`";
                $stores = $this->db->query($sql, array($id))->result_array();
                $data['stores'] = array();
                if ($stores) {
                    $data['stores'] = $stores;
                }
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $this->response("Invalid Lottery", REST_Controller::HTTP_BAD_REQUEST);
            }
        } else {
            $sql = "SELECT A.`id`, A.`name`, A.`start_date`, A.`end_date`, COUNT(B.`id`) AS `total_store`
			FROM `lottery` A, `lottery_store` B
				WHERE
			A.`id` = B.`id_lottery` AND A.`deleted` = 0 GROUP BY A.`id` ORDER BY A.`id` DESC";
            $data = $this->db->query($sql)->result_array();
            $this->response(array('lotteries' => $data), REST_Controller::HTTP_OK);
        }
    }

    public function stores_get($id = 0)
    {
        $data = array();
        $id = intval($id);
        if ($id > 0) {
            $sql = "SELECT
			B.`id` AS `id_store`,
			B.`name`, RS.`label` AS `state`, RC.`label` AS `city`, IFNULL(B.`address`,'') AS `address`,
			M.`company`
			FROM `lottery_store` A, `merchant_store` B, `merchant` M, `ref_state` RS, `ref_city` RC
			WHERE
				A.`id_merchant_store` = B.`id` AND
				B.`id_merchant` = M.`id` AND
				B.`state` = RS.`value` AND
				B.`city` = RC.`id` AND
				A.`id_lottery` = ? AND B.`deleted`=0 AND M.`deleted`=0 ORDER BY B.`id_merchant`, B.`id`";
            $data = $this->db->query($sql, array($id))->result_array();
        } else {
            $idlottery = intval($this->get('id_lottery'));
            $str_where = $idlottery > 0 ? "AND A.`id` != $idlottery" : "";
            $now = date('Y-m-d H:i:s');
            //get store already
            $sql = "SELECT B.`id_merchant_store` FROM `lottery` A, `lottery_store` B WHERE A.`id` = B.`id_lottery` $str_where AND A.`end_date` >= '$now' AND A.`deleted` = 0";
            $_stores = $this->db->query($sql)->result_array();
            $where = "";
            $bind = array();
            if ($_stores) {
                $where = "AND B.`id` NOT IN ?";
                $bind = array(array_unique(array_column($_stores, 'id_merchant_store')));
            }
            $sql = "SELECT
				B.`id` AS `id_store`,
				B.`name`, RS.`label` AS `state`, RC.`label` AS `city`, IFNULL(B.`address`,'') AS `address`,
				M.`company`
					FROM `merchant_store` B, `merchant` M, `ref_state` RS, `ref_city` RC
				WHERE
					B.`id_merchant` = M.`id` AND
					B.`state` = RS.`value` AND
					B.`city` = RC.`id` $where
					AND B.`deleted`=0 AND M.`deleted`=0 ORDER BY B.`id_merchant`, B.`id`";
            $data = $this->db->query($sql, $bind)->result_array();
        }
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);

    }

    public function index_post()
    {
        $cols = array(
            'name',
            'desc',
            'start_date',
            'end_date',
            'stores',
        );

        foreach ($cols as $col) {
            if ($col == 'stores') {
                $$col = $this->post($col);
            } else {
                $$col = trim($this->post($col));
            }
        }

        if (!is_array($stores)) {
            $this->response("Stores can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        }

        $start_date = date("Y-m-d H:i:s", strtotime($start_date));
        $end_date = date("Y-m-d H:i:s", strtotime($end_date));

        $this->db->trans_begin();
        try {
            $lottery = array();
            foreach (array(
                'name',
                'desc',
                'start_date',
                'end_date',
            ) as $col) {
                $lottery[$col] = $$col;
            }
            $lottery["createdBy"] = $this->userID;
            $this->db->insert('lottery', $lottery);
            $lastID = $this->db->insert_id();

            $lottery_stores = array();
            foreach ($stores as $s) {
                $lottery_stores[] = array(
                    'id_lottery' => $lastID,
                    'id_merchant_store' => intval(trim($s['id_store'])),
                );
            }
            if ($lottery_stores) {
                $this->db->insert_batch('lottery_store', $lottery_stores);
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($lastID, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_put($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response('Invalid Lottery', REST_Controller::HTTP_BAD_REQUEST);
        }

        $sql = "SELECT `id` FROM `lottery` WHERE `id` = ?";
        $ori = $this->db->query($sql, array($id))->row_array();
        if (!$ori) {
            $this->response('Invalid Lottery', REST_Controller::HTTP_BAD_REQUEST);
        }

        $cols = array(
            'name',
            'desc',
            'start_date',
            'end_date',
            'stores',
        );

        foreach ($cols as $col) {
            if ($col == 'stores') {
                $$col = $this->put($col);
            } else {
                $$col = trim($this->put($col));
            }
        }

        if (!is_array($stores)) {
            $this->response("Stores can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $storeIDS = array_map('intval', array_column($stores, 'id_store'));
            if ($storeIDS) {
                $now = date('Y-m-d H:i:s');
                $sql = "SELECT B.`id_merchant_store` FROM `lottery` A, `lottery_store` B WHERE A.`id` = B.`id_lottery` AND A.`id` != ? AND A.`end_date` >= '$now' AND A.`deleted` = 0 AND B.`id_merchant_store` IN ? LIMIT 1";
                $_stores = $this->db->query($sql, array($$id, $storeIDS))->row_array();
                if ($_stores) {
                    $this->response("Some Stores already registerd in other lottery", REST_Controller::HTTP_BAD_REQUEST);
                }
            }
        }

        $start_date = date("Y-m-d H:i:s", strtotime($start_date));
        $end_date = date("Y-m-d H:i:s", strtotime($end_date));

        $this->db->trans_begin();
        try {
            $lottery = array();
            foreach (array(
                'name',
                'desc',
                'start_date',
                'end_date',
            ) as $col) {
                $lottery[$col] = $$col;
            }
            $lottery["updatedBy"] = $this->userID;
            $this->db->update('lottery', $lottery, array('id' => $id));

            $sql = "DELETE FROM `lottery_store` WHERE `id_lottery` = ?";
            $this->db->query($sql, array($id));

            $lottery_stores = array();
            foreach ($stores as $s) {
                $lottery_stores[] = array(
                    'id_lottery' => $id,
                    'id_merchant_store' => trim(intval($s['id_store'])),
                );
            }
            if ($lottery_stores) {
                $this->db->insert_batch('lottery_store', $lottery_stores);
            }
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($lastID, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
