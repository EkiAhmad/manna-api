<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Sales extends Onlystore
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index_get()
    {
        $filters = array('start_date', 'end_date');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        $sql = "SELECT COUNT(`id`) AS `trx`, IFNULL(SUM(`total_price_product`),0) AS `total_modal`,IFNULL(SUM(`total_price_sales`),0) AS `total` FROM `sales` WHERE `date` BETWEEN ? AND ? AND `id_merchant_store` = ? AND `status` = 1";
        $_total = $this->db->query($sql, array($start_date, $end_date, $this->storeID))->row();
        $total = intval($_total->total);
        $total_profit = $total - (intval($_total->total_modal));
        $total_trx = intval($_total->trx);

        $sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
            A.`total_price_product`, A.`total_price_sales`, A.`total_quantity`, UPPER(A.`metode`) AS `metode`
        FROM `sales` A
		WHERE A.`date` BETWEEN ? AND ? AND A.`id_merchant_store` = ? AND A.`status` = 1 AND A.`deleted` = 0 ORDER BY A.`id` DESC";
        $data = $this->db->query($sql, array($start_date, $end_date, $this->storeID))->result_array();

        $this->response(array('sales' => $data, 'summary' => array('trx' => $total_trx, 'omzet' => $total, 'profit' => $total_profit)), REST_Controller::HTTP_OK);
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
