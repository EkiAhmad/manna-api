<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlymerchant.php';

class Products extends Onlymerchant
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Upload_model');
        $this->load->model('Products_model');
    }

    public function categories_get()
    {
        $data = array();
        if ($this->merchantProductType == "1") {
            $sql = "SELECT `id` AS `value`, `name` AS `label` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `id_parent` IS NOT NULL ORDER BY 2";
            $data = $this->db->query($sql, array($this->merchantID))->result_array();
        }
        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $data = array();
        $productPath = base_url() . $this->Upload_model->path['product'];
        $id = intval($id);
        if ($id < 1) {
            //perlu filter deleted merchant
            $sql = "";
            if ($this->merchantProductType == "1") {
                $sql = "SELECT A.`id`, A.`sku`, A.`name`, B.`name` AS `category`,
                IFNULL(C.`company`,'') AS `merchant`, '' AS `store`,
                IF(IFNULL(A.`image`,'') != '', CONCAT('" . $productPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`
                FROM `product` A
                    JOIN `category_product` B ON A.`id_category_product` = B.`id`
                    JOIN `merchant` C ON A.`id_merchant` = C.`id`
                WHERE A.`id_merchant` = ? AND A.`id_merchant_store` IS NULL AND A.`deleted` = 0 ORDER BY A.`id` DESC";
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT A.`id`, A.`sku`, A.`name`, B.`name` AS `category`,
                IFNULL(C.`company`,'') AS `merchant`, IFNULL(D.`name`,'') AS `store`,
                IF(IFNULL(A.`image`,'') != '', CONCAT('" . $productPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`
                FROM `product` A
                    JOIN `category_product` B ON A.`id_category_product` = B.`id`
                    JOIN `merchant` C ON A.`id_merchant` = C.`id`
                    JOIN `merchant_store` D ON A.`id_merchant_store` = D.`id`
                WHERE A.`id_merchant` = ?  AND A.`id_merchant_store` IS NOT NULL AND A.`deleted` = 0 ORDER BY A.`id` DESC";
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT A.`id`, A.`sku`, A.`name`, B.`name` AS `category`,
                IFNULL(C.`company`,'') AS `merchant`, IFNULL(D.`name`,'') AS `store`,
                IF(IFNULL(A.`image`,'') != '', CONCAT('" . $productPath . "','',A.`image`), '') AS `image`, A.`price`, IF(A.`is_non_ppn` = 0, 'ppn', 'non ppn') AS `is_non_ppn`
                FROM `product` A
                    JOIN `category_product` B ON A.`id_category_product` = B.`id`
                    LEFT JOIN `merchant` C ON A.`id_merchant` = C.`id`
                    LEFT JOIN `merchant_store` D ON A.`id_merchant_store` = D.`id`
                WHERE A.`id_merchant` = ? AND A.`deleted` = 0 ORDER BY A.`id` DESC";
            } else {
                $this->response("Invalid Merchant Product Status", REST_Controller::HTTP_BAD_REQUEST);
            }

            $data = $this->db->query($sql, array($this->merchantID))->result_array();

            $this->response(array('products' => $data), REST_Controller::HTTP_OK);
        } else {
            $sql = "";
            if ($this->merchantProductType == "1") {
                $sql = "SELECT `id_merchant`, `id_merchant_store`, `id_category_product`, `sku`, `name`,
                IF(IFNULL(`image`,'') != '', CONCAT('" . $productPath . "','',`image`), '') AS `image`,
                `price`, `piece`, `merk`, `attribute`, `is_non_ppn`, `price_modal`, `is_modal_non_ppn`
                FROM `product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NULL AND `deleted` = 0";
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT `id_merchant`, `id_merchant_store`, `id_category_product`, `sku`, `name`,
                IF(IFNULL(`image`,'') != '', CONCAT('" . $productPath . "','',`image`), '') AS `image`,
                `price`, `piece`, `merk`, `attribute`, `is_non_ppn`, `price_modal`, `is_modal_non_ppn`
                FROM `product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NOT NULL AND `deleted` = 0";
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT `id_merchant`, `id_merchant_store`, `id_category_product`, `sku`, `name`,
                IF(IFNULL(`image`,'') != '', CONCAT('" . $productPath . "','',`image`), '') AS `image`,
                `price`, `piece`, `merk`, `attribute`, `is_non_ppn`, `price_modal`, `is_modal_non_ppn`
                FROM `product` WHERE `id` = ? AND `id_merchant` = ? AND `deleted` = 0";
            } else {
                $this->response("Invalid Merchant Product Status", REST_Controller::HTTP_BAD_REQUEST);
            }
            $data = (array) $this->db->query($sql, array($id, $this->merchantID))->row();
            if ($data) {
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $this->response('Product not found', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
    }

    public function index_post($id = 0)
    {
        if ($this->merchantProductType != "1") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }

        $ori = null;
        if ($id) {
            $id = intval($id);
            if ($id < 1) {
                $this->response('Invalid Product', REST_Controller::HTTP_BAD_REQUEST);
            }

            $sql = "";
            if ($this->merchantProductType == "1") {
                $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`id_merchant`,'') AS `id_merchant`, IFNULL(`id_merchant_store`,'') AS `id_merchant_store`, `sku` FROM `product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NULL AND `deleted` = 0 LIMIT 1";
            } else if ($this->merchantProductType == "2") {
                $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`id_merchant`,'') AS `id_merchant`, IFNULL(`id_merchant_store`,'') AS `id_merchant_store`, `sku` FROM `product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NOT NULL AND `deleted` = 0 LIMIT 1";
            } else if ($this->merchantProductType == "3") {
                $sql = "SELECT `id`, IFNULL(`image`,'') AS `image`, IFNULL(`id_merchant`,'') AS `id_merchant`, IFNULL(`id_merchant_store`,'') AS `id_merchant_store`, `sku` FROM `product` WHERE `id` = ?  AND `id_merchant` = ? AND `deleted` = 0 LIMIT 1";
            } else {
                $this->response('Product not found', REST_Controller::HTTP_BAD_REQUEST);
            }
            $ori = $this->db->query($sql, array($id, $this->merchantID))->row();
            if (!$ori) {
                $this->response('Product not found', REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $id = intval($ori->id);
            }
        }

        $cols = array(
            'id_category_product',
            'sku',
            'name',
            'price',
            'price_modal',
            'is_modal_non_ppn',
            'is_non_ppn',
            'piece',
        );

        if ($_FILES) {
            $data = json_decode($this->post('data'), true);
            if (!$data) {
                $this->response("Please fill form", REST_Controller::HTTP_BAD_REQUEST);
            }
            foreach ($cols as $col) {
                $$col = isset($data[$col]) ? trim($data[$col]) : '';
            }
        } else {
            foreach ($cols as $col) {
                $$col = trim($this->post($col));
            }
        }

        $colsInt = array(
            'id_category_product',
            'price',
            'price_modal',
            'is_modal_non_ppn',
            'is_non_ppn',
        );
        foreach ($colsInt as $col) {
            $$col = intval($$col);
        }
        $id_merchant = $this->merchantID;

        // if ($id_merchant < 1 && $id_merchant_store < 1) {
        //     $this->response("Please select Product source", REST_Controller::HTTP_BAD_REQUEST);
        // }
        if ($id_category_product < 1) {
            $this->response("Invalid Product category", REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT `id` FROM `category_product` WHERE `id` = ? AND `id_merchant` = ? AND `id_merchant_store` IS NULL";
        $found = $this->db->query($sql, array($id_category_product, $this->merchantID))->row();
        if (!$found) {
            $this->response("Invalid Product Category", REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($price < 0) {
            $price = 0;
        }
        if ($price_modal < 0) {
            $price_modal = 0;
        }

        $sku = preg_replace('/\s+/', '', $sku);
        if (!$sku) {
            $this->response("SKU can't be empty", REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($this->merchantProductType == "1") {
            $id_merchant_store = null;
        } else if ($this->merchantProductType == "2") {
            if ($id_merchant_store < 1) {
                $this->response("Please select Product source from Store", REST_Controller::HTTP_BAD_REQUEST);
            }
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
            $product['id_merchant'] = $id_merchant;
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

            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->db->trans_commit();
                $this->response($id_product, REST_Controller::HTTP_OK);
            }
        } catch (Exception $e) {
            $this->db->trans_rollback();
            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import_post()
    {
        if ($this->merchantProductType != "1") {
            $this->response(array('message' => 'Not Allowed'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $datas = $this->post('datas');
        if (!is_array($datas)) {
            $datas = array();
        }
        if (!$datas) {
            $this->response(array('message' => 'Excel data is empty'), REST_Controller::HTTP_BAD_REQUEST);
        }
        if (count($datas) > 501) {
            $this->response(array('message' => 'Excel data more than 500 records'), REST_Controller::HTTP_BAD_REQUEST);
        }
        setlocale(LC_ALL, 'id_ID');

        $errMsg = $line = '';
        $skus = $categories = array();
        $category_others = 0;

        $total = array(
            'product' => 0,
            'new_product' => 0,
            'new_category' => 0,
        );

        $this->db->trans_begin();
        foreach ($datas as $index => $data) {
            try {
                $data = array_map('trim', $data);
                $total['product']++;

                //validate product name
                if (!$data['name']) {
                    $line = ($data['no'] ? $data['no'] : ($index + 1));
                    $errMsg = "Product Name can't be empty";
                    break;
                }
                $data['name'] = iconv('UTF-8', 'ASCII//TRANSLIT', $data['name']);

                //validate name
                $sql = "SELECT `id` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `name` = ? AND `deleted` = 0 LIMIT 1";
                $checkName = $this->db->query($sql, array($this->merchantID, $data['name']))->row_array();
                if ($checkName) {
                    $line = ($data['no'] ? $data['no'] : ($index + 1));
                    $errMsg = "Nama Produk sudah terdaftar";
                    break;
                }

                //validate sku
                if (!$data['sku']) {
                    $data['sku'] = $this->Products_model->getRandomSKU($this->merchantProductType);
                    usleep(100);
                } else {
                    $sql = "SELECT `id` FROM `product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `sku` = ? AND `deleted` = 0 LIMIT 1";
                    $checkSKU = $this->db->query($sql, array($this->merchantID, $data['sku']))->row_array();
                    if ($checkSKU) {
                        $line = ($data['no'] ? $data['no'] : ($index + 1));
                        $errMsg = "SKU already exist";
                        break;
                    } else if (isset($skus[$data['sku']])) {
                        $line = ($data['no'] ? $data['no'] : ($index + 1));
                        $errMsg = "Duplicate SKU";
                        break;
                    }
                    $skus[$data['sku']] = $data['sku'];
                }

                //validate category
                if (!$data['group']) {
                    if (!$category_others) {
                        $_category = $this->Products_model->getDefaultProductCategory($this->merchantProductType, $this->merchantID, '', $this->userID);
                        if (!$_category) {
                            $line = ($data['no'] ? $data['no'] : ($index + 1));
                            $errMsg = "Error while creating new category";
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
                        $sql = "SELECT `id` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `id_parent` IS NULL AND `name` = ? LIMIT 1";
                        $groupCategory = $this->db->query($sql, array($this->merchantID, $data['group']))->row_array();
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
                        $sql = "SELECT `id` FROM `category_product` WHERE `id_merchant` = ? AND `id_merchant_store` IS NULL AND `id_parent` IS NOT NULL AND `name` = ? LIMIT 1";
                        $category = $this->db->query($sql, array($this->merchantID, $category_name))->row_array();
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
                    $product = array(
                        'id_merchant' => $this->merchantID,
                        'id_category_product' => $data['category'],
                        'sku' => $data['sku'],
                        'name' => $data['name'],
                        'price' => $data['price_sales'],
                        'price_modal' => $data['price'],
                        'piece' => $data['unit'],
                    );
                    $this->db->insert('product', $product);
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
                $errMsg = "Error while processing data";
                break;
            }
        }
        if ($errMsg) {
            $this->db->trans_rollback();
            $this->response(array('line' => $line, 'message' => $errMsg), REST_Controller::HTTP_BAD_REQUEST);
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->response(array('message' => 'Error while processing data'), REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $this->db->trans_commit();
            $this->response(array('summary' => $total, 'message' => 'Success'), REST_Controller::HTTP_OK);
        }
    }

    private function createGroupCategory($name)
    {
        $cols = array(
            'name' => $name,
            'id_merchant' => $this->merchantID,
            'createdBy' => $this->userID,
        );
        $this->db->insert('category_product', $cols);
        return $this->db->insert_id();
    }

    private function createCategory($parent, $name)
    {
        $cols = array(
            'id_parent' => $parent,
            'name' => $name,
            'id_merchant' => $this->merchantID,
            'createdBy' => $this->userID,
        );
        $this->db->insert('category_product', $cols);
        return $this->db->insert_id();
    }

    private function slugifyCategoryName($str)
    {
        $search = array('Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ', 'ș', 'ț', 'î', 'â', 'ă', 'Î', 'Â', 'Ă', 'ë', 'Ë');
        $replace = array('s', 't', 's', 't', 's', 't', 's', 't', 'i', 'a', 'a', 'i', 'a', 'a', 'e', 'E');
        $str = str_ireplace($search, $replace, strtolower(trim($str)));
        $str = preg_replace('/[^\w\d\-\ ]/', '', $str);
        $str = str_replace(' ', '-', $str);

        return preg_replace('/\-{2,}/', '-', $str);
    }
}
