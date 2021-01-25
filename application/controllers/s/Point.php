<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Point extends Onlystore
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Upload_model');

    }

    public function index_get()
    {
        $filters = array('start_date', 'end_date');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        $sqla = "SELECT COUNT(A.`id`) AS `trx`
        FROM `sales` A
        WHERE A.`metode` = 'point' AND A.`date` BETWEEN ? AND ? AND A.`id_merchant_store` = ? AND A.`status` = 1";
        $_total_a = $this->db->query($sqla, array($start_date, $end_date, $this->storeID))->row();
        $sqlb = "SELECT IFNULL(SUM(A.`total_point_redeem`),0) AS `total_redeem`
        FROM `loyalty_request_payment` A
        WHERE  A.`id_merchant_store` = ? AND A.`deleted` = 0";
        $_total_b = $this->db->query($sqlb, array($this->storeID))->row();
        $sqlc = "SELECT IFNULL(A.`total_point`, 0) AS `total_point`
        FROM `merchant_store` A
        WHERE A.`id` = ? AND A.`deleted` = 0";
        $_total_c = $this->db->query($sqlc, array($this->storeID))->row();

        $sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
            A.`total_price_product`, A.`total_price_sales`, A.`total_quantity`, UPPER(A.`metode`) AS `metode`,B.`amount` AS `total_point`
        FROM `sales` A, `member_point_history` B
		WHERE A.`id` = B.`id_sales` AND B.`type` = 2 AND A.`date` BETWEEN ? AND ? AND A.`id_merchant_store` = ? AND A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`id` DESC";
        $data = $this->db->query($sql, array($start_date, $end_date, $this->storeID))->result_array();

        $this->response(array('sales' => $data, 'summary' => array('trx' => $_total_a->trx, 'redeem' => $_total_b->total_redeem, 'point' => $_total_c->total_point)), REST_Controller::HTTP_OK);
    }

    public function request_get()
    {
        $productPath = base_url() . $this->Upload_model->path['payment'];
        $sqla = "SELECT IFNULL(A.`total_point`, 0) AS `total_point`
        FROM `merchant_store` A
        WHERE A.`id` = ? AND A.`deleted` = 0";
        $_total = $this->db->query($sqla, array($this->storeID))->row();

        $sql = "SELECT A.`id`, A.`id_merchant_store`,A.`total_point_redeem`,
				IF(IFNULL(A.`bukti_redeem`,'') != '', CONCAT('" . $productPath . "','',A.`bukti_redeem`), '') AS `bukti_redeem`,
				A.`status_redeem`, CONCAT(A.`date_request`,' ', A.`time_request`) AS `date_request`,
				CONCAT(A.`date_success`,' ', A.`time_success`) AS `date_success`
			FROM `loyalty_request_payment` A
				WHERE A.`id_merchant_store` = ? AND A.`deleted` = 0 ORDER BY A.`id` DESC";
        $data = $this->db->query($sql, array($this->storeID))->result_array();

        $this->response(array('sales' => $data, 'total_point' => $_total->total_point), REST_Controller::HTTP_OK);
    }

    public function request_post() {
         
        $total_point = intval($this->post('total_point'));	
        $point = intval($this->post('point'));	
        if ($point > $total_point) {
            $this->response('Jumlah point yang ditukar melebihi point yang tersedia', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sqla = "SELECT IFNULL(A.`total_point`, 0) AS `total_point`
        FROM `merchant_store` A
        WHERE A.`id` = ? AND A.`deleted` = 0";
        $_total = $this->db->query($sqla, array($this->storeID))->row();
        $final_sum = $_total->total_point - $point;
		$points = array(
			'id_merchant_store' => $this->storeID,
			'total_point_redeem' => $point,
		);

        $this->db->trans_begin();
        try {      
            $points["date_request"] = date('Y-m-d');
            $points["time_request"] = date('H:i:s');       
            $points["createdBy"] = $this->userID;
            $this->db->insert('loyalty_request_payment', $points);
            $id = $this->db->insert_id();
            $this->db->set('total_point', $final_sum)
                    ->where('id', $this->storeID)
                    ->update('merchant_store');
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

    public function products_get()
    {
        $filters = array('start_date', 'end_date');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        // kurang handle lain-lain, taek
        $sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
			IF(IFNULL(B.`id_sales`,'-1')='-1', 'simple','expert') AS `mode`,
			IFNULL(C.`sku`,'-') AS `product_sku`,
			IFNULL(C.`name`,'Lain-lain') AS `product`,
			IFNULL(D.`name`,'Lain-lain') AS `category`,
			IFNULL(B.`price_product`, A.`total_price_product`) AS `price_product`,
			IFNULL(B.`price_sales`, A.`total_price_sales`) AS `price_sales`,
			IFNULL(B.`quantity`, A.`total_quantity`) AS `quantity`
        FROM `sales` A
            LEFT JOIN `sales_product` B ON A.`id` = B.`id_sales`
            LEFT JOIN `product` C ON B.`id_product` = C.`id`
            LEFT JOIN `category_product` D ON C.`id_category_product` = D.`id`
        WHERE
            A.`date` BETWEEN ? AND ? AND A.`id_merchant_store` = ? AND A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`code` DESC";
        $data = $this->db->query($sql, array($start_date, $end_date, $this->storeID))->result_array();

        $this->response(array('sales_product' => $data), REST_Controller::HTTP_OK);
    }
}
