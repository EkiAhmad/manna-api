<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlystore.php';

class Promo extends Onlystore
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Upload_model');
        $this->load->model('Promo_model');
    }

    public function categories_get()
    {
        $data = array();
        if ($this->merchantProductType == "2") {
            $sql = "SELECT `id` AS `value`, `name` AS `label` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `id_parent` IS NOT NULL ORDER BY 2";
            $data = $this->db->query($sql, array($this->merchantID, $this->storeID))->result_array();
        }
        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function product_get()
    {
        $data = array();
        if ($this->merchantProductType == "2") {
            $sql = "SELECT `id` AS `value`, `name` AS `label` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `deleted` = '0' ORDER BY 2";
            $data = $this->db->query($sql, array($this->merchantID, $this->storeID))->result_array();
        }
        $this->response(array('product' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $data = array();
        $promoPath = base_url() . $this->Upload_model->path['product'];
        $id = intval($id);
        if ($id < 1) {
            //perlu filter deleted merchant
            $sql = "";
            $data = array();
            if ($this->merchantProductType == "1") {
                $sql = "SELECT A.`id`, A.`sku`, E.`name`, B.`name` AS `category`,
				IFNULL(C.`company`,'') AS `merchant`, '' AS `store`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`, A.`id_product`
				FROM `promo` A
					JOIN `category_product` B ON A.`id_category_product` = B.`id`
					JOIN `merchant` C ON A.`id_merchant` = C.`id`
                    JOIN `product` E ON A.`id_product` = E.`id`
				WHERE A.`id_merchant` = ? AND A.`id_merchant_store` IS NULL AND A.`deleted` = 0 ORDER BY A.`id` DESC";
                $data = $this->db->query($sql, array($this->merchantID))->result_array();
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT A.`id`, A.`sku`, E.`name`, B.`name` AS `category`,
				IFNULL(C.`company`,'') AS `merchant`, IFNULL(D.`name`,'') AS `store`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`,
				A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`, A.`id_product`
				FROM `promo` A
					JOIN `category_product` B ON A.`id_category_product` = B.`id`
					JOIN `merchant` C ON A.`id_merchant` = C.`id`
					JOIN `merchant_store` D ON A.`id_merchant_store` = D.`id`
                    JOIN `product` E ON A.`id_product` = E.`id`
				WHERE A.`id_merchant` = ?  AND A.`id_merchant_store` = ? AND A.`deleted` = 0 ORDER BY A.`id` DESC";
                $data = $this->db->query($sql, array($this->merchantID, $this->storeID))->result_array();
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT A.`id`, A.`sku`, E.`name`, B.`name` AS `category`,
				IFNULL(C.`company`,'') AS `merchant`, IFNULL(D.`name`,'') AS `store`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`, A.`id_product`
				FROM `promo` A
					JOIN `category_product` B ON A.`id_category_product` = B.`id`
					LEFT JOIN `merchant` C ON A.`id_merchant` = C.`id`
					LEFT JOIN `merchant_store` D ON A.`id_merchant_store` = D.`id`
                    JOIN `product` E ON A.`id_product` = E.`id`
				WHERE A.`id_merchant` = ? AND A.`deleted` = 0 ORDER BY A.`id` DESC";
                $data = $this->db->query($sql, array($this->merchantID))->result_array();
            } else {
                $this->response("Gagal memproses data", REST_Controller::HTTP_BAD_REQUEST);
            }
            $this->response(array('promo' => $data), REST_Controller::HTTP_OK);
        } else {
            $sql = "";
            $data = array();
            if ($this->merchantProductType == "1") {
                $sql = "SELECT A.`id_merchant`, A.`id_merchant_store`, A.`id_category_product`, B.`sku`, B.`name`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`,
				A.`price`, B.`piece`, B.`merk`, B.`attribute`, A.`is_non_ppn`, A.`is_show_in_menu`, A.`status`, IFNULL(A.`desc`, '') AS `desc`, A.`id_product`
				FROM `promo` A JOIN `product` B ON A.`id_product` = B.`id` WHERE A.`id` = ? AND A.`id_merchant` = ? AND A.`id_merchant_store` IS NULL AND A.`deleted` = 0";
                $data = (array) $this->db->query($sql, array($id, $this->merchantID))->row();
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT A.`id_merchant`, A.`id_merchant_store`, A.`id_category_product`, B.`sku`, B.`name`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`,
				A.`price`, B.`piece`, B.`merk`, B.`attribute`, A.`is_non_ppn`, A.`is_show_in_menu`, A.`status`, IFNULL(A.`desc`, '') AS `desc`, A.`id_product`
				FROM `promo` A JOIN `product` B ON A.`id_product` = B.`id` WHERE A.`id` = ? AND A.`id_merchant` = ? AND A.`id_merchant_store` = ? AND A.`deleted` = 0";
                $data = (array) $this->db->query($sql, array($id, $this->merchantID, $this->storeID))->row();
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT A.`id_merchant`, A.`id_merchant_store`, A.`id_category_product`, B.`sku`, B.`name`,
				IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`,
				A.`price`, B.`piece`, B.`merk`, B.`attribute`, A.`is_non_ppn`, A.`is_show_in_menu`, A.`status`, IFNULL(A.`desc`, '') AS `desc`, A.`id_product`
				FROM `promo` A JOIN `product` B ON A.`id_product` = B.`id` WHERE A.`id` = ? AND A.`id_merchant` = ? AND A.`deleted` = 0";
                $data = (array) $this->db->query($sql, array($id, $this->merchantID))->row();
            } else {
                $this->response("Gagal memproses data", REST_Controller::HTTP_BAD_REQUEST);
            }
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
                $this->response('Produk tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
    }

    public function index_post($id = 0)
    {
        if ($this->merchantProductType != "2") {
            $this->response(array('message' => 'Not Allowed'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $ori = $product_ori = null;
        $idsVarian = $varians = array();

        if ($id) {
            $id = intval($id);
            if ($id < 1) {
                $this->response(array('message' => 'Promo tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            }

            $sql = "";
            if ($this->merchantProductType == "1") {
                $this->response(array('message' => 'Produk tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT A.`id`, IFNULL(A.`image`,'') AS `image`, IFNULL(A.`id_merchant`,'') AS `id_merchant`, IFNULL(A.`id_merchant_store`,'') AS `id_merchant_store`, A.`sku` FROM `promo` A JOIN `product` B ON A.`id_product` = B.`id` WHERE A.`id` = ? AND A.`id_merchant` = ? AND A.`id_merchant_store` = ? AND A.`deleted` = 0 AND B.`deleted` = 0 LIMIT 1";
                $ori = $this->db->query($sql, array($id, $this->merchantID, $this->storeID))->row();
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT A.`id`, IFNULL(A.`image`,'') AS `image`, IFNULL(A.`id_merchant`,'') AS `id_merchant`, IFNULL(A.`id_merchant_store`,'') AS `id_merchant_store`, A.`sku` FROM `promo` A JOIN `product` B ON A.`id_product` = B.`id` WHERE A.`id` = ?  AND A.`id_merchant` = ? AND A.`deleted` = 0 AND B.`deleted` = 0 LIMIT 1";
                $ori = $this->db->query($sql, array($id, $this->merchantID))->row();
            } else {
                $this->response(array('message' => 'Produk tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            }
            if (!$ori) {
                $this->response(array('message' => 'Produk tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $id = intval($ori->id);
                $sku = $ori->sku;
            }
        }else {
            $sku = $this->Promo_model->getRandomSKU($this->merchantProductType);
        }
        // $this->response(array('message' => $sku), REST_Controller::HTTP_BAD_REQUEST);

        $id_merchant = $this->merchantID;
        $id_merchant_store = $this->storeID;

        $cols = array(
            'id_product',
            'price',
            'is_non_ppn',
            'desc',
        );

        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            if (!$data) { // workaround for android
                foreach ($cols as $col) {
                    $$col = trim($this->post($col));
                }
            } else {
                foreach ($cols as $col) {
                    $$col = isset($data[$col]) ? trim($data[$col]) : '';
                }
            }
        } else {
            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
        }

        $colsInt = array(
            'id_product',
            'price',
            'is_non_ppn',
        );
        foreach ($colsInt as $col) {
            $$col = intval($$col);
        }

        if ($id_product) {
            $sql = "SELECT `id`,`id_category_product`,`name`,`desc`,`price`,`piece`,`merk`,`attribute`,`price_modal`,
            `is_modal_non_ppn`,`is_show_in_menu`,`status`
                 FROM `product` 
                 WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` = ?";
            $product_ori = $this->db->query($sql, array($id_product, $this->merchantID, $this->storeID))->row();
        }

        if (!$product_ori) {
            $this->response(array('message' => 'Produk tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($id_product) {
            $sql = "SELECT `id`
                 FROM `promo` 
                 WHERE `id_product` = ? AND `id_merchant` = ? AND `id_merchant_store` = ? AND `deleted` = 0";
            $has_product = $this->db->query($sql, array($id_product, $this->merchantID, $this->storeID))->row();
        }

        if ($has_product) {
            $this->response(array('message' => 'Promo sudah ada'), REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($price < 0) {
            $price = 0;
        } 

        $this->db->trans_begin();
        try {
            $promo = array();
            foreach ($cols as $col) {
                $promo[$col] = $$col;
            }
            $promo['id_merchant'] = $id_merchant;
            $promo['id_merchant_store'] = $id_merchant_store;
            $promo['sku'] = $sku;
            $promo['piece'] = $product_ori->name;
            $promo['merk'] = $product_ori->merk;
            $promo['attribute'] = $product_ori->attribute;
            $promo['price_modal'] = $product_ori->price_modal;
            $promo['is_modal_non_ppn'] = $product_ori->is_modal_non_ppn;
            $promo['is_show_in_menu'] = $product_ori->is_show_in_menu;
            $promo['id_category_product'] = $product_ori->id_category_product;
            if ($id) {
                $promo["updatedBy"] = $this->userID;
                $this->db->update('promo', $promo, array('id' => $id));
            } else {
                $promo["createdBy"] = $this->userID;
                $this->db->insert('promo', $promo);
                $id = $this->db->insert_id();
            }

            if ($_FILES) {
                $name_promo = 'Promo-'.$sku;
                $upload = $this->Upload_model->product($name_promo);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response(array('message' => $errMsg), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('promo', array('image' => $upload['result']['file_name']), array('id' => $id));
                    if ($ori && $ori->image) {
                        @unlink(FCPATH . $this->Upload_model->path['product'] . $ori->image);
                    }
                }
            }


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

    public function import_post()
    {
        if ($this->merchantProductType != "2") {
            $this->response(array('message' => 'Not Allowed'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $datas = $this->post('datas');
        if (!is_array($datas)) {
            $datas = array();
        }
        if (!$datas) {
            $this->response(array('message' => 'Data Excel kosong'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if (count($datas) > 501) {
            $this->response(array('message' => 'Maksimal data hanya 500 produk'), REST_Controller::HTTP_BAD_REQUEST);
        }
        setlocale(LC_ALL, 'id_ID');

        $errMsg = $line = '';
        $skus = $categories = array();
        $category_others = 0;

        $total = array(
            'promo' => 0,
            'new_product' => 0,
            'new_category' => 0,
        );

        $this->db->trans_begin();
        foreach ($datas as $index => $data) {
            try {
                $data = array_map('trim', $data);
                $total['promo']++;

                //validate product name
                if (!$data['name']) {
                    $line = ($data['no'] ? $data['no'] : ($index + 1));
                    $errMsg = "Nama Produk harus diisi";
                    break;
                }
                $data['name'] = iconv('UTF-8', 'ASCII//TRANSLIT', $data['name']);

                //validate name
                $sql = "SELECT `id` FROM `promo` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `name` = ? AND `deleted` = 0 LIMIT 1";
                $checkName = $this->db->query($sql, array($this->merchantID, $this->storeID, $data['name']))->row_array();
                if ($checkName) {
                    $line = ($data['no'] ? $data['no'] : ($index + 1));
                    $errMsg = "Nama Produk sudah terdaftar";
                    break;
                }

                //validate sku
                if (!$data['sku']) {
                    $data['sku'] = $this->Promo_model->getRandomSKU($this->merchantProductType);
                    usleep(100);
                } else {
                    $sql = "SELECT `id` FROM `promo` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `sku` = ? AND `deleted` = 0 LIMIT 1";
                    $checkSKU = $this->db->query($sql, array($this->merchantID, $this->storeID, $data['sku']))->row_array();
                    if ($checkSKU) {
                        $line = ($data['no'] ? $data['no'] : ($index + 1));
                        $errMsg = "SKU sudah terdaftar";
                        break;
                    } else if (isset($skus[$data['sku']])) {
                        $line = ($data['no'] ? $data['no'] : ($index + 1));
                        $errMsg = "SKU sama dengan Produk lain";
                        break;
                    }
                    $skus[$data['sku']] = $data['sku'];
                }

                //validate category
                if (!$data['group']) {
                    if (!$category_others) {
                        $_category = $this->Promo_model->getDefaultProductCategory($this->merchantProductType, $this->merchantID, $this->storeID, $this->userID);
                        if (!$_category) {
                            $line = ($data['no'] ? $data['no'] : ($index + 1));
                            $errMsg = "Gagal buat kategori baru";
                            break;
                        }
                        $data['category'] = $_category;
                        $category_others = $_category;
                    } else {
                        $data['category'] = $category_others;
                    }
                } else {
                    $_group_name = $this->slugifyCategoryName($data['group']);
                    if (!isset($categories[$_group_name])) {
                        $group_id = 0;
                        $sql = "SELECT `id` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `id_parent` IS NULL AND `name` = ? LIMIT 1";
                        $groupCategory = $this->db->query($sql, array($this->merchantID, $this->storeID, $data['group']))->row_array();
                        if ($groupCategory) {
                            $group_id = $groupCategory['id'];
                        } else {
                            $group_id = $this->createGroupCategory($data['group']);
                        }
                        $categories[$_group_name] = array('id' => $group_id, 'category' => array());
                    }

                    $category_name = !$data['category'] ? $data['group'] : $data['category'];
                    $_category_name = $this->slugifyCategoryName($category_name);

                    if (isset($categories[$_group_name]['category'][$_category_name])) {
                        $data['category'] = $categories[$_group_name]['category'][$_category_name];
                    } else {
                        $sql = "SELECT `id` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` = ? AND `id_parent` IS NOT NULL AND `name` = ? LIMIT 1";
                        $category = $this->db->query($sql, array($this->merchantID, $this->storeID, $category_name))->row_array();
                        if ($category) {
                            $data['category'] = $category['id'];
                            $categories[$_group_name]['category'][$_category_name] = $category['id'];
                        } else {
                            $data['category'] = $this->createCategory($categories[$_group_name]['id'], $category_name);
                            $categories[$_group_name]['category'][$_category_name] = $data['category'];
                            $total['new_category']++;
                        }
                    }
                }

                $data['price'] = intval($data['price']);
                $data['price_sales'] = intval($data['price_sales']);
                $data['unit'] = $data['unit'] ? $data['unit'] : 'Pcs';
                $data['category'] = intval($data['category']);

                if (!$data['price_sales'] && $data['price']) {
                    $data['price_sales'] = $data['price'];
                }

                if ($data['sku'] && $data['category'] > 0) {
                    $promo = array(
                        'id_merchant' => $this->merchantID,
                        'id_merchant_store' => $this->storeID,
                        'id_category_product' => $data['category'],
                        'sku' => $data['sku'],
                        'name' => $data['name'],
                        'price' => $data['price_sales'],
                        'piece' => $data['unit'],
                    );
                    $this->db->insert('promo', $promo);
                    $product_id = $this->db->insert_id();

                    if ($data['barcode']) {
                        $barcode = array(
                            'id_product' => $product_id,
                            'barcode' => $data['barcode'],
                        );
                        $this->db->insert('product_barcode', $barcode);
                    }
                    $total['new_product']++;
                }

            } catch (Exception $e) {
                $this->db->trans_rollback();
                $line = ($data['no'] ? $data['no'] : ($index + 1));
                $errMsg = "Gagal memproses data";
                break;
            }
        }
        if ($errMsg) {
            $this->db->trans_rollback();
            $this->response(array('line' => $line, 'message' => $errMsg), REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $this->db->trans_commit();
            $this->response(array('summary' => $total, 'message' => 'Success'), REST_Controller::HTTP_OK);
        }
    }

    // private function createGroupCategory($name)
    // {
    //     $cols = array(
    //         'name' => $name,
    //         'id_merchant' => $this->merchantID,
    //         'id_merchant_store' => $this->storeID,
    //         'createdBy' => $this->userID,
    //     );
    //     $this->db->insert('category_product', $cols);
    //     return $this->db->insert_id();
    // }

    // private function createCategory($parent, $name)
    // {
    //     $cols = array(
    //         'id_parent' => $parent,
    //         'name' => $name,
    //         'id_merchant' => $this->merchantID,
    //         'id_merchant_store' => $this->storeID,
    //         'createdBy' => $this->userID,
    //     );
    //     $this->db->insert('category_product', $cols);
    //     return $this->db->insert_id();
    // }

    private function slugifyCategoryName($str)
    {
        $search = array('Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ', 'ș', 'ț', 'î', 'â', 'ă', 'Î', 'Â', 'Ă', 'ë', 'Ë');
        $replace = array('s', 't', 's', 't', 's', 't', 's', 't', 'i', 'a', 'a', 'i', 'a', 'a', 'e', 'E');
        $str = str_ireplace($search, $replace, strtolower(trim($str)));
        $str = preg_replace('/[^\w\d\-\ ]/', '', $str);
        $str = str_replace(' ', '-', $str);

        return preg_replace('/\-{2,}/', '-', $str);
    }

    public function index_delete($id)
    {
        $id = intval($id);
        $query_check = "SELECT id FROM promo WHERE id = ? AND deleted = '0'";
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
                
                if ($this->db->update('promo', $data, array('id' => $id))) {
                    $this->response('Successfully deleted products promo', REST_Controller::HTTP_OK);
                } else {
                    $this->response('Failed deleted products promo', REST_Controller::HTTP_BAD_REQUEST);
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
