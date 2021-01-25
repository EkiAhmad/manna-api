<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlysupplier.php';

class Products extends Onlysupplier
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index_get()
    {
        $sql = "SELECT `id_supplier`, `sku`, `name`, `price`, `last_update` FROM `supplier_product` WHERE `id_supplier` = ? ORDER BY `last_update` DESC";
        $products = $this->db->query($sql, array($this->supplierID))->result_array();

        $this->response(array('products' => $products), REST_Controller::HTTP_OK);
    }
}
