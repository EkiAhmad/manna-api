<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Promosurprise extends Onlyus {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Upload_model');
        $this->load->model('Promo_model');
    }
    public function store_get()
    {
        $sql = "SELECT `id` AS `value`, `name` AS `label` FROM `merchant_store` WHERE `deleted` = '0' ORDER BY 2";
        $data = $this->db->query($sql)->result_array();
        $this->response(array('store' => $data), REST_Controller::HTTP_OK);
    }

	public function index_get($id = 0)
    {
        $data = array();
        $promoPath = base_url() . $this->Upload_model->path['surprize'];
        $id = intval($id);
        if ($id < 1) {
            //perlu filter deleted merchant
            $sql = "";
            $data = array();
            
            $sql = "SELECT A.`id`, IFNULL(C.`company`,'') AS `merchant`, '' AS `store`, IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`, A.`link`, A.`desc`,B.`name`
            FROM `promo_surprise` A
                JOIN `merchant` C ON A.`id_merchant` = C.`id`
                JOIN `merchant_store` B ON A.`id_merchant_store` = B.`id`
            WHERE  A.`deleted` = 0 ORDER BY A.`id` DESC";
            $data = $this->db->query($sql)->result_array();
            // if(!$data){
            //     $this->response("Gagal memproses data", REST_Controller::HTTP_BAD_REQUEST);
            // }
            $this->response(array('promosurprise' => $data), REST_Controller::HTTP_OK);
        } else {
            $sql = "";
            $data = array();
            $sql = "SELECT A.`id_merchant`, A.`id_merchant_store`,
            IF(IFNULL(A.`image`,'') != '', CONCAT('" . $promoPath . "','',A.`image`), '') AS `image`,
            A.`link`, A.`status`, IFNULL(A.`desc`, '') AS `desc`
            FROM `promo_surprise` A WHERE A.`id` = ? AND A.`deleted` = 0";
            $data = (array) $this->db->query($sql, array($id))->row();
             
            if ($data) {
                $this->response($data, REST_Controller::HTTP_OK);
            } else {
                $this->response('Promo tidak ditemukan', REST_Controller::HTTP_BAD_REQUEST);
            }
        }
    }

    public function index_post($id = 0)
    {

        $ori = null;

        if ($id) {
            $id = intval($id);
            if ($id < 1) {
                $this->response(array('message' => 'Promo Surprize tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            }

            $sql = "SELECT A.`id`, IFNULL(A.`image`,'') AS `image`, IFNULL(A.`id_merchant`,'') AS `id_merchant`, IFNULL(A.`id_merchant_store`,'') AS `id_merchant_store` FROM `promo_surprise` A WHERE A.`id` = ?  AND A.`deleted` = 0 LIMIT 1";
            $ori = $this->db->query($sql, array($id))->row();
           
            if (!$ori) {
                $this->response(array('message' => 'Promo Surprize tidak ditemukan'), REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $id = intval($ori->id);
                
            }
        }

        $cols = array( 
            'link',
            'desc',
            'id_merchant_store'
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
            'id_merchant_store'
        );
        foreach ($colsInt as $col) {
            $$col = intval($$col);
        }
        $sql_store = "SELECT A.`id`, IFNULL(A.`id_merchant`,'') AS `id_merchant` FROM `merchant_store` A WHERE A.`id` = ?  AND A.`deleted` = 0 LIMIT 1";
        $_ori = $this->db->query($sql_store, array($id_merchant_store))->row();

        $id_merchant = $_ori->id_merchant;
        $id_merchant_store = $_ori->id;
        if ($id_merchant < 1 && $id_merchant_store < 1) {
            $this->response(array('message' => 'Gagal memproses data'), REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->db->trans_begin();
        try {
            $promo = array();
            foreach ($cols as $col) {
                $promo[$col] = $$col;
            }
            $promo['id_merchant'] = $id_merchant;
            $promo['id_merchant_store'] = $id_merchant_store;
            if ($id) {
                $promo["updatedBy"] = $this->userID;
                $this->db->update('promo_surprise', $promo, array('id' => $id));
            } else {
                $promo["createdBy"] = $this->userID;
                $this->db->insert('promo_surprise', $promo);
                $id = $this->db->insert_id();
            }

            if ($_FILES) {
                $name_surprize = 'PS-'.$id_merchant_store;
                $upload = $this->Upload_model->surprize($name_surprize);
                if ($upload['error']) {
                    $this->db->trans_rollback();
                    $errMsg = str_replace(array('<p>', '</p>'), '', $upload['result']);
                    $this->response(array('message' => $errMsg), REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                } else {
                    $this->db->update('promo_surprise', array('image' => $upload['result']['file_name']), array('id' => $id));
                    if ($ori && $ori->image) {
                        @unlink(FCPATH . $this->Upload_model->path['promo'] . $ori->image);
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

    public function index_delete($id)
    {
        $id = intval($id);
        $query_check = "SELECT id FROM promo_surprise WHERE id = ? AND deleted = '0'";
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
                
                if ($this->db->update('promo_surprise', $data, array('id' => $id))) {
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

/* End of file Promosurprise.php */
/* Location: ./application/controllers/s/Promosurprise.php */