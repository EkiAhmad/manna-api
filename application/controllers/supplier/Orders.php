<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlysupplier.php';

class Orders extends Onlysupplier
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index_get($id = "", $barcode = "")
    {
        $id = intval(trim($id));
        if ($id > 0) {
            $sql = "SELECT A.`id`, A.`id_merchant_store`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`, A.`status`, IFNULL(A.`notes_store`,'') AS `store_notes`, IFNULL(A.`store_received_date`,'') AS `store_received_date`, IFNULL(A.`notes_supplier`,'') AS `supplier_notes`, IFNULL(A.`supplier_sent_date`,'') AS `supplier_sent_date`, '' AS `detail`
			FROM `order` A
			WHERE A.`id` = ? AND A.`id_supplier` = ?";
            $data = $this->db->query($sql, array($id, $this->supplierID))->row_array();
            if ($data) {
                if ($barcode) {
                    $barcode = trim(urldecode($barcode));
                    $barcode = preg_replace('/\s+/', '', $barcode);
                }
                $detail = array();
                $msg = 'Pesanan ditemukan';
                if ($barcode) {
                    $sql = "SELECT A.`id`, B.`sku`, B.`name` AS `product`, A.`price`, A.`quantity`, A.`price_received`, A.`quantity_received` FROM `order_detail` A, `product` B, `product_barcode` C
					WHERE
						A.`id_product` = B.`id` AND
						B.`id` = C.`id_product` AND
						A.`id_order` = ? AND C.`barcode` = ? ORDER BY A.`id`";
                    $detail = $this->db->query($sql, array($data['id'], $barcode))->result_array();
                    if (!$detail) {
                        $msg = "Pesanan Barang dengan barcode: $barcode tidak ditemukan";
                    } else {
                        $msg = "Pesanan Barang dengan barcode: $barcode ditemukan";
                    }
                } else {
                    $sql = "SELECT A.`id`, B.`sku`, B.`name` AS `product`, A.`price`, A.`quantity`, A.`price_received`, A.`quantity_received` FROM `order_detail` A, `product` B WHERE A.`id_product` = B.`id` AND A.`id_order` = ? ORDER BY A.`id`";
                    $detail = $this->db->query($sql, array($data['id']))->result_array();
                }
                $data['detail'] = $detail;

                $sql = "SELECT A.`id`, A.`name`, S.`label` AS `state`, C.`label` AS `city`, IFNULL(A.`address`,'') AS `address`, IFNULL(U.`fullname`,'') AS `pic`, IFNULL(U.`phone`,'') AS `pic_phone`
				FROM `merchant_store` A, `user_store` US, `user` U, `ref_state` S, `ref_city` C
				WHERE A.`id` = US.`id_merchant_store` AND
				US.`id_user` = U.`id` AND
				A.`state` = S.`value` AND
				A.`city` = C.`id` AND A.`id` = ?";
                $store = $this->db->query($sql, array($data['id_merchant_store']))->row_array();
                unset($data['id_merchant_store']);
                if ($store) {
                    $result = array(
                        'data' => $data,
                        'store' => $store,
                    );
                    $this->response(array('order' => $result, 'message' => $msg), REST_Controller::HTTP_OK);
                }
            }
            $this->response(array('message' => 'Pesanan tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $filters = array('start_date', 'end_date');
            foreach ($filters as $var) {
                $$var = $this->get($var);
            }
            if (!$start_date) {
                $start_date = date('Y-m-01');
            }
            if (!$end_date) {
                $end_date = date('Y-m-d');
            }
            $_paramCheckDate = date('2020-01-01 00:00:00');
            $_checkDate = strtotime($_paramCheckDate);

            $_start_date = strtotime($start_date);
            $_end_date = strtotime($end_date);

            if ($_checkDate > $_start_date || $_checkDate > $_end_date) {
                $this->response(array('message' => 'Format filter tanggal salah'), REST_Controller::HTTP_BAD_REQUEST);
            }
            if ($_start_date > $_end_date) {
                $this->response(array('message' => 'Tanggal awal harus lebih kecil dari Tanggal Akhir'), REST_Controller::HTTP_BAD_REQUEST);
            }
            $start_date = date('Y-m-d', strtotime($start_date));
            $end_date = date('Y-m-d', strtotime($end_date));

            $sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
            A.`id_merchant_store` AS `id_store`, B.`name` AS `store`, IFNULL(A.`store_received_date`,'') AS `store_received_date`, A.`status`, IFNULL(A.`supplier_sent_date`,'') AS `supplier_sent_date`
				FROM `order` A, `merchant_store` B
			WHERE A.`id_merchant_store` = B.`id` AND A.`date` BETWEEN ? AND ? AND A.`id_supplier` = ? AND A.`status` = 1 ORDER BY A.`status` ASC, A.`id` DESC";
            $data1 = $this->db->query($sql, array($start_date, $end_date, $this->supplierID))->result_array();

            $sql = "SELECT A.`id`, A.`code` AS `trx`, CONCAT(A.`date`,' ', A.`time`) AS `trx_date`,
            A.`id_merchant_store` AS `id_store`, IFNULL(A.`store_received_date`,'') AS `store_received_date`, B.`name` AS `store`, A.`status`, IFNULL(A.`supplier_sent_date`,'') AS `supplier_sent_date`
				FROM `order` A, `merchant_store` B
			WHERE A.`id_merchant_store` = B.`id` AND A.`date` BETWEEN ? AND ? AND A.`id_supplier` = ? AND A.`status` > 1 ORDER BY A.`updatedAt` DESC";
            $data2 = $this->db->query($sql, array($start_date, $end_date, $this->supplierID))->result_array();

            $data = array_merge($data1, $data2);

            $this->response(array(
                'orders' => $data,
                'orders_count' => strval(count($data)),
                'filter' => array(
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                )), REST_Controller::HTTP_OK);
        }
    }

    public function index_put($id = 0)
    {
        $id = intval($id);
        if ($id < 1) {
            $this->response(array('message' => 'Pesanan tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $sql = "SELECT `id`, `status`, `id_merchant_store` FROM `order` WHERE `id` = ? AND `id_supplier` = ?";
        $ori = $this->db->query($sql, array($id, $this->supplierID))->row_array();
        if (!$ori) {
            $this->response(array('message' => 'Pesanan tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!in_array($ori['id_merchant_store'], $this->supplierStores)) {
            $this->response(array('message' => 'Toko tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($ori['status'] == "3" || $ori['status'] == "4" || $ori['status'] == "5" || $ori['status'] == "6") {
            $this->response(array('message' => 'Pesanan sudah tidak bisa diubah'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $detail_ids = array();
        $sql = "SELECT `id` FROM `order_detail` WHERE `id_order` = ?";
        $_detail = $this->db->query($sql, array($id))->result_array();
        if ($_detail) {
            $detail_ids = array_column($_detail, 'id');
        }

        $cols = array(
            'status',
            'detail',
            'price_update',
            'supplier_sent_date',
            'supplier_notes',
        );
        foreach ($cols as $col) {
            $$col = $this->put($col);
        }
        $supplier_notes = trim($supplier_notes);
        $status = intval($status);
        $price_update = intval($price_update);

        if (!in_array($status, array(2, 3, 4))) {
            $this->response(array('message' => 'Update status salah'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!in_array($status, array(2, 3, 4, 5))) {
            $this->response(array('message' => 'Update status salah'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if (!is_array($detail)) {
            $this->response(array('message' => "Detil Pesanan salah"), REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($status === 4) {
            if (!$supplier_sent_date) {
                $this->response(array('message' => "Tanggal Kirim Pesanan harus diisi"), REST_Controller::HTTP_BAD_REQUEST);
            }
            $_paramCheckDate = date('2020-01-01 00:00:00');
            $_checkDate = strtotime($_paramCheckDate);
            $_sent_date = strtotime($supplier_sent_date);
            if ($_checkDate > $_sent_date) {
                $this->response(array('message' => 'Format Tanggal Kirim salah'), REST_Controller::HTTP_BAD_REQUEST);
            }
            $supplier_sent_date = date('Y-m-d H:i:s', $_sent_date);
        } else {
            $supplier_sent_date = null;
        }
        $supplier_notes = trim($supplier_notes);

        $error = false;
        $detail_update = $product_update = array();
        $total_price = $total_qty = 0;

        foreach ($detail as $o) {
            $ido = isset($o['id']) ? trim($o['id']) : '';
            $sku = isset($o['sku']) ? trim($o['sku']) : '';
            $name = isset($o['product']) ? trim($o['product']) : '';

            if (!in_array($ido, $detail_ids) || !$sku) {
                $error = true;
                break;
            }
            $ido = intval($ido);
            if ($ido < 1) {
                $error = true;
                break;
            }
            $price = isset($o['price_received']) ? intval(trim($o['price_received'])) : 0;
            $qty = isset($o['quantity_received']) ? floatval(trim($o['quantity_received'])) : 0;
            if ($qty < 0) {
                $qty = 0;
            }
            if ($price < 0) {
                $price = 0;
            }
            $detail_update[] = array(
                'id' => $ido,
                'price_received' => $price,
                'quantity_received' => $qty,
            );
            $total_price += ($price * $qty);
            $total_qty += $qty;

            if ($price_update) {
                $product_update[] = array(
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                );
            }
        }
        if ($error) {
            $this->response(array('message' => "Detil Pesanan salah"), REST_Controller::HTTP_BAD_REQUEST);
        }

        $now = date('Y-m-d H:i:s');
        if ($detail_update) {
            $this->db->trans_begin();
            try {
                $this->db->update_batch('order_detail', $detail_update, 'id');

                $order = array(
                    'total_price_received' => $total_price,
                    'total_quantity_received' => $total_qty,
                    'status' => $status,
                    'notes_supplier' => $supplier_notes,
                    'supplier_sent_date' => $supplier_sent_date,
                    'updatedBy_supplier' => $this->supplierID,
                    'updatedAt_supplier' => $now,
                    'updatedAt' => $now,
                );
                $this->db->update('order', $order, array('id' => $id));

                if ($this->db->trans_status() === false) {
                    $this->db->trans_rollback();
                    $this->response(array('message' => 'Database error'), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->trans_commit();
                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                $this->response('Internal error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
            $update_all = $insert_all = array();
            foreach ($product_update as $p) {
                $_sql = "SELECT `id` FROM `supplier_product` WHERE `id_supplier` = ? AND `sku` = ? LIMIT 1";
                $_row = $this->db->query($_sql, array($this->supplierID, $p['sku']))->row_array();
                if ($_row) {
                    $update_all[] = array(
                        'id' => $_row['id'],
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'last_update' => $now,
                    );
                } else {
                    $insert_all[] = array(
                        'id_supplier' => $this->supplierID,
                        'sku' => $p['sku'],
                        'name' => $p['name'],
                        'price' => $p['price'],
                        'last_update' => $now,
                    );
                }
            }
            if ($update_all) {
                $this->db->update_batch('supplier_product', $update_all, 'id');
            }
            if ($insert_all) {
                $this->db->insert_batch('supplier_product', $insert_all);
            }
            $this->response(array('id' => $id, 'message' => "Update sukses"), REST_Controller::HTTP_OK);

        }
    }
}
