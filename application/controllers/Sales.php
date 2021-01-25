<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Sales extends Onlyus {

	public function __construct()
  {
		parent::__construct();
	}

	public function index_get() {
		$sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
			A.`total_price_sales`, A.`total_quantity`, UPPER(A.`metode`) AS `metode`,
			B.`name` AS `store`
		FROM `sales` A, `merchant_store` B
			WHERE A.`id_merchant_store` = B.`id` AND A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`id` DESC";
		$data = $this->db->query($sql)->result_array();

		$this->response(array('sales'=>$data), REST_Controller::HTTP_OK);
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
