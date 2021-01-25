<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class History extends Onlyus {

	public function __construct()
  {
		parent::__construct();
	}

	public function index_get() {
		$filters = array('start_date', 'end_date','type');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
        $start_date = date('Y-m-d', strtotime($start_date));
		$end_date = date('Y-m-d', strtotime($end_date));
		$type = intval($type);
        $filterType = $type > 0 ? " AND B.`type` = $type" : "";

		$sqla = "SELECT COUNT(A.`id`) AS `trx`
        FROM `sales` A
        WHERE (A.`metode` = 'point' OR A.`metode` = 'online') AND A.`date` BETWEEN ? AND ? AND  A.`status` = 1";
        $_total_a = $this->db->query($sqla, array($start_date, $end_date))->row();
        $sqlb = "SELECT IFNULL(SUM(A.`total_point_redeem`),0) AS `total_redeem`
        FROM `loyalty_request_payment` A
        WHERE  A.`deleted` = 0";
        $_total_b = $this->db->query($sqlb)->row();
        $sqlc = "SELECT SUM(IFNULL(A.`total_point`, 0)) AS `total_point`
        FROM `merchant_store` A
        WHERE A.`deleted` = 0";
		$_total_c = $this->db->query($sqlc)->row();
		
		$sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
            A.`total_price_product`, A.`total_price_sales`, A.`total_quantity`, UPPER(A.`metode`) AS `metode`,B.`amount` AS `total_point`,
			C.`name` AS `store`,B.`type`
        FROM `sales` A
		JOIN `merchant_store` C ON A.`id_merchant_store` = C.`id`
		LEFT JOIN  `member_point_history` B ON A.`id` = B.`id_sales`
		WHERE A.`date` BETWEEN ? AND ?  $filterType AND A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`id` DESC";
        $data = $this->db->query($sql, array($start_date, $end_date))->result_array();

		$this->response(array('sales'=>$data, 'summary' => array('trx' => $_total_a->trx, 'redeem' => $_total_b->total_redeem, 'point' => $_total_c->total_point)), REST_Controller::HTTP_OK);
	}

	public function products_get() {
		// kurang handle lain-lain, taek
		$sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
			IFNULL(C.`name`,'Lain-lain') AS `product`, IFNULL(C.`sku`,'-') AS `product_sku`, IFNULL(D.`name`,'Lain-lain') AS `category`,
			B.`price_sales`, B.`quantity`,
			M.`name` AS `store`
		FROM `sales` A
			JOIN `merchant_store` M ON A.`id_merchant_store` = M.`id`
			JOIN `sales_product` B ON A.`id` = B.`id_sales`
			LEFT JOIN `product` C ON B.`id_product` = C.`id`
			LEFT JOIN `category_product` D ON C.`id_category_product` = D.`id`
		WHERE
			A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`code` DESC";
		$data = $this->db->query($sql)->result_array();

		$this->response(array('sales_product'=>$data), REST_Controller::HTTP_OK);
	}
}
