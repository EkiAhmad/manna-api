<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Orders extends Onlystore
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Upload_model');

    }

    public function index_get($id = 0)
    {
        $productPath = base_url() . $this->Upload_model->path['product'];
        $filters = array('start_date', 'end_date');
        foreach ($filters as $var) {
            $$var = $this->get($var);
        }
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));

        $data = array();

        if ($id) {
            if (is_numeric($id)) {
                $id = intval($id);
            } else {
                $id = trim($id);
                $sql = "SELECT `id` FROM `sales` WHERE `id_merchant_store` = ? AND `code` = ?";
                $sales = $this->db->query($sql, array($this->storeID, $id))->row_array();
                if (!$sales) {
                    $this->response(array("message" => "Transaction ID not found", "transaction_id" => $id), REST_Controller::HTTP_BAD_REQUEST);
                }
                $id = intval($sales['id']);
            }
        }

        if ($id < 1) {
            $sql = "SELECT A.`id`, A.`code`, A.`date`, A.`time`, A.`id_merchant_store`, A.`id_member`, A.`total_price_product`, A.`total_price_sales`, A.`total_quantity`, A.`total_ppn`, A.`service_charge`, IFNULL(A.`notes`,'') AS `notes`,
            IFNULL(A.`type`,'') AS `type`,
            (CASE
                WHEN A.`type` = 'dine_in' THEN 'Dine In'
                WHEN A.`type` = 'take_away' THEN 'Take Away'
                ELSE ''
            END) AS `type_view`,
            IFNULL(A.`seat`,'') AS `seat`,
            UPPER(A.`metode`) AS `metode`, A.`status`, B.`phone`, B.`fullname`, B.`email`
            FROM `sales` A
                JOIN `member` B ON A.`id_member` = B.`id`
            where A.`date` BETWEEN ? AND ? AND A.`id_merchant_store` = ? AND A.`deleted` = 0 ORDER BY A.`id` DESC";
            $data = $this->db->query($sql, array($start_date, $end_date, $this->storeID))->result_array();
            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $sql = "SELECT A.`id`, A.`code`, A.`date`, A.`time`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`, A.`id_merchant_store`, A.`total_price_product`, A.`total_price_sales`, A.`total_quantity`, A.`total_price_varian`, A.`total_ppn`, A.`service_charge`, UPPER(A.`metode`) AS `metode`,
            IFNULL(A.`type`,'') AS `type`,
            (CASE
                WHEN A.`type` = 'dine_in' THEN 'Dine In'
                WHEN A.`type` = 'take_away' THEN 'Take Away'
                ELSE ''
            END) AS `type_view`,
            IFNULL(A.`seat`,'') AS `seat`,
            IFNULL(A.`notes`,'') AS `notes`, A.`status`, A.`id_member`, B.`phone`, UPPER(B.`fullname`) AS `fullname`, IFNULL(B.`email`,'') AS `email`
            FROM `sales` A
                JOIN `member` B ON A.`id_member` = B.`id`
            WHERE A.`id_merchant_store` = ? AND A.`id` = ?  AND A.`deleted` = 0";
            $data = $this->db->query($sql, array($this->storeID, $id))->row_array();
            if ($data) {

                $sql = "SELECT A.`id`, A.`id_sales`, A.`id_product`, A.`price_product`,A.`total_price_varian_sales`, A.`price_sales`, A.`quantity`, B.`sku`, C.`name` as `nama_kategori`, B.`name` as `nama_produk`, B.`is_non_ppn`,IF(IFNULL(B.`image`,'') != '', CONCAT('" . $productPath . "','',B.`image`), '') AS `image`,
				 IFNULL(D.`id`,'') as `id_sales_varian`,
				 IFNULL(D.`name_varian`,'') AS `name_varian`,
				 IFNULL(D.`item_name`,'') AS `item_name`,
				 IFNULL(D.`item_price_modal`,'') AS `item_price_modal`,
				 IFNULL(D.`item_price`,'') AS `item_price`,
                 A.`is_promo` AS `promo`
                     FROM `sales_product` A
                     JOIN `product` B ON A.`id_product` = B.`id`
                     JOIN `category_product` C ON B.`id_category_product` = C.`id`
					 LEFT JOIN `sales_product_varian` D ON A.`id` = D.`id_sales_product`
                     WHERE A.`id_sales` = ? ORDER BY A.`id_product`";
                $query = $this->db->query($sql, array($id));

                $sales_product = array();
                while ($row = $query->unbuffered_row('array')) {
                    if (!isset($sales_product[$row['id']])) {
                        $sales_product[$row['id']] = array(
                            'id' => $row['id'],
                            'id_sales' => $row['id_sales'],
                            'id_product' => $row['id_product'],
                            'price_product' => $row['price_product'],
                            'price_sales' => $row['price_sales'],
                            'total_price_varian_sales' => $row['total_price_varian_sales'],
                            'quantity' => $row['quantity'],
                            'sku' => $row['sku'],
                            'nama_kategori' => $row['nama_kategori'],
                            'nama_produk' => $row['nama_produk'],
                            'ppn' => (($row['is_non_ppn'] == 0) ? $row['price_sales']*10/100 : 0),
                            'image' => $row['image'],
                            'total_price' => ($row['price_sales'] * $row['quantity']) + $row['total_price_varian_sales'],
                            'varians' => array(),
                            'promo' => $row['promo'],
                        );
                    }
                    if ($row['id_sales_varian']) {
                        $sales_product[$row['id']]['varians'][] = array(
                            'id' => $row['id_sales_varian'],
                            'name_varian' => $row['name_varian'],
                            'item_name' => $row['item_name'],
                            'item_price_modal' => $row['item_price_modal'],
                            'item_price' => $row['item_price'],
                        );
                    }
                }
                $data['sales_product'] = array_values($sales_product);

                $data['product_varian'] = array();
                $sqls = "SELECT A.`id` as `id`, C.`name_varian`, C.`item_name`, C.`item_price_modal`, C.`item_price`
                     FROM `sales_product` A
                     JOIN `sales_product_varian` C ON A.`id` = C.`id_sales_product`
                     WHERE A.`id_sales` = ? ORDER BY A.`id`";
                $querys = $this->db->query($sqls, array($id));
                while ($row = $querys->unbuffered_row('array')) {
                    $data['product_varian'][] = array(
                        'id' => $row['id'],
                        'name_varian' => $row['name_varian'],
                        'item_name' => $row['item_name'],
                        'item_price_modal' => $row['item_price_modal'],
                        'item_price' => $row['item_price'],
                    );
                }
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $this->response("Gagal memproses data", REST_Controller::HTTP_BAD_REQUEST);
            }
        }
    }

    public function index_post($id = 0)
    {
        if ($id) {
            $id = intval($id);
            if ($id < 1) {
                $this->response(array('message' => 'Pesanan tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        if (!$this->post('status')) {
            $this->response(array('message' => "Status Pesanan harus diisi"), REST_Controller::HTTP_BAD_REQUEST);
        }
        $this->db->trans_begin();
        try {
            $this->db->where('id', $id);

            $this->db->update('sales', [
                'status' => $this->post('status'),
            ]);
            // $this->db->update('sales', $stores, array('id' => $id));

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response(array('message' => 'Database error'), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response(array('message' => 'Sukses'), REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
