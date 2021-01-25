<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Productcategories extends Onlyus
{

    public function __construct()
    {
        parent::__construct();
    }

    public function select_get()
    {
        // $sql = "SELECT IFNULL(B.`id`, A.`id`) AS `value`, IF( IFNULL(B.`id`,'') = '', A.`name`, CONCAT(A.`name`,' - ', B.`name`) ) AS `label`
        //     FROM `category_product` A
        //         LEFT JOIN `category_product` B ON ( A.`id` = B.`id_parent` AND B.`id_parent` IS NOT NULL)
        //     WHERE A.`id_parent` IS NULL ORDER BY 2";
        $data = array();

        $id_merchant = intval($this->get('id_merchant'));
        $id_merchant_store = intval($this->get('id_store'));
        $where = "";

        if ($id_merchant) {
            $where .= " AND `id_merchant` = $id_merchant";

            if ($id_merchant_store) {
                $where .= " AND `id_merchant_store` = $id_merchant_store";
            } else {
                $where .= " AND `id_merchant_store` IS NULL";
            }

            $sql = "SELECT `id` AS `value`, `name` AS `label`, `order` FROM `category_product` WHERE 1 $where AND `id_parent` IS NOT NULL ORDER BY 2";
            $data = $this->db->query($sql)->result_array();
        }

        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function stores_get()
    {
        // $sql = "SELECT `id` AS `id_merchant_store`, `id_merchant`, `name` FROM `merchant_store` WHERE `deleted`=0 ORDER BY 2";
        $sql = "SELECT A.`id` as `value`, A.`name` AS `label`, A.`id_merchant` FROM `merchant_store` A, `merchant` B WHERE A.`id_merchant` = B.`id` AND B.`product_type` = 2 AND A.`app_mmenu` = 1 AND A.`deleted`=0  AND B.`deleted` = 0 ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        // $dataSql = $this->db->query($sql)->result_array();
        // $data = [];
        // foreach ($dataSql as $key => $value) {
        //     $data[$key]['value'] = "id_merchant:'".$dataSql[$key]['id_merchant']."',id_merchant_store:'".$dataSql[$key]['value']."'";
        //     $data[$key]['label'] = $dataSql[$key]['label'];
        // }
        $this->response(array('stores' => $data), REST_Controller::HTTP_OK);
    }

    public function group_get()
    {
        $data = array();
            $sql = "SELECT `id` as `value`, `name` as `label`, `order` FROM `category_product` WHERE id_parent IS NULL AND `id_merchant` = ? ORDER BY 2";
            $data = $this->db->query($sql, array($this->merchantID))->result_array();

        $this->response(array('categories' => $data), REST_Controller::HTTP_OK);
    }

    public function index_get($id = 0)
    {
        $id = intval($id);
        $dataChild = array();

        if ($id >= 0) {
            $sql = "SELECT b.id, b.id_parent, a.name AS label_parent, b.name AS label, b.order, b.id_merchant, b.id_merchant_store, c.name AS store FROM category_product a JOIN category_product b ON a.id = b.id_parent JOIN merchant_store c on b.id_merchant_store = c.id WHERE b.id_parent IS NOT NULL ORDER BY 4";
            $dataChild = $this->db->query($sql)->result_array();
        }

        $this->response(array('categories' => $dataChild), REST_Controller::HTTP_OK);

    }

    public function index_post()
    {
        // $id_parent = intval($this->post('id_parent'));
        $label = trim($this->post('label'));
        $id_merchant_store = trim($this->post('id_merchant_store'));
        $sql_merchant = "SELECT id_merchant FROM `merchant_store` WHERE id = ? AND deleted = 0";
        $id_merchant = $this->db->query($sql_merchant, array($id_merchant_store))->num_rows();

        if ($label == null || $label == "") {
            $this->response('Kategori tidak boleh kososng', REST_Controller::HTTP_BAD_REQUEST);
        }else{
            $sql = "SELECT id, name FROM category_product WHERE name = ? AND  id_merchant_store = ? AND id_parent IS NULL";
            $exist_group = $this->db->query($sql, array($label, $id_merchant_store))->row();

            $this->db->trans_begin();
            try {
                if (empty($exist_group)) {
                    $input = ['label' => $label, 'id_merchant' => $id_merchant, 'id_merchant_store' => $id_merchant_store];
                    $get_group_id = $this->group_post($input);
                    $cols = array(
                        'id_parent' => $get_group_id,
                        'id_merchant' => $id_merchant,
                        'id_merchant_store' => $id_merchant_store,
                        'name' => $label,
                        'createdBy' => $this->userID,
                    );

                    $this->db->insert('category_product', $cols);
                } else {
                    $query = "SELECT id, name FROM category_product WHERE name = ? AND id_parent IS NOT NULL AND  id_merchant_store = ?";
                    $exist_category = $this->db->query($query, array($label, $id_merchant_store))->row();
                    if ($exist_group ==  null || $exist_category == '') {
                        $cols = array(
                            'id_parent' => $exist_group->id,
                            'id_merchant' => $id_merchant,
                            'id_merchant_store' => $id_merchant_store,
                            'name' => $label,
                            'createdBy' => $this->userID,
                        );
                        $this->db->insert('category_product', $cols); 

                    } else {
                        $this->db->trans_rollback();
                        $this->response("kategori ".$label." sudah ada", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                if ($this->db->trans_status() === false) {
                    $this->db->trans_rollback();
                    $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_BAD_REQUEST);
                } else {
                    $this->db->trans_commit();
                    $this->response('Sukses', REST_Controller::HTTP_OK);
                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->response("Gagal memproses data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index_put($id)
    {
        $id = intval($id);
        $id_parent = intval($this->put('id_parent'));
        $id_merchant_store = trim($this->put('id_merchant_store'));
        $sql_merchant = "SELECT id_merchant FROM `merchant_store` WHERE id = ? AND deleted = 0";
        $id_merchant = $this->db->query($sql_merchant, array($id_merchant_store))->num_rows();

        if ($id < 1) {
            $this->response('Kategori tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }

        $sql = "SELECT id, id_parent FROM category_product WHERE `id` = ? AND `id_parent` IS NOT NULL";
        $ori = $this->db->query($sql, array($id))->row();
        if (!$ori) {
            $this->response('Invalid Category', REST_Controller::HTTP_BAD_REQUEST);
        }

        if ($id_parent != $ori->id_parent) {
            $sql = "SELECT id FROM category_product WHERE `id` = ? AND `id_parent` IS NULL AND `id_merchant_store` = ?";
            $ori = $this->db->query($sql, array($id_parent, $id_merchant_store))->row();
            if (!$ori) {
                $this->response('Grup tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
        $sql_check_name = "SELECT id, id_parent, name FROM category_product WHERE name = ? AND id != ? AND id_parent IS NOT NULL AND id_merchant_store = ?";
        $label = trim($this->put('label'));
        $check_name = $this->db->query($sql_check_name, array($label, $id, $id_merchant_store))->row();
        if ($check_name) {
            $this->response('Kategori tidak boleh sama', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $cols = array(
                'id_merchant' => $id_merchant,
                'id_merchant_store' => $id_merchant_store,
                'name' => $label,
                'updateBy' => $this->userID,
            );

            if ($this->db->update('category_product', $cols, array('id' => $id))) {
                $this->response($id, REST_Controller::HTTP_OK);
            }

        }
        $this->response("Gagal memproses data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function index_delete($id)
    {
        $id = intval($id);
        $query_check = "SELECT id, id_merchant, id_merchant_store FROM category_product WHERE id = ? LIMIT 1";
        $deleted_data = $this->db->query($query_check, array($id))->row();
        $id_merchant = $deleted_data->id_merchant;
        $id_merchant_store = $deleted_data->id_merchant_store;

        if (empty($deleted_data)) {
            $this->response('Your id is not registered yet', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            if ($id>0) {            
                $check_product = "SELECT id, name FROM product WHERE id_category_product = ? AND `id_merchant_store` = ?";
                $product_data = $this->db->query($check_product, array($id, $id_merchant_store))->result_array();
                //cek ada produk atau tidak
                if (!$product_data) {
                    if ($this->db->delete('category_product', array('id' => $id))) {
                        //delete grup e pisan
                        $this->response('Berhasil Hapus Kategori', REST_Controller::HTTP_OK);
                    } else {
                        $this->response('Failed deleted group', REST_Controller::HTTP_BAD_REQUEST);
                    }
                } else {
                    $tanpa_kategori = "SELECT id, name FROM category_product WHERE name = 'Tanpa Kategori' AND `id_parent` IS NOT NULL AND `id_merchant_store` = ?";
                    $id_tanpa_kategori = $this->db->query($tanpa_kategori, array($id_merchant_store))->result_array();
                    $id_product = [];
                    foreach ($product_data as $key => $value) {
                        $id_product[] = $value['id'];
                    }
                    $this->db->trans_begin();
                    if (empty($id_tanpa_kategori)) {
                        $label = "Tanpa Kategori";
                        try {
                            $input = ['label' => $label, 'id_merchant' => $id_merchant, 'id_merchant_store' => $id_merchant_store];
                            $get_group_id = $this->group_post($input);

                            $cols = array(
                                'id_parent' => $get_group_id,
                                'id_merchant' => $id_merchant,
                                'id_merchant_store' => $id_merchant_store,
                                'name' => $label,
                                'order' => '255',
                                'createdBy' => $this->userID,
                            );

                            if ($this->db->insert('category_product', $cols)) {
                                $id_tanpa_kat = $this->db->insert_id();
                                //ubah kategori produk nya jadi tanpa kategori
                                // $this->response("berhasil tambah group dan category Tanpa Kategori", REST_Controller::HTTP_OK);
                                $cols = array(
                                    'updatedBy' => $this->userID,
                                    'id_category_product' => $id_tanpa_kat,
                                );
                                for ($i=0; $i < count($id_product); $i++) {
                                    $this->db->update('product', $cols, array('id' => $id_product[$i]));
                                }
                                $this->db->delete('category_product', array('id' => $id));
                            }                            
                        } catch (Exception $e) {
                            $this->db->trans_rollback();
                            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                        }
                    } else {                        
                        //ubah kategori produk nya jadi tanpa kategori
                        try {
                            $cols = array(
                                'updatedBy' => $this->userID,
                                'id_category_product' => $id_tanpa_kategori[0]['id'],
                            );
                            for ($i=0; $i < count($id_product); $i++) {
                                $this->db->update('product', $cols, array('id' => $id_product[$i]));
                            }
                            $this->db->delete('category_product', array('id' => $id));
                        } catch (Exception $e) {
                            $this->db->trans_rollback();
                            $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                        }

                    }
                    if ($this->db->trans_status() === false) {
                        $this->db->trans_rollback();
                        $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_BAD_REQUEST);
                    } else {
                        $this->db->trans_commit();
                        $this->response('Successfully update category product to Tanpa Kategori and delete category', REST_Controller::HTTP_OK);
                    }
                }

                
                

            // } catch(Exception $e){
            //     $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            // }            
        } else {
            $this->response('Your Id is wrong', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }            
        }        
    }

    public function group_post($input)
    {
        $label = $input['label'];
        $id_merchant = $input['id_merchant'];
        $id_merchant_store = $input['id_merchant_store'];

        $label = trim($label);
        if ($label == null || $label == "") {
            $this->response('Grup tidak boleh kosong', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            $cols = array(
                'name' => $label,
                'id_merchant' => $id_merchant,
                'id_merchant_store' => $id_merchant_store,
                'createdBy' => $this->userID,
            );
            $sql = "SELECT name FROM category_product WHERE name = ? AND id_parent IS NULL";
            $exist = $this->db->query($sql, array($cols['name']))->num_rows();
            if ($exist) {
                $this->response("Kategori ".$label." sudah ada", REST_Controller::HTTP_BAD_REQUEST);
            }else{
                if ($this->db->insert('category_product', $cols)) {
                    $id = $this->db->insert_id();
                    // $this->response($id, REST_Controller::HTTP_OK);
                    return $id;
                }            
            }
            $this->response("Gagal memproses data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);            
        }

    }

    public function group_put($id)
    {
        if ($this->merchantProductType != "2") {
            $this->response('Not Allowed', REST_Controller::HTTP_BAD_REQUEST);
        }

        $id = intval($id);
        if ($id < 1) {
            $this->response('Grup tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
        }
        $sql = "SELECT id FROM category_product WHERE `id` = ? AND `id_parent` IS NULL AND `id_merchant` = ? AND `id_merchant_store` = ?";
        $ori = $this->db->query($sql, array($id, $this->merchantID, $this->storeID))->row();
        if (!$ori) {
            $this->response('Invalid Group', REST_Controller::HTTP_BAD_REQUEST);
        }

        $label = trim($this->put('label'));
        $cols = array(
            'name' => $label,
            'updateBy' => $this->userID,
        );

        $sql = "SELECT name FROM category_product WHERE name = ? ";
        $exist = $this->db->query($sql, array($cols['name']))->num_rows();
        if ($exist) {
            $this->response("Category cant't be the same", REST_Controller::HTTP_BAD_REQUEST);
        }else{
            if ($this->db->update('category_product', $cols, array('id' => $id))) {
                $this->response($id, REST_Controller::HTTP_OK);
            }
        }

        $this->response("Gagal memproses data", REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function slugify($str)
    {
        $search = array('Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ', 'ș', 'ț', 'î', 'â', 'ă', 'Î', 'Â', 'Ă', 'ë', 'Ë');
        $replace = array('s', 't', 's', 't', 's', 't', 's', 't', 'i', 'a', 'a', 'i', 'a', 'a', 'e', 'E');
        $str = str_ireplace($search, $replace, strtolower(trim($str)));
        $str = preg_replace('/[^\w\d\-\ ]/', '', $str);
        $str = str_replace(' ', '-', $str);

        return preg_replace('/\-{2,}/', '-', $str);
    }

    public function order_put($id)
    {
        $query_check = "SELECT id FROM category_product WHERE id = ? ";
        $exist_id = $this->db->query($query_check, array($id))->num_rows();
        if (!$exist_id) {
            $this->response('Your id is not registered yet', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            if ($id>0) {
            $cols = array(
                'order',
                'updatedAt',
                'updateBy',
            );

            foreach ($cols as $col) {
                $$col = trim($this->put($col));
            }
            $data = [];
                try {
                    foreach (array(
                        'order',
                        'updatedAt',
                        'updateBy',
                    ) as $col) {
                        $data[$col] = $$col;
                    }
                    // $data["createdAt"] = getdate();
                    $data["updatedAt"] = date('Y-m-d H:i:s');
                    $data["updateBy"] = $this->userID;
                    
                    if($this->db->update('category_product', $data, array('id' => $id))){
                        $this->response('Successfully edit order', REST_Controller::HTTP_OK);
                    } else {
                        $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
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
