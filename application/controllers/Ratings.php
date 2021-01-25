<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/core/Onlyus.php';

class Ratings extends Onlyus {
	public function __construct()
	{
		parent::__construct();
		//Do your magic here
	}

	public function index_get($id = 0)
	{
		$id = intval($id);
		if ($id>0) {
            // $sql = "SELECT * FROM rating_category WHERE id = ? AND deleted = '0' ";
            // $detail = $this->db->query($sql, array($id))->row_array();
            // if (!$detail) {
            //     $this->response(array('message' => 'data not found'), REST_Controller::HTTP_BAD_REQUEST);
            // } else {    			
    		// 	$this->response($detail, REST_Controller::HTTP_OK);
            // }
		} else {
			$sql = "SELECT A.`id`, M.`fullname`, S.`name`, A.`code`, A.`value` FROM `rating_sales_history` A
                LEFT JOIN `member` M ON M.`id` = A.`id_member`
                LEFT JOIN `merchant_store` S ON S.`id` = A.`id_merchant_store`
             WHERE A.`deleted` = 0 ";
			$data = $this->db->query($sql)->result_array();

			$this->response(array('ratings' => $data), REST_Controller::HTTP_OK);
		}
	}

	public function index_post()
	{
		$cols = array(
            'category',
            'createdAt',
            'createdBy',
        );

        foreach ($cols as $col) {
            $$col = trim($this->post($col));
        }
        $data = [];
        try {
        	foreach (array(
        		'category',
        		'createdAt',
        		'createdBy',
        	) as $col) {
        		$data[$col] = $$col;
        	}
        	// $data["createdAt"] = getdate();
            $data["createdAt"] = date('Y-m-d H:i:s');
        	$data["createdBy"] = $this->userID;

            if ($data['category'] == '' || null) {
                $this->response('Category can`t be empty', REST_Controller::HTTP_BAD_REQUEST);
            } else {
                $sql = "SELECT category FROM rating_category WHERE category = ? AND deleted = '0'";
                $exist = $this->db->query($sql, array($data['category']))->num_rows();

                if ($exist){
                    $this->response('Category cant`t be the same', REST_Controller::HTTP_BAD_REQUEST);
                }else {
                	if($this->db->insert('rating_category', $data)){
                        $this->response('Successfully add ratings with Category '. $data["category"], REST_Controller::HTTP_OK);
                    } else {
                        $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
            }

        } catch(Exception $e){
        	$this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }
	}


    public function index_put($id)
    {
        $id = intval($id);
        $query_check = "SELECT id FROM rating_category WHERE id = ? AND deleted = '0'";
        $exist_id = $this->db->query($query_check, array($id))->num_rows();
        if (!$exist_id) {
            $this->response('Your id is not registered yet', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            if ($id>0) {
            $cols = array(
                'category',
                'updatedAt',
                'updatedBy',
            );

            foreach ($cols as $col) {
                $$col = trim($this->put($col));
            }
            $data = [];
            try {
                foreach (array(
                    'category',
                    'updatedAt',
                    'updatedBy',
                ) as $col) {
                    $data[$col] = $$col;
                }
                // $data["createdAt"] = getdate();
                $data["updatedAt"] = date('Y-m-d H:i:s');
                $data["updatedBy"] = $this->userID;
                
                if ($data['category'] == '' || null) {
                    $this->response('Category can`t be empty', REST_Controller::HTTP_BAD_REQUEST);
                } else {
                    $sql = "SELECT category FROM rating_category WHERE id != ? AND category = ? AND deleted = '0'";
                    $exist = $this->db->query($sql, array($id,$data['category']))->num_rows();
                    if ($exist){
                        $this->response('Category cant`t be the same', REST_Controller::HTTP_BAD_REQUEST);
                    }else {
                        if($this->db->update('rating_category', $data, array('id' => $id))){
                            $this->response('Successfully add ratings with Category '. $data["category"], REST_Controller::HTTP_OK);
                        } else {
                            $this->response('Database error', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
                        }
                    }
                }

            } catch(Exception $e){
                $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }            
            } else {
                $this->response('Your Id is wrong', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
    }

    public function index_delete($id)
    {
        $id = intval($id);
        $query_check = "SELECT id FROM rating_category WHERE id = ? AND deleted = '0'";
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
                
                if ($this->db->update('rating_category', $data, array('id' => $id))) {
                    $this->response('Successfully deleted ratings', REST_Controller::HTTP_OK);
                } else {
                    $this->response('Failed deleted ratings', REST_Controller::HTTP_BAD_REQUEST);
                }

            } catch(Exception $e){
                $this->response('Error while processing data', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
            }            
        } else {
            $this->response('Your Id is wrong', REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
        }            
        }        
    }

    public function order_put($id)
    {
        $query_check = "SELECT id FROM rating_category WHERE id = ? AND deleted = '0'";
        $exist_id = $this->db->query($query_check, array($id))->num_rows();
        if (!$exist_id) {
            $this->response('Your id is not registered yet', REST_Controller::HTTP_BAD_REQUEST);
        } else {
            if ($id>0) {
            $cols = array(
                'order',
                'updatedAt',
                'updatedBy',
            );

            foreach ($cols as $col) {
                $$col = trim($this->put($col));
            }
            $data = [];
                try {
                    foreach (array(
                        'order',
                        'updatedAt',
                        'updatedBy',
                    ) as $col) {
                        $data[$col] = $$col;
                    }
                    // $data["createdAt"] = getdate();
                    $data["updatedAt"] = date('Y-m-d H:i:s');
                    $data["updatedBy"] = $this->userID;
                    
                    if($this->db->update('rating_category', $data, array('id' => $id))){
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

/* End of file Ratings.php */
/* Location: ./application/controllers/Ratings.php */