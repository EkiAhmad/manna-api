<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Products extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Upload_model');
        $this->load->model('Products_model');
    }

    public function merchants_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`company` AS `label` FROM `merchant` A WHERE A.`product_type` = 1 AND A.`deleted`=0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('merchants' => $data), REST_Controller::HTTP_OK);
    }

    public function stores_get()
    {
        $sql = "SELECT A.`id` as `value`, A.`name` AS `label` FROM `merchant_store` A, `merchant` B WHERE A.`id_merchant` = B.`id` AND B.`product_type` = 2 AND A.`app_mmenu` = 1 AND A.`deleted`=0  AND B.`deleted` = 0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $data = array();
        $productPath = base_url() . $this->Upload_model->path['product'];
        $id = intval($id);
        if ($id < 1) {
            //perlu filter deleted merchant
            $sql = "SELECT A.`id`, A.`sku`, A.`name`, B.`name` AS `category`,
			C.`company` AS `merchant`, C.`product_type` AS `product_type`,
			IFNULL(D.`name`,'') AS `store`, IFNULL(D.`app_mmenu`,'0') AS `app_mmenu`,
			IF(IFNULL(A.`image`,'') != '', CONCAT('" . $productPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`
			FROM `product` A
				JOIN `category_product` B ON A.`id_category_product` = B.`id`
				JOIN `merchant` C ON A.`id_merchant` = C.`id`
				LEFT JOIN `merchant_store` D ON A.`id_merchant_store` = D.`id`
			WHERE A.`deleted` = 0 ORDER BY A.`id` DESC";
            $query = $this->db->query($sql);
            while ($row = $query->unbuffered_row('array')) {
                if (($row['product_type'] == '2' && $row['app_mmenu'] == '1') || $row['product_type'] == '1') {
                    $data[] = array(
                        'id' => $row['id'],
                        'sku' => $row['sku'],
                        'name' => $row['name'],
                        'category' => $row['category'],
                        'merchant' => $row['merchant'],
                        'store' => $row['store'],
                        'image' => $row['image'],
                        'price' => $row['price'],
                        'is_non_ppn' => $row['is_non_ppn'],
                    );
                }
            }

            $this->response(array('products' => $data), REST_Controller::HTTP_OK);
        } else {
            $sql = "SELECT `id_merchant`, IFNULL(`id_merchant_store`,'') AS `id_merchant_store`, `id_category_product`, `sku`, `name`,
			IF(IFNULL(`image`,'') != '', CONCAT('" . $productPath . "','',`image`), '') AS `image`,
			`price`, `piece`, `merk`, `attribute`, `is_non_ppn`, `price_modal`, `is_modal_non_ppn`,
			`is_show_in_menu`, `status`
			FROM `product` WHERE `id` = ? AND `deleted` = 0";
            $data = $this->db->query($sql, array($id))->row_array();
            if ($data) {
                $data['varians'] = array();
                $sql = "SELECT `id`, `name`, `type`, `order`, `is_required`, `options`, `status` FROM `product_varian` WHERE `id_product` = ? AND `deleted` = 0 ORDER BY `order`";
                $query = $this->db->query($sql, array($id));
                while ($row = $query->unbuffered_row('array')) {
                    $data['varians'][] = array(
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'order' => $row['order'],
                        'is_required' => $row['is_required'],
                        'options' => json_decode($row['options'], true),
                        'status' => $row['status'],
                    );
                }
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $this->response('Product not found', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
    }

    public function index_post($id = 0)
    {
        $ori = null;
        $idsVarian = $varians = array();

        if ($id) {
            $id = intval($id);
            if ($id < 1) {
                $this->response('Invalid Product', REST_Controller::HTTP_BAD_REQUEST);
            }

            $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`id_merchant`,'') AS `id_merchant`, IFNULL(`id_merchant_store`,'') AS `id_merchant_store`, `sku` FROM `product` WHERE `id` = ? AND `deleted` = 0 LIMIT 1";
            $ori = $this->db->query($sql, array($id))->row();
            if (!$ori) {
                $this->response('Product not found', REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $id = intval($ori->id);
            }

            //get current varian list
            $sql = "SELECT `id` FROM `product_varian` WHERE `id_product` = ? AND `deleted` = 0";
            $oriVarian = $this->db->query($sql, array($id))->result_array();
            if ($oriVarian) {
                $idsVarian = array_map('intval', array_column($oriVarian, 'id'));
            }
        }

        $cols = array(
            'id_merchant',
            'id_merchant_store',
            'id_category_product',
            'sku',
            'name',
            'price',
            'price_modal',
            'is_modal_non_ppn',
            'is_non_ppn',
            'piece',
            'is_show_in_menu',
            'status',
        );

        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            if (!$data) {
                $this->response("Please fill form", REST_Controller::HTTP_BAD_REQUEST);
            }
            foreach ($cols as $col) {
                $$col = isset($data[$col]) ? trim($data[$col]) : '';
            }
            $varians = isset($data['varians']) ? $data['varians'] : array();
        } else {
            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
            $varians = $this->post('varians');
        }

        if (!is_array($varians)) {
            $varians = array();
        }

        $colsInt = array(
            'id_merchant',
            'id_merchant_store',
            'id_category_product',
            'price',
            'price_modal',
            'is_modal_non_ppn',
            'is_non_ppn',
            'is_show_in_menu',
            'status',
        );
        foreach ($colsInt as $col) {
            $$col = intval($$col);
        }
        if ($id_merchant < 1 && $id_merchant_store < 1) {
            $this->response("Please select Product source", REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($price < 0) {
            $price = 0;
        }
        if ($price_modal < 0) {
            $price_modal = 0;
        }

        if ($id_merchant_store) {
            $sql = "SELECT A.`id_merchant` FROM `user` A, `user_store` B WHERE A.`id` = B.`id_user` AND A.`id_merchant` IS NOT NULL AND B.`id_merchant_store` = ? LIMIT 1";
            $exist = $this->db->query($sql, array($id_merchant_store))->row();
            if ($exist && $exist->id_merchant) {
                $id_merchant = $exist->id_merchant;
            } else {
                $this->response('Invalid Store', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $merchantProductType = 0;
        if ($id_category_product < 1) {
            $sql = "SELECT `product_type` FROM `merchant` WHERE `id` = ?";
            $_merchant = $this->db->query($sql, array($id_merchant))->row();
            if ($_merchant) {
                $merchantProductType = $_merchant->product_type;
                $_category = $this->Products_model->getDefaultProductCategory($_merchant->product_type, $id_merchant, $id_merchant_store, $this->userID);
                if (!$_category) {
                    $this->response('Failed creating category', REST_Controller::HTTP_BAD_REQUEST);
                }
                $id_category_product = $_category;
            } else {
                $this->response('Invalid Merchant', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $sku = preg_replace('/\s+/', '', $sku);
        if (!$sku) {
            $sku = $this->Products_model->getRandomSKU($merchantProductType);
        }

        if ($ori) {
            if ($id_merchant != $ori->id_merchant || $sku != $ori->sku) {
                $sql = "SELECT `id` FROM `product` WHERE `id_merchant` = ? AND `sku` = ?";
                $exist = $this->db->query($sql, array($id_merchant, $sku))->row();
                if ($exist && $exist->id) {
                    $this->response('SKU already registered', REST_Controller::HTTP_BAD_REQUEST);
                }
            }
        } else {
            $sql = "SELECT `id` FROM `product` WHERE `id_merchant` = ? AND `sku` = ?";
            $exist = $this->db->query($sql, array($id_merchant, $sku))->row();
            if ($exist && $exist->id) {
                $this->response('SKU already registered', REST_Controller::HTTP_BAD_REQUEST);
            }
        }

        $found = false;
        if ($id_merchant_store) {
            $sql = "SELECT `id` FROM `category_product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` = ?";
            $found = $this->db->query($sql, array($id_category_product, $id_merchant, $id_merchant_store))->row();
        } else {
            $sql = "SELECT `id` FROM `category_product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
            $found = $this->db->query($sql, array($id_category_product, $id_merchant))->row();
        }
        if (!$found) {
            $this->response("Invalid Product Category", REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->db->trans_begin();
        try {
            $product = array();
            foreach ($cols as $col) {
                if ($col == 'id_merchant_store') {
                    if (!$id_merchant_store) {
                        $id_merchant_store = null;
                    }
                }
                $product[$col] = $$col;
            }
            if ($id) {
                $product["updatedBy"] = $this->userID;
                $this->db->update('product', $product, array('id' => $id));
            } else {
                $product["createdBy"] = $this->userID;
                $this->db->insert('product', $product);
                $id = $this->db->insert_id();
            }

            if ($_FILES) {
                $upload = $this->Upload_model->product($sku);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response($errMsg, REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('product', array('image' => $upload['result']['file_name']), array('id' => $id));
                    if ($ori && $ori->image) {
                        @unlink(FCPATH . $this->Upload_model->path['product'] . $ori->image);
                    }
                }
            }

            $new_varian = $upd_varian = $idUpdated = array();
            foreach ($varians as $v) {
                $v_id = isset($v['id']) ? intval(trim($v['id'])) : 0;
                $v_name = isset($v['name']) ? trim($v['name']) : '';
                $v_options = isset($v['options']) ? $v['options'] : array();
                if (!$v_name || !is_array($v_options) || !$v_options) {
                    continue;
                }

                if ($id < 1 || !$idsVarian || $v_id < 1) {
                    $new_varian[] = array(
                        'id_product' => $id,
                        'name' => $v_name,
                        'type' => isset($v['type']) ? trim($v['type']) : 1,
                        'order' => isset($v['order']) ? trim($v['order']) : 0,
                        'status' => isset($v['status']) ? trim($v['status']) : 1,
                        'is_required' => isset($v['is_required']) ? trim($v['is_required']) : 0,
                        'options' => json_encode($v_options),
                    );
                } else {
                    if ($v_id > 0) {
                        if (in_array($v_id, $idsVarian)) {
                            $idUpdated[] = $v_id;
                            $upd_varian[] = array(
                                'id' => $v_id,
                                'id_product' => $id,
                                'name' => $v_name,
                                'type' => isset($v['type']) ? trim($v['type']) : 1,
                                'order' => isset($v['order']) ? trim($v['order']) : 0,
                                'status' => isset($v['status']) ? trim($v['status']) : 1,
                                'is_required' => isset($v['is_required']) ? trim($v['is_required']) : 0,
                                'options' => json_encode($v_options),
                            );
                        }
                    }
                }
            }

            if (!$varians && $idsVarian) {
                $this->db->update('product_varian', array('deleted' => 1), array('id_product' => $id));
            } else {
                if ($idUpdated) {
                    $sql = "UPDATE `product_varian` SET `deleted` = 1 WHERE `id` NOT IN ? AND `id_product` = ?";
                    $this->db->query($sql, array($idUpdated, $id));
                }
                if ($upd_varian) {
                    $this->db->update_batch('product_varian', $upd_varian, 'id');
                }
                if ($new_varian) {
                    $this->db->insert_batch('product_varian', $new_varian);
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

    public function index_delete($id)
    {
        $id = intval($id);
        $query_check = "SELECT id FROM product WHERE id = ? AND deleted = '0'";
        $exist_id = $this->db->query($query_check, array($id))->num_rows();
        if (!$exist_id) {
            $this->response('Your id is not registered yet', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            if ($id>0) {
            $cols = array(
                'updatedAt',
                'updatedBy',
            );

            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
            $data = [];
            try {
                foreach (array(
                    'updatedAt',
                    'updatedBy',
                ) as $col) {
                    $data[$col] = $$col;
                }
                $data["deleted"] = 1;
                $data["updatedAt"] = date('Y-m-d H:i:s');
                $data["updatedBy"] = $this->userID;
                
                if ($this->db->update('product', $data, array('id' => $id))) {
                    $this->response('Successfully deleted products', REST_Controller::HTTP_OK);
                } else {
                    $this->response('Failed deleted products', REST_Controller::HTTP_BAD_REQUEST);
                }

            } catch(Exception $e){
                $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }            
        } else {
            $this->response('Your Id is wrong', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }            
        }        
    }
}
